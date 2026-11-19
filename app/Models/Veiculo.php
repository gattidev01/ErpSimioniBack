<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veiculo extends Model
{
    use HasUuids;

    protected $table = 'veiculos';

    protected $fillable = ['cliente_id', 'placa', 'marca', 'modelo', 'ano', 'cor', 'km', 'chassi', 'observacoes'];

    protected function casts(): array
    {
        return ['km' => 'integer'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OrdemServico::class);
    }

    public function descricao(): string
    {
        return trim("{$this->marca} {$this->modelo}")." ({$this->placa})";
    }
}
