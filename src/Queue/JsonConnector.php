<?php

declare(strict_types=1);

namespace Kangangga\Json\Queue;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Connectors\ConnectorInterface;

class JsonConnector implements ConnectorInterface
{
    protected $connections;

    public function __construct(ConnectionResolverInterface $connections)
    {
        $this->connections = $connections;
    }

    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new JsonQueue(
            $this->connections->connection($config['connection'] ?? 'json'),
            $config['table'] ?? 'jobs',
            $config['queue'] ?? 'default',
            $config['retry_after'] ?? 60,
        );
    }
}
