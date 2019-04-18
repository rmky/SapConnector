<?php
namespace exface\SapConnector\DataConnectors\Traits;

use GuzzleHttp\Exception\RequestException;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\UrlDataConnector\Interfaces\HttpConnectionInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This trait adds support for CSRF-tokens to an HTTP connector.
 * 
 * If a request fails due to missing CSRF token, a new token is requested from the `csrf_request_url`.
 * This token is then saved in the current user session and added to every request via the
 * `X-CSRF-Token` header.
 * 
 * @author Andrej Kabachnik
 *
 */
trait CsrfTokenTrait
{    
    private $csrfToken = null;
    
    private $csrfRequestUrl = '';
    
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
     * @return HttpConnectionInterface
     */
    protected function setCsrfToken(string $value) : HttpConnectionInterface
    {
        $this->csrfToken = $value;
        $this->getWorkbench()->getApp('exface.SapConnector')->setContextVariable('csrf_token_' . $this->getUrl(), $value, ContextManagerInterface::CONTEXT_SCOPE_SESSION);
        return $this;
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
        } catch (RequestException $e) {
            $response = $e->getResponse();
            // If there was an error, but there is no response (i.e. the error occurred before
            // the response was received), just rethrow the exception.
            if (! $response) {
                throw $e;
            }
            $token = $response->getHeader('X-CSRF-Token')[0];
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
     *
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\HttpConnector::getUseCookies()
     */
    public function getUseCookies()
    {
        return true;
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