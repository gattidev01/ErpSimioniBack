<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peca extends Model
{
    use HasUuids;

    protected $table = 'pecas';

    protected $fillable = ['codigo', 'descricao', 'marca', 'unidade', 'custo', 'preco', 'estoque', 'estoque_minimo', 'localizacao'];

    protected function casts(): array
    {
        return [
            'custo' => 'float',
            'preco' => 'float',
            'estoque' => 'float',
            'estoque_minimo' => 'float',
        ];
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class)->latest('created_at');
    }

    public function scopeEstoqueBaixo(Builder $q): Builder
    {
        return $q->whereColumn('estoque', '<=', 'estoque_minimo');
    }

    public function estaBaixo(): bool
    {
        return $this->estoque <= $this->estoque_minimo;
    }
}
