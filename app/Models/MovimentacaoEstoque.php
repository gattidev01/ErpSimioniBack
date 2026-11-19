<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoEstoque extends Model
{
    use HasUuids;

    protected $table = 'movimentacoes_estoque';

    public const UPDATED_AT = null;

    protected $fillable = ['peca_id', 'tipo', 'quantidade', 'saldo_apos', 'custo_unitario', 'os_id', 'user_id', 'observacao'];

    protected function casts(): array
    {
        return ['quantidade' => 'float', 'saldo_apos' => 'float', 'custo_unitario' => 'float'];
    }

    public function peca(): BelongsTo
    {
        return $this->belongsTo(Peca::class);
    }
}
