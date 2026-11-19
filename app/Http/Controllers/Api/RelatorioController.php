<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GerarRelatorio;
use App\Models\Relatorio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(
            Relatorio::query()
                ->with('usuario:id,name')
                ->when(! $user->temPerfil('admin'), fn ($q) => $q->where('user_id', $user->id))
                ->latest()->limit(50)->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $tipos = $user->temPerfil('admin') ? Relatorio::TIPOS : array_diff(Relatorio::TIPOS, ['comissoes']);

        $d = $request->validate([
            'tipo' => ['required', Rule::in($tipos)],
            'formato' => ['required', Rule::in(Relatorio::FORMATOS)],
            'inicio' => ['required_unless:tipo,estoque', 'nullable', 'date'],
            'fim' => ['required_unless:tipo,estoque', 'nullable', 'date', 'after_or_equal:inicio'],
        ], ['tipo.in' => 'Relatório indisponível para o seu perfil.']);

        $rel = Relatorio::create([
            'user_id' => $user->id,
            'tipo' => $d['tipo'],
            'formato' => $d['formato'],
            'parametros' => ['inicio' => $d['inicio'] ?? null, 'fim' => $d['fim'] ?? null],
            'status' => 'pendente',
        ]);
        GerarRelatorio::dispatch($rel->id);

        return response()->json($rel->fresh(), 202);
    }

    public function show(Request $request, Relatorio $relatorio): JsonResponse
    {
        $this->autorizar($request, $relatorio);

        return response()->json($relatorio);
    }

    public function download(Request $request, Relatorio $relatorio): StreamedResponse
    {
        $this->autorizar($request, $relatorio);
        abort_unless($relatorio->status === 'pronto' && $relatorio->arquivo && Storage::disk('local')->exists($relatorio->arquivo), 404, 'Relatório ainda não está pronto.');

        return Storage::disk('local')->download($relatorio->arquivo, "relatorio-{$relatorio->tipo}.{$relatorio->formato}");
    }

    private function autorizar(Request $request, Relatorio $rel): void
    {
        $user = $request->user();
        abort_unless($user->temPerfil('admin') || $rel->user_id === $user->id, 403);
    }
}
