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
            $this->csrfToken = $this->fetchCsrfToken();
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
        return $this;
    }
    
    protected function fetchCsrfToken() : string
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
            
            // Remove inline comments as they cause errors
            $sql = preg_replace('/\--.*/i', '', $sql);
            $sql = preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $sql);            
            
            $response = $this->performRequest('POST', 'freestyle', $sql);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            throw new DataQueryFailedError($query, 'SQL Error: ' . strip_tags($response->getBody()->__toString()));
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
        $headers = array_merge(['X-CSRF-Token' => $this->getCsrfToken(), 'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'], $headers);
        $request = new Request($method, $url, $headers, $body);
        return $this->getClient()->send($request);
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

}