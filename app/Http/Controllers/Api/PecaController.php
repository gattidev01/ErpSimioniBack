<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lancamento;
use App\Models\OrcamentoItem;
use App\Models\OrdemItem;
use App\Models\Peca;
use App\Services\EstoqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PecaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Peca::query()
                ->when($request->boolean('baixo'), fn ($q) => $q->estoqueBaixo())
                ->orderBy('descricao')
                ->get()
        );
    }

    public function show(Peca $peca): JsonResponse
    {
        return response()->json($peca->load(['movimentacoes' => fn ($q) => $q->limit(100)]));
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request);
        $inicial = (float) ($dados['estoque'] ?? 0);
        $dados['estoque'] = 0;

        $peca = DB::transaction(function () use ($dados, $inicial, $request) {
            $peca = Peca::create($dados);
            if ($inicial > 0) {
                app(EstoqueService::class)->entrada($peca, $inicial, $peca->custo, $request->user()->id, 'Estoque inicial');
            }

            return $peca->fresh();
        });

        return response()->json($peca, 201);
    }

    public function update(Request $request, Peca $peca): JsonResponse
    {
        $dados = $this->validar($request, $peca);
        unset($dados['estoque']); // saldo só muda por movimentação

        $peca->update($dados);

        return response()->json($peca);
    }

    public function destroy(Peca $peca): JsonResponse
    {
        $usada = OrdemItem::where('ref_id', $peca->id)->exists() || OrcamentoItem::where('ref_id', $peca->id)->exists();
        if ($usada) {
            throw ValidationException::withMessages(['peca' => 'Esta peça está em orçamentos/OS e não pode ser excluída.']);
        }
        $peca->delete();

        return response()->json(null, 204);
    }

    /** Entrada de mercadoria, opcionalmente lançando a conta a pagar */
    public function entrada(Request $request, Peca $peca, EstoqueService $estoque): JsonResponse
    {
        $d = $request->validate([
            'quantidade' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'custo' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'fornecedor' => ['nullable', 'string', 'max:80'],
            'lancar_despesa' => ['sometimes', 'boolean'],
            'vencimento' => ['required_if:lancar_despesa,true', 'nullable', 'date'],
            'forma_pagamento' => ['nullable', 'string', 'max:30'],
        ]);

        $peca = DB::transaction(function () use ($d, $peca, $estoque, $request) {
            $obs = 'Compra'.(! empty($d['fornecedor']) ? " - {$d['fornecedor']}" : '');
            $peca = $estoque->entrada($peca, (float) $d['quantidade'], (float) $d['custo'], $request->user()->id, $obs);

            if (! empty($d['lancar_despesa'])) {
                Lancamento::create([
                    'tipo' => 'despesa',
                    'descricao' => "Compra: {$d['quantidade']}x {$peca->descricao}".(! empty($d['fornecedor']) ? " - {$d['fornecedor']}" : ''),
                    'categoria' => 'Compra de peças',
                    'valor' => round($d['quantidade'] * $d['custo'], 2),
                    'vencimento' => $d['vencimento'],
                    'forma_pagamento' => $d['forma_pagamento'] ?? 'Boleto',
                ]);
            }

            return $peca;
        });

        return response()->json($peca);
    }

    private function validar(Request $request, ?Peca $atual = null): array
    {
        $request->merge(['codigo' => strtoupper(trim((string) $request->input('codigo'))) ?: null]);

        $d = $request->validate([
            'codigo' => ['nullable', 'string', 'max:30', Rule::unique('pecas', 'codigo')->ignore($atual?->id)],
            'descricao' => ['required', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:40'],
            'unidade' => ['required', 'string', 'max:4'],
            'custo' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'preco' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'estoque' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'localizacao' => ['nullable', 'string', 'max:20'],
        ], ['codigo.unique' => 'Código já utilizado.']);

        $d['marca'] ??= '';
        $d['localizacao'] ??= '';
        $d['estoque_minimo'] ??= 0;

        return $d;
    }
}
