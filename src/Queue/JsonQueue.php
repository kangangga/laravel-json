<?php

namespace Kangangga\Json\Queue;

use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Contracts\Queue\ClearableQueue;
use Kangangga\Json\Connection;
use Illuminate\Support\Str;

class JsonQueue extends Queue implements QueueContract, ClearableQueue
{
    /**
     * The database connection instance.
     */
    protected Connection $database;

    /**
     * The database table that holds the jobs.
     */
    protected string $table;

    /**
     * The name of the default queue.
     */
    protected string $default;

    /**
     * The expiration time of a job.
     */
    protected int $retryAfter = 60;

    /**
     * Create a new database queue instance.
     */
    public function __construct(
        Connection $database,
        string $table,
        string $default = 'default',
        int $retryAfter = 60
    ) {
        $this->database = $database;
        $this->table = $table;
        $this->default = $default;
        $this->retryAfter = $retryAfter;
        $this->connectionName = $database->getName();

        // Ensure jobs table exists
        $tablePath = $this->database->getTablePath($this->table);
        if (!file_exists($tablePath)) {
            file_put_contents($tablePath, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Get the size of the queue.
     */
    public function size($queue = null): int
    {
        $queue = $this->getQueue($queue);
        $jobs = $this->readJobs();

        return count(array_filter($jobs, function ($job) use ($queue) {
            return $job['queue'] === $queue && $job['reserved_at'] === null;
        }));
    }

    /**
     * Push a new job onto the queue.
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushToDatabase($queue, $this->createPayload($job, $this->getQueue($queue), $data));
    }

    /**
     * Push a raw payload onto the queue.
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->pushToDatabase($queue, $payload);
    }

    /**
     * Push a new job onto the queue after a delay.
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushToDatabase(
            $queue,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $this->availableAt($delay)
        );
    }

    /**
     * Push an array of jobs onto the queue.
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);

        foreach ((array) $jobs as $job) {
            $this->pushToDatabase($queue, $this->createPayload($job, $queue, $data));
        }

        return true;
    }

    /**
     * Release a reserved job back onto the queue.
     */
    public function release($queue, $job, $delay)
    {
        return $this->pushToDatabase($queue, $job->payload, $this->availableAt($delay), $job->attempts);
    }

    /**
     * Pop the next job off of the queue.
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);
        $jobs = $this->readJobs();
        $now = time();

        // Find next available job
        foreach ($jobs as $id => $job) {
            if (
                $job['queue'] === $queue &&
                $job['reserved_at'] === null &&
                $job['available_at'] <= $now
            ) {
                // Reserve the job
                $jobs[$id]['reserved_at'] = $now;
                $jobs[$id]['attempts'] = ($job['attempts'] ?? 0) + 1;

                $this->writeJobs($jobs);

                return new JsonJob(
                    $this->container,
                    $this,
                    (object) $jobs[$id],
                    $this->connectionName,
                    $queue
                );
            }
        }

        return null;
    }

    /**
     * Delete a reserved job from the queue.
     */
    public function deleteReserved($queue, $id)
    {
        $jobs = $this->readJobs();

        if (isset($jobs[$id])) {
            unset($jobs[$id]);
            $this->writeJobs($jobs);
        }
    }

    /**
     * Delete all of the jobs from the queue.
     */
    public function clear($queue)
    {
        return $this->database->table($this->table)
            ->where('queue', $this->getQueue($queue))
            ->delete();
    }

    /**
     * Get the queue or return the default.
     */
    protected function getQueue($queue): string
    {
        return $queue ?: $this->default;
    }

    /**
     * Push a job to the database.
     */
    protected function pushToDatabase($queue, $payload, $availableAt = null, $attempts = 0)
    {
        $jobs = $this->readJobs();

        $id = $this->generateId();
        $jobs[$id] = [
            'id' => $id,
            'queue' => $this->getQueue($queue),
            'payload' => $payload,
            'attempts' => $attempts,
            'reserved_at' => null,
            'available_at' => $availableAt ?? time(),
            'created_at' => time(),
        ];

        $this->writeJobs($jobs);

        return $id;
    }

    /**
     * Generate a unique ID for the job.
     */
    protected function generateId(): string
    {
        $jobs = $this->readJobs();

        do {
            $id = Str::random(32);
        } while (isset($jobs[$id]));

        return $id;
    }

    /**
     * Read jobs from JSON file.
     */
    protected function readJobs(): array
    {
        return $this->database->readTable($this->table);
    }

    /**
     * Write jobs to JSON file.
     */
    protected function writeJobs(array $jobs): bool
    {
        return $this->database->writeTable($this->table, $jobs);
    }

    /**
     * Get the underlying database instance.
     */
    public function getDatabase(): Connection
    {
        return $this->database;
    }
}
