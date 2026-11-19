<?php

namespace App\Services;

use App\Enums\StatusOrcamento;
use App\Enums\StatusOS;
use App\Models\OficinaConfig;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrcamentoService
{
    public function salvar(array $dados, ?Orcamento $orc, ?int $userId): Orcamento
    {
        if ($orc && $orc->status->travado()) {
            throw ValidationException::withMessages([
                'status' => 'Orçamento '.mb_strtolower($orc->status->label()).': reabra para editar.',
            ]);
        }

        return DB::transaction(function () use ($dados, $orc, $userId) {
            $itens = $dados['itens'] ?? null;
            unset($dados['itens'], $dados['status'], $dados['numero'], $dados['os_id']);

            if (! $orc) {
                $config = OficinaConfig::atual();
                $orc = new Orcamento([
                    'status' => StatusOrcamento::Rascunho,
                    'data_emissao' => today(),
                    'validade' => today()->addDays($config->validade_orcamento_dias ?: 7),
                    'condicoes' => $config->condicoes_orcamento,
                    'criado_por' => $userId,
                ]);
                $orc->numero = Numeracao::proximo(Orcamento::class, 1);
            }

            $orc->fill($dados);
            // renovou a validade de um orçamento expirado: volta a aguardar resposta
            if ($orc->status === StatusOrcamento::Expirado && $orc->validade->gte(today())) {
                $orc->status = StatusOrcamento::Enviado;
            }
            $orc->save();

            if ($itens !== null) {
                ItensDocumento::substituir($orc->itens(), $itens);
            }

            return $orc->load('itens');
        });
    }

    public function alterarStatus(Orcamento $orc, StatusOrcamento $novo): Orcamento
    {
        if ($novo === StatusOrcamento::Expirado || ! $orc->status->podeIrPara($novo)) {
            throw ValidationException::withMessages([
                'status' => "Não é possível ir de \"{$orc->status->label()}\" para \"{$novo->label()}\".",
            ]);
        }
        if ($novo === StatusOrcamento::Rascunho && $orc->os_id) {
            throw ValidationException::withMessages(['status' => 'Este orçamento já virou OS e não pode ser reaberto.']);
        }
        if ($orc->itens()->count() === 0 && $novo !== StatusOrcamento::Rascunho) {
            throw ValidationException::withMessages(['itens' => 'Adicione ao menos um item.']);
        }

        $orc->update(['status' => $novo]);

        return $orc->load('itens');
    }

    /** Cria a OS (status "aprovada") a partir do orçamento */
    public function converterEmOS(Orcamento $orc): OrdemServico
    {
        return DB::transaction(function () use ($orc) {
            $orc = Orcamento::query()->lockForUpdate()->with('itens')->findOrFail($orc->id);
            if ($orc->os_id) {
                throw ValidationException::withMessages(['os' => 'Este orçamento já gerou uma OS.']);
            }
            if (! in_array($orc->status, [StatusOrcamento::Aprovado, StatusOrcamento::Enviado, StatusOrcamento::Rascunho, StatusOrcamento::Expirado], true)) {
                throw ValidationException::withMessages(['status' => 'Orçamento recusado não pode virar OS.']);
            }

            $os = OrdemServico::create([
                'numero' => Numeracao::proximo(OrdemServico::class, 1001),
                'cliente_id' => $orc->cliente_id,
                'veiculo_id' => $orc->veiculo_id,
                'status' => StatusOS::Aprovada,
                'data_abertura' => today(),
                'data_previsao' => today()->addDay(),
                'km_entrada' => $orc->km_entrada,
                'defeito_relatado' => $orc->solicitacao,
                'diagnostico' => $orc->diagnostico,
                'observacoes' => $orc->observacoes,
                'desconto' => $orc->desconto,
                'forma_pagamento' => $orc->forma_pagamento,
                'orcamento_id' => $orc->id,
            ]);
            ItensDocumento::substituir($os->itens(), $orc->itens->map(fn ($i) => $i->only(['tipo', 'ref_id', 'descricao', 'quantidade', 'valor_unitario']))->all());

            $orc->update(['status' => StatusOrcamento::Aprovado, 'os_id' => $os->id]);

            return $os->load('itens');
        });
    }

    public function duplicar(Orcamento $orc, ?int $userId): Orcamento
    {
        return DB::transaction(function () use ($orc, $userId) {
            $config = OficinaConfig::atual();
            $copia = $orc->replicate(['numero', 'status', 'os_id', 'data_emissao', 'validade', 'criado_por']);
            $copia->fill([
                'numero' => Numeracao::proximo(Orcamento::class, 1),
                'status' => StatusOrcamento::Rascunho,
                'data_emissao' => today(),
                'validade' => today()->addDays($config->validade_orcamento_dias ?: 7),
                'criado_por' => $userId,
            ])->save();
            ItensDocumento::substituir($copia->itens(), $orc->itens->map(fn ($i) => $i->only(['tipo', 'ref_id', 'descricao', 'quantidade', 'valor_unitario']))->all());

            return $copia->load('itens');
        });
    }
}
