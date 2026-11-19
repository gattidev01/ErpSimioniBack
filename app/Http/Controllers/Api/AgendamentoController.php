<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgendamentoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate([
            'desde' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'finalizados' => ['nullable', 'boolean'],
        ]);

        return response()->json(
            Agendamento::query()
                ->with(['cliente:id,nome,telefone', 'veiculo:id,placa,marca,modelo'])
                ->whereDate('data', '>=', $d['desde'] ?? today())
                ->when($d['ate'] ?? null, fn ($q, $ate) => $q->whereDate('data', '<=', $ate))
                ->when(! ($d['finalizados'] ?? false), fn ($q) => $q->whereIn('status', ['agendado', 'confirmado']))
                ->orderBy('data')->orderBy('hora')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Agendamento::create($this->validar($request)), 201);
    }

    public function update(Request $request, Agendamento $agendamento): JsonResponse
    {
        $dados = $this->validar($request);
        // mudou data/hora: o lembrete deve ser enviado de novo
        if ($agendamento->data->toDateString() !== $dados['data'] || $agendamento->hora !== $dados['hora']) {
            $dados['lembrete_enviado_em'] = null;
        }
        $agendamento->update($dados);

        return response()->json($agendamento);
    }

    public function destroy(Agendamento $agendamento): JsonResponse
    {
        $agendamento->delete();

        return response()->json(null, 204);
    }

    /** Aviso de conflito de horário (o front decide se salva mesmo assim) */
    public function conflito(Request $request): JsonResponse
    {
        $d = $request->validate(['data' => ['required', 'date'], 'hora' => ['required', 'date_format:H:i'], 'ignorar_id' => ['nullable', 'uuid']]);

        $existe = Agendamento::whereDate('data', $d['data'])->where('hora', $d['hora'])
            ->where('status', '!=', 'cancelado')
            ->when($d['ignorar_id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
            ->exists();

        return response()->json(['conflito' => $existe]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'veiculo_id' => ['required', 'uuid', Rule::exists('veiculos', 'id')->where('cliente_id', $request->input('cliente_id'))],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'servico' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'in:agendado,confirmado,concluido,cancelado'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ], ['veiculo_id.exists' => 'O veículo não pertence a este cliente.']);
    }
}
