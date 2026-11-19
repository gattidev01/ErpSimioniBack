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
        // Em desenvolvimento: erro ao acessar relação não carregada (evita N+1 silencioso)
        Model::preventLazyLoading(! $this->app->isProduction());

        // Força bruta no login: 5 tentativas por minuto por e-mail+IP
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)
            ->by(Str::lower((string) $r->input('email')).'|'.$r->ip())
            ->response(fn () => response()->json(['message' => 'Muitas tentativas. Aguarde um minuto.'], 429)));
    }
}
