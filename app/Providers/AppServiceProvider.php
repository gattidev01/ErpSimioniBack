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

        // Força bruta no login: 5 tentativas/min por e-mail+IP e, contra quem testa vários
        // e-mails (credential stuffing), no máximo 20 tentativas/hora por IP.
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)
                ->by('email:'.Str::lower((string) $r->input('email')).'|'.$r->ip())
                ->response(fn () => response()->json(['message' => 'Muitas tentativas. Aguarde um minuto.'], 429)),
            Limit::perHour(20)
                ->by('ip:'.$r->ip())
                ->response(fn () => response()->json(['message' => 'Muitas tentativas deste endereço. Tente novamente mais tarde.'], 429)),
        ]);

        // Limite geral da API: 240 requisições/min por usuário logado (ou por IP, sem login)
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(240)
            ->by($r->user()?->id ? 'u:'.$r->user()->id : 'ip:'.$r->ip()));
    }
}
