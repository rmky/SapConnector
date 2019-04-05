<?php
namespace exface\SapConnector\QueryBuilders;

use exface\UrlDataConnector\QueryBuilders\OData2JsonUrlBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\UrlDataConnector\Psr7DataQuery;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\StringDataType;

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
    
    protected function buildResultRows($parsed_data, Psr7DataQuery $query)
    {
        $rows = parent::buildResultRows($parsed_data, $query);
        
        foreach ($this->getAttributes() as $qpart) {
            if ($qpart->getDataType() instanceof DateDataType) {
                $dataType = $qpart->getDataType();
                foreach ($rows as $rowNr => $row) {
                    $val = $row[$qpart->getDataAddress()];
                    if (StringDataType::startsWith($val, '/Date(')) {
                        $mil = substr($val, 6, -2);
                        $seconds = $mil / 1000;
                        $newVal = $dataType->parse($seconds);
                        $rows[$rowNr][$qpart->getDataAddress()] = $newVal;
                    }
                    
                }
            }
        }
        
        return $rows;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\QueryBuilders\AbstractUrlBuilder::buildRequestGet()
     */
    protected function buildRequestGet()
    {
        $request = parent::buildRequestGet();
        
        $method = $request->getMethod();
        if ($method !== 'POST' || $method !== 'PUT' || $method !== 'DELETE') {
            $qPramsString = $request->getUri()->getQuery();
            if (mb_stripos($qPramsString, '&$format') === false) {
                $qPramsString .= '&$format=json';
                $request = $request->withUri($request->getUri()->withQuery($qPramsString));
            }
        }
        
        return $request;
    }
}