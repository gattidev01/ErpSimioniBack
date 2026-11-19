<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funcionario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FuncionarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Funcionario::orderBy('nome')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Funcionario::create($this->validar($request)), 201);
    }

    public function update(Request $request, Funcionario $funcionario): JsonResponse
    {
        $funcionario->update($this->validar($request));

        return response()->json($funcionario);
    }

    public function destroy(Funcionario $funcionario): JsonResponse
    {
        if ($funcionario->ordens()->exists()) {
            throw ValidationException::withMessages(['funcionario' => 'Funcionário possui OS vinculadas. Marque-o como inativo em vez de excluir.']);
        }
        $funcionario->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'funcao' => ['nullable', 'string', 'max:60'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'comissao' => ['required', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['required', 'boolean'],
        ]);
        $d['funcao'] ??= '';
        $d['telefone'] ??= '';

        return $d;
    }
}
