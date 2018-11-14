<?php
namespace exface\SapConnector\QueryBuilders;

use exface\UrlDataConnector\QueryBuilders\ODataJsonUrlBuilder;
use exface\UrlDataConnector\Psr7DataQuery;

/**
 * Query builder for SAP oData services in JSON format.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapODataJsonUrlBuilder extends ODataJsonUrlBuilder
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\QueryBuilders\ODataJsonUrlBuilder::buildPathToResponseRows()
     */
    protected function buildPathToResponseRows(Psr7DataQuery $query)
    {
        $customPath = $this->getMainObject()->getDataAddressProperty('response_data_path');
        return $customPath ?? 'd/results';
    }
}