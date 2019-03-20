<?php
namespace exface\SapConnector\QueryBuilders;

use exface\UrlDataConnector\QueryBuilders\OData2JsonUrlBuilder;

/**
 * Query builder for SAP oData services in JSON format.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapOData2JsonUrlBuilder extends OData2JsonUrlBuilder
{
    /**
     * 
     * {@inheritdoc}
     * @see OData2JsonUrlBuilder::buildUrlFilterPredicate
     */
    protected function buildUrlFilterPredicate(QueryPartFilter $qpart, string $property, string $escapedValue) : string
    {
        $comp = $qpart->getComparator();
        switch ($comp) {
            case EXF_COMPARATOR_IS:
                // SAP NetWeaver produces a 500-error on substringof() eq true - need to remove the "eq true".
                if ($qpart->getDataType() instanceof NumberDataType) {
                    return parent::buildUrlFilterPredicate($qpart, $property, $escapedValue);
                } else {
                    return "substringof({$escapedValue}, {$property})";
                } 
            default:
                return parent::buildUrlFilterPredicate($qpart, $property, $escapedValue);
        }
    }
}