<?php
namespace exface\SapConnector\ModelBuilders;

use exface\UrlDataConnector\ModelBuilders\ODataModelBuilder;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use Symfony\Component\DomCrawler\Crawler;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Creates a meta model from SAP specific oData $metadata.
 * 
 * In addition to the regular oData $metadata, this model builder will process SAP specific
 * node attributes like sap:label, sap:sortable, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapODataModelBuilder extends ODataModelBuilder
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\ModelBuilders\ODataModelBuilder::getObjectData($entity_nodes, $app, $data_source)
     */
    protected function getObjectData(Crawler $entity_nodes, AppInterface $app, DataSourceInterface $data_source) 
    {
        $ds = parent::getObjectData($entity_nodes, $app, $data_source);
        
        $rows = $ds->getRows();
        foreach ($entity_nodes as $i => $entity) {
            $entitySet = $this->getEntitySetNode($entity);
            if ($label = $entitySet->attr('sap:label')) {
                $rows[$i]['LABEL'] = $label;
            }
            
            if (! ($entitySet->attr('sap:updatable') === 'true' || $entitySet->attr('sap:creatable') === 'true' || $entitySet->attr('sap:deletable') === 'true')) {
                // Allways set to false for some reason???
                //$rows[$i]['WRITABLE_FLAG'] = 0;
            }
            
            if ($rows[$i]['DATA_ADDRESS_PROPS']){
                $data_address_props = json_decode($rows[$i]['DATA_ADDRESS_PROPS'], true);
            } else {
                $data_address_props = []; 
            }
            
            if (strtolower($entitySet->attr('sap:pageable')) === 'false') {
                $data_address_props['request_remote_pagination'] = false;
            }
            $rows[$i]['DATA_ADDRESS_PROPS'] = json_encode($data_address_props);
        }
            
        $ds->removeRows()->addRows($rows);
        
        return $ds;
    }
    
    protected function getAttributeData(Crawler $property_nodes, MetaObjectInterface $object)
    {
        $ds = parent::getAttributeData($property_nodes, $object);
        
        $rows = $ds->getRows();
        foreach ($property_nodes as $i => $property) {
            if ($label = $property->getAttribute('sap:label')) {
                $rows[$i]['LABEL'] = $label;
            }
            
            // Allways set to false for some reason???
            //$rows[$i]['WRITABLEFLAG'] = ($property->getAttribute('sap:creatable') === 'false' && $property->getAttribute('sap:updatable') === 'false' ? 0 : 1);
            //$rows[$i]['EDITABLEFLAG'] = $rows[$i]['WRITABLEFLAG'];
            
            // Additional flags like "sap:sortable" will be translated to data address properties.
            // Not being able to sort on server side does not mean, the attribute is not sortable
            // - it can still be sorted in the query builder. If it should not be sortable at all,
            // the user will disable sorting manually in the meta model.            
            if ($rows[$i]['DATA_ADDRESS_PROPS']){
                $data_address_props = json_decode($rows[$i]['DATA_ADDRESS_PROPS'], true);
            } else {
                $data_address_props = [];
            }
            
            // The SAP $metadata will have sap:filterable="false" or sap:sortable="false" if the attribute
            // can be filtered or sorted over and no such properties at all if remote filtering and sorting
            // is supported
            if (strtolower($property->getAttribute('sap:filterable')) !== 'false') {
                $data_address_props['filter_remote'] = 1;
            }
            
            if (strtolower($property->getAttribute('sap:sortable')) !== 'false') {
                $data_address_props['sort_remote'] = 1;
            }
            
            if (! empty($data_address_props)) {
                $rows[$i]['DATA_ADDRESS_PROPS'] = json_encode($data_address_props);
            }
        }
        $ds->removeRows()->addRows($rows);
        
        return $ds;
    }
}