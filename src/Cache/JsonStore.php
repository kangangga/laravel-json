<?php

namespace Kangangga\Json\Cache;

use Override;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Cache\RetrievesMultipleKeys;
use Illuminate\Contracts\Cache\LockProvider;
use Kangangga\Json\Connection;

final class JsonStore implements LockProvider, Store
{
    // Provides "many" and "putMany" in a non-optimized way
    use RetrievesMultipleKeys;

    private const TEN_YEARS_IN_SECONDS = 315360000;

    /**
     * @param Connection $connection The JSON connection to use for the cache
     * @param string     $table      Name of the table where cache items are stored
     * @param string     $prefix     Prefix for the name of cache items
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'cache',
        private readonly string $prefix = '',
    ) {
        // Ensure cache table exists
        $tablePath = $this->connection->getTablePath($this->table);
        if (!file_exists($tablePath)) {
            file_put_contents($tablePath, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    #[Override]
    public function lock($name, $seconds = 0, $owner = null)
    {
        return new JsonLock(
            $this->connection,
            $this->prefix . $name,
            $seconds,
            $owner
        );
    }

    /**
     * Restore a lock instance using the owner identifier.
     */
    #[Override]
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $seconds
     */
    #[Override]
    public function put($key, $value, $seconds)
    {
        $key = $this->prefix . $key;
        $expiration = time() + $seconds;

        // Read current cache data
        $data = $this->readCacheData();

        // Add or update the cache item
        $data[$key] = [
            'value' => $this->serialize($value),
            'expires_at' => $expiration,
        ];

        // Write back to file
        return $this->writeCacheData($data);
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $seconds
     */
    public function add($key, $value, $seconds)
    {
        if ($this->get($key) !== null) {
            return false;
        }

        return $this->put($key, $value, $seconds);
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     */
    #[Override]
    public function get($key)
    {
        $key = $this->prefix . $key;
        $data = $this->readCacheData();

        if (!isset($data[$key])) {
            return null;
        }

        $item = $data[$key];

        // Check if expired
        if (isset($item['expires_at']) && $item['expires_at'] < time()) {
            $this->forget($key);
            return null;
        }

        return $this->unserialize($item['value'] ?? null);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string    $key
     * @param int|float $value
     */
    #[Override]
    public function increment($key, $value = 1)
    {
        $current = $this->get($key);

        if ($current === null) {
            $current = 0;
        }

        $newValue = $current + $value;

        // Get TTL from existing item or use default
        $data = $this->readCacheData();
        $prefixedKey = $this->prefix . $key;
        $ttl = isset($data[$prefixedKey]['expires_at'])
            ? $data[$prefixedKey]['expires_at'] - time()
            : self::TEN_YEARS_IN_SECONDS;

        $this->put($key, $newValue, max($ttl, 0));

        return $newValue;
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string    $key
     * @param int|float $value
     */
    #[Override]
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, -1 * $value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed  $value
     */
    #[Override]
    public function forever($key, $value)
    {
        return $this->put($key, $value, self::TEN_YEARS_IN_SECONDS);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     */
    #[Override]
    public function forget($key)
    {
        $key = $this->prefix . $key;
        $data = $this->readCacheData();

        if (isset($data[$key])) {
            unset($data[$key]);
            return $this->writeCacheData($data);
        }

        return false;
    }

    /**
     * Remove all items from the cache.
     */
    public function flush()
    {
        return $this->writeCacheData([]);
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Read cache data from file.
     */
    private function readCacheData(): array
    {
        return $this->connection->readTable($this->table);
    }

    /**
     * Write cache data to file.
     */
    private function writeCacheData(array $data): bool
    {
        return $this->connection->writeTable($this->table, $data);
    }

    private function serialize($value): string|int|float
    {
        // Don't serialize numbers, so they can be incremented
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return serialize($value);
    }

    private function unserialize($value)
    {
        if (!is_string($value) || !str_contains($value, ';')) {
            return $value;
        }

        return unserialize($value);
    }
}
