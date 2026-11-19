<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Servico extends Model
{
    use HasUuids;

    protected $table = 'servicos';

    protected $fillable = ['descricao', 'categoria', 'preco', 'tempo_estimado'];

    protected function casts(): array
    {
        return ['preco' => 'float', 'tempo_estimado' => 'float'];
    }
}
