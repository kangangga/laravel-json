<?php

namespace Kangangga\Json;

use Illuminate\Database\Connection as BaseConnection;
use Kangangga\Json\Query\Builder as QueryBuilder;
use Kangangga\Json\Serializers\SerializerInterface;
use Kangangga\Json\Serializers\JsonSerializer;
use Kangangga\Json\Serializers\MsgpackSerializer;
use Kangangga\Json\Serializers\JsonGzipSerializer;
use Kangangga\Json\Serializers\YamlSerializer;
use Kangangga\Json\Concerns\ManagesTransactions;

class Connection extends BaseConnection
{
    use ManagesTransactions;
    /**
     * The JSON database path.
     *
     * @var string
     */
    protected $database;

    /**
     * The connection name.
     *
     * @var string
     */
    protected $name;

    /**
     * The serializer instance.
     *
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * Create a new database connection instance.
     *
     * @param  array  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->database = $config['database'];
        $this->tablePrefix = $config['prefix'] ?? '';
        $this->name = $config['name'] ?? 'json';

        // Initialize serializer
        $this->serializer = $this->createSerializer($config['serializer'] ?? 'json');

        // Ensure database directory exists
        if (!file_exists($this->database)) {
            mkdir($this->database, 0755, true);
        }

        // Set a dummy PDO connection (required by parent class but not used)
        $this->pdo = null;
        $this->readPdo = null;

        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultQueryGrammar();
    }

    /**
     * Create serializer instance based on type.
     */
    protected function createSerializer(string $type): SerializerInterface
    {
        return match ($type) {
            'msgpack' => new MsgpackSerializer(),
            'json_gzip' => new JsonGzipSerializer(),
            'yaml' => new YamlSerializer(),
            default => new JsonSerializer(),
        };
    }

    /**
     * Get the PDO connection (not used for JSON database).
     *
     * @return null
     */
    public function getPdo()
    {
        return null;
    }

    /**
     * Get the read PDO connection (not used for JSON database).
     *
     * @return null
     */
    public function getReadPdo()
    {
        return null;
    }

    /**
     * Get a configuration value.
     *
     * @param  string|null  $key
     * @return mixed
     */
    public function getConfig($key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? null;
    }

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Kangangga\Json\Query\Builder
     */
    public function query()
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return \Kangangga\Json\Query\Builder
     */
    public function table($table, $as = null)
    {
        return $this->query()->from($table);
    }

    /**
     * Get the table file path.
     *
     * @param  string  $table
     * @return string
     */
    public function getTablePath($table)
    {
        $extension = $this->serializer->getExtension();
        return $this->database . DIRECTORY_SEPARATOR . $table . '.' . $extension;
    }

    /**
     * Read data from file using configured serializer.
     *
     * @param  string  $table
     * @return array
     */
    public function readTable($table)
    {
        // Check transaction buffer
        if ($this->inTransaction && isset($this->pendingWrites[$table])) {
            return $this->pendingWrites[$table];
        }

        $path = $this->getTablePath($table);

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        return $this->serializer->unserialize($content);
    }

    /**
     * Write data to file or buffer if in transaction.
     *
     * @param  string  $table
     * @param  array  $data
     * @return bool
     */
    public function writeTable($table, array $data)
    {
        // Write to transaction buffer
        if ($this->inTransaction) {
            $this->pendingWrites[$table] = $data;
            return true;
        }

        return $this->saveToDisk($table, $data);
    }

    /**
     * Save data physically to disk.
     *
     * @param  string  $table
     * @param  array  $data
     * @return bool
     */
    public function saveToDisk($table, array $data)
    {
        $path = $this->getTablePath($table);
        $content = $this->serializer->serialize($data);

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            // Parse table name from query
            preg_match('/from\s+["`]?(\w+)["`]?/i', $query, $matches);
            $table = $matches[1] ?? null;

            if (!$table) {
                return [];
            }

            $data = $this->readTable($table);

            // Simple filtering based on WHERE clauses
            // This is a basic implementation - you can extend it
            return array_values($data);
        });
    }

    public function insert($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            // Because insert() also compiles to SQL string, we reuse insertGetId logic
            // ignoring the return ID
            $this->insertGetId($query, $bindings);
            return true;
        });
    }

    /**
     * Run an insert statement and return the last inserted ID.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  string|null  $sequence
     * @return int
     */
    public function insertGetId($query, $bindings = [], $sequence = null)
    {
        preg_match('/into\s+["`]?(\w+)["`]?/i', $query, $matches);
        $table = $matches[1] ?? null;

        if (!$table) {
            return 0;
        }

        $data = $this->readTable($table);

        // Generate new ID
        $id = empty($data) ? 1 : max(array_column($data, 'id')) + 1;

        // Parse columns from query to map bindings correctly
        // Format: insert into table (col1, col2) values (?, ?)
        $record = ['id' => $id];

        if (preg_match('/\(([^)]+)\)\s*values/i', $query, $matches)) {
            $columnsStr = $matches[1];
            $columns = array_map(function ($col) {
                return trim($col, ' "`');
            }, explode(',', $columnsStr));

            foreach ($columns as $i => $col) {
                // If binding exists for this column
                if (array_key_exists($i, $bindings)) {
                    $record[$col] = $bindings[$i];
                }
            }
        } else {
            // Fallback if no columns specified (should rare in Builder)
            $record = array_merge($record, $bindings);
        }

        $data[] = $record;

        $this->writeTable($table, $data);

        return $id;
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            // Parse table
            preg_match('/from\s+["`]?(\w+)["`]?/i', $query, $matches);
            $table = $matches[1] ?? null;

            if (!$table) {
                return 0;
            }

            // Jika query adalah truncate (delete w/o where)
            if (empty($bindings) && !str_contains($query, 'where')) {
                $count = count($this->readTable($table));
                $this->writeTable($table, []);
                return $count;
            }

            $data = $this->readTable($table);
            $initialCount = count($data);

            // Basic delete by ID implementation
            // Mencari pattern "id" = ?
            if (preg_match('/["`]?id["`]?\s*=\s*\?/i', $query) && !empty($bindings)) {
                $id = $bindings[0];
                $data = array_filter($data, function ($row) use ($id) {
                    return ($row['id'] ?? null) != $id;
                });
            }
            // Basic delete by ID array (whereIn)
            elseif (preg_match('/["`]?id["`]?\s+in\s+\(/i', $query) && !empty($bindings)) {
                $data = array_filter($data, function ($row) use ($bindings) {
                    return !in_array($row['id'] ?? null, $bindings);
                });
            }

            $this->writeTable($table, array_values($data));

            return $initialCount - count($data);
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            // Handle Truncate
            if (str_starts_with(strtolower(trim($query)), 'truncate')) {
                preg_match('/truncate\s+table\s+["`]?(\w+)["`]?/i', $query, $matches);
                $table = $matches[1] ?? null;

                if ($table) {
                    $this->writeTable($table, []);
                    return true;
                }
            }

            return true;
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            return 1;
        });
    }

    /**
     * Truncate a table.
     *
     * @param  string  $table
     * @return void
     */
    public function truncate($table)
    {
        $this->writeTable($table, []);
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Kangangga\Json\Query\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new \Kangangga\Json\Query\Grammar($this);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Kangangga\Json\Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new \Kangangga\Json\Query\Processor;
    }
}
