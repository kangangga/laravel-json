<?php

namespace Kangangga\Json\Concerns;

use Closure;
use Throwable;

trait ManagesTransactions
{
    protected $pendingWrites = [];
    protected $pendingDeletes = [];
    protected $inTransaction = false;

    public function beginTransaction(): void
    {
        $this->inTransaction = true;
        $this->pendingWrites = [];
    }

    public function commit(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        // Commit all pending writes to disk
        foreach ($this->pendingWrites as $table => $data) {
            // Kita panggil method raw write milik class Connection (bypass check transaction)
            $this->saveToDisk($table, $data);
        }

        $this->inTransaction = false;
        $this->pendingWrites = [];
    }

    public function rollBack($toLevel = null): void
    {
        if (!$this->inTransaction) {
            return;
        }

        // Simply discard pending writes
        $this->inTransaction = false;
        $this->pendingWrites = [];
    }

    public function transaction(Closure $callback, $attempts = 1, array $options = [])
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
