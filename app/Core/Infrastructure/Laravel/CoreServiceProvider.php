<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Laravel;

use App\Core\Application\TransactionManager;
use App\Core\Http\Error\ApiExceptionRenderer;
use App\Core\Infrastructure\Persistence\LaravelTransactionManager;
use App\Core\Infrastructure\SystemClock;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClockInterface::class, SystemClock::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(
            ApiExceptionRenderer::class,
            static fn (): ApiExceptionRenderer => new ApiExceptionRenderer((bool) config('app.debug'))
        );
    }
}
