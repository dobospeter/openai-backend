<?php

declare(strict_types=1);

namespace App\Api\Version\Controllers;

use Illuminate\Http\JsonResponse;

final class VersionController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['currentAppVersion' => config('app.currentAppVersion')]);
    }
}
