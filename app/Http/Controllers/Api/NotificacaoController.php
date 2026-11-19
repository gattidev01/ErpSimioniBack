<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Alertas do "sininho" do sistema */
class NotificacaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'nao_lidas' => $user->unreadNotifications()->count(),
            'itens' => $user->notifications()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id,
                'lida' => $n->read_at !== null,
                'criada_em' => $n->created_at->toIso8601String(),
                ...$n->data,
            ]),
        ]);
    }

    public function marcarLidas(Request $request): JsonResponse
    {
        $d = $request->validate(['ids' => ['sometimes', 'array'], 'ids.*' => ['uuid']]);
        $q = $request->user()->unreadNotifications();
        if (! empty($d['ids'])) {
            $q->whereIn('id', $d['ids']);
        }
        $q->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
