<?php

namespace App\Models;

use App\Enums\StatusOS;
use App\Models\Concerns\TemItens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrdemServico extends Model
{
    use HasUuids, TemItens;

    protected $table = 'ordens_servico';

    protected $fillable = [
        'numero', 'cliente_id', 'veiculo_id', 'mecanico_id', 'status', 'data_abertura', 'data_previsao',
        'data_conclusao', 'km_entrada', 'defeito_relatado', 'diagnostico', 'observacoes', 'desconto',
        'forma_pagamento', 'estoque_baixado', 'agendamento_id', 'orcamento_id',
    ];

    protected $appends = ['total'];

    protected function casts(): array
    {
        return [
            'status' => StatusOS::class,
            'data_abertura' => 'date:Y-m-d',
            'data_previsao' => 'date:Y-m-d',
            'data_conclusao' => 'date:Y-m-d',
            'km_entrada' => 'integer',
            'numero' => 'integer',
            'desconto' => 'float',
            'estoque_baixado' => 'boolean',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrdemItem::class, 'documento_id')->orderBy('ordem');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'mecanico_id');
    }

    public function lancamento(): HasOne
    {
        return $this->hasOne(Lancamento::class, 'os_id');
    }
}
