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
            $rows[$i]['LABEL'] = $entitySet->attr('sap:label');
            
            if (! ($entitySet->attr('sap:updatable') === 'true' || $entitySet->attr('sap:creatable') === 'true' || $entitySet->attr('sap:deletable') === 'true')) {
                // Allways set to false for some reason???
                //$rows[$i]['WRITABLE_FLAG'] = 0;
            }
            
            if ($rows[$i]['DATA_ADDRESS_PROPS']){
                $data_address_props = json_decode($rows[$i]['DATA_ADDRESS_PROPS'], true);
            } else {
                $data_address_props = []; 
            }
            
            if ($entitySet->attr('sap:pageable') !== 'true') {
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
            $rows[$i]['LABEL'] = $property->getAttribute('sap:label');
            $rows[$i]['FILTERABLEFLAG'] = ($property->getAttribute('sap:filterable') === 'true' ? 1 : 0);
            $rows[$i]['SORTABLEFLAG'] = ($property->getAttribute('sap:sortable') === 'true' ? 1 : 0);
            $rows[$i]['AGGREGATABLEFLAG'] = 0;
            // Allways set to false for some reason???
            //$rows[$i]['WRITABLEFLAG'] = ($property->getAttribute('sap:creatable') === 'true' || $property->getAttribute('sap:updatable') === 'true' ? 1 : 0);
            //$rows[$i]['EDITABLEFLAG'] = $rows[$i]['WRITABLEFLAG'];
        }
        $ds->removeRows()->addRows($rows);
        
        return $ds;
    }
}