<?php

namespace App\Jobs;

use App\Models\Relatorio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/** Remove relatórios gerados há mais de N dias (padrão 7) */
class LimparArquivosAntigos implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $dias = (int) config('oficina.relatorios_dias', 7);
        Relatorio::where('created_at', '<', now()->subDays($dias))->get()->each(function (Relatorio $r) {
            if ($r->arquivo) {
                Storage::disk('local')->delete($r->arquivo);
            }
            $r->delete();
        });
    }
}
