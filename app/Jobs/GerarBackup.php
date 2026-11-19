<?php

namespace App\Jobs;

use App\Notifications\AlertaEquipe;
use App\Services\BackupService;
use App\Support\Equipe;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GerarBackup implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function handle(BackupService $backup): void
    {
        $arquivo = $backup->gerar();
        Log::info("Backup gerado: {$arquivo}");
    }

    public function failed(?Throwable $e): void
    {
        Equipe::alertar(new AlertaEquipe(
            'backup',
            'Falha no backup automático',
            'O backup do banco de dados não pôde ser gerado: '.($e?->getMessage() ?? 'erro desconhecido'),
        ));
    }
}
