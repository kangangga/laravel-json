<?php

namespace Kangangga\Json\Tests;

use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Kangangga\Json\JsonServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setup test database directory
        $this->app['config']->set('database.connections.json', [
            'driver' => 'json',
            'database' => __DIR__ . '/database/json',
            'prefix' => '',
        ]);

        // Create test directory
        if (!file_exists(__DIR__ . '/database/json')) {
            mkdir(__DIR__ . '/database/json', 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        // $files = glob(__DIR__ . '/database/*');
        // foreach ($files as $file) {
        //     if (is_file($file)) {
        //         unlink($file);
        //     }
        // }
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            JsonServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'json');
    }
}
