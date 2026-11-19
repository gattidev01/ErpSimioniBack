<?php

use App\Jobs\AlertarContasVencidas;
use App\Jobs\AlertarEstoqueBaixo;
use App\Jobs\EnviarLembretesAgendamento;
use App\Jobs\ExpirarOrcamentos;
use App\Jobs\GerarBackup;
use App\Jobs\LimparArquivosAntigos;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Rotinas automáticas. Precisam do agendador rodando:
|   desenvolvimento: php artisan schedule:work
|   produção:        php artisan schedule:run a cada minuto (Agendador de Tarefas do Windows / cron)
| Os jobs vão para a fila, processada por: php artisan queue:work
*/

Schedule::job(new ExpirarOrcamentos)->dailyAt('00:10')->name('expirar-orcamentos');
Schedule::job(new GerarBackup)->dailyAt(config('oficina.backup_horario', '02:00'))->name('backup-diario');
Schedule::job(new LimparArquivosAntigos)->dailyAt('03:00')->name('limpar-arquivos');
Schedule::job(new AlertarContasVencidas)->dailyAt('07:00')->name('alerta-contas');
Schedule::job(new AlertarEstoqueBaixo)->dailyAt('07:05')->name('alerta-estoque');
Schedule::job(new EnviarLembretesAgendamento)->dailyAt(config('oficina.lembrete_horario', '18:00'))->name('lembretes-agendamento');

// Executa uma rotina na hora, sem esperar o horário (útil para testar)
Artisan::command('oficina:rotina {nome : expirar|backup|limpar|contas|estoque|lembretes}', function (string $nome) {
    $job = match ($nome) {
        'expirar' => new ExpirarOrcamentos,
        'backup' => new GerarBackup,
        'limpar' => new LimparArquivosAntigos,
        'contas' => new AlertarContasVencidas,
        'estoque' => new AlertarEstoqueBaixo,
        'lembretes' => new EnviarLembretesAgendamento,
        default => null,
    };
    if (! $job) {
        $this->error("Rotina desconhecida: {$nome}");

        return 1;
    }
    dispatch($job);
    $this->info("Rotina \"{$nome}\" enviada para a fila. Rode \"php artisan queue:work\" para processar.");

    return 0;
})->purpose('Dispara uma rotina automática da oficina imediatamente');
