<?php

namespace Tests;

use App\Enums\Perfil;
use App\Models\Cliente;
use App\Models\Funcionario;
use App\Models\Peca;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function usuario(Perfil $perfil = Perfil::Admin, array $extra = []): User
    {
        return User::create([
            'name' => ucfirst($perfil->value),
            'email' => $perfil->value.uniqid().'@teste.local',
            'password' => 'Senha123',
            'perfil' => $perfil,
            'ativo' => true,
            ...$extra,
        ]);
    }

    protected function logarComo(Perfil $perfil = Perfil::Admin, array $extra = []): User
    {
        $u = $this->usuario($perfil, $extra);
        Sanctum::actingAs($u);

        return $u;
    }

    /** Cliente + veículo + serviço + peça (estoque 10) prontos para uso */
    protected function cenario(): object
    {
        $cliente = Cliente::create(['nome' => 'Cliente Teste', 'tipo' => 'PF', 'telefone' => '(11) 98888-7777', 'email' => 'cliente@teste.com', 'documento' => '', 'endereco' => '']);
        $veiculo = Veiculo::create(['cliente_id' => $cliente->id, 'placa' => 'ABC1D23', 'marca' => 'VW', 'modelo' => 'Gol', 'km' => 1000]);
        $servico = Servico::create(['descricao' => 'Troca de óleo', 'categoria' => 'Revisão', 'preco' => 60, 'tempo_estimado' => 1]);
        $peca = Peca::create(['codigo' => 'P1', 'descricao' => 'Filtro de óleo', 'unidade' => 'UN', 'custo' => 10, 'preco' => 30, 'estoque' => 10, 'estoque_minimo' => 3]);
        $mecanico = Funcionario::create(['nome' => 'Mecânico', 'comissao' => 10, 'ativo' => true]);

        return (object) compact('cliente', 'veiculo', 'servico', 'peca', 'mecanico');
    }

    /** Corpo de OS no formato camelCase que o front envia */
    protected function corpoOS(object $c, array $extra = []): array
    {
        return [
            'clienteId' => $c->cliente->id,
            'veiculoId' => $c->veiculo->id,
            'mecanicoId' => $c->mecanico->id,
            'dataAbertura' => today()->toDateString(),
            'dataPrevisao' => today()->addDay()->toDateString(),
            'kmEntrada' => 1500,
            'defeitoRelatado' => 'Revisão',
            'formaPagamento' => 'PIX',
            'desconto' => 10,
            'itens' => [
                ['tipo' => 'servico', 'refId' => $c->servico->id, 'descricao' => 'Troca de óleo', 'quantidade' => 1, 'valorUnitario' => 60],
                ['tipo' => 'peca', 'refId' => $c->peca->id, 'descricao' => 'Filtro de óleo', 'quantidade' => 4, 'valorUnitario' => 30],
            ],
            ...$extra,
        ];
    }
}
