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
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->configureCsrfDomain();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            $limit = !env('API_RATE_LIMIT_ENABLED', true) ? 9999 : 1000;
            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });
    }

    // CONFIGURE CSRF DOMAIN
    private function configureCsrfDomain()
    {
        $allowedOrigin = ['.pinvest.co.id', '.kampuskita.co'];

        $requestHeaders = getallheaders();
        if( !empty($requestHeaders['Referer']) ) {
            foreach($allowedOrigin as $o) {
                $patternReg = str_replace('.', "\.", $o);
                $parse = parse_url($requestHeaders['Referer']);

                if( preg_match("/{$patternReg}/", $parse['host']) == 1 ) {
                    config(['session.domain' => $o]);
                }
            }
        }
    }
}
