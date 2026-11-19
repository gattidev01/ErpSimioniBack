@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $data = fn ($d) => $d ? $d->format('d/m/Y') : '-';
    $qtd = fn ($q) => rtrim(rtrim(number_format((float) $q, 3, ',', '.'), '0'), ',');
    $ehOrcamento = $titulo === 'ORÇAMENTO';
    $servicos = $doc->itens->where('tipo', 'servico')->values();
    $pecasItens = $doc->itens->where('tipo', 'peca')->values();
    $grafite = '#1d2939';
    $n = 0;
    $meta = $ehOrcamento
        ? [['Emissão', $data($emissao)], ['Válido até', $data($validade)], ['Prazo de execução', $prazo ?: 'A combinar'], ['Forma de pagamento', $doc->forma_pagamento]]
        : [['Abertura', $data($emissao)], ['Previsão de entrega', $data($previsao)], ['Responsável técnico', $responsavel ?: '-'], ['Forma de pagamento', $doc->forma_pagamento]];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{{ $titulo }} {{ $numero }}</title>
<style>
    @page { margin: 0 0 16mm 0; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 7.9pt; color: #101828; margin: 0; }
    .pagina { padding: 0 12mm; }
    .muted { color: #667085; }
    /* cabeçalho */
    .cab { background: {{ $grafite }}; color: #fff; padding: 4mm 12mm 3.5mm; }
    .cab table { width: 100%; border-collapse: collapse; }
    .cab td { vertical-align: middle; }
    .logo-box { background: #fff; border-radius: 6px; width: 28mm; height: 21mm; text-align: center; }
    .logo-box img { max-width: 25mm; max-height: 18mm; margin-top: 1.5mm; }
    .of-nome { font-size: 15pt; font-weight: bold; }
    .of-linha { color: #d0d5dd; font-size: 7.8pt; margin-top: 1mm; }
    .etiqueta { display: inline-block; background: {{ $cor['fundo'] }}; color: {{ $cor['texto'] }}; font-weight: bold; font-size: 7.4pt; padding: 1mm 2.5mm; border-radius: 3px; }
    .numero { font-size: 20pt; font-weight: bold; margin-top: 1.5mm; }
    .faixa { height: 1.6mm; background: {{ $cor['fundo'] }}; }
    /* faixa de dados */
    .meta { width: 100%; border-collapse: collapse; margin-top: 4mm; background: #f4f5f7; border: 0.25mm solid #d0d5dd; }
    .meta td { padding: 2mm 3.5mm; border-left: 0.25mm solid #d0d5dd; width: 25%; }
    .meta td:first-child { border-left: none; }
    .meta .rot { font-size: 6.4pt; color: #667085; text-transform: uppercase; }
    .meta .val { font-size: 9.2pt; font-weight: bold; margin-top: 0.8mm; }
    /* fichas */
    .fichas { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 3.5mm; }
    .fichas > tbody > tr > td { width: 50%; vertical-align: top; }
    .ficha { border: 0.25mm solid #d0d5dd; }
    .ficha-cab { background: #f4f5f7; border-bottom: 0.25mm solid #d0d5dd; border-left: 1.2mm solid {{ $cor['fundo'] }}; padding: 1.6mm 3mm; font-weight: bold; font-size: 7.6pt; color: {{ $grafite }}; text-transform: uppercase; }
    .campos { width: 100%; border-collapse: collapse; margin: 1.5mm 0 1.5mm; }
    .campos td { padding: 0.55mm 3mm; vertical-align: top; }
    .campos .rot { color: #667085; width: 22mm; font-size: 7.6pt; }
    .campos .val { font-weight: bold; }
    .placa { border: 0.45mm solid {{ $grafite }}; border-radius: 1.2mm; width: 31mm; text-align: center; background: #fff; }
    .placa .br { background: #1f3f99; color: #fff; font-size: 4.6pt; font-weight: bold; padding: 0.5mm 0; }
    .placa .num { font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; font-size: 12pt; letter-spacing: 0.6mm; padding: 0.6mm 0 0.8mm; }
    /* textos */
    .blocos { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 3.5mm; }
    .blocos > tbody > tr > td { vertical-align: top; border: 0.25mm solid #d0d5dd; padding: 2mm 3.5mm; }
    .tit-acento { color: {{ $cor['fundo'] }}; font-weight: bold; font-size: 6.8pt; text-transform: uppercase; margin-bottom: 1mm; }
    .texto { white-space: pre-line; }
    /* itens */
    table.itens { width: 100%; border-collapse: collapse; margin-top: 3.5mm; }
    table.itens th.dir { text-align: right; }
    table.itens th.cen { text-align: center; }
    table.itens th { background: {{ $grafite }}; color: #fff; font-size: 7.4pt; padding: 1.8mm 2.2mm; text-align: left; border: 0.2mm solid {{ $grafite }}; }
    table.itens td { padding: 1.1mm 2.2mm; border: 0.2mm solid #d0d5dd; }
    table.itens tr.secao td { background: #f4f5f7; font-weight: bold; font-size: 7.2pt; color: {{ $grafite }}; }
    .dir { text-align: right; white-space: nowrap; }
    .cen { text-align: center; }
    .cinza { color: #667085; }
    /* totais */
    .rodape-doc { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 3.5mm; }
    .rodape-doc > tbody > tr > td { vertical-align: top; }
    .cond { border: 0.25mm solid #d0d5dd; padding: 2mm 3.5mm; }
    .cond div { margin-top: 0.8mm; }
    .tot-linha { width: 100%; border-collapse: collapse; }
    .tot-linha td { padding: 1.1mm 1mm; border-bottom: 0.2mm solid #d0d5dd; }
    .total { background: {{ $grafite }}; color: #fff; border-left: 1.6mm solid {{ $cor['fundo'] }}; padding: 2mm 3mm; margin-top: 2.5mm; }
    .total .rot { color: #d0d5dd; font-size: 7.4pt; font-weight: bold; }
    .total .val { font-size: 13pt; font-weight: bold; text-align: right; }
    /* aprovação */
    .aprov { border: 0.25mm solid #d0d5dd; margin-top: 3mm; page-break-inside: avoid; }
    .caixa-check { display: inline-block; width: 3mm; height: 3mm; border: 0.3mm solid {{ $grafite }}; vertical-align: middle; margin-right: 1.5mm; }
    .assin { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    .assin td { width: 33%; padding: 0 3mm; vertical-align: top; font-size: 7.2pt; color: #667085; text-align: center; }
    .assin .linha { border-top: 0.25mm solid #98a2b3; padding-top: 1mm; }
    footer { position: fixed; bottom: -11mm; left: 12mm; right: 12mm; border-top: 0.5mm solid {{ $cor['fundo'] }}; padding-top: 1.5mm; font-size: 6.8pt; color: #667085; }
</style>
</head>
<body>
<footer>
    <table style="width:100%; border-collapse:collapse;"><tr>
        <td>{{ collect([$oficina->nome, $oficina->cnpj ? 'CNPJ '.$oficina->cnpj : null, $oficina->telefone])->filter()->join('  ·  ') }}</td>
        <td style="text-align:right;">{{ $titulo }} Nº {{ $numero }}  ·  Gerado em {{ now()->format('d/m/Y H:i') }}</td>
    </tr></table>
</footer>

<div class="cab">
    <table>
        <tr>
            @if ($oficina->logo)
                <td style="width: 32mm;"><div class="logo-box"><img src="{{ $oficina->logo }}" alt=""></div></td>
            @endif
            <td>
                <div class="of-nome">{{ $oficina->nome }}</div>
                @if ($oficina->endereco)<div class="of-linha">{{ $oficina->endereco }}</div>@endif
                <div class="of-linha">{{ collect([$oficina->telefone, $oficina->email])->filter()->join('   ·   ') }}</div>
                @if ($oficina->cnpj)<div class="of-linha">CNPJ {{ $oficina->cnpj }}</div>@endif
            </td>
            <td style="text-align: right; width: 60mm;">
                <span class="etiqueta">{{ $titulo }}</span>
                <div class="numero">Nº {{ $numero }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="faixa"></div>

<div class="pagina">
    <table class="meta">
        <tr>
            @foreach ($meta as [$rot, $val])
                <td><div class="rot">{{ $rot }}</div><div class="val">{{ $val }}</div></td>
            @endforeach
        </tr>
    </table>

    <table class="fichas">
        <tr>
            <td style="padding-right: 2.5mm;">
                <div class="ficha">
                    <div class="ficha-cab">Dados do cliente</div>
                    <table class="campos">
                        <tr><td class="rot">Nome</td><td class="val">{{ $cliente->nome }}</td></tr>
                        <tr><td class="rot">{{ $cliente->tipo === 'PJ' ? 'CNPJ' : 'CPF' }}</td><td class="val">{{ $cliente->documento ?: '-' }}</td></tr>
                        <tr><td class="rot">Telefone</td><td class="val">{{ $cliente->telefone ?: '-' }}</td></tr>
                        <tr><td class="rot">E-mail</td><td class="val">{{ $cliente->email ?: '-' }}</td></tr>
                        <tr><td class="rot">Endereço</td><td class="val">{{ $cliente->endereco ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
            <td style="padding-left: 2.5mm;">
                <div class="ficha">
                    <div class="ficha-cab">Dados do veículo</div>
                    <table class="campos">
                        <tr>
                            <td class="rot">Marca/modelo</td><td class="val">{{ trim($veiculo->marca.' '.$veiculo->modelo) }}</td>
                            <td rowspan="3" style="width: 33mm; padding-top: 1mm;">
                                <div class="placa"><div class="br">BRASIL</div><div class="num">{{ $veiculo->placa }}</div></div>
                            </td>
                        </tr>
                        <tr><td class="rot">Ano</td><td class="val">{{ $veiculo->ano ?: '-' }}</td></tr>
                        <tr><td class="rot">Cor</td><td class="val">{{ $veiculo->cor ?: '-' }}</td></tr>
                        <tr><td class="rot">Quilometragem</td><td class="val" colspan="2">{{ $doc->km_entrada ? number_format($doc->km_entrada, 0, ',', '.').' km' : '-' }}</td></tr>
                        <tr><td class="rot">Chassi</td><td class="val" colspan="2">{{ $veiculo->chassi ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    @php
        $blocos = collect([
            ['Solicitação do cliente', $solicitacao],
            [$ehOrcamento ? 'Diagnóstico técnico' : 'Diagnóstico / serviço executado', $doc->diagnostico],
        ])->filter(fn ($b) => filled($b[1]))->values();
    @endphp
    @if ($blocos->isNotEmpty())
        <table class="blocos">
            <tr>
                @foreach ($blocos as $i => [$rot, $txt])
                    <td style="width: {{ $blocos->count() === 2 ? '50%' : '100%' }}; {{ $i === 1 ? 'border-left: none;' : '' }}">
                        <div class="tit-acento">{{ $rot }}</div>
                        <div class="texto">{{ $txt }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="itens">
        <thead>
            <tr>
                <th class="cen" style="width: 9mm;">Item</th>
                <th style="width: 20mm;">Código</th>
                <th>Descrição</th>
                <th class="dir" style="width: 12mm;">Qtd</th>
                <th class="cen" style="width: 9mm;">Un</th>
                <th class="dir" style="width: 24mm;">Valor unit.</th>
                <th class="dir" style="width: 25mm;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['MÃO DE OBRA / SERVIÇOS' => [$servicos, $doc->totalServicos()], 'PEÇAS E MATERIAIS' => [$pecasItens, $doc->totalPecas()]] as $rotulo => [$lista, $subtotal])
                @if ($lista->isNotEmpty())
                    <tr class="secao">
                        <td colspan="5">{{ $rotulo }}</td>
                        <td colspan="2" class="dir cinza">Subtotal {{ $brl($subtotal) }}</td>
                    </tr>
                    @foreach ($lista as $item)
                        @php $n++; $peca = $item->ref_id ? ($pecas[$item->ref_id] ?? null) : null; @endphp
                        <tr>
                            <td class="cen cinza">{{ str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="cinza">{{ $peca?->codigo ?: '-' }}</td>
                            <td>{{ $item->descricao }}</td>
                            <td class="dir">{{ $qtd($item->quantidade) }}</td>
                            <td class="cen cinza">{{ $peca?->unidade ?: ($item->tipo === 'servico' ? 'SV' : 'UN') }}</td>
                            <td class="dir">{{ $brl($item->valor_unitario) }}</td>
                            <td class="dir"><strong>{{ $brl($item->total()) }}</strong></td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>

    <table class="rodape-doc">
        <tr>
            <td style="padding-right: 5mm;">
                <div class="cond">
                    <div class="tit-acento">Condições comerciais</div>
                    <div><span class="muted">Forma de pagamento:</span> {{ $doc->forma_pagamento }}</div>
                    @if ($ehOrcamento)
                        <div><span class="muted">Validade da proposta:</span> {{ $data($validade) }}</div>
                        <div><span class="muted">Prazo de execução:</span> {{ $prazo ?: 'A combinar' }}</div>
                    @endif
                    <div><span class="muted">Garantia dos serviços:</span> {{ $oficina->garantia_dias > 0 ? $oficina->garantia_dias.' dias (Código de Defesa do Consumidor)' : '-' }}</div>
                    @if ($doc->observacoes)
                        <div class="tit-acento" style="margin-top: 2mm;">Observações</div>
                        <div class="texto">{{ $doc->observacoes }}</div>
                    @endif
                </div>
            </td>
            <td style="width: 72mm;">
                <table class="tot-linha">
                    <tr><td class="muted">Mão de obra</td><td class="dir"><strong>{{ $brl($doc->totalServicos()) }}</strong></td></tr>
                    <tr><td class="muted">Peças e materiais</td><td class="dir"><strong>{{ $brl($doc->totalPecas()) }}</strong></td></tr>
                    @if ($doc->desconto > 0)
                        <tr><td class="muted">Desconto</td><td class="dir" style="color:#b42318;"><strong>- {{ $brl($doc->desconto) }}</strong></td></tr>
                    @endif
                </table>
                <div class="total">
                    <div class="rot">{{ $ehOrcamento ? 'TOTAL DO ORÇAMENTO' : 'TOTAL DA OS' }}</div>
                    <div class="val">{{ $brl($doc->total()) }}</div>
                </div>
            </td>
        </tr>
    </table>

    @if ($condicoes)
        <div style="margin-top: 3.5mm;">
            <div style="font-weight: bold; font-size: 6.8pt; color: #667085;">TERMOS E CONDIÇÕES</div>
            <div class="texto muted" style="font-size: 7.2pt;">{{ $condicoes }}</div>
        </div>
    @endif

    <div class="aprov">
        <div class="ficha-cab">{{ $ehOrcamento ? 'Aprovação do cliente' : 'Autorização e entrega' }}</div>
        <div style="padding: 2.5mm 3.5mm 3mm;">
            <div>
                {{ $ehOrcamento
                    ? 'Autorizo a execução dos serviços e a aplicação das peças descritas neste orçamento, nas condições acima.'
                    : 'Declaro que autorizei os serviços descritos nesta ordem e que recebi o veículo nas condições acima.' }}
            </div>
            @if ($ehOrcamento)
                <div style="margin-top: 2mm;">
                    <span class="caixa-check"></span>Aprovado &nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="caixa-check"></span>Aprovado parcialmente &nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="caixa-check"></span>Não aprovado
                </div>
            @endif
            <table class="assin">
                <tr>
                    <td><div class="linha">Cliente: {{ $aceite }}</div></td>
                    <td><div class="linha">Responsável: {{ $oficina->nome }}</div></td>
                    <td style="text-align: left; padding-top: 1mm;">Data: ____/____/________</td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
