<?php

declare(strict_types=1);

use App\Api\Docs\Middleware\RequireBasicAuth;
use App\Core\Domain\Exception\InvalidValue;
use App\Core\Domain\Exception\NotFound;
use App\Core\Http\Error\ApiExceptionRenderer;
use App\Core\Http\Middleware\RejectMalformedJson;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: static function (): void {
            // Each API module keeps its own routes next to its controllers (app/Api/<Module>/Routes).
            Route::middleware('api')->prefix('api')->group(function (): void {
                require app_path('Api/Version/Routes/api.php');
                require app_path('Api/Conversation/Routes/api.php');
            });

            Route::middleware(['throttle:docs', RequireBasicAuth::class])
                ->group(app_path('Api/Docs/Routes/docs.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [RejectMalformedJson::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Client mistakes are answered, not logged. Everything else goes through the regular reporter.
        $exceptions->dontReport([NotFound::class, InvalidValue::class]);

        // One error format for the whole API, whatever went wrong.
        $exceptions->render(
            static fn (Throwable $e, Request $request) => $request->is('api/*')
                ? app(ApiExceptionRenderer::class)->render($e)
                : null
        );
    })->create();
