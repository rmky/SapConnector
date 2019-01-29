<?php
namespace exface\SapConnector\QueryBuilders;

use exface\Core\QueryBuilders\MySqlBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\Interfaces\Model\AggregatorInterface;

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
                //'--'
            ], [
                ' UP TO ',
                '',
                //'--"'
            ], 
            $query
        );
        
        // Remove comments as they cause strange errors when SQL is copied into eclipse
        // TODO find a way to use comments - otherwise it's hard to understand the query!
        $query = preg_replace('/--.*/', "", $query);
        
        // Add spaces to brackets
        $query = preg_replace('/\(([^ ])/', "( $1", $query);
        $query = preg_replace('/([^ ])\)/', "$1 )", $query);
        // The above does not replace )) with ) ) for some reason: repeating helps.
        $query = preg_replace('/([^ ])\)/', "$1 )", $query);
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
                $output = parent::buildSqlWhereComparator($subject, $comparator, $value, $data_type);
        }
        
        // Add line breaks to IN statements (to avoid more than 255 characters per line)
        $output = str_replace("','", "',\n'", $output);
        
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
            $value = $data_type->parse($value);
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
            $value = $data_type->parse($value);
            return "'" . str_replace('-', '', $value) . "'";
        }
        return parent::prepareWhereValue($value, $data_type, $sql_data_type);
    }
    
    /**
     * Adds a wrapper to a select statement, that should take care of the returned value if the statement
     * itself returns null (like IFNULL(), NVL() or COALESCE() depending on the SQL dialect).
     *
     * @param string $select_statement            
     * @param string $value_if_null            
     * @return string
     */
    protected function buildSqlSelectNullCheck($select_statement, $value_if_null)
    {
        if (StringDataType::startsWith($select_statement, 'COUNT(') === true) {
            return $select_statement;
        }
        return 'COALESCE(' . $select_statement . ', ' . (is_numeric($value_if_null) ? $value_if_null : "'" . $value_if_null . "'") . ')';
    } 
    
    /**
     * The OpenSQL builder cannot read attributes from reverse relations because OpenSQL
     * does not support subqueries in SELECT clauses - only in WHERE (as of 7.50).
     * 
     * Since reversly related attributes cannot be read, they will be not part of
     * the main query and will produce subqueries on data sheet level (subsheets)
     * with WHERE IN and GROUP BY. The results will then be joined in-memory. 
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        $sameSource = parent::canReadAttribute($attribute);
        if ($sameSource === false) {
            return false;
        }
        
        $relPath = $attribute->getRelationPath();
        if ($relPath->isEmpty() === false) {
            foreach ($relPath->getRelations() as $rel) {
                if ($rel->isReverseRelation() === true) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlGroupByExpression()
     */
    protected function buildSqlGroupByExpression(QueryPartAttribute $qpart, $sql, AggregatorInterface $aggregator){
        $function_name = $aggregator->getFunction()->getValue();
        
        switch ($function_name) {
            case AggregatorFunctionsDataType::COUNT:
                // For some reason COUNT(col) produces a grammar error
                return 'COUNT(*)';
            default:
                return parent::buildSqlGroupByExpression($qpart, $sql, $aggregator);
        }
    }
    
    protected function isEnrichmentAllowed() : bool
    {
        return false;
    }
}