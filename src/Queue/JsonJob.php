<?php

namespace Kangangga\Json\Queue;

use Illuminate\Queue\Jobs\Job;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;

class JsonJob extends Job implements JobContract
{
    /**
     * The JSON queue instance.
     */
    protected JsonQueue $jsonQueue;

    /**
     * The job payload.
     */
    protected object $job;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Container $container,
        JsonQueue $jsonQueue,
        object $job,
        string $connectionName,
        string $queue
    ) {
        $this->container = $container;
        $this->jsonQueue = $jsonQueue;
        $this->job = $job;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    /**
     * Get the job identifier.
     */
    public function getJobId(): string
    {
        return $this->job->id;
    }

    /**
     * Get the raw body of the job.
     */
    public function getRawBody(): string
    {
        return $this->job->payload;
    }

    /**
     * Get the number of times the job has been attempted.
     */
    public function attempts(): int
    {
        return (int) $this->job->attempts;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete(): void
    {
        parent::delete();
        $this->jsonQueue->deleteReserved($this->queue, $this->job->id);
    }

    /**
     * Release the job back into the queue.
     */
    public function release($delay = 0): void
    {
        parent::release($delay);
        $this->jsonQueue->release($this->queue, $this, $delay);
    }

    /**
     * Get the name of the queue the job belongs to.
     */
    public function getQueue(): string
    {
        return $this->job->queue;
    }

    /**
     * Indicates if the job has been reserved.
     */
    public function isReserved(): bool
    {
        return $this->job->reserved_at !== null;
    }

    /**
     * Get the timestamp when the job was reserved.
     */
    public function reservedAt(): ?int
    {
        return $this->job->reserved_at;
    }
}
