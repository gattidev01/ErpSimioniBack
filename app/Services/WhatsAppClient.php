<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da WhatsApp Business Cloud API (Meta).
 * Credenciais ficam só no servidor (.env), nunca no front.
 *
 * Observação: fora da janela de 24h após a última mensagem do cliente, a Meta só aceita
 * mensagens de template aprovadas. Configure WHATSAPP_TEMPLATE_DOCUMENTO/LEMBRETE para isso.
 */
class WhatsAppClient
{
    public function configurado(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /** Envia um PDF como documento. Retorna o id da mensagem na Meta. */
    public function enviarDocumento(string $telefone, string $pdf, string $nomeArquivo, string $legenda, array $parametrosTemplate = []): string
    {
        $mediaId = $this->http()
            ->attach('file', $pdf, $nomeArquivo, ['Content-Type' => 'application/pdf'])
            ->post($this->url('media'), ['messaging_product' => 'whatsapp', 'type' => 'application/pdf'])
            ->throw()
            ->json('id');

        if (! $mediaId) {
            throw new RuntimeException('A Meta não retornou o id do arquivo enviado.');
        }

        $template = config('services.whatsapp.template_documento');
        $corpo = $template
            ? $this->template($template, $parametrosTemplate, [
                'type' => 'header',
                'parameters' => [['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $nomeArquivo]]],
            ])
            : ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $nomeArquivo, 'caption' => mb_substr($legenda, 0, 1000)]];

        return $this->enviar($telefone, $corpo);
    }

    /** Mensagem de texto simples (ou template, se configurado) */
    public function enviarTexto(string $telefone, string $texto, array $parametrosTemplate = []): string
    {
        $template = config('services.whatsapp.template_lembrete');
        $corpo = $template
            ? $this->template($template, $parametrosTemplate)
            : ['type' => 'text', 'text' => ['body' => mb_substr($texto, 0, 4000), 'preview_url' => false]];

        return $this->enviar($telefone, $corpo);
    }

    /** Converte para o formato internacional só com dígitos (assume Brasil) */
    public static function normalizarTelefone(string $telefone): ?string
    {
        $d = preg_replace('/\D/', '', $telefone);
        if (strlen($d) === 10 || strlen($d) === 11) {
            $d = '55'.$d;
        }

        return strlen($d) >= 12 && strlen($d) <= 15 ? $d : null;
    }

    private function enviar(string $telefone, array $corpo): string
    {
        $numero = self::normalizarTelefone($telefone) ?? throw new RuntimeException("Telefone inválido: {$telefone}");

        $id = $this->http()
            ->post($this->url('messages'), ['messaging_product' => 'whatsapp', 'to' => $numero, ...$corpo])
            ->throw()
            ->json('messages.0.id');

        return (string) $id;
    }

    private function template(string $nome, array $parametrosCorpo, ?array $header = null): array
    {
        $componentes = [];
        if ($header) {
            $componentes[] = $header;
        }
        if ($parametrosCorpo) {
            $componentes[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], array_values($parametrosCorpo)),
            ];
        }

        return [
            'type' => 'template',
            'template' => [
                'name' => $nome,
                'language' => ['code' => config('services.whatsapp.idioma', 'pt_BR')],
                'components' => $componentes,
            ],
        ];
    }

    private function http(): PendingRequest
    {
        if (! $this->configurado()) {
            throw new RuntimeException('WhatsApp não configurado (WHATSAPP_TOKEN e WHATSAPP_PHONE_NUMBER_ID no .env).');
        }

        return Http::withToken(config('services.whatsapp.token'))->timeout(30)->acceptJson();
    }

    private function url(string $recurso): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/%s',
            config('services.whatsapp.versao', 'v23.0'),
            config('services.whatsapp.phone_number_id'),
            $recurso,
        );
    }
}
