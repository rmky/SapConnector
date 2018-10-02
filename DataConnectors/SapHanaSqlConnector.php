<?php
namespace exface\SapConnector\DataConnectors;

use exface\SapConnector\ModelBuilders\SapHanaSqlModelBuilder;
use exface\Core\DataConnectors\OdbcSqlConnector;

/**
 * SQL connector for SAP HANA based on ODBC
 *
 * @author Andrej Kabachnik
 */
class SapHanaSqlConnector extends OdbcSqlConnector
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new SapHanaSqlModelBuilder($this);
    }
}
?>