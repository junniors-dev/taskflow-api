<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
    }

    private function configureModels(): void
    {
        // Fuera de produccion, cargar una relacion de forma perezosa lanza
        // excepcion: asi un N+1 rompe la prueba en vez de aparecer como
        // lentitud inexplicable meses despues.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Y asignar un atributo que no esta declarado deja de fallar en
        // silencio.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // La clave combina email e IP a proposito: solo por IP castigaria a
        // todos los usuarios detras de una misma red, y solo por email
        // permitiria bloquear la cuenta de otro a voluntad.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::transliterate($request->string('email')->lower().'|'.$request->ip())));
    }
}
