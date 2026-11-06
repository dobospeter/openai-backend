<?php

declare(strict_types=1);

namespace App\Api\Docs\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Basic authentication for the API documentation. Fails closed: with no credentials
 * configured nobody gets in.
 */
final class RequireBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAuthorized($request)) {
            return $next($request);
        }

        return response('Authentication required.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="API documentation", charset="UTF-8"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        $username = (string) config('docs.username');
        $password = (string) config('docs.password');

        if ($username === '' || $password === '') {
            return false;
        }

        // Both comparisons always run and are constant time, so timing reveals nothing about either value.
        $validUsername = hash_equals($username, (string) $request->getUser());
        $validPassword = hash_equals($password, (string) $request->getPassword());

        return $validUsername && $validPassword;
    }
}
