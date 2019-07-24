<?php
namespace exface\SapConnector\DataConnectors\Traits;

use GuzzleHttp\Exception\RequestException;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\UrlDataConnector\Interfaces\HttpConnectionInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * This trait adds support for CSRF-tokens to an HTTP connector.
 * 
 * If a request fails due to missing CSRF token, a new token is requested from the `csrf_request_url`.
 * This token is then saved in the current user session and added to every request via the
 * `X-CSRF-Token` header.
 * 
 * See https://a.kabachnik.info/how-to-use-sap-web-services-with-csrf-tokens-from-third-party-web-apps.html
 * for more information about CSRF in SAP.
 * 
 * @author Andrej Kabachnik
 *
 */
trait CsrfTokenTrait
{    
    private $csrfToken = null;
    
    private $csrfCookie = null;
    
    private $csrfRequestUrl = '';
    
    /**
     *
     * @return string
     */
    protected function getCsrfToken() : string
    {
        if ($this->csrfToken === null) {
            $sessionToken = $this->getWorkbench()->getApp('exface.SapConnector')->getContextVariable($this->getCsrfTokenContextVarName(), ContextManagerInterface::CONTEXT_SCOPE_SESSION);
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
     * @return string
     */
    protected function getCsrfTokenContextVarName() : string
    {
        return 'csrf_token_' . $this->getCsrfRequestUrl();
    }
    
    /**
     *
     * @param string $value
     * @return HttpConnectionInterface
     */
    protected function setCsrfToken(string $value) : HttpConnectionInterface
    {
        $this->csrfToken = $value;
        $this->getWorkbench()->getApp('exface.SapConnector')->setContextVariable($this->getCsrfTokenContextVarName(), $value, ContextManagerInterface::CONTEXT_SCOPE_SESSION);
        return $this;
    }
    
    /**
    *
    * @return string
    */
    protected function getCsrfCookie() : string
    {
        if ($this->csrfCookie === null) {
            $sessionCookie = $this->getWorkbench()->getApp('exface.SapConnector')->getContextVariable($this->getCsrfCookieContextVarName(), ContextManagerInterface::CONTEXT_SCOPE_SESSION);
            if ($sessionCookie) {
                $this->csrfCookie = $sessionCookie;
            } else {
                $this->refreshCsrfToken();
            }
        }
        return $this->csrfCookie;
    }
    
    /**
     * 
     * @return string
     */
    protected function getCsrfCookieContextVarName() : string
    {
        return 'csrf_cookie_' . $this->getCsrfRequestUrl();
    }
    
    /**
     *
     * @param string $value
     * @return HttpConnectionInterface
     */
    protected function setCsrfCookie(string $value) : HttpConnectionInterface
    {
        $this->csrfCookie = $value;
        $this->getWorkbench()->getApp('exface.SapConnector')->setContextVariable($this->getCsrfCookieContextVarName(), $value, ContextManagerInterface::CONTEXT_SCOPE_SESSION);
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getCsrfHeaders() : array
    {
        return [
            'X-CSRF-Token' => $this->getCsrfToken(),
            'Cookie' => $this->getCsrfCookie()
        ];
    }
    
    /**
     * 
     * @param RequestInterface $request
     * @return RequestInterface
     */
    protected function addCsrfHeaders(RequestInterface $request) : RequestInterface
    {
        foreach ($this->getCsrfHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }
    
    /**
     *
     * @param bool $retryOnError
     * @throws RequestException
     * @throws DataConnectionFailedError
     * @return string
     */
    protected function refreshCsrfToken(bool $retryOnError = true) : string
    {
        $token = null;
        try {
            $response = $this->getClient()->get($this->getCsrfRequestUrl(), ['headers' => ['X-CSRF-Token' => 'Fetch']]);
            $token = $response->getHeader('X-CSRF-Token')[0];
            $cookie = implode(';', $response->getHeader('Set-Cookie'));
        } catch (RequestException $e) {
            $response = $e->getResponse();
            // If there was an error, but there is no response (i.e. the error occurred before
            // the response was received), just rethrow the exception.
            if (! $response) {
                throw $e;
            }
            $token = $response->getHeader('X-CSRF-Token')[0];
            $cookie = implode(';', $response->getHeader('Set-Cookie'));
        }
        
        // Sometimes, SAP returns a 401 error although the authentication is performed, so the same
        // request works perfectly well the second time. Just retry to see if this works.
        // TODO why does the 401 come in the first place? Perhaps it has something to do with
        // the session expiring...
        if (! $token && $retryOnError === true && $response && $response->getStatusCode() == 401) {
            try {
                $token = $this->refreshCsrfToken(false);
            } catch (\Throwable $re) {
                $this->getWorkbench()->getLogger()->logException(new DataConnectionFailedError($this, 'Retry fetch CSRF token failed: ' . $this->getResponseErrorText($response), null, $re));
            }
        }
        
        if (! $token) {
            throw new DataConnectionFailedError($this, 'Cannot fetch CSRF token: ' . $this->getResponseErrorText($response) . '. See logs for more details!', null, $e);
        }
        
        $this->setCsrfToken($token);
        $this->setCsrfCookie($cookie);
        
        return $token;
    }
    
    /**
     * 
     * @return string
     */
    public function getCsrfRequestUrl() : string
    {
        $url = $this->csrfRequestUrl;
        
        if ($this->getFixedUrlParams() !== '') {
            $url = $url . '?' . $this->getFixedUrlParams();
        }
        
        return $url;
    }
    
    /**
     * The endpoint of the webservice to request a CSRF token (relative to `url`)
     * 
     * @uxon-property csrf_request_url
     * @uxon-type string
     * 
     * @param string $urlRelativeToBase
     * @return HttpConnectionInterface
     */
    public function setCsrfRequestUrl(string $urlRelativeToBase) : HttpConnectionInterface
    {
        $this->csrfRequestUrl = $urlRelativeToBase;
        return $this;
    }
    
    /**
     * Extracts the message text from an error-response of an ADT web service
     *
     * @param ResponseInterface $response
     * @return string
     */
    abstract function getResponseErrorText(ResponseInterface $response, \Throwable $exceptionThrown = null) : string;
    
    /**
     * 
     * @return string
     */
    abstract function getFixedUrlParams() : string;
}