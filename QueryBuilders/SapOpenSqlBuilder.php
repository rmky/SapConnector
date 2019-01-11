<?php
namespace exface\SapConnector\QueryBuilders;

use exface\Core\QueryBuilders\MySqlBuilder;

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
        $query = str_replace(
            [
                ' LIMIT ',
                //'--',
                '"'
            ], [
                ' UP TO ',
                //'*',
                ''
            ], 
            $query
        );
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
}