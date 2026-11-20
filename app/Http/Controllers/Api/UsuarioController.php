<?php

namespace App\Http\Controllers\Api;

use App\Enums\Perfil;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Gestão de usuários (somente administradores) */
class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::with('funcionario:id,nome')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);

        return response()->json(User::create($d)->load('funcionario:id,nome'), 201);
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $d = $this->validar($request, $usuario);
        if (empty($d['password'])) {
            unset($d['password']);
        }
        $this->protegerUltimoAdmin($usuario, $d);

        $usuario->update($d);
        if (isset($d['password']) || ! $usuario->ativo) {
            // troca de senha ou desativação: encerra as sessões abertas dessa pessoa
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
        }

        return response()->json($usuario->load('funcionario:id,nome'));
    }

    public function destroy(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->is($request->user())) {
            throw ValidationException::withMessages(['usuario' => 'Você não pode excluir o próprio usuário.']);
        }
        $this->protegerUltimoAdmin($usuario, ['ativo' => false]);
        $usuario->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request, ?User $atual = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($atual?->id)],
            'perfil' => ['required', new Enum(Perfil::class)],
            'funcionario_id' => ['nullable', 'uuid', 'exists:funcionarios,id', 'required_if:perfil,mecanico'],
            'ativo' => ['required', 'boolean'],
            'password' => [$atual ? 'nullable' : 'required', Password::min(10)->letters()->mixedCase()->numbers()],
        ], ['funcionario_id.required_if' => 'Vincule o mecânico a um funcionário para ele ver as OS dele.']);
    }

    /** Impede que o sistema fique sem nenhum administrador ativo */
    private function protegerUltimoAdmin(User $usuario, array $novos): void
    {
        if ($usuario->perfil !== Perfil::Admin) {
            return;
        }
        $deixaDeSerAdmin = ($novos['perfil'] ?? 'admin') !== 'admin' || ($novos['ativo'] ?? true) === false;
        $outrosAdmins = User::where('perfil', 'admin')->where('ativo', true)->whereKeyNot($usuario->id)->count();
        if ($deixaDeSerAdmin && $outrosAdmins === 0) {
            throw ValidationException::withMessages(['perfil' => 'É preciso manter ao menos um administrador ativo.']);
        }
    }
}
