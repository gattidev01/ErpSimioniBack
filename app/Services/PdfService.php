<?php

namespace App\Services;

use App\Models\OficinaConfig;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Models\Peca;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class PdfService
{
    /** Embute só os caracteres usados da fonte (PDF bem menor) e bloqueia acesso remoto/PHP no HTML */
    public const OPCOES = ['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false, 'isPhpEnabled' => false];

    /** PDF (binário) de um orçamento ou OS */
    public function documento(Orcamento|OrdemServico $doc): string
    {
        $ehOrcamento = $doc instanceof Orcamento;
        $doc->loadMissing($ehOrcamento ? ['itens', 'cliente', 'veiculo'] : ['itens', 'cliente', 'veiculo', 'mecanico']);

        $dados = [
            'oficina' => OficinaConfig::atual(),
            'cor' => $this->cor(OficinaConfig::atual()->cor_documento),
            'titulo' => $ehOrcamento ? 'ORÇAMENTO' : 'ORDEM DE SERVIÇO',
            'numero' => $ehOrcamento ? $doc->numeroFormatado() : (string) $doc->numero,
            'emissao' => $ehOrcamento ? $doc->data_emissao : $doc->data_abertura,
            'validade' => $ehOrcamento ? $doc->validade : null,
            'previsao' => $ehOrcamento ? null : $doc->data_previsao,
            'doc' => $doc,
            'cliente' => $doc->cliente,
            'veiculo' => $doc->veiculo,
            'responsavel' => $ehOrcamento ? null : $doc->mecanico?->nome,
            'solicitacao' => $ehOrcamento ? $doc->solicitacao : $doc->defeito_relatado,
            'prazo' => $ehOrcamento ? $doc->prazo_execucao : null,
            'condicoes' => $ehOrcamento ? $doc->condicoes : null,
            'aceite' => $doc->cliente->nome,
            // código e unidade das peças do cadastro (para a tabela de itens)
            'pecas' => Peca::whereIn('id', $doc->itens->where('tipo', 'peca')->pluck('ref_id')->filter())->get()->keyBy('id'),
        ];

        return Pdf::loadView('pdf.documento', $dados)->setPaper('a4')->setOption(self::OPCOES)->output();
    }

    public function nomeArquivo(Orcamento|OrdemServico $doc): string
    {
        $prefixo = $doc instanceof Orcamento ? 'orcamento-'.$doc->numeroFormatado() : 'os-'.$doc->numero;

        return $prefixo.'-'.Str::slug($doc->cliente->nome ?? 'cliente').'.pdf';
    }

    /** Cor principal + cor de texto legível sobre ela */
    public function cor(?string $hex): array
    {
        $hex = preg_match('/^#[0-9a-f]{6}$/i', (string) $hex) ? $hex : '#d92d20';
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $claro = ($r * 299 + $g * 587 + $b * 114) / 1000 > 160;

        return ['fundo' => $hex, 'texto' => $claro ? '#1d2330' : '#ffffff'];
    }
}
