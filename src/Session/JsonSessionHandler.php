<?php

namespace Kangangga\Json\Session;

use SessionHandlerInterface;
use Kangangga\Json\Connection;
use Illuminate\Contracts\Container\Container;

class JsonSessionHandler implements SessionHandlerInterface
{
    /**
     * The database connection instance.
     */
    protected Connection $connection;

    /**
     * The name of the session table.
     */
    protected string $table;

    /**
     * The number of minutes the session should be valid.
     */
    protected int $minutes;

    /**
     * The container instance.
     */
    protected ?Container $container;

    /**
     * Create a new database session handler instance.
     */
    public function __construct(
        Connection $connection,
        string $table,
        int $minutes,
        ?Container $container = null
    ) {
        $this->connection = $connection;
        $this->table = $table;
        $this->minutes = $minutes;
        $this->container = $container;

        // Ensure session table exists
        $tablePath = $this->connection->getTablePath($this->table);
        if (!file_exists($tablePath)) {
            $this->writeSessions([]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId): string|false
    {
        $sessions = $this->readSessions();

        if (!isset($sessions[$sessionId])) {
            return '';
        }

        $session = $sessions[$sessionId];

        // Check if session is expired
        if (isset($session['last_activity']) && $session['last_activity'] < time() - ($this->minutes * 60)) {
            return '';
        }

        return $session['payload'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data): bool
    {
        $sessions = $this->readSessions();

        $sessions[$sessionId] = [
            'id' => $sessionId,
            'payload' => $data,
            'last_activity' => time(),
            'user_id' => $this->getUserId(),
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
        ];

        return $this->writeSessions($sessions);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId): bool
    {
        $sessions = $this->readSessions();

        if (isset($sessions[$sessionId])) {
            unset($sessions[$sessionId]);
            return $this->writeSessions($sessions);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime): int|false
    {
        $sessions = $this->readSessions();
        $expiration = time() - $lifetime;
        $deleted = 0;

        foreach ($sessions as $sessionId => $session) {
            if (isset($session['last_activity']) && $session['last_activity'] < $expiration) {
                unset($sessions[$sessionId]);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->writeSessions($sessions);
        }

        return $deleted;
    }

    /**
     * Read sessions from file.
     */
    protected function readSessions(): array
    {
        return $this->connection->readTable($this->table);
    }

    /**
     * Write sessions to file.
     */
    protected function writeSessions(array $sessions): bool
    {
        return $this->connection->writeTable($this->table, $sessions);
    }

    /**
     * Get the user ID for the session.
     */
    protected function getUserId(): ?int
    {
        if ($this->container && $this->container->bound('auth')) {
            return $this->container->make('auth')->id();
        }

        return null;
    }

    /**
     * Get the IP address for the session.
     */
    protected function getIpAddress(): ?string
    {
        if ($this->container && $this->container->bound('request')) {
            return $this->container->make('request')->ip();
        }

        return null;
    }

    /**
     * Get the user agent for the session.
     */
    protected function getUserAgent(): ?string
    {
        if ($this->container && $this->container->bound('request')) {
            return $this->container->make('request')->userAgent();
        }

        return null;
    }

    /**
     * Set the existence state for the session.
     */
    public function setExists(bool $value): static
    {
        return $this;
    }
}
