<?php
namespace exface\SapConnector\QueryBuilders;

use exface\Core\QueryBuilders\MySqlBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;

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
    
    protected function buildSqlOrderBy(QueryPartSorter $qpart)
    {
        $sql = parent::buildSqlOrderBy($qpart);
        return str_replace(['ASC', 'DESC'], ['ASCENDING', 'DESCENDING'], $sql);
    }
}