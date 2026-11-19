<?php

namespace App\Models;

use App\Enums\StatusOrcamento;
use App\Models\Concerns\TemItens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orcamento extends Model
{
    use HasUuids, TemItens;

    protected $table = 'orcamentos';

    protected $fillable = [
        'numero', 'cliente_id', 'veiculo_id', 'status', 'data_emissao', 'validade', 'km_entrada',
        'solicitacao', 'diagnostico', 'desconto', 'forma_pagamento', 'prazo_execucao',
        'condicoes', 'observacoes', 'os_id', 'criado_por',
    ];

    protected $appends = ['total'];

    protected function casts(): array
    {
        return [
            'status' => StatusOrcamento::class,
            'data_emissao' => 'date:Y-m-d',
            'validade' => 'date:Y-m-d',
            'km_entrada' => 'integer',
            'numero' => 'integer',
            'desconto' => 'float',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class, 'documento_id')->orderBy('ordem');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }

    public function numeroFormatado(): string
    {
        return str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }
}
