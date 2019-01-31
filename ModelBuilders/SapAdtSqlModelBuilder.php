<?php
namespace exface\SapConnector\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\ModelBuilders\AbstractSqlModelBuilder;
use exface\SapConnector\DataConnectors\SapAdtSqlConnector;
use Symfony\Component\DomCrawler\Crawler;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\DateDataType;

/**
 * 
 * 
 * @author Andrej Kabachnik
 * 
 * @method SapAdtSqlConnector getDataConnection()
 *
 */
class SapAdtSqlModelBuilder extends AbstractSqlModelBuilder
{
    protected function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $table_name): array
    {
        $response = $this->getDataConnection()->performRequest('POST', 'ddic?rowNumber=1&ddicEntityName=' . $table_name, '');
        $xmlCrawler = new Crawler($response->getBody()->__toString());
        $attrRows = [];
        foreach ($xmlCrawler->filterXPath('//dataPreview:columns/dataPreview:metadata') as $colNode) {
            $attrRows[] = [
                'ALIAS' => $colNode->getAttribute('dataPreview:name'),
                'NAME' => $colNode->getAttribute('dataPreview:description'),
                'DATATYPE' => $this->getDataTypeId($this->guessDataType($meta_object->getWorkbench(), $colNode->getAttribute('dataPreview:type'))),
                'DATA_ADDRESS' => $colNode->getAttribute('dataPreview:name'),
                'OBJECT' => $meta_object->getId()
            ];
        }
        
        return $attrRows;
    }

    protected function findObjectTables(string $data_address_mask = null): array
    {
        throw new NotImplementedError('Generating meta objects currently not supported for SAP ADT SQL connections');
    }    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::guessDataType()
     */
    protected function guessDataType(Workbench $workbench, $sapType, $length = null, $number_scale = null)
    {
        $sapType = strtoupper(trim($sapType));
        
        switch ($sapType) {
            case 'F':
                $data_type = DataTypeFactory::createFromString($workbench, NumberDataType::class);
                break;
            case 'I':
                $data_type = DataTypeFactory::createFromString($workbench, IntegerDataType::class);
                break;
            case 'T':
                $data_type = DataTypeFactory::createFromString($workbench, TimestampDataType::class);
                break;
            case 'D':
                $data_type = DataTypeFactory::createFromString($workbench, DateDataType::class);
                break;
            default:
                $data_type = DataTypeFactory::createFromString($workbench, StringDataType::class);
        }
        
        return $data_type;
    }
}