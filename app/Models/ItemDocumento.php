<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Linha de serviço/peça de um orçamento ou OS */
abstract class ItemDocumento extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['documento_id', 'tipo', 'ref_id', 'descricao', 'quantidade', 'valor_unitario', 'ordem'];

    protected $hidden = ['documento_id', 'ordem'];

    protected function casts(): array
    {
        return ['quantidade' => 'float', 'valor_unitario' => 'float'];
    }

    public function total(): float
    {
        return round($this->quantidade * $this->valor_unitario, 2);
    }
}
