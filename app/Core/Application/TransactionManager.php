<?php

declare(strict_types=1);

namespace App\Core\Application;

interface TransactionManager
{
    /**
     * Runs the callback atomically: everything it persists is committed together or not at all.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed;
}
