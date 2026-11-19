<?php

namespace App\Jobs;

use App\Models\Lancamento;
use App\Notifications\AlertaEquipe;
use App\Support\Equipe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Resumo diário de contas vencidas e das que vencem hoje */
class AlertarContasVencidas implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $vencidas = Lancamento::query()->vencidos()->orderBy('vencimento')->get();
        $hoje = Lancamento::query()->whereNull('pago_em')->whereDate('vencimento', today())->get();

        if ($vencidas->isEmpty() && $hoje->isEmpty()) {
            return;
        }

        $fmt = fn ($l) => ($l->tipo === 'receita' ? 'A receber' : 'A pagar')
            ." - {$l->descricao} - R$ ".number_format($l->valor, 2, ',', '.')
            .' (venc. '.$l->vencimento->format('d/m/Y').')';

        $receber = $vencidas->where('tipo', 'receita')->sum('valor');
        $pagar = $vencidas->where('tipo', 'despesa')->sum('valor');

        Equipe::alertar(new AlertaEquipe(
            'financeiro',
            'Contas vencidas e do dia',
            sprintf(
                '%d conta(s) vencida(s): R$ %s a receber e R$ %s a pagar. %d vence(m) hoje.',
                $vencidas->count(),
                number_format($receber, 2, ',', '.'),
                number_format($pagar, 2, ',', '.'),
                $hoje->count(),
            ),
            $vencidas->concat($hoje)->map($fmt)->all(),
            '/financeiro',
        ));
    }
}
