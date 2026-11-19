<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusOrcamento;
use App\Http\Controllers\Controller;
use App\Models\Orcamento;
use App\Services\ItensDocumento;
use App\Services\OrcamentoService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OrcamentoController extends Controller
{
    public function __construct(private OrcamentoService $servico) {}

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(
            Orcamento::query()
                ->with(['itens', 'cliente:id,nome,telefone,email', 'veiculo:id,placa,marca,modelo'])
                ->when($request->query('status'), fn ($b, $s) => $b->where('status', $s))
                ->when($request->query('cliente_id'), fn ($b, $id) => $b->where('cliente_id', $id))
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                    ->where('numero', is_numeric($q) ? (int) $q : -1)
                    ->orWhere('solicitacao', 'like', "%{$q}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$q}%"))
                    ->orWhereHas('veiculo', fn ($v) => $v->where('placa', 'like', "%{$q}%"))))
                ->orderByDesc('numero')
                ->limit(500)
                ->get()
        );
    }

    public function show(Orcamento $orcamento): JsonResponse
    {
        return response()->json($orcamento->load(['itens', 'cliente', 'veiculo']));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->servico->salvar($this->validar($request), null, $request->user()->id), 201);
    }

    public function update(Request $request, Orcamento $orcamento): JsonResponse
    {
        return response()->json($this->servico->salvar($this->validar($request), $orcamento, $request->user()->id));
    }

    public function destroy(Orcamento $orcamento): JsonResponse
    {
        if ($orcamento->os_id) {
            throw ValidationException::withMessages(['orcamento' => 'Este orçamento já gerou uma OS e não pode ser excluído.']);
        }
        $orcamento->delete();

        return response()->json(null, 204);
    }

    public function status(Request $request, Orcamento $orcamento): JsonResponse
    {
        $d = $request->validate(['status' => ['required', new Enum(StatusOrcamento::class)]]);

        return response()->json($this->servico->alterarStatus($orcamento, StatusOrcamento::from($d['status'])));
    }

    public function converter(Orcamento $orcamento): JsonResponse
    {
        return response()->json($this->servico->converterEmOS($orcamento), 201);
    }

    public function duplicar(Request $request, Orcamento $orcamento): JsonResponse
    {
        return response()->json($this->servico->duplicar($orcamento->load('itens'), $request->user()->id), 201);
    }

    public function pdf(Orcamento $orcamento, PdfService $pdfs): Response
    {
        return response($pdfs->documento($orcamento), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdfs->nomeArquivo($orcamento).'"',
        ]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'veiculo_id' => ['required', 'uuid', Rule::exists('veiculos', 'id')->where('cliente_id', $request->input('cliente_id'))],
            'data_emissao' => ['sometimes', 'date'],
            'validade' => ['sometimes', 'date', 'after_or_equal:data_emissao'],
            'km_entrada' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'solicitacao' => ['nullable', 'string', 'max:1000'],
            'diagnostico' => ['nullable', 'string', 'max:2000'],
            'desconto' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'forma_pagamento' => ['required', 'string', 'max:30'],
            'prazo_execucao' => ['nullable', 'string', 'max:60'],
            'condicoes' => ['nullable', 'string', 'max:2000'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            ...ItensDocumento::regras(obrigatorio: true),
        ], [
            'veiculo_id.exists' => 'O veículo não pertence a este cliente.',
            'validade.after_or_equal' => 'A validade não pode ser anterior à emissão.',
            'itens.required' => 'Adicione ao menos um serviço ou peça.',
            'itens.min' => 'Adicione ao menos um serviço ou peça.',
        ]);
    }
}
