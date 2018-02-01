<?php
namespace exface\SapConnector\DataConnectors;

use exface\UrlDataConnector\DataConnectors\ODataConnector;
use exface\SapConnector\ModelBuilders\SapODataModelBuilder;

/**
 * HTTP data connector for SAP oData services.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapODataConnector extends ODataConnector
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\ODataConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new SapODataModelBuilder($this);
    }
}