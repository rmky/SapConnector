<?php
namespace exface\SapConnector\DataConnectors;

use exface\UrlDataConnector\DataConnectors\HttpConnector;
use exface\SapConnector\DataConnectors\Traits\CsrfTokenTrait;
use exface\SapConnector\DataConnectors\Traits\SapHttpConnectorTrait;

/**
 * Data connector SAP SOAP web services.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapSoapConnector extends HttpConnector
{
    use SapHttpConnectorTrait;
    use CsrfTokenTrait;
}