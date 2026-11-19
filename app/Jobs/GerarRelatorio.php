<?php

namespace App\Jobs;

use App\Models\Relatorio;
use App\Services\RelatorioService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GerarRelatorio implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public string $relatorioId) {}

    public function handle(RelatorioService $servico): void
    {
        $rel = Relatorio::find($this->relatorioId);
        if (! $rel) {
            return;
        }
        $rel->update(['status' => 'processando', 'erro' => null]);
        $arquivo = $servico->gerar($rel);
        $rel->update(['status' => 'pronto', 'arquivo' => $arquivo, 'concluido_em' => now()]);
    }

    public function failed(?Throwable $e): void
    {
        Relatorio::whereKey($this->relatorioId)->update([
            'status' => 'falhou',
            'erro' => mb_substr($e?->getMessage() ?? 'Erro desconhecido', 0, 1000),
        ]);
    }
}
