<?php

namespace Tests\Feature;

use App\Enums\Perfil;
use App\Models\OrdemServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPermissoesTest extends TestCase
{
    use RefreshDatabase;

    private array $spa = ['Referer' => 'http://localhost:5173', 'Accept' => 'application/json'];

    public function test_login_por_sessao_e_dados_do_usuario(): void
    {
        $u = $this->usuario(Perfil::Atendente);

        $this->withHeaders($this->spa)->postJson('/api/login', ['email' => $u->email, 'password' => 'Senha123'])
            ->assertOk()
            ->assertJsonPath('perfil', 'atendente')
            ->assertJsonPath('perfilLabel', 'Atendente');

        $this->withHeaders($this->spa)->getJson('/api/me')->assertOk()->assertJsonPath('email', $u->email);
    }

    public function test_senha_errada_e_usuario_inativo_nao_entram(): void
    {
        $u = $this->usuario(Perfil::Admin);
        $this->withHeaders($this->spa)->postJson('/api/login', ['email' => $u->email, 'password' => 'errada'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');

        $inativo = $this->usuario(Perfil::Admin, ['ativo' => false]);
        $this->withHeaders($this->spa)->postJson('/api/login', ['email' => $inativo->email, 'password' => 'Senha123'])
            ->assertStatus(422);
    }

    public function test_login_limita_tentativas(): void
    {
        $u = $this->usuario();
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->spa)->postJson('/api/login', ['email' => $u->email, 'password' => 'x']);
        }
        $this->withHeaders($this->spa)->postJson('/api/login', ['email' => $u->email, 'password' => 'Senha123'])->assertStatus(429);
    }

    public function test_sem_login_retorna_401(): void
    {
        $this->getJson('/api/clientes')->assertUnauthorized();
    }

    public function test_mecanico_nao_acessa_cadastros_e_ve_so_as_proprias_os(): void
    {
        $c = $this->cenario();
        $outro = \App\Models\Funcionario::create(['nome' => 'Outro', 'comissao' => 0, 'ativo' => true]);

        $this->logarComo(Perfil::Admin);
        $minha = $this->postJson('/api/ordens', $this->corpoOS($c))->assertCreated()->json('id');
        $alheia = $this->postJson('/api/ordens', $this->corpoOS($c, ['mecanicoId' => $outro->id]))->assertCreated()->json('id');

        $this->logarComo(Perfil::Mecanico, ['funcionario_id' => $c->mecanico->id]);
        $this->getJson('/api/clientes')->assertForbidden();
        $this->getJson('/api/dashboard')->assertForbidden();

        $ids = collect($this->getJson('/api/ordens')->assertOk()->json())->pluck('id');
        $this->assertEquals([$minha], $ids->all());
        $this->getJson("/api/ordens/{$alheia}")->assertForbidden();

        // mecânico só altera diagnóstico; itens enviados são ignorados
        $this->putJson("/api/ordens/{$minha}", ['diagnostico' => 'Filtro saturado', 'desconto' => 999])
            ->assertOk()->assertJsonPath('diagnostico', 'Filtro saturado')->assertJsonPath('desconto', 10);

        // pode iniciar o serviço, mas não cancelar
        OrdemServico::whereKey($minha)->update(['status' => 'aprovada']);
        $this->postJson("/api/ordens/{$minha}/status", ['status' => 'em_andamento'])->assertOk();
        $this->postJson("/api/ordens/{$minha}/status", ['status' => 'cancelada'])->assertForbidden();
    }

    public function test_atendente_nao_gerencia_usuarios_nem_configuracoes(): void
    {
        $this->logarComo(Perfil::Atendente);
        $this->getJson('/api/usuarios')->assertForbidden();
        $this->putJson('/api/configuracoes', ['nome' => 'X'])->assertForbidden();
        $this->getJson('/api/backups')->assertForbidden();
        $this->getJson('/api/configuracoes')->assertOk();
    }

    public function test_nao_remove_ultimo_admin(): void
    {
        $admin = $this->logarComo(Perfil::Admin);
        $this->putJson("/api/usuarios/{$admin->id}", [
            'name' => 'Admin', 'email' => $admin->email, 'perfil' => 'atendente', 'ativo' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('perfil');
    }
}
