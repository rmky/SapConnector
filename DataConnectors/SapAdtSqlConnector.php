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
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;

/**
 * Data connector for the SAP ABAP Development Tools (ADT) SQL console webservice.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapAdtSqlConnector extends HttpConnector implements SqlDataConnectorInterface
{
    private $csrfToken = null;
    
    private $lastRowNumberUrlParam = null;
    
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
            throw new DataConnectionFailedError($this, 'Cannot fetch CSRF token: ' . $this->getErrorText($response), null, $e);
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
        
        // Need to pass the connection back to the query (this is done by the AbstractSqlConnector in ordinary
        // SQL-connectors and would not happen here as this class extends the HttpConnector)
        $query->setConnection($this);
        
        try {
            $sql = $query->getSql();
            $urlParams = '';
            
            // Remove inline comments as they cause errors
            $sql = preg_replace('/\--.*/i', '', $sql);
            // Normalize line endings
            $sql = preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $sql); 
            
            // Handle pagination
            // The DataPreview service seems to remove UP TO clauses from the queries
            // and replace them with the URL parameter rowNumber. We can fake pagination,
            // though, by requesting all rows (including the offset) and filtering the
            // offset away in extractDataRows(). This will make the pagination work, but
            // will surely become slower with every page - that should be acceptable,
            // however, as users are very unlikely to manually browse beyond page 10.
            $limits = [];
            $offset = 0;
            preg_match_all('/UP TO (\d+) OFFSET (\d+)/', $sql, $limits);
            if ($limits[0]) {
                $limit = $limits[1][0];
                $offset = $limits[2][0] ?? 0;
                $this->lastRowNumberUrlParam = $limit + $offset;
                $urlParams .= '&rowNumber=' . $this->lastRowNumberUrlParam;  
                $sql = str_replace($limits[0][0], '', $sql);
            }            
            
            $response = $this->performRequest('POST', 'freestyle?' . $urlParams, $sql);
        } catch (RequestException $e) {
            if (! $response) {
                $response = $e->getResponse();
            }
            $errorText = $response ? $this->getErrorText($response) : $e->getMessage();
            throw new DataQueryFailedError($query, $errorText, '6T2T2UI', $e);
        }
        
        $xml = new Crawler($response->getBody()->__toString());
        $query->setResultArray($this->extractDataRows($xml, $offset));
        
        $cnt = $this->extractTotalRowCounter($xml);
        if ($cnt !== null) {
            $query->setResultRowCounter($cnt);
        }
        
        return $query;
    }
    
    protected function extractTotalRowCounter(Crawler $xmlCrawler) : ?int
    {
        $totalRows = $xmlCrawler->filterXPath('//dataPreview:totalRows')->text();
        return is_numeric($totalRows) === true ? intval($totalRows) : null;
    }
    
    protected function extractDataRows(Crawler $xmlCrawler, int $offset = 0) : array
    {
        $data = [];
        foreach($xmlCrawler->filterXPath('//dataPreview:columns') as $colNode) {
            $colCrawler = new Crawler($colNode);
            $colName = $colCrawler->filterXPath('//dataPreview:metadata')->attr('dataPreview:name');
            $r = 0;
            foreach($colCrawler->filterXPath('//dataPreview:data') as $dataNode) {
                if ($r < $offset) {
                    $r++;
                    continue;
                }
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
            if ($e->getResponse() === null) {
                throw $e;
            }
            
            if ($e->getResponse()->getHeader('X-CSRF-Token')[0] === 'Required') {
                $this->refreshCsrfToken();
                $request = $request->withHeader('X-CSRF-Token', [$this->getCsrfToken()]);
                $response = $this->getClient()->send($request);
            } elseif($e->getResponse()->getStatusCode() === '401') {
                throw $e;
            } else {
                throw $e;
            }
        }
        
        return $response;
    }
    
    public function getAffectedRowsCount(SqlDataQuery $query)
    {
        return $this->lastRowNumberUrlParam;
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
    {
        return;
    }
    
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
        try {
            if (mb_strtolower(substr($text, 0, 6)) === '<html>') {
                // If the response is HTML, get the <h1> tag
                $crawler = new Crawler($text);
                $message = $crawler->filter('h1')->text();
            } elseif (mb_strtolower(substr($text, 0, 5)) === '<?xml') {
                // If the response is XML, look for the <message> tag
                $crawler = new Crawler($text);
                $message = $crawler->filterXPath('//message')->text();
            }
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            // Ignore errors
        }
        
        // If no message could be found, just output the response body
        // Btw. strip_tags() did not work well as fallback, because it would also output
        // embedded CSS.
        return $message ?? $text;
    }

    
}