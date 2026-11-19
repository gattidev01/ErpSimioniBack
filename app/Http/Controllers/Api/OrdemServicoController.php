<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusOS;
use App\Http\Controllers\Controller;
use App\Models\OrdemServico;
use App\Services\EstoqueService;
use App\Services\ItensDocumento;
use App\Services\OrdemServicoService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OrdemServicoController extends Controller
{
    public function __construct(private OrdemServicoService $servico) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $ordens = OrdemServico::query()
            ->with(['itens', 'cliente:id,nome,telefone', 'veiculo:id,placa,marca,modelo', 'mecanico:id,nome', 'lancamento:id,os_id,pago_em,valor'])
            // mecânico só enxerga as próprias OS
            ->when($user->ehMecanico(), fn ($b) => $b->where('mecanico_id', $user->funcionario_id ?? '-'))
            ->when($status === 'abertas', fn ($b) => $b->whereIn('status', ['orcamento', 'aprovada', 'em_andamento', 'aguardando_peca']))
            ->when($status && $status !== 'abertas' && $status !== 'todas', fn ($b) => $b->where('status', $status))
            ->when($request->query('cliente_id'), fn ($b, $id) => $b->where('cliente_id', $id))
            ->when($request->query('veiculo_id'), fn ($b, $id) => $b->where('veiculo_id', $id))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('numero', is_numeric($q) ? (int) $q : -1)
                ->orWhere('defeito_relatado', 'like', "%{$q}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$q}%"))
                ->orWhereHas('veiculo', fn ($v) => $v->where('placa', 'like', "%{$q}%")->orWhere('modelo', 'like', "%{$q}%"))))
            ->orderByDesc('numero')
            ->limit((int) min(1000, max(1, (int) $request->query('limite', 500))))
            ->get();

        return response()->json($ordens);
    }

    public function show(Request $request, OrdemServico $ordem): JsonResponse
    {
        $this->autorizarMecanico($request, $ordem);

        return response()->json($ordem->load(['itens', 'cliente', 'veiculo', 'mecanico', 'lancamento']));
    }

    public function store(Request $request): JsonResponse
    {
        $os = $this->servico->salvar($this->validar($request), null, $request->user()->id);

        return response()->json($os, 201);
    }

    public function update(Request $request, OrdemServico $ordem): JsonResponse
    {
        $this->autorizarMecanico($request, $ordem);

        $dados = $request->user()->ehMecanico()
            // mecânico só registra diagnóstico e observações
            ? $request->validate(['diagnostico' => ['nullable', 'string', 'max:2000'], 'observacoes' => ['nullable', 'string', 'max:1000']])
            : $this->validar($request, $ordem);

        return response()->json($this->servico->salvar($dados, $ordem, $request->user()->id));
    }

    public function destroy(OrdemServico $ordem): JsonResponse
    {
        if ($ordem->estoque_baixado) {
            throw ValidationException::withMessages(['os' => 'Reabra ou cancele a OS antes de excluir (para estornar o estoque).']);
        }
        if ($ordem->lancamento()->exists()) {
            throw ValidationException::withMessages(['os' => 'Esta OS possui lançamento financeiro. Exclua o lançamento primeiro.']);
        }
        $ordem->delete();

        return response()->json(null, 204);
    }

    public function status(Request $request, OrdemServico $ordem, EstoqueService $estoque): JsonResponse
    {
        $this->autorizarMecanico($request, $ordem);
        $d = $request->validate([
            'status' => ['required', new Enum(StatusOS::class)],
            'ignorar_falta_estoque' => ['sometimes', 'boolean'],
        ]);
        $novo = StatusOS::from($d['status']);

        if ($request->user()->ehMecanico() && ! in_array($novo, StatusOS::permitidosMecanico(), true)) {
            abort(403, 'Mecânico não pode mudar a OS para este status.');
        }

        if ($novo->baixaEstoque() && ! $ordem->estoque_baixado && empty($d['ignorar_falta_estoque'])) {
            $faltas = $estoque->faltas($ordem->load('itens'));
            if ($faltas) {
                // o front pergunta ao usuário e reenvia com ignorar_falta_estoque=true
                return response()->json([
                    'message' => 'Estoque insuficiente para concluir.',
                    'faltas' => $faltas,
                    'codigo' => 'estoque_insuficiente',
                ], 409);
            }
        }

        return response()->json($this->servico->alterarStatus($ordem, $novo, $request->user()->id));
    }

    public function faturar(Request $request, OrdemServico $ordem): JsonResponse
    {
        $d = $request->validate(['recebido' => ['required', 'boolean']]);

        return response()->json($this->servico->faturar($ordem, $d['recebido']), 201);
    }

    public function pdf(Request $request, OrdemServico $ordem, PdfService $pdfs): Response
    {
        $this->autorizarMecanico($request, $ordem);

        return response($pdfs->documento($ordem), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdfs->nomeArquivo($ordem).'"',
        ]);
    }

    private function autorizarMecanico(Request $request, OrdemServico $os): void
    {
        $user = $request->user();
        if ($user->ehMecanico() && (! $user->funcionario_id || $os->mecanico_id !== $user->funcionario_id)) {
            abort(403, 'Esta OS não está atribuída a você.');
        }
    }

    private function validar(Request $request, ?OrdemServico $os = null): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'veiculo_id' => ['required', 'uuid', Rule::exists('veiculos', 'id')->where('cliente_id', $request->input('cliente_id'))],
            'mecanico_id' => ['nullable', 'uuid', 'exists:funcionarios,id'],
            'data_abertura' => ['required', 'date'],
            'data_previsao' => ['required', 'date'],
            'km_entrada' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'defeito_relatado' => ['nullable', 'string', 'max:1000'],
            'diagnostico' => ['nullable', 'string', 'max:2000'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'desconto' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'forma_pagamento' => ['required', 'string', 'max:30'],
            'agendamento_id' => [$os ? 'prohibited' : 'nullable', 'uuid', 'exists:agendamentos,id'],
            ...ItensDocumento::regras(),
        ], ['veiculo_id.exists' => 'O veículo não pertence a este cliente.']);
    }
}
