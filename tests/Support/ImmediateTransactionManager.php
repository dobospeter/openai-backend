<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Application\TransactionManager;

final class ImmediateTransactionManager implements TransactionManager
{
    public int $runs = 0;

    public function transactional(callable $callback): mixed
    {
        $this->runs++;

        return $callback();
    }
}
