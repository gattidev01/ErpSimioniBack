<?php

namespace Tests\Feature;

use App\Enums\Perfil;
use App\Jobs\AlertarContasVencidas;
use App\Jobs\AlertarEstoqueBaixo;
use App\Jobs\EnviarLembretesAgendamento;
use App\Jobs\ExpirarOrcamentos;
use App\Jobs\GerarRelatorio;
use App\Jobs\ProcessarEnvio;
use App\Models\Agendamento;
use App\Models\Envio;
use App\Models\Lancamento;
use App\Models\Orcamento;
use App\Models\Relatorio;
use App\Notifications\AlertaEquipe;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RotinasRelatoriosTest extends TestCase
{
    use RefreshDatabase;

    public function test_expira_orcamentos_vencidos(): void
    {
        $c = $this->cenario();
        $base = ['cliente_id' => $c->cliente->id, 'veiculo_id' => $c->veiculo->id, 'data_emissao' => today()->subDays(10), 'forma_pagamento' => 'PIX'];
        $vencido = Orcamento::create([...$base, 'numero' => 1, 'status' => 'enviado', 'validade' => today()->subDay()]);
        $valido = Orcamento::create([...$base, 'numero' => 2, 'status' => 'enviado', 'validade' => today()->addDays(3)]);
        $aprovado = Orcamento::create([...$base, 'numero' => 3, 'status' => 'aprovado', 'validade' => today()->subDay()]);

        (new ExpirarOrcamentos)->handle();

        $this->assertSame('expirado', $vencido->fresh()->status->value);
        $this->assertSame('enviado', $valido->fresh()->status->value);
        $this->assertSame('aprovado', $aprovado->fresh()->status->value);
    }

    public function test_alerta_contas_vencidas_para_a_equipe(): void
    {
        Notification::fake();
        $admin = $this->usuario(Perfil::Admin);
        $atendente = $this->usuario(Perfil::Atendente);
        $mecanico = $this->usuario(Perfil::Mecanico);
        Lancamento::create(['tipo' => 'despesa', 'descricao' => 'Luz', 'categoria' => 'Energia', 'valor' => 100, 'vencimento' => today()->subDays(2), 'forma_pagamento' => 'Boleto']);

        (new AlertarContasVencidas)->handle();

        Notification::assertSentTo([$admin, $atendente], AlertaEquipe::class, fn ($n) => $n->tipo === 'financeiro');
        Notification::assertNotSentTo($mecanico, AlertaEquipe::class);
    }

    public function test_alerta_estoque_baixo_e_disparado_quando_os_baixa_estoque(): void
    {
        Queue::fake([AlertarEstoqueBaixo::class]);
        $this->logarComo();
        $c = $this->cenario(); // estoque 10, mínimo 3; a OS usa 4 → não fica baixo
        $corpo = $this->corpoOS($c);
        $corpo['itens'][1]['quantidade'] = 8; // sobra 2 → abaixo do mínimo
        $id = $this->postJson('/api/ordens', $corpo)->json('id');
        foreach (['aprovada', 'em_andamento', 'concluida'] as $s) {
            $this->postJson("/api/ordens/{$id}/status", ['status' => $s])->assertOk();
        }

        Queue::assertPushed(AlertarEstoqueBaixo::class, fn ($j) => $j->pecaIds === [$c->peca->id]);
    }

    public function test_lembrete_de_agendamento_de_amanha(): void
    {
        Queue::fake([ProcessarEnvio::class]);
        config(['services.whatsapp.token' => null]); // sem WhatsApp → usa e-mail
        $c = $this->cenario();
        $amanha = Agendamento::create(['cliente_id' => $c->cliente->id, 'veiculo_id' => $c->veiculo->id, 'data' => today()->addDay(), 'hora' => '09:00', 'status' => 'agendado']);
        Agendamento::create(['cliente_id' => $c->cliente->id, 'veiculo_id' => $c->veiculo->id, 'data' => today()->addDays(3), 'hora' => '09:00', 'status' => 'agendado']);

        app()->call([new EnviarLembretesAgendamento, 'handle']);
        app()->call([new EnviarLembretesAgendamento, 'handle']); // rodar 2x não duplica

        $this->assertSame(1, Envio::count());
        $this->assertDatabaseHas('envios', ['documento_id' => $amanha->id, 'canal' => 'email', 'destino' => 'cliente@teste.com']);
        Queue::assertPushed(ProcessarEnvio::class, 1);
    }

    public function test_relatorios_em_pdf_e_excel_gerados_pelo_job(): void
    {
        Storage::fake('local');
        $this->logarComo();
        $c = $this->cenario();
        $this->postJson('/api/ordens', $this->corpoOS($c));

        foreach (['pdf', 'xlsx'] as $formato) {
            Queue::fake([GerarRelatorio::class]);
            $id = $this->postJson('/api/relatorios', [
                'tipo' => 'ordens', 'formato' => $formato,
                'inicio' => today()->startOfMonth()->toDateString(), 'fim' => today()->endOfMonth()->toDateString(),
            ])->assertStatus(202)->assertJsonPath('status', 'pendente')->json('id');
            Queue::assertPushed(GerarRelatorio::class);

            app()->call([new GerarRelatorio($id), 'handle']);
            $rel = Relatorio::find($id);
            $this->assertSame('pronto', $rel->status, (string) $rel->erro);
            Storage::disk('local')->assertExists($rel->arquivo);

            $conteudo = $this->get("/api/relatorios/{$id}/download")->assertOk()->streamedContent();
            $this->assertStringStartsWith($formato === 'pdf' ? '%PDF' : 'PK', $conteudo);
        }
    }

    public function test_todos_os_tipos_de_relatorio_geram_sem_erro(): void
    {
        Storage::fake('local');
        $u = $this->usuario(Perfil::Admin);
        $this->cenario();
        foreach (Relatorio::TIPOS as $tipo) {
            $rel = Relatorio::create(['user_id' => $u->id, 'tipo' => $tipo, 'formato' => 'pdf', 'parametros' => ['inicio' => today()->subMonth()->toDateString(), 'fim' => today()->toDateString()]]);
            app()->call([new GerarRelatorio($rel->id), 'handle']);
            $this->assertSame('pronto', $rel->fresh()->status, "Relatório {$tipo}");
        }
    }

    public function test_atendente_nao_gera_relatorio_de_comissoes(): void
    {
        $this->logarComo(Perfil::Atendente);
        $this->postJson('/api/relatorios', ['tipo' => 'comissoes', 'formato' => 'pdf', 'inicio' => '2026-01-01', 'fim' => '2026-01-31'])
            ->assertStatus(422);
    }

    public function test_download_de_backup_nao_aceita_caminho_arbitrario(): void
    {
        $this->assertNull(app(BackupService::class)->caminho('../.env'));
        $this->assertNull(app(BackupService::class)->caminho('backup-2026.sql.gz.php'));
        $this->logarComo();
        $this->get('/api/backups/..%2F..%2F.env')->assertNotFound();
    }
}
