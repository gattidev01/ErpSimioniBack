<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    use HasUuids;

    protected $table = 'agendamentos';

    protected $fillable = ['cliente_id', 'veiculo_id', 'data', 'hora', 'servico', 'status', 'observacoes', 'lembrete_enviado_em'];

    protected function casts(): array
    {
        return ['data' => 'date:Y-m-d', 'lembrete_enviado_em' => 'datetime'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
