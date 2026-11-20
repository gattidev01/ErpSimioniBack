<?php

use App\Http\Middleware\ConverterCamelCase;
use App\Http\Middleware\ExigirPerfil;
use App\Http\Middleware\UsuarioAtivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // login do SPA por cookie de sessão (HttpOnly) + proteção CSRF do Sanctum
        $middleware->statefulApi();
        $middleware->api(append: [ConverterCamelCase::class, UsuarioAtivo::class]);
        $middleware->throttleApi(); // limite 'api' definido no AppServiceProvider
        $middleware->alias(['perfil' => ExigirPerfil::class]);

        // Em produção o acesso chega por Traefik -> Nginx do front. Confiar nesses proxies faz o
        // Laravel enxergar HTTPS e o IP real do visitante (usado no limite de tentativas de login).
        // Só ligue onde a API NÃO é acessível diretamente pela internet.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
