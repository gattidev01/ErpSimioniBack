<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Servico::orderBy('descricao')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Servico::create($this->validar($request)), 201);
    }

    public function update(Request $request, Servico $servico): JsonResponse
    {
        $servico->update($this->validar($request));

        return response()->json($servico);
    }

    public function destroy(Servico $servico): JsonResponse
    {
        // itens de OS/orçamento guardam a descrição e o valor, então excluir do catálogo é seguro
        $servico->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descricao' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:40'],
            'preco' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'tempo_estimado' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ]);
    }
}
