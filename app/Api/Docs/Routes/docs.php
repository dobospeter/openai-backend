<?php

declare(strict_types=1);

use App\Api\Docs\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;

// Registered in bootstrap/app.php, outside /api and behind the Basic Auth middleware.
Route::get('docs', [DocumentationController::class, 'ui'])->name('docs.ui');
Route::get('docs/openapi.yaml', [DocumentationController::class, 'spec'])->name('docs.spec');
