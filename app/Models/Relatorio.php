<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relatorio extends Model
{
    use HasUuids;

    public const TIPOS = ['financeiro', 'ordens', 'orcamentos', 'comissoes', 'estoque'];
    public const FORMATOS = ['pdf', 'xlsx'];

    protected $table = 'relatorios';

    protected $fillable = ['user_id', 'tipo', 'formato', 'parametros', 'status', 'arquivo', 'erro', 'concluido_em'];

    protected $hidden = ['arquivo'];

    protected function casts(): array
    {
        return ['parametros' => 'array', 'concluido_em' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
