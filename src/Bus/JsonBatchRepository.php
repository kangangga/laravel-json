<?php

namespace Kangangga\Json\Bus;

use Closure;
use Override;
use DateTimeInterface;
use Illuminate\Support\Str;
use Kangangga\Json\Connection;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\UpdatedBatchJobCounts;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PrunableBatchRepository;

class JsonBatchRepository extends DatabaseBatchRepository implements PrunableBatchRepository
{
    /** @var \Kangangga\Json\Connection */
    protected $connection;

    public function __construct(BatchFactory $factory, Connection $connection, string $table)
    {
        $this->factory = $factory;
        $this->connection = $connection;
        $this->table = $table;
    }

    #[Override]
    public function get($limit = 50, $before = null)
    {
        return $this->connection->table($this->table)
            ->orderByDesc('id')
            ->limit($limit)
            ->when($before, function ($query, $before) {
                return $query->where('id', '<', $before);
            })
            ->get()
            ->map(function ($batch) {
                return $this->toBatch((array) $batch);
            })
            ->all();
    }

    #[Override]
    public function find(string $batchId)
    {
        $batch = $this->connection->table($this->table)->where('id', $batchId)->first();

        if ($batch) {
            return $this->toBatch((array) $batch);
        }

        return null;
    }

    #[Override]
    public function store(PendingBatch $batch)
    {
        $id = (string) Str::orderedUuid();

        $this->connection->table($this->table)->insert([
            'id' => $id,
            'name' => $batch->name,
            'total_jobs' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => [],
            'options' => serialize($batch->options),
            'created_at' => Carbon::now()->getTimestamp(),
            'cancelled_at' => null,
            'finished_at' => null,
        ]);

        return $this->find($id);
    }

    #[Override]
    public function incrementTotalJobs(string $batchId, int $amount)
    {
        $this->connection->transaction(function () use ($batchId, $amount) {
            // Note: We avoid 'lockForUpdate' because our driver doesn't support it yet.
            // But transaction ensures atomicity within this connection instance.
            $batch = $this->connection->table($this->table)->where('id', $batchId)->first();

            if ($batch) {
                $this->connection->table($this->table)->where('id', $batchId)->update([
                    'total_jobs' => ((array)$batch)['total_jobs'] + $amount,
                    'pending_jobs' => ((array)$batch)['pending_jobs'] + $amount,
                    'finished_at' => null,
                ]);
            }
        });
    }

    #[Override]
    public function decrementPendingJobs(string $batchId, string $jobId)
    {
        return $this->connection->transaction(function () use ($batchId, $jobId) {
            $batch = $this->connection->table($this->table)->where('id', $batchId)->first();

            if (!$batch) {
                return new UpdatedBatchJobCounts(0, 0);
            }
            $batch = (array) $batch;

            $failedJobIds = $batch['failed_job_ids'] ?? [];
            if (($key = array_search($jobId, $failedJobIds)) !== false) {
                unset($failedJobIds[$key]);
            }

            $pendingJobs = (int) $batch['pending_jobs'] - 1;
            $failedJobs = (int) $batch['failed_jobs'];

            $this->connection->table($this->table)->where('id', $batchId)->update([
                'pending_jobs' => $pendingJobs,
                'failed_job_ids' => array_values($failedJobIds)
            ]);

            return new UpdatedBatchJobCounts(
                intval($pendingJobs),
                intval($failedJobs)
            );
        });
    }

    #[Override]
    public function incrementFailedJobs(string $batchId, string $jobId)
    {
        return $this->connection->transaction(function () use ($batchId, $jobId) {
            $batch = $this->connection->table($this->table)->where('id', $batchId)->first();

            if (!$batch) {
                return new UpdatedBatchJobCounts(0, 0);
            }
            $batch = (array) $batch;

            $failedJobIds = $batch['failed_job_ids'] ?? [];
            $failedJobIds[] = $jobId;
            $failedJobIds = array_unique($failedJobIds);

            $failedJobs = (int) $batch['failed_jobs'] + 1;
            $pendingJobs = (int) $batch['pending_jobs']; // Assuming incrementFailed doesn't decrement pending (Laravel logic)

            $this->connection->table($this->table)->where('id', $batchId)->update([
                'failed_jobs' => $failedJobs,
                'failed_job_ids' => array_values($failedJobIds)
            ]);

            return new UpdatedBatchJobCounts(
                intval($pendingJobs),
                intval($failedJobs)
            );
        });
    }

    #[Override]
    public function markAsFinished(string $batchId)
    {
        $this->connection->table($this->table)->where('id', $batchId)->update([
            'finished_at' => Carbon::now()->getTimestamp(),
        ]);
    }

    #[Override]
    public function cancel(string $batchId)
    {
        $this->connection->table($this->table)->where('id', $batchId)->update([
            'cancelled_at' => Carbon::now()->getTimestamp(),
            'finished_at' => Carbon::now()->getTimestamp(),
        ]);
    }

    #[Override]
    public function delete(string $batchId)
    {
        $this->connection->table($this->table)->where('id', $batchId)->delete();
    }

    /** Execute the given Closure within a storage specific transaction. */
    #[Override]
    public function transaction(Closure $callback)
    {
        return $this->connection->transaction($callback);
    }

    /** Rollback the last database transaction for the connection. */
    #[Override]
    public function rollBack()
    {
        $this->connection->rollBack();
    }

    /** Prune the entries older than the given date. */
    #[Override]
    public function prune(DateTimeInterface $before)
    {
        $timestamp = $before->getTimestamp();
        return $this->connection->table($this->table)
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $timestamp)
            ->delete();
    }

    /** Prune all the unfinished entries older than the given date. */
    #[Override]
    public function pruneUnfinished(DateTimeInterface $before)
    {
        $timestamp = $before->getTimestamp();
        return $this->connection->table($this->table)
            ->whereNull('finished_at')
            ->where('created_at', '<', $timestamp)
            ->delete();
    }

    /** Prune all the cancelled entries older than the given date. */
    #[Override]
    public function pruneCancelled(DateTimeInterface $before)
    {
        $timestamp = $before->getTimestamp();
        return $this->connection->table($this->table)
            ->whereNotNull('cancelled_at')
            ->where('created_at', '<', $timestamp)
            ->delete();
    }

    /** @param array $batch */
    #[Override]
    protected function toBatch($batch)
    {
        return $this->factory->make(
            $this,
            $batch['id'],
            $batch['name'],
            $batch['total_jobs'],
            $batch['pending_jobs'],
            $batch['failed_jobs'],
            $batch['failed_job_ids'],
            unserialize($batch['options']),
            $this->toCarbon($batch['created_at']),
            $this->toCarbon($batch['cancelled_at']),
            $this->toCarbon($batch['finished_at']),
        );
    }

    /** @return ($date is null ? null : CarbonImmutable) */
    private function toCarbon($date): ?CarbonImmutable
    {
        if ($date === null) {
            return null;
        }
        return CarbonImmutable::createFromTimestamp((int) $date);
    }
}
