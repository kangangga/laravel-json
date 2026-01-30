<?php

namespace Kangangga\Json\Query;

use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;

class Grammar extends BaseGrammar
{
    /**
     * The grammar table prefix.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * Compile an insert statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert(\Illuminate\Database\Query\Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        return "insert into $table ($columns) values $parameters";
    }

    /**
     * Compile an insert and get ID statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @param  string  $sequence
     * @return string
     */
    public function compileInsertGetId(\Illuminate\Database\Query\Builder $query, $values, $sequence)
    {
        return $this->compileInsert($query, $values);
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileUpdate(\Illuminate\Database\Query\Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        $columns = collect($values)->map(function ($value, $key) {
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        })->implode(', ');

        $where = $this->compileWheres($query);

        return trim("update {$table} set $columns $where");
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(\Illuminate\Database\Query\Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $where = $this->compileWheres($query);

        return trim("delete from $table $where");
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(\Illuminate\Database\Query\Builder $query)
    {
        return ['truncate ' . $this->wrapTable($query->from) => []];
    }
}
