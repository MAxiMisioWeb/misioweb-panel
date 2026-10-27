<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Se lee de la config (no de env()) para que funcione con la caché de configuración.
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at(array_map('trim', explode(',', $proxies)));
        }

        // En producción el sitio solo se sirve por HTTPS (lo termina el proxy): los enlaces y
        // formularios nunca salen con http://, aunque el proxy no se reconozca.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
