<?php

namespace Kangangga\Json;

use InvalidArgumentException;
use Illuminate\Bus\BatchFactory;
use Illuminate\Cache\CacheManager;
use Kangangga\Json\Eloquent\Model;
use Kangangga\Json\Cache\JsonStore;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Kangangga\Json\Queue\JsonConnector;
use Spatie\LaravelPackageTools\Package;
use Kangangga\Json\Commands\JsonCommand;
use Kangangga\Json\Bus\JsonBatchRepository;
use Kangangga\Json\Session\JsonSessionHandler;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class JsonServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('json')
            ->hasConfigFile('json')
            ->hasCommand(JsonCommand::class);
    }

    public function registeringPackage()
    {
        // Register JSON database driver
        $this->app->resolving('db', function (\Illuminate\Database\DatabaseManager $db) {
            $db->extend('json', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });

        // Session handler for JSON
        $this->app->resolving(SessionManager::class, function (SessionManager $sessionManager) {
            $sessionManager->extend('json', function (Application $app) {
                $connectionName = $app->config->get('session.connection') ?: 'json';
                $connection = $app->make('db')->connection($connectionName);

                if (!$connection instanceof Connection) {
                    throw new InvalidArgumentException(
                        sprintf('The database connection "%s" used for the session does not use the "json" driver.', $connectionName)
                    );
                }

                return new JsonSessionHandler(
                    $connection,
                    $app->config->get('session.table', 'sessions'),
                    $app->config->get('session.lifetime'),
                    $app,
                );
            });
        });

        // Add cache driver - Simplified version without Collection dependency
        $this->app->resolving('cache', function (CacheManager $cache) {
            $cache->extend('json', function (Application $app, array $config) use ($cache) {
                $connection = $app['db']->connection($config['connection'] ?? 'json');

                if (!$connection instanceof Connection) {
                    throw new InvalidArgumentException(
                        'The cache connection must use the "json" driver.'
                    );
                }

                $store = new JsonStore(
                    $connection,
                    $config['table'] ?? 'cache',
                    $cache->getPrefix($config)
                );

                return $cache->repository($store, $config);
            });
        });

        // Add connector for queue support
        $this->app->resolving('queue', function ($queue) {
            $queue->addConnector('json', function () {
                return new JsonConnector(app('db'));
            });
        });

        $this->app->singleton(JsonBatchRepository::class, function ($app) {
            $connection = $app->make('db')->connection($app->config->get('queue.batching.database'));
            if (! $connection instanceof Connection) {
                throw new InvalidArgumentException(sprintf('The "json" batch driver requires a JSON connection. The "%s" connection uses the "%s" driver.', $connection->getName(), $connection->getDriverName()));
            }
            return new JsonBatchRepository(
                $app->make(BatchFactory::class),
                $connection,
                $app->config->get('queue.batching.table', 'job_batches')
            );
        });

        if ($this->app['config']->get('queue.batching.driver') === 'json') {
            $this->app->extend(\Illuminate\Bus\BatchRepository::class, function ($service, $app) {
                return $app->make(JsonBatchRepository::class);
            });
        }
    }

    public function packageRegistered()
    {
        // Inject JSON connection config into database connections
        if (!config()->has('database.connections.json')) {
            config()->set('database.connections.json', config('json.connections.json'));
        }

        // Inject JSON cache store config into cache stores
        if (!config()->has('cache.stores.json')) {
            config()->set('cache.stores.json', config('json.cache'));
        }

        // Inject JSON queue connection config
        if (!config()->has('queue.connections.json')) {
            config()->set('queue.connections.json', config('json.queue'));
        }
    }

    public function bootingPackage()
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
        // Ensure the JSON database directory exists
        $jsonConfig = config('database.connections.json');
        if ($jsonConfig && isset($jsonConfig['database'])) {
            $databasePath = $jsonConfig['database'];
            if (!file_exists($databasePath)) {
                @mkdir($databasePath, 0755, true);
            }
        }
    }
}
