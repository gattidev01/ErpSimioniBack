<?php

namespace Tests\Feature;

use App\Jobs\ProcessarEnvio;
use App\Mail\MensagemCliente;
use App\Models\Envio;
use App\Models\Orcamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrcamentoEnvioTest extends TestCase
{
    use RefreshDatabase;

    private function criarOrcamento(object $c): array
    {
        return $this->postJson('/api/orcamentos', [
            'clienteId' => $c->cliente->id,
            'veiculoId' => $c->veiculo->id,
            'formaPagamento' => 'PIX',
            'solicitacao' => 'Revisão',
            'itens' => [
                ['tipo' => 'servico', 'refId' => $c->servico->id, 'descricao' => 'Troca de óleo', 'quantidade' => 1, 'valorUnitario' => 60],
                ['tipo' => 'peca', 'refId' => $c->peca->id, 'descricao' => 'Filtro', 'quantidade' => 2, 'valorUnitario' => 30],
            ],
        ])->assertCreated()->json();
    }

    public function test_cria_aprova_converte_em_os_e_trava_edicao(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $orc = $this->criarOrcamento($c);

        $this->assertSame(1, $orc['numero']);
        $this->assertSame('rascunho', $orc['status']);
        $this->assertSame(120, (int) $orc['total']);
        $this->assertSame(today()->addDays(7)->toDateString(), $orc['validade']);

        $this->postJson("/api/orcamentos/{$orc['id']}/status", ['status' => 'aprovado'])->assertOk();
        $os = $this->postJson("/api/orcamentos/{$orc['id']}/converter")->assertCreated()
            ->assertJsonPath('status', 'aprovada')
            ->assertJsonPath('orcamentoId', $orc['id'])
            ->assertJsonCount(2, 'itens')
            ->json();

        $this->assertSame($os['id'], Orcamento::find($orc['id'])->os_id);
        $this->postJson("/api/orcamentos/{$orc['id']}/converter")->assertStatus(422); // só uma vez
        $this->putJson("/api/orcamentos/{$orc['id']}", ['clienteId' => $c->cliente->id, 'veiculoId' => $c->veiculo->id, 'formaPagamento' => 'PIX', 'itens' => []])
            ->assertStatus(422);
        $this->postJson("/api/orcamentos/{$orc['id']}/status", ['status' => 'rascunho'])->assertStatus(422); // já virou OS
        $this->deleteJson("/api/orcamentos/{$orc['id']}")->assertStatus(422);

        $copia = $this->postJson("/api/orcamentos/{$orc['id']}/duplicar")->assertCreated()->json();
        $this->assertSame(2, $copia['numero']);
        $this->assertSame('rascunho', $copia['status']);
    }

    public function test_pdf_do_orcamento_e_da_os(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $orc = $this->criarOrcamento($c);

        $r = $this->get("/api/orcamentos/{$orc['id']}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $r->getContent());

        $os = $this->postJson('/api/ordens', $this->corpoOS($c))->json('id');
        $this->assertStringStartsWith('%PDF', $this->get("/api/ordens/{$os}/pdf")->assertOk()->getContent());
    }

    public function test_enviar_por_email_vai_para_fila_e_job_envia_com_pdf(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $orc = $this->criarOrcamento($c);

        Queue::fake();
        $envio = $this->postJson("/api/orcamentos/{$orc['id']}/enviar", ['canal' => 'email'])
            ->assertStatus(202)
            ->assertJsonPath('destino', 'cliente@teste.com') // pegou do cadastro
            ->assertJsonPath('status', 'pendente')
            ->json();
        Queue::assertPushed(ProcessarEnvio::class, fn ($job) => $job->envioId === $envio['id']);

        // executa o job "de verdade"
        Mail::fake();
        app()->call([new ProcessarEnvio($envio['id']), 'handle']);

        Mail::assertSent(MensagemCliente::class, function (MensagemCliente $m) {
            return $m->hasTo('cliente@teste.com')
                && str_starts_with((string) $m->pdf, '%PDF')
                && str_contains($m->texto, 'orçamento 0001');
        });
        $this->assertSame(Envio::ENVIADO, Envio::find($envio['id'])->status);
        $this->assertSame('enviado', Orcamento::find($orc['id'])->status->value); // rascunho → enviado
    }

    public function test_whatsapp_sem_configuracao_e_recusado(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $orc = $this->criarOrcamento($c);
        config(['services.whatsapp.token' => null]);

        $this->postJson("/api/orcamentos/{$orc['id']}/enviar", ['canal' => 'whatsapp'])
            ->assertStatus(422)->assertJsonValidationErrors('canal');
    }

    public function test_whatsapp_envia_pdf_pela_api_da_meta(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $orc = $this->criarOrcamento($c);
        config(['services.whatsapp.token' => 'tok', 'services.whatsapp.phone_number_id' => '123', 'services.whatsapp.template_documento' => null]);

        Http::fake([
            'graph.facebook.com/*/123/media' => Http::response(['id' => 'MEDIA1']),
            'graph.facebook.com/*/123/messages' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);
        Queue::fake();
        $envio = $this->postJson("/api/orcamentos/{$orc['id']}/enviar", ['canal' => 'whatsapp'])->assertStatus(202)
            ->assertJsonPath('destino', '5511988887777')->json();

        app()->call([new ProcessarEnvio($envio['id']), 'handle']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/messages')
            && $r['to'] === '5511988887777'
            && $r['type'] === 'document'
            && $r['document']['id'] === 'MEDIA1');
        $this->assertSame(Envio::ENVIADO, Envio::find($envio['id'])->status);
    }

    public function test_falha_no_envio_fica_registrada_e_pode_reenviar(): void
    {
        $this->logarComo();
        $c = $this->cenario();
        $orc = $this->criarOrcamento($c);
        config(['services.whatsapp.token' => 'tok', 'services.whatsapp.phone_number_id' => '123']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'Token expirado']], 401)]);
        Queue::fake();

        $id = $this->postJson("/api/orcamentos/{$orc['id']}/enviar", ['canal' => 'whatsapp'])->json('id');
        $job = new ProcessarEnvio($id);
        try {
            app()->call([$job, 'handle']);
            $this->fail('Deveria lançar exceção');
        } catch (\Throwable $e) {
            $job->failed($e);
        }

        $envio = Envio::find($id);
        $this->assertSame(Envio::FALHOU, $envio->status);
        $this->assertStringContainsString('401', $envio->erro);

        $this->postJson("/api/envios/{$id}/reenviar")->assertStatus(202)->assertJsonPath('status', 'pendente');
        Queue::assertPushed(ProcessarEnvio::class, 2);
    }
}
