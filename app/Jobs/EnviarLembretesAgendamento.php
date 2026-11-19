<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Models\Envio;
use App\Services\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Cria um envio de lembrete para cada agendamento de amanhã (WhatsApp, ou e-mail se não houver) */
class EnviarLembretesAgendamento implements ShouldQueue
{
    use Queueable;

    public function handle(WhatsAppClient $whatsapp): void
    {
        $agendamentos = Agendamento::with('cliente')
            ->whereDate('data', today()->addDay())
            ->whereIn('status', ['agendado', 'confirmado'])
            ->whereNull('lembrete_enviado_em')
            ->get();

        foreach ($agendamentos as $ag) {
            $tel = WhatsAppClient::normalizarTelefone($ag->cliente->telefone ?? '');
            [$canal, $destino] = match (true) {
                $whatsapp->configurado() && $tel !== null => ['whatsapp', $tel],
                filter_var($ag->cliente->email, FILTER_VALIDATE_EMAIL) !== false => ['email', $ag->cliente->email],
                default => [null, null],
            };
            if (! $canal) {
                continue; // sem contato utilizável
            }

            $jaExiste = Envio::where('documento_tipo', 'agendamento')->where('documento_id', $ag->id)
                ->whereIn('status', [Envio::PENDENTE, Envio::ENVIANDO, Envio::ENVIADO])->exists();
            if ($jaExiste) {
                continue;
            }

            $envio = Envio::create([
                'documento_tipo' => 'agendamento',
                'documento_id' => $ag->id,
                'canal' => $canal,
                'destino' => $destino,
            ]);
            ProcessarEnvio::dispatch($envio->id);
        }
    }
}
