<?php
namespace exface\SapConnector\QueryBuilders;

use exface\UrlDataConnector\QueryBuilders\OData2JsonUrlBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\UrlDataConnector\Psr7DataQuery;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\UrlDataConnector\QueryBuilders\JsonUrlBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;

/**
 * Query builder for SAP oData services in JSON format.
 * 
 * See the AbstractUrlBuilder for information about available data address properties.
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
                        // FIXME should not round here. Otherwise real date values allways change
                        // when an object is saved the first time after being created.
                        $seconds = round($mil / 1000);
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
    
    /**
     * We need a custom JSON stringifier for SAP because some data types are handled in
     * a very (VERY) strange way.
     * 
     * - Edm.Decimal is a number, but MUST be enclosed in quotes
     * 
     * @see JsonUrlBuilder::encodeBody()
     */
    protected function encodeBody($serializableData) : string
    {
        $forceQuoteVals = [];
        
        foreach ($this->getValues() as $qpart) {
            if ($this->needsQuotes($qpart) === true) {
                $forceQuoteVals[] = $qpart->getDataAddress();
            }
        }
        
        if (is_array($serializableData)) {
            $content = '';
            foreach ($serializableData as $val) {
                $content .= ($content ? ',' : '') . $this->encodeBody($val);
            }
            return '[' . $content . ']';
        } elseif ($serializableData instanceof \stdClass) {
            $pairs = [];
            $arr = (array) $serializableData;
            foreach ($arr as $p => $v) {
                $pairs[] = '"' . $p . '":' . (in_array($p, $forceQuoteVals) || false === is_numeric($v) ? '"' . str_replace('"', '\"', $v) . '"' : $v);
            }
            return '{' . implode(',', $pairs) . '}';
        } else {
            return '"' . str_replace('"', '\"', $serializableData) . '"';
        }
        
        return parent::encodeBody($serializableData);
    }
    
    protected function needsQuotes(QueryPartAttribute $qpart) : bool
    {
        $modelType = $qpart->getDataType();
        $odataType = $qpart->getDataAddressProperty('odata_type');
        switch (true) {
            case $odataType  === 'Edm.Decimal': return true;
            case $modelType instanceof StringDataType: return true;
        }
        return false;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see OData2JsonUrlBuilder::buildODataValue()
     */
    protected function buildODataValue(QueryPartAttribute $qpart, $preformattedValue = null)
    {
        switch ($qpart->getAttribute()->getDataAddressProperty('odata_type')) {
            case 'Edm.DateTime':
                $date = new \DateTime(str_replace("'", '', $preformattedValue));
                return "/Date(" . $date->format('U') . "000)/";
            default:
                return parent::buildODataValue($qpart, $preformattedValue);
        }
    }
}