<?php

declare(strict_types=1);

namespace App\Domains\Shared\Actions;

use Illuminate\Support\Facades\DB;
use Throwable;

abstract class Action
{
    /**
     * Execute the action within a database transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * Execute the action within a database transaction with retry logic.
     *
     * @template T
     * @param callable(): T $callback
     * @param int $attempts
     * @return T
     * @throws Throwable
     */
    protected function transactionWithRetry(callable $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
