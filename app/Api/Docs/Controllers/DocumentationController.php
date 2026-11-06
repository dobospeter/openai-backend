<?php

declare(strict_types=1);

namespace App\Api\Docs\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DocumentationController
{
    public function ui(): View
    {
        return view('docs.swagger', ['specUrl' => route('docs.spec', [], false)]);
    }

    public function spec(): BinaryFileResponse
    {
        return response()->file((string) config('docs.spec_path'), [
            'Content-Type' => 'application/yaml',
            'Cache-Control' => 'no-store',
        ]);
    }
}
