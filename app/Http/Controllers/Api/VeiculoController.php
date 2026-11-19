<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Veiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VeiculoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(
            Veiculo::query()
                ->with('cliente:id,nome')
                ->withCount('ordens')
                ->when($request->query('cliente_id'), fn ($b, $id) => $b->where('cliente_id', $id))
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                    ->where('placa', 'like', "%{$q}%")
                    ->orWhere('modelo', 'like', "%{$q}%")
                    ->orWhere('marca', 'like', "%{$q}%")))
                ->orderBy('placa')
                ->get()
        );
    }

    public function show(Veiculo $veiculo): JsonResponse
    {
        return response()->json($veiculo->load(['cliente', 'ordens' => fn ($q) => $q->with('itens')->latest('numero')]));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Veiculo::create($this->validar($request)), 201);
    }

    public function update(Request $request, Veiculo $veiculo): JsonResponse
    {
        $veiculo->update($this->validar($request, $veiculo));

        return response()->json($veiculo);
    }

    public function destroy(Veiculo $veiculo): JsonResponse
    {
        if ($veiculo->ordens()->exists()) {
            throw ValidationException::withMessages(['veiculo' => 'Este veículo possui ordens de serviço e não pode ser excluído.']);
        }
        $veiculo->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request, ?Veiculo $atual = null): array
    {
        // normaliza a placa antes de validar (ABC-1D23 → ABC1D23)
        $request->merge(['placa' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('placa')))]);

        $d = $request->validate([
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'placa' => ['required', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', Rule::unique('veiculos', 'placa')->ignore($atual?->id)],
            'marca' => ['nullable', 'string', 'max:40'],
            'modelo' => ['required', 'string', 'max:60'],
            'ano' => ['nullable', 'string', 'max:9'],
            'cor' => ['nullable', 'string', 'max:30'],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'chassi' => ['nullable', 'string', 'max:17'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ], ['placa.regex' => 'Placa inválida (ex.: ABC1234 ou ABC1D23).', 'placa.unique' => 'Já existe um veículo com esta placa.']);

        $d['km'] ??= 0;
        $d['chassi'] = strtoupper($d['chassi'] ?? '');

        return array_map(fn ($v) => $v ?? '', $d);
    }
}
