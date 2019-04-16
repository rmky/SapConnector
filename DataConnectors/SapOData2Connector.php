<?php
namespace exface\SapConnector\DataConnectors;

use exface\UrlDataConnector\DataConnectors\OData2Connector;
use exface\SapConnector\ModelBuilders\SapOData2ModelBuilder;
use exface\SapConnector\DataConnectors\Traits\CsrfTokenTrait;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\UrlDataConnector\Psr7DataQuery;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\SapConnector\DataConnectors\Traits\SapHttpConnectorTrait;

/**
 * HTTP data connector for SAP oData 2.0 services.
 * 
 * @author Andrej Kabachnik
 *
 */
class SapOData2Connector extends OData2Connector
{
    use CsrfTokenTrait;
    use SapHttpConnectorTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\ODataConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new SapOData2ModelBuilder($this);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UrlDataConnector\DataConnectors\HttpConnector::performQuery()
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! ($query instanceof Psr7DataQuery)) {
            throw new DataConnectionQueryTypeError($this, 'Connector "' . $this->getAliasWithNamespace() . '" expects a Psr7DataQuery as input, "' . get_class($query) . '" given instead!');
        }
        /* @var $query \exface\UrlDataConnector\Psr7DataQuery */
            
        $request = $query->getRequest();
        if ($request->getMethod() !== 'GET' && $request->getMethod() !== 'OPTIONS') {
            $query->setRequest($request->withHeader('X-CSRF-Token', $this->getCsrfToken()));
        }
        
        return parent::performQuery($query);
    }
}