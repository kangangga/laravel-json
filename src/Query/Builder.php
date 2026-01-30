<?php

namespace Kangangga\Json\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;

class Builder extends BaseBuilder
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    public $operators = [
        '=',
        '<',
        '>',
        '<=',
        '>=',
        '<>',
        '!=',
        'like',
        'like binary',
        'not like',
        'ilike',
        '&',
        '|',
        '^',
        '<<',
        '>>',
        'rlike',
        'not rlike',
        'regexp',
        'not regexp',
        '~',
        '~*',
        '!~',
        '!~*',
        'similar to',
        'not similar to',
        'not ilike',
        '~~*',
        '!~~*',
        'contains',
        'exists',
        'type',
        'mod',
        'where',
        'all',
        'size',
        'regex',
        'text',
        'slice',
        'elemmatch',
    ];

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        if (empty($values)) {
            return true;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $data = $this->connection->readTable($this->from);

        $currentMax = 0;
        foreach ($data as $row) {
            if (isset($row['id']) && is_numeric($row['id']) && $row['id'] > $currentMax) {
                $currentMax = $row['id'];
            }
        }

        foreach ($values as $row) {
            if (!isset($row['id'])) {
                $currentMax++;
                $row['id'] = $currentMax;
            }
            $data[] = $row;
        }

        return $this->connection->writeTable($this->from, $data);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $data = $this->connection->readTable($this->from);

        $currentMax = 0;
        foreach ($data as $row) {
            if (isset($row['id']) && is_numeric($row['id']) && $row['id'] > $currentMax) {
                $currentMax = $row['id'];
            }
        }

        $currentMax++;
        $values['id'] = $currentMax;

        $data[] = $values;
        $this->connection->writeTable($this->from, $data);

        return $currentMax;
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $data = $this->connection->readTable($this->from);
        $count = 0;
        $updatedData = [];

        // Normalize values keys
        $normalizedValues = [];
        foreach ($values as $key => $value) {
            $normalizedValues[$this->normalizeColumn($key)] = $value;
        }

        foreach ($data as $row) {
            if ($this->matches($row)) {
                $row = array_merge($row, $normalizedValues);
                $count++;
            }
            $updatedData[] = $row;
        }

        if ($count > 0) {
            $this->connection->writeTable($this->from, $updatedData);
        }

        return $count;
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        if (!is_null($id)) {
            // Implicitly add where clause
            $this->where('id', '=', $id);
        }

        $data = $this->connection->readTable($this->from);
        $initialCount = count($data);

        $data = array_filter($data, function ($row) {
            return !$this->matches($row);
        });

        // Re-index array to keep JSON list format
        $data = array_values($data);

        if (count($data) < $initialCount) {
            $this->connection->writeTable($this->from, $data);
        }

        return $initialCount - count($data);
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $data = $this->connection->readTable($this->from);

        // Filter data
        $data = array_filter($data, function ($row) {
            return $this->matches($row);
        });

        // Handle Aggregates
        if (!empty($this->aggregate)) {
            $function = $this->aggregate['function'];
            $cols = $this->aggregate['columns'];
            $col = $cols[0] ?? null;

            $result = match ($function) {
                'count' => count($data),
                'max' => empty($data) ? null : max(array_column($data, $col)),
                'min' => empty($data) ? null : min(array_column($data, $col)),
                'sum' => empty($data) ? 0 : array_sum(array_column($data, $col)),
                'avg' => empty($data) ? 0 : (array_sum(array_column($data, $col)) / count($data)),
                default => count($data)
            };

            return collect([
                (object) ['aggregate' => $result]
            ]);
        }

        // Sort data
        if ($this->orders) {
            $data = $this->sortData($data);
        }

        // Offset & Limit
        if ($this->offset || $this->limit) {
            $data = array_slice(
                $data,
                $this->offset ?? 0,
                $this->limit ?? null
            );
        }

        // Cast to object to mimic DB behavior
        $data = array_map(function ($item) {
            return (object) $item;
        }, $data);

        return collect($data);
    }

    /**
     * Check if a row matches the query constraints.
     */
    protected function matches(array $row)
    {
        // Handle basic wheres
        foreach ($this->wheres ?? [] as $where) {
            if (!$this->matchWhere($row, $where)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match a single where clause.
     */
    protected function matchWhere(array $row, array $where)
    {
        $type = $where['type'];
        $column = isset($where['column']) ? $this->normalizeColumn($where['column']) : null;

        if ($type === 'Basic') {
            return $this->matchBasic($row, $where);
        }

        if ($type === 'In') {
            return in_array(Arr::get($row, $column), $where['values']);
        }

        if ($type === 'Null') {
            return is_null(Arr::get($row, $column));
        }

        if ($type === 'NotNull') {
            return !is_null(Arr::get($row, $column));
        }

        if ($type === 'Nested') {
            return true;
        }

        return true;
    }

    protected function normalizeColumn($column)
    {
        if (is_string($column) && str_starts_with($column, $this->from . '.')) {
            return substr($column, strlen($this->from) + 1);
        }
        return $column;
    }

    /**
     * Match a basic where clause.
     */
    protected function matchBasic(array $row, array $where)
    {
        $column = $this->normalizeColumn($where['column']);
        $operator = $where['operator'];
        $value = $where['value'];

        // Handle JSON arrow syntax (e.g. "value->name")
        if (str_contains($column, '->')) {
            $column = str_replace('->', '.', $column);
            // Handle case where column is stored as JSON string
            $parts = explode('.', $column);
            $rootKey = $parts[0];
            if (isset($row[$rootKey]) && is_string($row[$rootKey]) && /* quick check check */ (str_starts_with($row[$rootKey], '{') || str_starts_with($row[$rootKey], '['))) {
                $decoded = json_decode($row[$rootKey], true);
                if (is_array($decoded)) {
                    // Create temp structure for lookup
                    $tempRow = $row;
                    $tempRow[$rootKey] = $decoded;
                    $rowValue = Arr::get($tempRow, $column);
                } else {
                    $rowValue = Arr::get($row, $column);
                }
            } else {
                $rowValue = Arr::get($row, $column);
            }
        } else {
            $rowValue = $row[$column] ?? null;
        }

        switch ($operator) {
            case '=':
                return $rowValue == $value;
            case '!=':
            case '<>':
                return $rowValue != $value;
            case '>':
                return $rowValue > $value;
            case '>=':
                return $rowValue >= $value;
            case '<':
                return $rowValue < $value;
            case '<=':
                return $rowValue <= $value;
            case 'like':
                // Convert SQL like to Regex
                // % -> .*, _ -> .
                $pattern = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($value, '/')) . '$/i';
                return (bool) preg_match($pattern, (string) $rowValue);
            default:
                return false;
        }
    }

    /**
     * Sort the data.
     */
    protected function sortData(array $data)
    {
        usort($data, function ($a, $b) {
            foreach ($this->orders as $order) {
                $column = $order['column'];
                $direction = $order['direction'];

                $valA = $a[$column] ?? null;
                $valB = $b[$column] ?? null;

                if ($valA == $valB) {
                    continue;
                }

                $result = ($valA < $valB) ? -1 : 1;

                if ($direction === 'desc') {
                    $result *= -1;
                }

                return $result;
            }
            return 0;
        });

        return $data;
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate()
    {
        $this->connection->statement('truncate table ' . $this->from);
    }
}
