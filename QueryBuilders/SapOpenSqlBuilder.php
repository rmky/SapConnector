<?php
namespace exface\SapConnector\QueryBuilders;

use exface\Core\QueryBuilders\MySqlBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;

/**
 * SQL query builder for SAP OpenSQL
 *
 * This query builder is based on the MySQL syntax, which is similar.
 *
 * @author Andrej Kabachnik
 *        
 */
class SapOpenSqlBuilder extends MySqlBuilder
{
    protected $short_alias_remove_chars = array(
        '.',
        '>',
        '<',
        '-',
        '(',
        ')',
        ':',
        ' ',
        '=',
        '/'
    );
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MySqlBuilder::buildSqlQuerySelect()
     */
    public function buildSqlQuerySelect()
    {
        $query = parent::buildSqlQuerySelect();
        
        // Do some simple replacements
        $query = str_replace(
            [
                ' LIMIT ',
                '"',
                '--'
            ], [
                ' UP TO ',
                '',
                '--"'
            ], 
            $query
        );
        
        // Add spaces to brackets
        $query = preg_replace('/\(([^ ])/', "( $1", $query);
        $query = preg_replace('/([^ ])\)/', "$1 )", $query);
        
        return $query;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getAliasDelim()
     */
    protected function getAliasDelim() : string
    {
        return '~';
    }
    
    /**
     * 
     * @param string $alias
     * @return string
     */
    protected function buildSqlAsForTables(string $alias) : string
    {
        return ' AS ' . $alias;
    }
    
    /**
     * 
     * @param string $alias
     * @return string
     */
    protected function buildSqlAsForSelects(string $alias) : string
    {
        return ' AS ' . $alias;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlOrderBy()
     */
    protected function buildSqlOrderBy(QueryPartSorter $qpart)
    {
        $sql = parent::buildSqlOrderBy($qpart);
        return str_replace([' ASC', ' DESC'], [' ASCENDING', ' DESCENDING'], $sql);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlWhereComparator()
     */
    protected function buildSqlWhereComparator($subject, $comparator, $value, DataTypeInterface $data_type, $sql_data_type = NULL, $value_list_delimiter = EXF_LIST_SEPARATOR)
    {
        switch ($comparator) {
            case EXF_COMPARATOR_IS_NOT:
                $output = $subject . " NOT LIKE '%" . $this->prepareWhereValue($value, $data_type) . "%'";
                break;
            case EXF_COMPARATOR_IS:
                $output = $subject . " LIKE '%" . $this->prepareWhereValue($value, $data_type) . "%'";
                break;
            default:
                return parent::buildSqlWhereComparator($subject, $comparator, $value, $data_type);
        }
        return $output;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MySqlBuilder::prepareWhereValue()
     */
    protected function prepareWhereValue($value, DataTypeInterface $data_type, $sql_data_type = NULL)
    {
        // IDEA some data type specific procession here
        if ($data_type instanceof DateDataType) {
            $value = $data_type::cast($value);
            return "'" . str_replace('-', '', $value) . "'";
        } 
        return parent::prepareWhereValue($value, $data_type, $sql_data_type);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::prepareInputValue($value, $data_type, $sql_data_type)
     */
    protected function prepareInputValue($value, DataTypeInterface $data_type, $sql_data_type = NULL)
    {
        if ($data_type instanceof DateDataType) {
            $value = $data_type::cast($value);
            return "'" . str_replace('-', '', $value) . "'";
        }
        return parent::prepareWhereValue($value, $data_type, $sql_data_type);
    }
}