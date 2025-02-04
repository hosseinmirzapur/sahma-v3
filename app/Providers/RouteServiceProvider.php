<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // WorkerWebRoutes
            Route::domain($this->getUserDomain())
                ->name('web.user.')
                ->middleware(['web'])
                ->group(base_path('routes/user-web.php'));
        });
    }

    public function getUserDomain(): string
    {
        /** @phpstan-ignore-next-line */
        return parse_url(config('app.url'))['host'];
    }
}
