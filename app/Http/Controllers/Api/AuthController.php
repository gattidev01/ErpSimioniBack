<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'lembrar' => ['sometimes', 'boolean'],
        ]);

        $ok = Auth::guard('web')->attempt(
            ['email' => $dados['email'], 'password' => $dados['password'], 'ativo' => true],
            $dados['lembrar'] ?? false,
        );
        if (! $ok) {
            // mensagem genérica: não revela se o e-mail existe
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        $request->session()->regenerate();

        return response()->json($this->dadosUsuario($request));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->dadosUsuario($request));
    }

    private function dadosUsuario(Request $request): array
    {
        $u = $request->user()->load('funcionario');

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'perfil' => $u->perfil->value,
            'perfil_label' => $u->perfil->label(),
            'funcionario_id' => $u->funcionario_id,
            'funcionario' => $u->funcionario?->nome,
        ];
    }
}
