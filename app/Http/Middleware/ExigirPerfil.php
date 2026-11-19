<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: ->middleware('perfil:admin,atendente') */
class ExigirPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $user = $request->user();

        if (! $user || ! $user->ativo) {
            abort(401, 'Sessão expirada. Entre novamente.');
        }
        if (! $user->temPerfil(...$perfis)) {
            abort(403, 'Seu perfil não tem acesso a esta função.');
        }

        return $next($request);
    }
}
