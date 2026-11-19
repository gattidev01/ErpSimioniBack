<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funcionario extends Model
{
    use HasUuids;

    protected $table = 'funcionarios';

    protected $fillable = ['nome', 'funcao', 'telefone', 'comissao', 'ativo'];

    protected function casts(): array
    {
        return ['comissao' => 'float', 'ativo' => 'boolean'];
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OrdemServico::class, 'mecanico_id');
    }
}
