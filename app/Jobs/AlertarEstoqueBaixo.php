<?php

namespace App\Jobs;

use App\Models\Peca;
use App\Notifications\AlertaEquipe;
use App\Support\Equipe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sem ids: resumo diário de todas as peças abaixo do mínimo.
 * Com ids: aviso imediato das peças que acabaram de ficar abaixo do mínimo (baixa de OS).
 */
class AlertarEstoqueBaixo implements ShouldQueue
{
    use Queueable;

    /** @param list<string>|null $pecaIds */
    public function __construct(public ?array $pecaIds = null) {}

    public function handle(): void
    {
        $pecas = Peca::query()
            ->estoqueBaixo()
            ->when($this->pecaIds, fn ($q) => $q->whereIn('id', $this->pecaIds))
            ->orderBy('descricao')
            ->get();

        if ($pecas->isEmpty()) {
            return;
        }

        Equipe::alertar(new AlertaEquipe(
            'estoque',
            $this->pecaIds ? 'Peça ficou abaixo do estoque mínimo' : 'Peças abaixo do estoque mínimo',
            "{$pecas->count()} peça(s) precisam de reposição.",
            $pecas->map(fn ($p) => "{$p->descricao}".($p->codigo ? " ({$p->codigo})" : '')
                .": {$p->estoque} {$p->unidade} (mínimo {$p->estoque_minimo})")->all(),
            '/estoque',
        ));
    }
}
