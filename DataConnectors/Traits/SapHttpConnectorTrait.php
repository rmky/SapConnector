<?php
namespace exface\SapConnector\DataConnectors\Traits;

use Symfony\Component\DomCrawler\Crawler;
use Psr\Http\Message\ResponseInterface;
use exface\UrlDataConnector\DataConnectors\HttpConnector;

/**
 * This trait adds support sap-client URL params and other SAP specifics to an HttpConnector.
 * 
 * @author Andrej Kabachnik
 *
 */
trait SapHttpConnectorTrait
{    
    private $sapClient = null;
    
    /**
     *
     * @return string|NULL
     */
    public function getSapClient() : ?string
    {
        return $this->sapClient;
    }
    
    /**
     * SAP client (MANDT) to connect to.
     *
     * @uxon-property sap_client
     * @uxon-type string
     *
     * @param string $client
     * @return self
     */
    public function setSapClient(string $client) : self
    {
        $this->sapClient = $client;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\HttpConnector::getFixedUrlParams()
     */
    public function getFixedUrlParams() : string
    {
        $paramString = parent::getFixedUrlParams();
        if ($this->getSapClient() !== null && stripos($paramString, 'sap-client') === false) {
            $paramString .= '&sap-client=' . $this->getSapClient();
        }
        return $paramString;
    }
    
    /**
     *
     * @see HttpConnector::getResponseErrorText()
     */
    protected function getResponseErrorText(ResponseInterface $response) : string
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