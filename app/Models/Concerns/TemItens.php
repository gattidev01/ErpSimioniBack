<?php

namespace App\Models\Concerns;

/**
 * Totais de documentos com itens (orçamento e OS).
 * Requer a relação itens() e o atributo desconto.
 */
trait TemItens
{
    public function totalServicos(): float
    {
        return round($this->itens->where('tipo', 'servico')->sum(fn ($i) => $i->total()), 2);
    }

    public function totalPecas(): float
    {
        return round($this->itens->where('tipo', 'peca')->sum(fn ($i) => $i->total()), 2);
    }

    public function subtotal(): float
    {
        return round($this->itens->sum(fn ($i) => $i->total()), 2);
    }

    public function total(): float
    {
        return max(0, round($this->subtotal() - (float) $this->desconto, 2));
    }

    /** Inclui o total calculado no JSON da API */
    public function getTotalAttribute(): float
    {
        return $this->relationLoaded('itens') ? $this->total() : 0.0;
    }
}
