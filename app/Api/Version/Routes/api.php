<?php

declare(strict_types=1);

use App\Api\Version\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::get('version', VersionController::class)->name('version');
