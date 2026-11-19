<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lancamento extends Model
{
    use HasUuids;

    protected $table = 'lancamentos';

    protected $fillable = ['tipo', 'descricao', 'categoria', 'valor', 'vencimento', 'pago_em', 'forma_pagamento', 'os_id'];

    protected $appends = ['situacao'];

    protected function casts(): array
    {
        return [
            'valor' => 'float',
            'vencimento' => 'date:Y-m-d',
            'pago_em' => 'date:Y-m-d',
        ];
    }

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }

    /** pago | vencido | pendente */
    public function getSituacaoAttribute(): string
    {
        if ($this->pago_em) {
            return 'pago';
        }

        return $this->vencimento && $this->vencimento->lt(today()) ? 'vencido' : 'pendente';
    }

    public function scopeVencidos(Builder $q): Builder
    {
        return $q->whereNull('pago_em')->whereDate('vencimento', '<', today());
    }
}
