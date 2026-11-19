<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lancamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LancamentoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'tipo' => ['nullable', 'in:receita,despesa'],
        ]);

        $lanc = Lancamento::query()
            ->when($d['tipo'] ?? null, fn ($q, $t) => $q->where('tipo', $t))
            ->when($d['mes'] ?? null, function ($q, $mes) {
                $inicio = "{$mes}-01";
                $fim = date('Y-m-t', strtotime($inicio));
                // do mês + pendências de meses anteriores
                $q->where(fn ($w) => $w
                    ->whereBetween('vencimento', [$inicio, $fim])
                    ->orWhereBetween('pago_em', [$inicio, $fim])
                    ->orWhere(fn ($p) => $p->whereNull('pago_em')->where('vencimento', '<', $inicio)));
            })
            ->orderBy('vencimento')
            ->get();

        return response()->json($lanc);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Lancamento::create($this->validar($request)), 201);
    }

    public function update(Request $request, Lancamento $lancamento): JsonResponse
    {
        $lancamento->update($this->validar($request));

        return response()->json($lancamento);
    }

    public function destroy(Lancamento $lancamento): JsonResponse
    {
        $lancamento->delete();

        return response()->json(null, 204);
    }

    /** Marca como pago/recebido (ou estorna, se já estava pago) */
    public function baixar(Request $request, Lancamento $lancamento): JsonResponse
    {
        $d = $request->validate(['pago_em' => ['nullable', 'date']]);
        $lancamento->update(['pago_em' => $lancamento->pago_em ? null : ($d['pago_em'] ?? today())]);

        return response()->json($lancamento);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', 'in:receita,despesa'],
            'descricao' => ['required', 'string', 'max:160'],
            'categoria' => ['required', 'string', 'max:40'],
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'vencimento' => ['required', 'date'],
            'pago_em' => ['nullable', 'date'],
            'forma_pagamento' => ['required', 'string', 'max:30'],
        ]);
    }
}
