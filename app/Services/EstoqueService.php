<?php

namespace App\Services;

use App\Jobs\AlertarEstoqueBaixo;
use App\Models\MovimentacaoEstoque;
use App\Models\OrdemServico;
use App\Models\Peca;
use Illuminate\Support\Facades\DB;

class EstoqueService
{
    /**
     * Dá baixa (status concluída/entregue) ou estorna (saiu desses status) as peças da OS.
     * Deve ser chamado dentro de uma transação, após gravar o novo status.
     */
    public function sincronizarComOS(OrdemServico $os, ?int $userId): void
    {
        $deveBaixar = $os->status->baixaEstoque();
        if ($deveBaixar === $os->estoque_baixado) {
            return;
        }

        $sinal = $deveBaixar ? -1 : 1;
        $porPeca = $os->itens
            ->where('tipo', 'peca')
            ->whereNotNull('ref_id')
            ->groupBy('ref_id')
            ->map(fn ($itens) => $itens->sum('quantidade'));

        $ficaramBaixas = [];
        foreach ($porPeca as $pecaId => $qtd) {
            $peca = Peca::query()->lockForUpdate()->find($pecaId);
            if (! $peca) {
                continue; // peça excluída do cadastro: só registra na OS
            }
            $estavaOk = ! $peca->estaBaixo();
            $peca->estoque = round($peca->estoque + $sinal * $qtd, 3);
            $peca->save();

            MovimentacaoEstoque::create([
                'peca_id' => $peca->id,
                'tipo' => $deveBaixar ? 'saida' : 'estorno',
                'quantidade' => $sinal * $qtd,
                'saldo_apos' => $peca->estoque,
                'os_id' => $os->id,
                'user_id' => $userId,
                'observacao' => ($deveBaixar ? 'Baixa' : 'Estorno')." da OS #{$os->numero}",
            ]);

            if ($deveBaixar && $estavaOk && $peca->estaBaixo()) {
                $ficaramBaixas[] = $peca->id;
            }
        }

        $os->estoque_baixado = $deveBaixar;
        if ($deveBaixar && ! $os->data_conclusao) {
            $os->data_conclusao = today();
        }
        $os->save();

        if ($ficaramBaixas) {
            // avisa a equipe só depois que a transação confirmar
            AlertarEstoqueBaixo::dispatch($ficaramBaixas)->afterCommit();
        }
    }

    /** Entrada de mercadoria (compra) */
    public function entrada(Peca $peca, float $quantidade, float $custo, ?int $userId, string $obs = ''): Peca
    {
        return DB::transaction(function () use ($peca, $quantidade, $custo, $userId, $obs) {
            $peca = Peca::query()->lockForUpdate()->findOrFail($peca->id);
            $peca->estoque = round($peca->estoque + $quantidade, 3);
            $peca->custo = $custo;
            $peca->save();

            MovimentacaoEstoque::create([
                'peca_id' => $peca->id,
                'tipo' => 'entrada',
                'quantidade' => $quantidade,
                'saldo_apos' => $peca->estoque,
                'custo_unitario' => $custo,
                'user_id' => $userId,
                'observacao' => mb_substr($obs ?: 'Entrada de mercadoria', 0, 200),
            ]);

            return $peca;
        });
    }

    /** Peças da OS cuja quantidade excede o estoque atual */
    public function faltas(OrdemServico $os): array
    {
        $faltas = [];
        $porPeca = $os->itens->where('tipo', 'peca')->whereNotNull('ref_id')->groupBy('ref_id');
        foreach ($porPeca as $pecaId => $itens) {
            $qtd = $itens->sum('quantidade');
            $peca = Peca::find($pecaId);
            if ($peca && $qtd > $peca->estoque) {
                $faltas[] = "{$peca->descricao} (precisa {$qtd}, tem {$peca->estoque})";
            }
        }

        return $faltas;
    }
}
