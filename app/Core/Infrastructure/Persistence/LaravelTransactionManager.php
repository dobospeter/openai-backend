<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Persistence;

use App\Core\Application\TransactionManager;
use Illuminate\Database\ConnectionInterface;

final class LaravelTransactionManager implements TransactionManager
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function transactional(callable $callback): mixed
    {
        return $this->connection->transaction($callback);
    }
}
