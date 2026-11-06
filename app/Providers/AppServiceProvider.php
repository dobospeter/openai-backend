<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->trustConfiguredProxies();
        $this->configureRateLimiting();
    }

    /**
     * Forwarded headers are only believed when the request comes from a proxy listed in
     * TRUSTED_PROXIES. By default nobody is trusted, so clients cannot spoof their address
     * (which would let them dodge the rate limits).
     */
    private function trustConfiguredProxies(): void
    {
        $proxies = trim((string) config('app.trusted_proxies'));

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));

        // Every message costs a call to the paid OpenAI API, hence the tighter limit.
        RateLimiter::for('messages', static fn (Request $request): Limit => Limit::perMinute(
            (int) config('openai.messages_per_minute')
        )->by($request->ip()));

        RateLimiter::for('docs', static fn (Request $request): Limit => Limit::perMinute(
            (int) config('docs.requests_per_minute')
        )->by($request->ip()));
    }
}
