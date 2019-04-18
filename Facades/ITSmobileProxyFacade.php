<?php
namespace exface\SapConnector\Facades;

use exface\Core\Facades\AbstractFacade\AbstractFacade;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Psr7\stream_for;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 *  
 * @author Andrej Kabachnik
 *
 */
class ITSmobileProxyFacade extends AbstractFacade implements HttpFacadeInterface
{    
    private $url = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $url = $request->getQueryParams()['url'];
        $method = $request->getMethod();
        $requestHeaders = $request->getHeaders();
        
        $client = new Client();
        $result = $client->request($method, $url, ['headers' => $requestHeaders]);
        
        $responseHeaders = $result->getHeaders();
        unset($responseHeaders['Transfer-Encoding']);
        
        $responseBody = $result->getBody()->__toString();
        $url = $request->getUri();
        $baseUrl = 'http://swuewm1.salt-solutions.de:8000';
        $proxyUrl = $request->getUri()->withQuery('')->__toString();
        $from = [];
        $to = [];
        
        preg_match_all('/href="([^"]*)"/', $responseBody, $urls);
        foreach ($urls[1] as $url) {
            $from[] = $url;
            $to[] = $proxyUrl . '?url=' . urlencode((substr($url, 0, 1) === '/' ? $baseUrl : '') . $url);
        }
        
        preg_match_all('/action="([^"]*)"/', $responseBody, $urls);
        foreach ($urls[1] as $url) {
            $from[] = $url;
            $to[] = $proxyUrl . '?url=' . urlencode((substr($url, 0, 1) === '/' ? $baseUrl : '') . $url);
        }
        
        preg_match_all('/src="([^"]*)"/', $responseBody, $urls);
        foreach ($urls[1] as $url) {
            $from[] = $url;
            $to[] = $proxyUrl . '?url=' . urlencode((substr($url, 0, 1) === '/' ? $baseUrl : '') . $url);
        }
        
        $responseBody = str_replace($from, $to, $responseBody);
        
        $response = new Response($result->getStatusCode(), $responseHeaders, $responseBody, $result->getProtocolVersion(), $result->getReasonPhrase());
        
        return $response;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns() : array
    {
        return [
            "/\/api\/itsmobileproxy[\/?]/"
        ];
    }
    
    /**
     *
     * @return string
     */
    public function getBaseUrl() : string{
        if (is_null($this->url)) {
            if (! $this->getWorkbench()->isStarted()) {
                $this->getWorkbench()->start();
            }
            $this->url = $this->getWorkbench()->getCMS()->buildUrlToApi() . '/api/itsmobileproxy';
        }
        return $this->url;
    }
    
    /**
     * 
     * @param string $uri
     * @return string
     */
    public function getProxyUrl(string $uri) : string
    {
        return $this->getBaseUrl() . '?url=' . urlencode($uri);
    }
}