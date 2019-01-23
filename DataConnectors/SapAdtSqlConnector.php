<?php
namespace exface\SapConnector\DataConnectors;

use exface\UrlDataConnector\DataConnectors\HttpConnector;
use exface\SapConnector\ModelBuilders\SapAdtSqlModelBuilder;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Exceptions\RuntimeException;
use GuzzleHttp\Exception\RequestException;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;

/**
 * Data connector for the SAP ABAP Development Tools (ADT) SQL console webservice.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapAdtSqlConnector extends HttpConnector implements SqlDataConnectorInterface
{
    private $csrfToken = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\ODataConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new SapAdtSqlModelBuilder($this);
    }
    
    /**
     *
     * @return string
     */
    protected function getCsrfToken() : string
    {
        if ($this->csrfToken === null) {
            $sessionToken = $this->getWorkbench()->getApp('exface.SapConnector')->getContextVariable('csrf_token_' . $this->getUrl(), ContextManagerInterface::CONTEXT_SCOPE_SESSION);
            if ($sessionToken) {
                $this->csrfToken = $sessionToken;
            } else {
                $this->refreshCsrfToken();
            }
        }
        return $this->csrfToken;
    }
    
    /**
     * 
     * @param string $value
     * @return SapAdtSqlConnector
     */
    protected function setCsrfToken(string $value) : SapAdtSqlConnector
    {
        $this->csrfToken = $value;
        $this->getWorkbench()->getApp('exface.SapConnector')->setContextVariable('csrf_token_' . $this->getUrl(), $value, ContextManagerInterface::CONTEXT_SCOPE_SESSION);
        return $this;
    }
    
    protected function refreshCsrfToken() : string
    {
        $token = null;
        try {
            $response = $this->getClient()->get('freestyle',['headers' => ['X-CSRF-Token' => 'Fetch']]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if (! $response) {
                throw $e;
            }
            $token = $response->getHeader('X-CSRF-Token')[0];
        }
        
        if (! $token) {
            throw new RuntimeException('Cannot fetch CSRF token: ' . $response->getStatusCode() . ' ' . $response->getBody()->__toString());
        }
        
        $this->setCsrfToken($token);
        
        return $token;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\HttpConnector::getUseCookies()
     */
    public function getUseCookies()
    {
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\AbstractUrlConnector::getUrl()
     */
    public function getUrl()
    {
        return rtrim(parent::getUrl(), "/") . '/sap/bc/adt/datapreview/';
    }
    
    protected function performQuery(DataQueryInterface $query)
    {
        if (($query instanceof SqlDataQuery) === false) {
            throw new DataQueryFailedError($query, 'The SAP ADT SQL connector expects SqlDataQueries, ' . get_class($query) . ' received instead!');
        }
        /* @var $query \exface\Core\CommonLogic\DataQueries\SqlDataQuery */
        
        try {
            $sql = $query->getSql();
            $urlParams = '';
            
            // Remove inline comments as they cause errors
            $sql = preg_replace('/\--.*/i', '', $sql);
            // Normalize line endings
            $sql = preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $sql); 
            
            // Handle pagination
            $limit = [];
            preg_match_all('/UP TO (\d+) OFFSET (\d+)/', $sql, $limit);
            if ($limit[0]) {
                $urlParams .= '&rowNumber=' . $limit[1][0];   
                $sql = str_replace($limit[0][0], '', $sql);
            }            
            
            $response = $this->performRequest('POST', 'freestyle?' . $urlParams, $sql);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            throw new DataQueryFailedError($query, $this->getErrorText($response), '6T2T2UI');
        }
        
        $query->setResultArray($this->extractDataRows(new Crawler($response->getBody()->__toString())));
        
        return $query;
    }
    
    protected function extractDataRows(Crawler $xmlCrawler) : array
    {
        $data = [];
        foreach($xmlCrawler->filterXPath('//dataPreview:columns') as $colNode) {
            $colCrawler = new Crawler($colNode);
            $colName = $colCrawler->filterXPath('//dataPreview:metadata')->attr('dataPreview:name');
            $r = 0;
            foreach($colCrawler->filterXPath('//dataPreview:data') as $dataNode) {
                $data[$r][$colName] = $dataNode->textContent;
                $r++;
            }
        }
        return $data;
    }
    
    public function performRequest(string $method, string $url, string $body, array $headers = []) : ResponseInterface
    {
        $headers = array_merge([
            'X-CSRF-Token' => $this->getCsrfToken(), 
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        ], $headers);
        $request = new Request($method, $url, $headers, $body);
        try {
            $response = $this->getClient()->send($request);
        } catch (RequestException $e) {
            if ($e->getResponse() !== null && $e->getResponse()->getHeader('X-CSRF-Token')[0] === 'Required') {
                $this->refreshCsrfToken();
                $request = $request->withHeader('X-CSRF-Token', [$this->getCsrfToken()]);
                $response = $this->getClient()->send($request);
            } else {
                throw $e;
            }
        }
        
        return $response;
    }
    
    public function getAffectedRowsCount(SqlDataQuery $query)
    {
        return 100;
    }

    public function getInsertId(SqlDataQuery $query)
    {}

    public function makeArray(SqlDataQuery $query)
    {}

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::runSql()
     */
    public function runSql($string)
    {
        $query = new SqlDataQuery();
        $query->setSql($string);
        return $this->query($query);
    }

    public function freeResult(SqlDataQuery $query)
    {}
    
    /**
     * Extracts the message text from an error-response of an ADT web service 
     * 
     * @param ResponseInterface $response
     * @return string
     */
    protected function getErrorText(ResponseInterface $response) : string
    {
        $message = null;
        
        $text = trim($response->getBody()->__toString());
        if (mb_strtolower(substr($text, 0, 6)) === '<html>') {
            // If the response is HTML, get the <h1> tag
            $crawler = new Crawler($text);
            $message = $crawler->filter('h1')->text();
        } elseif (mb_strtolower(substr($text, 0, 5)) === '<?xml') {
            // If the response is XML, look for the <message> tag
            $crawler = new Crawler($text);
            $message = $crawler->filterXPath('//message')->text();
        }
        
        // If no message could be found, just output the response body
        // Btw. strip_tags() did not work well as fallback, because it would also output
        // embedded CSS.
        return $message ?? $text;
    }
}