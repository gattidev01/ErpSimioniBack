<?php

namespace App\Jobs;

use App\Enums\StatusOrcamento;
use App\Mail\MensagemCliente;
use App\Models\Agendamento;
use App\Models\Envio;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Services\PdfService;
use App\Services\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Envia um orçamento/OS (com PDF) ou um lembrete de agendamento
 * por e-mail ou WhatsApp, registrando o resultado em `envios`.
 */
class ProcessarEnvio implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Espera entre tentativas: 1 min, depois 5 min */
    public array $backoff = [60, 300];

    public int $timeout = 120;

    public function __construct(public string $envioId) {}

    public function handle(PdfService $pdfs, WhatsAppClient $whatsapp): void
    {
        $envio = Envio::find($this->envioId);
        if (! $envio || $envio->status === Envio::ENVIADO) {
            return;
        }

        $envio->update(['status' => Envio::ENVIANDO, 'tentativas' => $envio->tentativas + 1, 'erro' => null]);

        $doc = $envio->documento() ?? throw new RuntimeException('Documento não encontrado (pode ter sido excluído).');
        $texto = $envio->mensagem ?: $this->textoPadrao($doc);

        if ($doc instanceof Agendamento) {
            $envio->canal === 'whatsapp'
                ? $whatsapp->enviarTexto($envio->destino, $texto, [$doc->cliente->nome, $doc->data->format('d/m/Y'), $doc->hora])
                : Mail::to($envio->destino)->send(new MensagemCliente('Lembrete do seu agendamento', $texto));

            $doc->update(['lembrete_enviado_em' => now()]);
        } else {
            $pdf = $pdfs->documento($doc);
            $nome = $pdfs->nomeArquivo($doc);
            $assunto = $doc instanceof Orcamento
                ? "Orçamento {$doc->numeroFormatado()} - {$doc->veiculo->descricao()}"
                : "Ordem de serviço #{$doc->numero} - {$doc->veiculo->descricao()}";

            $envio->canal === 'whatsapp'
                ? $whatsapp->enviarDocumento($envio->destino, $pdf, $nome, $texto, [
                    $doc->cliente->nome,
                    $doc instanceof Orcamento ? $doc->numeroFormatado() : (string) $doc->numero,
                    'R$ '.number_format($doc->total(), 2, ',', '.'),
                ])
                : Mail::to($envio->destino)->send(new MensagemCliente($assunto, $texto, $pdf, $nome));

            // orçamento em rascunho passa a "enviado" depois que chega ao cliente
            if ($doc instanceof Orcamento && $doc->status === StatusOrcamento::Rascunho) {
                $doc->update(['status' => StatusOrcamento::Enviado]);
            }
        }

        $envio->update(['status' => Envio::ENVIADO, 'enviado_em' => now()]);
    }

    /** Chamado pelo Laravel quando todas as tentativas falharem */
    public function failed(?Throwable $e): void
    {
        Envio::whereKey($this->envioId)->update([
            'status' => Envio::FALHOU,
            'erro' => mb_substr($e?->getMessage() ?? 'Erro desconhecido', 0, 1000),
        ]);
    }

    private function textoPadrao(Orcamento|OrdemServico|Agendamento $doc): string
    {
        $nome = strtok($doc->cliente->nome, ' ');

        return match (true) {
            $doc instanceof Orcamento => "Olá, {$nome}!\n\nSegue o orçamento {$doc->numeroFormatado()} para o veículo {$doc->veiculo->descricao()}, "
                .'no valor de R$ '.number_format($doc->total(), 2, ',', '.').", válido até {$doc->validade->format('d/m/Y')}.\n\n"
                .'Qualquer dúvida, é só responder esta mensagem.',
            $doc instanceof OrdemServico => "Olá, {$nome}!\n\nSegue a ordem de serviço #{$doc->numero} do veículo {$doc->veiculo->descricao()}.\n"
                .'Status: '.$doc->status->label().'.',
            default => "Olá, {$nome}! Lembrando do seu agendamento em {$doc->data->format('d/m/Y')} às {$doc->hora}"
                ." para o veículo {$doc->veiculo->descricao()}".($doc->servico ? " ({$doc->servico})" : '').'. Até lá!',
        };
    }
}
