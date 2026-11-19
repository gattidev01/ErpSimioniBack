<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * O front trabalha em camelCase (clienteId) e o banco em snake_case (cliente_id).
 * Converte as chaves na entrada (query + corpo) e na saída (JSON).
 */
class ConverterCamelCase
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace(self::chaves($request->query->all(), Str::snake(...)));
        $fonte = $request->isJson() ? $request->json() : $request->request;
        $fonte->replace(self::chaves($fonte->all(), Str::snake(...)));

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setData(self::chaves($response->getData(true), Str::camel(...)));
        }

        return $response;
    }

    public static function chaves(mixed $dados, callable $fn): mixed
    {
        if (! is_array($dados)) {
            return $dados;
        }
        $saida = [];
        foreach ($dados as $k => $v) {
            $saida[is_string($k) ? $fn($k) : $k] = self::chaves($v, $fn);
        }

        return $saida;
    }
}
