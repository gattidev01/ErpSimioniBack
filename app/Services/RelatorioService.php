<?php

namespace App\Services;

use App\Enums\StatusOS;
use App\Models\Funcionario;
use App\Models\Lancamento;
use App\Models\OficinaConfig;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Models\Peca;
use App\Models\Relatorio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class RelatorioService
{
    public const PASTA = 'relatorios';

    /** Gera o arquivo e devolve o caminho relativo no disco local */
    public function gerar(Relatorio $rel): string
    {
        $p = $rel->parametros ?? [];
        $inicio = Carbon::parse($p['inicio'] ?? now()->startOfMonth())->startOfDay();
        $fim = Carbon::parse($p['fim'] ?? now()->endOfMonth())->endOfDay();

        $dados = match ($rel->tipo) {
            'financeiro' => $this->financeiro($inicio, $fim),
            'ordens' => $this->ordens($inicio, $fim),
            'orcamentos' => $this->orcamentos($inicio, $fim),
            'comissoes' => $this->comissoes($inicio, $fim),
            'estoque' => $this->estoque(),
        };
        $dados['periodo'] = $rel->tipo === 'estoque'
            ? 'Posição em '.now()->format('d/m/Y H:i')
            : $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y');

        Storage::disk('local')->makeDirectory(self::PASTA);
        $arquivo = self::PASTA."/{$rel->tipo}-".now()->format('Ymd-His')."-{$rel->id}.{$rel->formato}";
        $caminho = Storage::disk('local')->path($arquivo);

        if ($rel->formato === 'xlsx') {
            $this->xlsx($caminho, $dados);
        } else {
            $config = OficinaConfig::atual();
            $cor = app(PdfService::class)->cor($config->cor_documento);
            Pdf::loadView('pdf.relatorio', ['r' => $dados, 'oficina' => $config, 'cor' => $cor])
                ->setPaper('a4', count($dados['colunas']) > 6 ? 'landscape' : 'portrait')
                ->setOption(PdfService::OPCOES)
                ->save($caminho);
        }

        return $arquivo;
    }

    // ---------- Conjuntos de dados: titulo, colunas, linhas, resumo ----------

    private function financeiro(Carbon $i, Carbon $f): array
    {
        $lanc = Lancamento::query()
            ->where(fn ($q) => $q->whereBetween('vencimento', [$i->toDateString(), $f->toDateString()])
                ->orWhereBetween('pago_em', [$i->toDateString(), $f->toDateString()]))
            ->orderBy('vencimento')->get();

        $pagos = $lanc->filter(fn ($l) => $l->pago_em && $l->pago_em->between($i, $f));
        $receb = $pagos->where('tipo', 'receita')->sum('valor');
        $pago = $pagos->where('tipo', 'despesa')->sum('valor');

        return [
            'titulo' => 'Relatório financeiro',
            'colunas' => ['Vencimento', 'Descrição', 'Categoria', 'Tipo', 'Situação', 'Pago em', 'Valor'],
            'moeda' => [6],
            'linhas' => $lanc->map(fn ($l) => [
                $l->vencimento->format('d/m/Y'), $l->descricao, $l->categoria,
                $l->tipo === 'receita' ? 'Receita' : 'Despesa', ucfirst($l->situacao),
                $l->pago_em?->format('d/m/Y') ?? '', $l->tipo === 'despesa' ? -$l->valor : $l->valor,
            ])->all(),
            'resumo' => [
                'Recebido no período' => $receb,
                'Pago no período' => $pago,
                'Saldo' => $receb - $pago,
                'A receber (em aberto)' => $lanc->where('tipo', 'receita')->whereNull('pago_em')->sum('valor'),
                'A pagar (em aberto)' => $lanc->where('tipo', 'despesa')->whereNull('pago_em')->sum('valor'),
            ],
        ];
    }

    private function ordens(Carbon $i, Carbon $f): array
    {
        $os = OrdemServico::with(['itens', 'cliente', 'veiculo', 'mecanico'])
            ->whereBetween('data_abertura', [$i->toDateString(), $f->toDateString()])
            ->orderBy('numero')->get();
        $validas = $os->where('status', '!=', StatusOS::Cancelada);

        return [
            'titulo' => 'Ordens de serviço',
            'colunas' => ['Nº', 'Abertura', 'Cliente', 'Veículo', 'Mecânico', 'Status', 'Serviços', 'Peças', 'Total'],
            'moeda' => [6, 7, 8],
            'linhas' => $os->map(fn ($o) => [
                $o->numero, $o->data_abertura->format('d/m/Y'), $o->cliente->nome, $o->veiculo->descricao(),
                $o->mecanico?->nome ?? '', $o->status->label(), $o->totalServicos(), $o->totalPecas(), $o->total(),
            ])->all(),
            'resumo' => [
                'Quantidade de OS' => $os->count(),
                'Canceladas' => $os->count() - $validas->count(),
                'Total (sem canceladas)' => $validas->sum(fn ($o) => $o->total()),
                'Ticket médio' => $validas->count() ? $validas->sum(fn ($o) => $o->total()) / $validas->count() : 0,
            ],
            'inteiros' => ['Quantidade de OS', 'Canceladas'],
        ];
    }

    private function orcamentos(Carbon $i, Carbon $f): array
    {
        $orc = Orcamento::with(['itens', 'cliente', 'veiculo'])
            ->whereBetween('data_emissao', [$i->toDateString(), $f->toDateString()])
            ->orderBy('numero')->get();
        $aprov = $orc->where('status.value', 'aprovado');
        $decididos = $orc->filter(fn ($o) => in_array($o->status->value, ['aprovado', 'recusado', 'expirado'], true));

        return [
            'titulo' => 'Orçamentos',
            'colunas' => ['Nº', 'Emissão', 'Validade', 'Cliente', 'Veículo', 'Situação', 'Total'],
            'moeda' => [6],
            'linhas' => $orc->map(fn ($o) => [
                $o->numeroFormatado(), $o->data_emissao->format('d/m/Y'), $o->validade->format('d/m/Y'),
                $o->cliente->nome, $o->veiculo->descricao(), $o->status->label(), $o->total(),
            ])->all(),
            'resumo' => [
                'Emitidos' => $orc->count(),
                'Aprovados' => $aprov->count(),
                'Taxa de aprovação (%)' => $decididos->count() ? round($aprov->count() / $decididos->count() * 100, 1) : 0,
                'Valor emitido' => $orc->sum(fn ($o) => $o->total()),
                'Valor aprovado' => $aprov->sum(fn ($o) => $o->total()),
            ],
            'inteiros' => ['Emitidos', 'Aprovados', 'Taxa de aprovação (%)'],
        ];
    }

    private function comissoes(Carbon $i, Carbon $f): array
    {
        $linhas = Funcionario::orderBy('nome')->get()->map(function ($func) use ($i, $f) {
            $os = OrdemServico::with('itens')
                ->where('mecanico_id', $func->id)
                ->whereIn('status', [StatusOS::Concluida->value, StatusOS::Entregue->value])
                ->whereBetween('data_conclusao', [$i->toDateString(), $f->toDateString()])
                ->get();
            $mo = $os->sum(fn ($o) => $o->totalServicos());

            return [$func->nome, $func->funcao, $os->count(), $mo, $func->comissao, round($mo * $func->comissao / 100, 2)];
        });

        return [
            'titulo' => 'Comissões da equipe',
            'colunas' => ['Funcionário', 'Função', 'OS concluídas', 'Mão de obra', 'Comissão (%)', 'Valor da comissão'],
            'moeda' => [3, 5],
            'linhas' => $linhas->all(),
            'resumo' => ['Total de comissões' => $linhas->sum(fn ($l) => $l[5])],
        ];
    }

    private function estoque(): array
    {
        $pecas = Peca::orderBy('descricao')->get();

        return [
            'titulo' => 'Posição de estoque',
            'colunas' => ['Código', 'Descrição', 'Marca', 'Local', 'Estoque', 'Mínimo', 'Custo', 'Venda', 'Valor (custo)'],
            'moeda' => [6, 7, 8],
            'linhas' => $pecas->map(fn ($p) => [
                $p->codigo ?? '', $p->descricao, $p->marca, $p->localizacao,
                $p->estoque, $p->estoque_minimo, $p->custo, $p->preco, round(max(0, $p->estoque) * $p->custo, 2),
            ])->all(),
            'resumo' => [
                'Itens cadastrados' => $pecas->count(),
                'Abaixo do mínimo' => $pecas->filter->estaBaixo()->count(),
                'Valor em estoque (custo)' => $pecas->sum(fn ($p) => max(0, $p->estoque) * $p->custo),
                'Valor em estoque (venda)' => $pecas->sum(fn ($p) => max(0, $p->estoque) * $p->preco),
            ],
            'inteiros' => ['Itens cadastrados', 'Abaixo do mínimo'],
        ];
    }

    private function xlsx(string $caminho, array $d): void
    {
        $cor = ltrim(app(PdfService::class)->cor(OficinaConfig::atual()->cor_documento)['fundo'], '#');
        $titulo = (new Style)->withFontBold(true)->withFontSize(14);
        $cab = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor(strtoupper($cor));
        $negrito = (new Style)->withFontBold(true);
        $linha = fn (array $vals, ?Style $s = null) => new Row(array_map(fn ($v) => Cell::fromValue($v, $s), $vals));

        $w = new Writer;
        $w->openToFile($caminho);
        $w->addRow($linha([$d['titulo']], $titulo));
        $w->addRow($linha([$d['periodo']]));
        $w->addRow($linha([]));
        $w->addRow($linha($d['colunas'], $cab));
        foreach ($d['linhas'] as $l) {
            $w->addRow($linha($l));
        }
        $w->addRow($linha([]));
        foreach ($d['resumo'] as $rotulo => $valor) {
            $w->addRow(new Row([Cell::fromValue($rotulo, $negrito), Cell::fromValue($valor)]));
        }
        $w->close();
    }
}
