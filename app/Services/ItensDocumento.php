<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Regras de validação e gravação dos itens (compartilhadas por OS e orçamento) */
class ItensDocumento
{
    public static function regras(bool $obrigatorio = false): array
    {
        return [
            'itens' => [$obrigatorio ? 'required' : 'sometimes', 'array', $obrigatorio ? 'min:1' : 'min:0', 'max:200'],
            'itens.*.tipo' => ['required', 'in:servico,peca'],
            'itens.*.ref_id' => ['nullable', 'uuid'],
            'itens.*.descricao' => ['required', 'string', 'max:160'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'itens.*.valor_unitario' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /** Substitui todos os itens do documento */
    public static function substituir(HasMany $relacao, array $itens): void
    {
        $relacao->delete();
        foreach (array_values($itens) as $i => $item) {
            $relacao->create([
                'tipo' => $item['tipo'],
                'ref_id' => $item['ref_id'] ?? null ?: null,
                'descricao' => trim($item['descricao']),
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $item['valor_unitario'],
                'ordem' => $i,
            ]);
        }
    }
}
