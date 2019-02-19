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
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringEnumDataType;
use exface\Core\DataTypes\NumberEnumDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Factories\SelectorFactory;
use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\Exceptions\DataTypes\DataTypeNotFoundError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

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
    private $domains = [];
    
    private $overwriteEnumTypes = false;
    
    protected function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $table_name): array
    {
        $response = $this->getDataConnection()->performRequest('POST', 'ddic?rowNumber=1&ddicEntityName=' . $table_name, '');
        $xmlCrawler = new Crawler($response->getBody()->__toString());
        $attrRows = [];
        foreach ($xmlCrawler->filterXPath('//dataPreview:columns/dataPreview:metadata') as $colNode) {
            $addr = $colNode->getAttribute('dataPreview:name');
            $type = $this->guessDataType($meta_object, $colNode->getAttribute('dataPreview:type'), $table_name, $addr);
            $attrRows[] = [
                'ALIAS' => $addr,
                'NAME' => $colNode->getAttribute('dataPreview:description'),
                'DATATYPE' => $this->getDataTypeId($type),
                'DATA_ADDRESS' => $addr,
                'OBJECT' => $meta_object->getId()
            ];
        }
        
        return $attrRows;
    }
    
    protected function generateAlias(string $openSqlName) : string
    {
        $alias = str_replace('/', '_', $openSqlName);
        if (substr($alias, 0, 1) === '_') {
            $alias = substr($alias, 1);
        }
        return $alias;
    }

    protected function findObjectTables(string $data_address_mask = null): array
    {
        throw new NotImplementedError('Generating meta objects currently not supported for SAP ADT SQL connections');
    }    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::guessDataType($workbench, $sql_data_type, $length, $scale)
     */
    protected function guessDataType(MetaObjectInterface $object, string $sapType, $tableName = null, $columnName = null) : DataTypeInterface
    {
        $workbench = $object->getWorkbench();
        $sapType = strtoupper(trim($sapType));
        $sapDomain = $this->getDomainName($tableName, $columnName);
        
        if ($sapDomain !== '') {
            try {
                $typeSelector = SelectorFactory::createFromAlias($object->getApp(), $this->generateAlias($sapDomain), DataTypeSelector::class);
                $data_type = $workbench->model()->getModelLoader()->loadDataType($typeSelector);
            } catch (DataTypeNotFoundError $e) {
                $data_type = null;
            }
        }
        
        if ($data_type === null) {
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
            
            $enumVals = $this->getDomainValues($tableName, $columnName);
            if (false === empty($enumVals)) {
                if ($data_type instanceof NumberDataType) {
                    $prototype = NumberEnumDataType::class;
                } else {
                    $prototype = StringEnumDataType::class;
                }
                
                $ds = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.DATATYPE');
                $ds->addRow([
                    'ALIAS' => $this->generateAlias($sapDomain),
                    'NAME' => $this->getDomainDescription($tableName, $columnName),
                    'PROTOTYPE' => $prototype,
                    'CONFIG_UXON' => (new UxonObject(['values' => $enumVals]))->toJson(),
                    'APP' => $object->getAppId()
                ]);
                $ds->dataCreate();
                
                $data_type = $workbench->model()->getModelLoader()->loadDataType($typeSelector);
            }
        }
        
        return $data_type;
    }
    
    protected function getDomainValues(string $tableName, string $columnName) : array
    {
        $values = [];
        foreach ($this->getDomainData($tableName) as $dom) {
            if (strcasecmp($dom['FIELDNAME'], $columnName) === 0 && $dom['VALUE'] !== '') {
                $values[$dom['VALUE']] = $dom['TEXT'];
            }
        }
        return $values;
    }
    
    protected function getDomainName(string $tableName, string $columnName) : string
    {
        foreach ($this->getDomainData($tableName) as $dom) {
            if (strcasecmp($dom['FIELDNAME'], $columnName) === 0) {
                return $dom['DOMAIN'];
            }
        }
        return '';
    }
    
    protected function getDomainDescription(string $tableName, string $columnName) : string
    {
        foreach ($this->getDomainData($tableName) as $dom) {
            if (strcasecmp($dom['FIELDNAME'], $columnName) === 0) {
                return $dom['DOMAIN_TEXT'];
            }
        }
        return '';
    }
    
    protected function getDomainData(string $tableName) : array
    {
        if ($this->domains[$tableName] === null) {
            $sql = <<<SQL
            
        SELECT
            dd03l~TABNAME,
            dd03l~FIELDNAME,
            dd03l~ROLLNAME AS DOMAIN,
            dd04t~DDTEXT as DOMAIN_TEXT,
            dd07v~DOMVALUE_L AS VALUE,
            dd07v~DDTEXT AS TEXT
        FROM DD03L AS dd03l
            LEFT OUTER JOIN DD04T AS dd04t ON dd03l~ROLLNAME = dd04t~ROLLNAME AND dd04t~ddLANGUAGE = '{$this->getSapLang($this->getModelLanguage())}'
            LEFT OUTER JOIN DD07V AS dd07v ON dd03l~ROLLNAME = dd07v~DOMNAME AND dd07v~DDLANGUAGE = '{$this->getSapLang($this->getModelLanguage())}'
        WHERE dd03l~TABNAME = '{$tableName}'
            AND dd03l~FIELDNAME <> '.INCLUDE'
            
SQL;
            $q = $this->getDataConnection()->runSql($sql);
            $this->domains[$tableName] = $q->getResultArray();
        }
        return $this->domains[$tableName];
    }
    
    /**
     *
     * @return string
     */
    public function getSapLang(string $langugeCode) : string
    {
        return 'D';
    }    
    
    protected function generateAttributes(MetaObjectInterface $meta_object, DataTransactionInterface $transaction = null) : DataSheetInterface
    {
        $result_data_sheet = DataSheetFactory::createFromObjectIdOrAlias($meta_object->getWorkbench(), 'exface.Core.ATTRIBUTE');
        
        $imported_rows = $this->getAttributeDataFromTableColumns($meta_object, $meta_object->getDataAddress());
        foreach ($imported_rows as $row) {
            $existingAttrs = $meta_object->findAttributesByDataAddress($row['DATA_ADDRESS']);
            if (empty($existingAttrs)) {
                if ($meta_object->isWritable(true) === false) {
                    $row['WRITABLEFLAG'] = false;
                    $row['EDITABLEFLAG'] = false;
                }
                $result_data_sheet->addRow($row);
            } else {
                foreach ($existingAttrs as $attr) {
                    if($attr->getAlias() === $row['ALIAS']) {
                        if ($this->getOverwriteEnumTypes()) {
                            $importedType = DataTypeFactory::createFromString($meta_object->getWorkbench(), $row['DATATYPE']);
                            if (($importedType instanceof EnumDataTypeInterface) && ! $attr->getDataType()->isExactly($importedType)) {
                                $dsUpdate = DataSheetFactory::createFromObjectIdOrAlias($meta_object->getWorkbench(), 'exface.Core.ATTRIBUTE');
                                $dsUpdate->addFilterFromString('UID', $attr->getId());
                                $dsUpdate->getColumns()->addMultiple([
                                    'UID',
                                    'DATATYPE',
                                    'MODIFIED_ON',
                                    'CUSTOM_DATA_TYPE' => '{}'
                                ]);
                                $dsUpdate->dataRead();
                                
                                $dsUpdate->setCellValue('DATATYPE', 0, $row['DATATYPE']);
                                $dsUpdate->setCellValue('CUSTOM_DATA_TYPE', 0, '{}');
                                $dsUpdate->dataUpdate();
                            }
                        }
                    }
                }
            }
        }
        
        if (! $result_data_sheet->isEmpty()) {
            $result_data_sheet->dataCreate(false, $transaction);
        }
        
        $result_data_sheet->setCounterForRowsInDataSource(count($imported_rows));
        
        return $result_data_sheet;
    }
    
    /**
     *
     * @return bool
     */
    protected function getOverwriteEnumTypes() : bool
    {
        return $this->overwriteEnumTypes;
    }
    
    /**
     * Set to TRUE to replace attribute data types for enum domains with auto-generated enum types.
     * 
     * This will not affect secondary attributes for a certain data address (i.e. will only
     * affect attributes, where the data address matches the alias).
     * 
     * @uxon-property overwrite_enum_types
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return SapAdtSqlModelBuilder
     */
    public function setOverwriteEnumTypes(bool $value) : SapAdtSqlModelBuilder
    {
        $this->overwriteEnumTypes = $value;
        return $this;
    }
}