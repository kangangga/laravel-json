<?php

namespace Kangangga\Json\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class JsonCommand extends Command
{
    public $signature = 'json:table {name : The name of the JSON table to create}
                        {--data= : Optional JSON data to seed the table}';

    public $description = 'Create a new JSON database table';

    public function handle(): int
    {
        $tableName = $this->argument('name');
        $connection = DB::connection('json');

        if (!$connection instanceof \Kangangga\Json\Connection) {
            $this->error('JSON connection not configured properly.');
            return self::FAILURE;
        }

        $config = config('database.connections.json');
        $databasePath = $config['database'] ?? database_path('json');

        // Ensure directory exists
        if (!File::exists($databasePath)) {
            File::makeDirectory($databasePath, 0755, true);
            $this->info("Created JSON database directory: {$databasePath}");
        }

        $tablePath = $databasePath . DIRECTORY_SEPARATOR . $tableName . '.json';

        // Check if table already exists
        if (File::exists($tablePath)) {
            if (!$this->confirm("Table '{$tableName}' already exists. Overwrite?")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        // Get optional seed data
        $data = $this->option('data');
        $initialData = [];

        if ($data) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $initialData = $decoded;
            } else {
                $this->warn('Invalid JSON data provided. Creating empty table.');
            }
        }

        // Create the table file
        File::put($tablePath, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("JSON table '{$tableName}' created successfully!");
        $this->info("Location: {$tablePath}");

        return self::SUCCESS;
    }
}
