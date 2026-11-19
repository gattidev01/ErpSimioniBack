<?php

namespace Tests\Feature;

use App\Models\Lancamento;
use App\Models\MovimentacaoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdemServicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_os_com_numeracao_totais_e_json_em_camel_case(): void
    {
        $this->logarComo();
        $c = $this->cenario();

        $r = $this->postJson('/api/ordens', $this->corpoOS($c))->assertCreated();
        $r->assertJsonPath('numero', 1001)
            ->assertJsonPath('status', 'orcamento')
            ->assertJsonPath('clienteId', $c->cliente->id)
            ->assertJsonPath('total', 170) // 60 + 4*30 - 10
            ->assertJsonPath('itens.1.valorUnitario', 30);

        $this->postJson('/api/ordens', $this->corpoOS($c))->assertJsonPath('numero', 1002);
        $this->assertSame(1500, $c->veiculo->fresh()->km); // km atualizado pela OS
    }

    public function test_valida_veiculo_de_outro_cliente(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $outro = \App\Models\Cliente::create(['nome' => 'Outro', 'tipo' => 'PF', 'telefone' => '1', 'email' => '', 'documento' => '', 'endereco' => '']);

        $this->postJson('/api/ordens', $this->corpoOS($c, ['clienteId' => $outro->id]))
            ->assertStatus(422)->assertJsonValidationErrors('veiculoId');
    }

    public function test_fluxo_completo_baixa_estorna_estoque_e_fatura(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $id = $this->postJson('/api/ordens', $this->corpoOS($c))->json('id');

        $this->postJson("/api/ordens/{$id}/status", ['status' => 'concluida'])->assertStatus(422); // pulou etapas
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'aprovada'])->assertOk();
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'em_andamento'])->assertOk();
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'concluida'])
            ->assertOk()->assertJsonPath('estoqueBaixado', true)->assertJsonPath('dataConclusao', today()->toDateString());

        $this->assertEquals(6, $c->peca->fresh()->estoque);
        $this->assertDatabaseHas('movimentacoes_estoque', ['peca_id' => $c->peca->id, 'tipo' => 'saida', 'os_id' => $id]);

        // itens travados depois da baixa
        $this->putJson("/api/ordens/{$id}", $this->corpoOS($c))->assertStatus(422)->assertJsonValidationErrors('itens');

        // reabrir estorna
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'em_andamento'])->assertOk()->assertJsonPath('estoqueBaixado', false);
        $this->assertEquals(10, $c->peca->fresh()->estoque);
        $this->assertSame(2, MovimentacaoEstoque::count());

        // conclui de novo, fatura uma única vez
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'concluida'])->assertOk();
        $this->postJson("/api/ordens/{$id}/faturar", ['recebido' => true])->assertCreated()->assertJsonPath('valor', 170);
        $this->postJson("/api/ordens/{$id}/faturar", ['recebido' => true])->assertStatus(422);
        $this->assertSame(1, Lancamento::count());
        $this->assertNotNull(Lancamento::first()->pago_em);

        // não pode excluir OS com estoque baixado
        $this->deleteJson("/api/ordens/{$id}")->assertStatus(422);
    }

    public function test_estoque_insuficiente_pede_confirmacao(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $corpo = $this->corpoOS($c);
        $corpo['itens'][1]['quantidade'] = 15;
        $id = $this->postJson('/api/ordens', $corpo)->json('id');
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'aprovada']);
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'em_andamento']);

        $this->postJson("/api/ordens/{$id}/status", ['status' => 'concluida'])
            ->assertStatus(409)->assertJsonPath('codigo', 'estoque_insuficiente');
        $this->assertEquals(10, $c->peca->fresh()->estoque);

        $this->postJson("/api/ordens/{$id}/status", ['status' => 'concluida', 'ignorarFaltaEstoque' => true])->assertOk();
        $this->assertEquals(-5, $c->peca->fresh()->estoque);
    }

    public function test_cancelar_os_entregue_nao_e_permitido_mas_reabrir_sim(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $id = $this->postJson('/api/ordens', $this->corpoOS($c))->json('id');
        foreach (['aprovada', 'em_andamento', 'concluida', 'entregue'] as $s) {
            $this->postJson("/api/ordens/{$id}/status", ['status' => $s])->assertOk();
        }
        $this->postJson("/api/ordens/{$id}/status", ['status' => 'cancelada'])->assertStatus(422);
    }

    public function test_entrada_de_estoque_gera_movimentacao_e_conta_a_pagar(): void
    {
        $this->logarComo();
        $c = $this->cenario();

        $this->postJson("/api/pecas/{$c->peca->id}/entrada", [
            'quantidade' => 5, 'custo' => 12, 'fornecedor' => 'Distribuidora', 'lancarDespesa' => true,
            'vencimento' => today()->addDays(10)->toDateString(), 'formaPagamento' => 'Boleto',
        ])->assertOk()->assertJsonPath('estoque', 15)->assertJsonPath('custo', 12);

        $this->assertDatabaseHas('lancamentos', ['tipo' => 'despesa', 'valor' => 60, 'categoria' => 'Compra de peças']);
        $this->assertDatabaseHas('movimentacoes_estoque', ['tipo' => 'entrada', 'peca_id' => $c->peca->id]);
    }
}
