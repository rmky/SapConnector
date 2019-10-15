<?php
namespace exface\SapConnector\DataConnectors;

use exface\UrlDataConnector\DataConnectors\HttpConnector;
use exface\SapConnector\DataConnectors\Traits\SapHttpConnectorTrait;

/**
 * Data connector to remote control ITSmobile transactions.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapITSmobileConnector extends HttpConnector
{
    use SapHttpConnectorTrait;
    
    public function getUseCookies() : bool
    {
        return true;
    }
    
    public function getUseCookieSessions() : bool
    {
        return true;
    }
}