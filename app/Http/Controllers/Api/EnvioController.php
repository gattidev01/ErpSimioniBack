<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessarEnvio;
use App\Models\Envio;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Services\WhatsAppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Dispara e acompanha envios de orçamento/OS por e-mail ou WhatsApp */
class EnvioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate([
            'documento_tipo' => ['sometimes', 'in:orcamento,ordem,agendamento'],
            'documento_id' => ['sometimes', 'uuid'],
        ]);

        return response()->json(
            Envio::query()
                ->with('usuario:id,name')
                ->when($d['documento_tipo'] ?? null, fn ($q, $t) => $q->where('documento_tipo', $t))
                ->when($d['documento_id'] ?? null, fn ($q, $id) => $q->where('documento_id', $id))
                ->latest()
                ->limit(100)
                ->get()
        );
    }

    public function enviarOrcamento(Request $request, Orcamento $orcamento, WhatsAppClient $wa): JsonResponse
    {
        if ($orcamento->itens()->count() === 0) {
            throw ValidationException::withMessages(['itens' => 'Adicione itens antes de enviar.']);
        }

        return $this->criar($request, 'orcamento', $orcamento->id, $orcamento->cliente, $wa);
    }

    public function enviarOrdem(Request $request, OrdemServico $ordem, WhatsAppClient $wa): JsonResponse
    {
        return $this->criar($request, 'ordem', $ordem->id, $ordem->cliente, $wa);
    }

    /** Reenvia um envio que falhou */
    public function reenviar(Envio $envio): JsonResponse
    {
        if ($envio->status !== Envio::FALHOU) {
            throw ValidationException::withMessages(['envio' => 'Só é possível reenviar envios que falharam.']);
        }
        $envio->update(['status' => Envio::PENDENTE, 'erro' => null]);
        ProcessarEnvio::dispatch($envio->id);

        return response()->json($envio->fresh(), 202);
    }

    private function criar(Request $request, string $tipo, string $id, $cliente, WhatsAppClient $wa): JsonResponse
    {
        $d = $request->validate([
            'canal' => ['required', 'in:email,whatsapp'],
            'destino' => ['nullable', 'string', 'max:120'],
            'mensagem' => ['nullable', 'string', 'max:2000'],
        ]);

        // sem destino informado: usa o contato do cadastro do cliente
        $destino = trim($d['destino'] ?? '') ?: ($d['canal'] === 'email' ? $cliente->email : $cliente->telefone);

        if ($d['canal'] === 'email') {
            if (! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['destino' => 'Informe um e-mail válido (o cliente não tem e-mail cadastrado).']);
            }
        } else {
            if (! $wa->configurado()) {
                throw ValidationException::withMessages(['canal' => 'WhatsApp ainda não está configurado no servidor.']);
            }
            $destino = WhatsAppClient::normalizarTelefone((string) $destino)
                ?? throw ValidationException::withMessages(['destino' => 'Telefone inválido para WhatsApp (use DDD + número).']);
        }

        $envio = Envio::create([
            'documento_tipo' => $tipo,
            'documento_id' => $id,
            'canal' => $d['canal'],
            'destino' => $destino,
            'mensagem' => $d['mensagem'] ?? null,
            'user_id' => $request->user()->id,
        ]);
        ProcessarEnvio::dispatch($envio->id);

        // 202: aceito; o job envia em segundo plano e o front acompanha pelo status
        return response()->json($envio->fresh(), 202);
    }
}
