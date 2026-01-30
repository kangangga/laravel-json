<?php

namespace Kangangga\Json\Cache;

use Override;
use Illuminate\Cache\Lock;
use Kangangga\Json\Connection;

final class JsonLock extends Lock
{
    /**
     * Create a new lock instance.
     *
     * @param Connection  $connection The JSON connection
     * @param string      $name       Name of the lock
     * @param int         $seconds    Time-to-live of the lock in seconds
     * @param string|null $owner      A unique string that identifies the owner. Random if not set
     */
    public function __construct(
        private readonly Connection $connection,
        string $name,
        int $seconds,
        ?string $owner = null,
    ) {
        parent::__construct($name, $seconds, $owner);
    }

    /**
     * Attempt to acquire the lock.
     */
    #[Override]
    public function acquire()
    {
        $lockData = $this->readLocks();
        $now = time();

        // Check if lock exists and is not expired
        if (isset($lockData[$this->name])) {
            $lock = $lockData[$this->name];

            // If lock is expired, we can acquire it
            if ($lock['expires_at'] < $now) {
                return $this->createLock($lockData);
            }

            // Lock is still valid
            return false;
        }

        // No existing lock, create new one
        return $this->createLock($lockData);
    }

    /**
     * Release the lock.
     */
    #[Override]
    public function release()
    {
        if (!$this->isOwnedByCurrentProcess()) {
            return false;
        }

        $lockData = $this->readLocks();

        if (isset($lockData[$this->name])) {
            unset($lockData[$this->name]);
            return $this->writeLocks($lockData);
        }

        return true;
    }

    /**
     * Releases this lock in disregard of ownership.
     */
    #[Override]
    public function forceRelease(): void
    {
        $lockData = $this->readLocks();

        if (isset($lockData[$this->name])) {
            unset($lockData[$this->name]);
            $this->writeLocks($lockData);
        }
    }

    /**
     * Returns the owner value written into the driver for this lock.
     */
    #[Override]
    protected function getCurrentOwner()
    {
        $lockData = $this->readLocks();

        if (isset($lockData[$this->name])) {
            return $lockData[$this->name]['owner'] ?? null;
        }

        return null;
    }

    /**
     * Create a new lock entry.
     */
    private function createLock(array $lockData): bool
    {
        $lockData[$this->name] = [
            'owner' => $this->owner,
            'expires_at' => time() + $this->seconds,
        ];

        return $this->writeLocks($lockData);
    }

    /**
     * Read locks from JSON file.
     */
    private function readLocks(): array
    {
        $lockFile = $this->getLockFilePath();

        if (!file_exists($lockFile)) {
            return [];
        }

        $content = file_get_contents($lockFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Write locks to JSON file.
     */
    private function writeLocks(array $lockData): bool
    {
        $lockFile = $this->getLockFilePath();
        $content = json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return file_put_contents($lockFile, $content) !== false;
    }

    /**
     * Get the lock file path.
     */
    private function getLockFilePath(): string
    {
        $dbPath = $this->connection->getConfig('database');
        return $dbPath . DIRECTORY_SEPARATOR . 'cache_locks.json';
    }
}
