<?php
namespace exface\SapConnector\Facades;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Psr7\stream_for;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataSourceFactory;
use exface\UrlDataConnector\Interfaces\HttpConnectionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\UrlDataConnector\Psr7DataQuery;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;

/**
 * This facade is used by the exface.SapConnector.ITSmobile widget to remote control ITSmobile applications.
 * 
 * Currently this proxy only supports ITSmobile theme 99 (default).
 * 
 * ## Known limitations
 * 
 * - The browser needs to use cookies in order for ITSmobile to work. This is no
 * different with the proxy - the browser of the client device MUST do the cookie
 * handling.
 * 
 * @author Andrej Kabachnik
 *
 */
class ITSmobileProxyFacade extends AbstractHttpFacade
{
    private $url = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if ($this->getWorkbench()->isStarted() === false) {
            $this->getWorkbench()->start();
        }
        
        $method = $request->getMethod();
        $requestHeaders = $request->getHeaders();
        $requestHeaders['Accept'][0] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3';
        $requestHeaders['Accept-Language'][0] = $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $requestHeaders['Cache-Control'][0] = 'no-cache';
        $requestHeaders['Pragma'][0] = 'no-cache';
        
        $uri = $request->getUri();
        
        $url = $request->getQueryParams()['url'];
        $pathParts = explode('/', StringDataType::substringAfter($uri->getPath(), 'api/itsmobileproxy/'));
        $dataSourceSelector = $pathParts[0];
        if (! $url && $pathParts[1] === 'url') {
            $url = gzinflate(urldecode($pathParts[2]));
        }

        if (! $dataSourceSelector) {
            throw new RuntimeException('No data source selector (UID or alias) found in the request!');
        }
        
        $dataSource = DataSourceFactory::createFromModel($this->getWorkbench(), $dataSourceSelector);
        
        $connection = $dataSource->getConnection();
        if ($connection instanceof HttpConnectionInterface) {
            $connection->setCacheEnabled(false);
            // FIXME for some reason, cookies do not work here. Guzzle simply does
            // not create the cookie jar file.
            // $connection->setUseCookies(true);
        } else {
            throw new RuntimeException('todo..');
        }
        
        $fwRequest = new Request($method, $url, $requestHeaders, $request->getBody()->__toString());
        $queryResult = $connection->query(new Psr7DataQuery($fwRequest));
        $result = $queryResult->getResponse();
        
        $responseHeaders = $result->getHeaders();
        unset($responseHeaders['Transfer-Encoding']);
        
        $responseBody = $result->getBody()->__toString();
        
        // Do theme-specific transformations
        $responseBody = $this->processITSmobileTheme($url, $responseBody);        
        
        // Replace all URIs in the response with their proxy versions.
        $baseUrl = StringDataType::substringBefore($connection->getUrl(), '/', false, true, true);
        $proxyUrl = $request->getUri()->withQuery('')->__toString();
        $responseBody = $this->replaceUrls($baseUrl, $proxyUrl, $responseBody);
        
        // Update the content-length header because all our transformation change the body
        $responseHeaders['content-length'][0] = mb_strlen($responseBody);
        
        // Send the transformed response.
        $response = new Response($result->getStatusCode(), $responseHeaders, $responseBody, $result->getProtocolVersion(), $result->getReasonPhrase());
        
        return $response;
    }
    
    /**
     * Replaces all occurences of $baseUrl in $responseBody with $proxyUrl
     * 
     * @param string $baseUrl
     * @param string $proxyUrl
     * @param string $responseBody
     * @return string
     */
    protected function replaceUrls(string $baseUrl, string $proxyUrl, string $responseBody) : string
    {
        $urls = [];
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
        return $responseBody;
    }
    
    /**
     * Theme 99 specific $responseBody transformations
     * 
     * @param string $url
     * @param string $responseBody
     * @param string $theme
     * @return string
     */
    protected function processITSmobileTheme(string $url, string $responseBody, $theme = '99') : string
    {
        $responseBody = str_replace('<script type="text/javascript" language="javascript" src="/sap/public/bc/its/mimes/itsmobile/99/scripts/all/mobile.js"></script>', '', $responseBody);
        $responseBody = str_replace('<link rel="stylesheet" href="/sap/public/bc/its/mimes/itsmobile/99/styles/all/mobile.css" type="text/css" >', '', $responseBody);
        $responseBody = str_replace('document.forms["mobileform"].submit();', '$(document.forms["mobileform"]).submit()', $responseBody);
        
        if (stripos($url, 'mobile.js') !== false) {
            $responseBody .= <<<JS
            
function firstSend()
{
   return true;
}

JS;
        }
        return $responseBody;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/itsmobileproxy';
    }
    
    /**
     *
     * @param string $uri
     * @return string
     */
    public function getProxyUrl(string $uri) : string
    {
        return $this->buildUrlToFacade() . '?url=' . urlencode($uri);
    }
}