<?php

namespace App\Jobs;

use App\Enums\StatusOrcamento;
use App\Models\Orcamento;
use App\Notifications\AlertaEquipe;
use App\Support\Equipe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Marca como expirados os orçamentos sem resposta e avisa sobre os que vencem amanhã */
class ExpirarOrcamentos implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $abertos = [StatusOrcamento::Rascunho->value, StatusOrcamento::Enviado->value];

        $expirados = Orcamento::with('cliente')
            ->whereIn('status', $abertos)
            ->whereDate('validade', '<', today())
            ->get();
        foreach ($expirados as $o) {
            $o->update(['status' => StatusOrcamento::Expirado]);
        }

        $vencendo = Orcamento::with(['cliente', 'itens'])
            ->where('status', StatusOrcamento::Enviado->value)
            ->whereDate('validade', today()->addDay())
            ->get();

        if ($vencendo->isNotEmpty()) {
            Equipe::alertar(new AlertaEquipe(
                'orcamentos',
                'Orçamentos vencem amanhã',
                "{$vencendo->count()} orçamento(s) enviado(s) vencem amanhã sem resposta do cliente. Vale um contato!",
                $vencendo->map(fn ($o) => "Nº {$o->numeroFormatado()} - {$o->cliente->nome} - R$ ".number_format($o->total(), 2, ',', '.'))->all(),
                '/orcamentos',
            ));
        }
    }
}
