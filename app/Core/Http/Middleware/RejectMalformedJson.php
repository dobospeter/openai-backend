<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Laravel silently reads a broken JSON body as "no input", which would let e.g. a typo create a
 * conversation with default settings. Answer 400 instead.
 */
final class RejectMalformedJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();

        if ($content !== '' && $request->isJson()) {
            json_decode($content);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BadRequestHttpException('The request body is not valid JSON.');
            }
        }

        return $next($request);
    }
}
