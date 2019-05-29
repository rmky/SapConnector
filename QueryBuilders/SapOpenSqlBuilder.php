<?php
namespace exface\SapConnector\QueryBuilders;

use exface\Core\QueryBuilders\MySqlBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\QueryBuilderException;

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
    /**
     *
     * @param QueryBuilderSelectorInterface $selector
     */
    public function __construct(QueryBuilderSelectorInterface $selector)
    {
        parent::__construct($selector);
        $forbidden = $this->getShortAliasForbiddenChars();
        $forbidden[] = '/';
        $this->setShortAliasForbiddenChars($forbidden);
    }
    
    /**
     * 
     * @return int
     */
    protected function getShortAliasMaxLength() : int
    {
        return 30;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MySqlBuilder::buildSqlQuerySelect()
     */
    public function buildSqlQuerySelect()
    {
        return $this->translateToOpenSQL(parent::buildSqlQuerySelect());
    }

    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MySqlBuilder::buildSqlQueryCount()
     */
    protected function buildSqlQueryCount() : string
    {
        return $this->translateToOpenSQL(parent::buildSqlQueryCount());
    }
    
    /**
     * Makes some text replacements to translate MySQL to OpenSQL
     * 
     * @param string $query
     * @return string
     */
    protected function translateToOpenSQL(string $query) : string
    {
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
        switch (true) {
            case $data_type instanceof DateDataType:
                $value = $data_type->parse($value);
                return "'" . str_replace(['-', ' ', ':'], '', $value) . "'";
            case $data_type instanceof TimeDataType:
                $value = $data_type->parse($value);
                return "'" . str_replace([' ', ':'], '', $value) . "'";
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
        switch (true) {
            case $data_type instanceof DateDataType:
                $value = $data_type->parse($value);
                return "'" . str_replace(['-', ' ', ':'], '', $value) . "'";
            case $data_type instanceof TimeDataType:
                $value = $data_type->parse($value);
                return "'" . str_replace([' ', ':'], '', $value) . "'";
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
        if (true === $this->checkForSqlStatement($select_statement)) {
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
        $args = $aggregator->getArguments();
        
        switch ($function_name) {
            case AggregatorFunctionsDataType::COUNT:
                // SAP can only do COUNT(*) or COUNT( DISTINCT col ), so do a COUNT DISTINCT if the column is not unique.
                if ($qpart->getAttribute()->getDataAddress() === $qpart->getAttribute()->getObject()->getUidAttribute()->getDataAddress()) {
                    return 'COUNT(*)';
                } else {
                    return 'COUNT( DISTINCT ' . $sql . ' )';
                }
            case AggregatorFunctionsDataType::COUNT_IF:
                $cond = $args[0];
                list($if_comp, $if_val) = explode(' ', $cond, 2);
                if (!$if_comp || is_null($if_val)) {
                    throw new QueryBuilderException('Invalid argument for COUNT_IF aggregator: "' . $cond . '"!', '6WXNHMN');
                }
                return "SUM( CASE WHEN " . $this->buildSqlWhereComparator($sql,  $if_comp, $if_val, $qpart->getAttribute()->getDataType()). " THEN 1 ELSE 0 END )";
            default:
                return parent::buildSqlGroupByExpression($qpart, $sql, $aggregator);
        }
    }
    
    protected function isEnrichmentAllowed() : bool
    {
        return false;
    }
    
    protected function getReadResultRows(SqlDataQuery $query) : array
    {
        $rows = [];
        foreach ($query->getResultArray() as $nr => $row) {
            foreach ($this->getAttributes() as $qpart) {
                $shortAlias = $this->getShortAlias($qpart->getColumnKey());
                $val = $row[$shortAlias];
                $type = $qpart->getDataType();
                switch (true) {
                    case strcasecmp($qpart->getDataAddressProperty('SQL_DATA_TYPE'), 'BINARY') === 0:
                        $val = $this->decodeBinary($val);
                        break;
                    case $type->isExactly('exface.Core.NumericString'):
                        if (is_numeric($val) && intval($val) === 0) {
                            $val = null;
                        }
                        break;
                    case $type instanceof BooleanDataType:
                        if ($val === 'X') {
                            $val = 1;
                        }
                        break;
                    case $type instanceof NumberDataType:
                        // Negative numbers have a minus at the end, so we need to put it up front manually
                        // Positive numbers may have a space at the end - remove that too.
                        if (substr($val, -1) === '-') {
                            $val = '-' . substr($val, 0, -1);
                        } elseif (substr($val, -1) === ' ') {
                            $val = substr($val, 0, -1);
                        }
                        break;
                    case $type instanceof DateDataType:
                    case $type instanceof DateTimeDataType:
                        // Dates come as YYYYMMDD, so we need to add the dashes manually.
                        $val = trim($val);
                        if ($val) {
                            $hasTime = strlen($val) === 14;
                            if ($val === '00000000' || $val === '00000000000000') {
                                $val = null;
                            } else {
                                $val = substr($val, 0, 4) . '-' . substr($val, 4, 2) . '-' . substr($val, 6);
                            }
                            
                            if ($hasTime) {
                                if ($type instanceof DateTimeDataType) {
                                    $val = substr($val, 0, 10) . ' ' . substr($val, 10, 2) . ':' . substr($val, 12, 2) . ':' . substr($val, 14, 2);
                                } else {
                                    $val = substr($val, 0, 10);
                                }
                            }
                        }
                        break;
                        
                }
                $rows[$nr][$qpart->getColumnKey()] = $val;
            }
        }
        return $rows;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MySqlBuilder::buildSqlQueryTotals()
     */
    public function buildSqlQueryTotals()
    {
        $totals_joins = array();
        $totals_core_selects = array();
        if (count($this->getTotals()) > 0) {
            // determine all joins, needed to perform the totals functions
            foreach ($this->getTotals() as $qpart) {
                $totals_core_selects[] = $this->buildSqlSelect($qpart, null, null, null, $qpart->getTotalAggregator());
                $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($qpart));
            }
        }
        
        // filters -> WHERE
        $totals_where = $this->buildSqlWhere($this->getFilters());
        $totals_having = $this->buildSqlHaving($this->getFilters());
        $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($this->getFilters()));
        // Object data source property SQL_SELECT_WHERE -> WHERE
        if ($custom_where = $this->getMainObject()->getDataAddressProperty('SQL_SELECT_WHERE')) {
            $totals_where = $this->appendCustomWhere($totals_where, $custom_where);
        }
        // GROUP BY
        foreach ($this->getAggregations() as $qpart) {
            $group_by .= ', ' . $this->buildSqlGroupBy($qpart);
            $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($qpart));
        }
        if ($group_by) {
            $totals_core_selects[] = $this->buildSqlSelect($this->getAttribute($this->getMainObject()->getUidAttributeAlias()), null, null, null, new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
        }
        
        $totals_core_select = implode(",\n", $totals_core_selects);
        $totals_from = $this->buildSqlFrom();
        $totals_join = implode("\n ", $totals_joins);
        $totals_where = $totals_where ? "\n WHERE " . $totals_where : '';
        $totals_having = $totals_having ? "\n WHERE " . $totals_having : '';
        $totals_group_by = $group_by ? "\n GROUP BY " . substr($group_by, 2) : '';
        
        $totals_query = "\n SELECT COUNT(*) AS EXFCNT" . ($totals_core_select ? ', ' . $totals_core_select : '') . ' FROM ' . $totals_from . $totals_join . $totals_where . $totals_group_by . $totals_having;
        
        return $this->translateToOpenSQL($totals_query);
    }
    
    /**
     * Comments seem to cause weired problems in OpenSQL - just remove them!
     * 
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlComment()
     */
    protected function buildSqlComment(string $text) : string
    {
        return '';
    }
}