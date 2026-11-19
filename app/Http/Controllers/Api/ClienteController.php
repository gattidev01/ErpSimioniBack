<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(
            Cliente::query()
                ->withCount('veiculos')
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                    ->where('nome', 'like', "%{$q}%")
                    ->orWhere('documento', 'like', "%{$q}%")
                    ->orWhere('telefone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")))
                ->orderBy('nome')
                ->get()
        );
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json($cliente->load([
            'veiculos',
            'ordens' => fn ($q) => $q->with('itens')->latest('numero'),
            'orcamentos' => fn ($q) => $q->with('itens')->latest('numero'),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Cliente::create($this->validar($request)), 201);
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($this->validar($request));

        return response()->json($cliente);
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        if ($cliente->veiculos()->exists() || $cliente->ordens()->exists() || $cliente->orcamentos()->exists()) {
            throw ValidationException::withMessages(['cliente' => 'Cliente possui veículos, orçamentos ou OS vinculados e não pode ser excluído.']);
        }
        $cliente->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:PF,PJ'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:120', 'required_without:telefone'],
            'endereco' => ['nullable', 'string', 'max:200'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ], ['telefone.required_without' => 'Informe ao menos um telefone ou e-mail.', 'email.required_without' => 'Informe ao menos um telefone ou e-mail.']);

        return array_map(fn ($v) => $v ?? '', $d);
    }
}
