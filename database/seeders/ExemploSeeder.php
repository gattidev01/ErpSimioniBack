<?php

namespace Database\Seeders;

use App\Enums\Perfil;
use App\Enums\StatusOrcamento;
use App\Enums\StatusOS;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Funcionario;
use App\Models\Lancamento;
use App\Models\OficinaConfig;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Models\Peca;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\ItensDocumento;
use Illuminate\Database\Seeder;

/** Dados de demonstração (somente APP_ENV=local) */
class ExemploSeeder extends Seeder
{
    public function run(): void
    {
        if (Cliente::exists()) {
            return; // já tem dados
        }

        OficinaConfig::atual()->update([
            'nome' => 'Simioni Performance',
            'cnpj' => '12.345.678/0001-90',
            'telefone' => '(11) 3333-4444',
            'email' => 'contato@oficina.com.br',
            'endereco' => 'Rua das Oficinas, 100 - Centro - São Paulo/SP',
        ]);

        $f = collect([
            ['Roberto Santos', 'Mecânico chefe', 10],
            ['Lucas Almeida', 'Mecânico', 8],
            ['Ana Costa', 'Eletricista', 8],
        ])->map(fn ($x) => Funcionario::create(['nome' => $x[0], 'funcao' => $x[1], 'comissao' => $x[2], 'telefone' => '', 'ativo' => true]));

        // usuários de teste dos outros perfis (senha só para ambiente local)
        User::firstOrCreate(['email' => 'atendente@oficina.local'], ['name' => 'Atendente', 'password' => 'Senha123', 'perfil' => Perfil::Atendente]);
        User::firstOrCreate(['email' => 'mecanico@oficina.local'], ['name' => 'Lucas (mecânico)', 'password' => 'Senha123', 'perfil' => Perfil::Mecanico, 'funcionario_id' => $f[1]->id]);

        $c = collect([
            ['João da Silva', 'PF', '123.456.789-00', '(11) 98888-1111', 'joao@email.com'],
            ['Maria Oliveira', 'PF', '987.654.321-00', '(11) 97777-2222', 'maria@email.com'],
            ['Transportes Rápido Ltda', 'PJ', '11.222.333/0001-44', '(11) 3555-6666', 'frota@rapido.com.br'],
            ['Carlos Pereira', 'PF', '321.654.987-00', '(11) 96666-3333', ''],
        ])->map(fn ($x) => Cliente::create(['nome' => $x[0], 'tipo' => $x[1], 'documento' => $x[2], 'telefone' => $x[3], 'email' => $x[4], 'endereco' => '']));

        $v = collect([
            [0, 'ABC1D23', 'Volkswagen', 'Gol 1.6', '2018', 'Prata', 85200],
            [1, 'EFG4H56', 'Fiat', 'Argo 1.3', '2021', 'Branco', 32100],
            [2, 'IJK7L89', 'Mercedes-Benz', 'Sprinter 415', '2019', 'Branco', 210500],
            [3, 'QRS3T45', 'Chevrolet', 'Onix 1.0 Turbo', '2022', 'Preto', 25400],
        ])->map(fn ($x) => Veiculo::create(['cliente_id' => $c[$x[0]]->id, 'placa' => $x[1], 'marca' => $x[2], 'modelo' => $x[3], 'ano' => $x[4], 'cor' => $x[5], 'km' => $x[6]]));

        $s = collect([
            ['Troca de óleo e filtro', 'Revisão', 60, 0.5],
            ['Revisão completa', 'Revisão', 350, 4],
            ['Troca de pastilhas de freio (eixo)', 'Freios', 120, 1],
            ['Alinhamento e balanceamento', 'Suspensão', 140, 1],
            ['Diagnóstico eletrônico (scanner)', 'Elétrica', 100, 0.5],
            ['Troca de correia dentada', 'Motor', 280, 3],
        ])->map(fn ($x) => Servico::create(['descricao' => $x[0], 'categoria' => $x[1], 'preco' => $x[2], 'tempo_estimado' => $x[3]]));

        $p = collect([
            ['OL-5W30', 'Óleo sintético 5W30', 'Mobil', 'L', 32, 55, 48, 20],
            ['FO-001', 'Filtro de óleo', 'Tecfil', 'UN', 18, 35, 15, 10],
            ['FA-001', 'Filtro de ar', 'Tecfil', 'UN', 25, 48, 4, 6],
            ['PF-DIA', 'Pastilha de freio dianteira', 'Cobreq', 'JG', 65, 130, 8, 4],
            ['DF-DIA', 'Disco de freio dianteiro', 'Fremax', 'PC', 110, 210, 2, 4],
            ['CD-KIT', 'Kit correia dentada + tensor', 'Gates', 'JG', 180, 340, 3, 2],
        ])->map(fn ($x) => Peca::create(['codigo' => $x[0], 'descricao' => $x[1], 'marca' => $x[2], 'unidade' => $x[3], 'custo' => $x[4], 'preco' => $x[5], 'estoque' => $x[6], 'estoque_minimo' => $x[7], 'localizacao' => '']));

        $item = fn ($tipo, $ref, $qtd) => ['tipo' => $tipo, 'ref_id' => $ref->id, 'descricao' => $ref->descricao, 'quantidade' => $qtd, 'valor_unitario' => $ref->preco];

        $os1 = OrdemServico::create([
            'numero' => 1001, 'cliente_id' => $c[2]->id, 'veiculo_id' => $v[2]->id, 'mecanico_id' => $f[1]->id,
            'status' => StatusOS::EmAndamento, 'data_abertura' => today()->subDays(2), 'data_previsao' => today()->addDay(),
            'km_entrada' => 210500, 'defeito_relatado' => 'Barulho ao frear', 'diagnostico' => 'Pastilhas e discos gastos',
            'forma_pagamento' => 'Boleto',
        ]);
        ItensDocumento::substituir($os1->itens(), [$item('servico', $s[2], 1), $item('peca', $p[3], 1), $item('peca', $p[4], 2)]);

        $os2 = OrdemServico::create([
            'numero' => 1002, 'cliente_id' => $c[1]->id, 'veiculo_id' => $v[1]->id, 'mecanico_id' => $f[2]->id,
            'status' => StatusOS::Aprovada, 'data_abertura' => today()->subDay(), 'data_previsao' => today()->addDays(2),
            'km_entrada' => 32100, 'defeito_relatado' => 'Luz da injeção acesa', 'forma_pagamento' => 'Cartão de crédito',
        ]);
        ItensDocumento::substituir($os2->itens(), [$item('servico', $s[4], 1)]);

        $orc = Orcamento::create([
            'numero' => 1, 'cliente_id' => $c[0]->id, 'veiculo_id' => $v[0]->id, 'status' => StatusOrcamento::Enviado,
            'data_emissao' => today()->subDay(), 'validade' => today()->addDays(6), 'km_entrada' => 85200,
            'solicitacao' => 'Troca da correia dentada', 'forma_pagamento' => 'PIX', 'prazo_execucao' => '1 dia útil',
            'condicoes' => OficinaConfig::atual()->condicoes_orcamento,
        ]);
        ItensDocumento::substituir($orc->itens(), [$item('servico', $s[5], 1), $item('peca', $p[5], 1)]);

        Lancamento::create(['tipo' => 'despesa', 'descricao' => 'Aluguel do galpão', 'categoria' => 'Aluguel', 'valor' => 3500, 'vencimento' => today()->subDays(10), 'pago_em' => today()->subDays(10), 'forma_pagamento' => 'Boleto']);
        Lancamento::create(['tipo' => 'despesa', 'descricao' => 'Conta de energia', 'categoria' => 'Energia', 'valor' => 640, 'vencimento' => today()->subDays(2), 'forma_pagamento' => 'Boleto']);

        Agendamento::create(['cliente_id' => $c[3]->id, 'veiculo_id' => $v[3]->id, 'data' => today()->addDay(), 'hora' => '09:00', 'servico' => 'Alinhamento e balanceamento', 'status' => 'confirmado']);
    }
}
