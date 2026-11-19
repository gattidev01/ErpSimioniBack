<?php

namespace App\Services;

use App\Enums\StatusOS;
use App\Models\Agendamento;
use App\Models\Lancamento;
use App\Models\OrdemServico;
use App\Models\Veiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdemServicoService
{
    public function __construct(private EstoqueService $estoque) {}

    /** Cria ou atualiza a OS com seus itens */
    public function salvar(array $dados, ?OrdemServico $os, ?int $userId): OrdemServico
    {
        return DB::transaction(function () use ($dados, $os, $userId) {
            $itens = $dados['itens'] ?? null;
            unset($dados['itens'], $dados['status'], $dados['numero'], $dados['estoque_baixado']);

            if (! $os) {
                $os = new OrdemServico(['status' => StatusOS::Orcamento]);
                $os->numero = Numeracao::proximo(OrdemServico::class, 1001);
            }

            if ($itens !== null && $os->exists && $os->estoque_baixado) {
                throw ValidationException::withMessages([
                    'itens' => 'Os itens não podem ser alterados depois da baixa no estoque. Reabra a OS primeiro.',
                ]);
            }

            $os->fill($dados)->save();

            if ($itens !== null) {
                ItensDocumento::substituir($os->itens(), $itens);
            }

            $this->atualizarKm($os);

            if (! empty($dados['agendamento_id']) && $os->wasRecentlyCreated) {
                Agendamento::whereKey($dados['agendamento_id'])->update(['status' => 'concluido']);
            }

            return $os->load('itens');
        });
    }

    /** Muda o status seguindo o fluxo e sincroniza o estoque */
    public function alterarStatus(OrdemServico $os, StatusOS $novo, ?int $userId): OrdemServico
    {
        if (! $os->status->podeIrPara($novo)) {
            throw ValidationException::withMessages([
                'status' => "Não é possível ir de \"{$os->status->label()}\" para \"{$novo->label()}\".",
            ]);
        }

        return DB::transaction(function () use ($os, $novo, $userId) {
            $os = OrdemServico::query()->lockForUpdate()->with('itens')->findOrFail($os->id);
            $os->status = $novo;
            $os->save();
            $this->estoque->sincronizarComOS($os, $userId);

            return $os->fresh('itens');
        });
    }

    /** Gera a conta a receber (uma única vez) */
    public function faturar(OrdemServico $os, bool $recebidoAgora): Lancamento
    {
        if (! $os->status->baixaEstoque()) {
            throw ValidationException::withMessages(['status' => 'Só é possível faturar OS concluída ou entregue.']);
        }

        return DB::transaction(function () use ($os, $recebidoAgora) {
            $os = OrdemServico::query()->lockForUpdate()->with(['itens', 'cliente'])->findOrFail($os->id);
            if ($os->lancamento()->exists()) {
                throw ValidationException::withMessages(['os' => 'Esta OS já foi faturada.']);
            }

            return Lancamento::create([
                'tipo' => 'receita',
                'descricao' => "OS #{$os->numero} - {$os->cliente->nome}",
                'categoria' => 'Serviços',
                'valor' => $os->total(),
                'vencimento' => today(),
                'pago_em' => $recebidoAgora ? today() : null,
                'forma_pagamento' => $os->forma_pagamento,
                'os_id' => $os->id,
            ]);
        });
    }

    private function atualizarKm(OrdemServico $os): void
    {
        Veiculo::whereKey($os->veiculo_id)->where('km', '<', $os->km_entrada)->update(['km' => $os->km_entrada]);
    }
}
