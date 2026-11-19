@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $fmt = function ($v, $i) use ($r, $brl) {
        if (in_array($i, $r['moeda'] ?? [], true)) return $brl($v);
        if (is_float($v)) return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
        return $v;
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{{ $r['titulo'] }}</title>
<style>
    @page { margin: 12mm 12mm 16mm; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 8.5pt; color: #1d2330; }
    .cab { width: 100%; border-bottom: 2.5px solid {{ $cor['fundo'] }}; padding-bottom: 6px; margin-bottom: 10px; }
    .cab td { vertical-align: middle; }
    .logo { max-width: 110px; max-height: 50px; }
    h1 { font-size: 14pt; margin: 0; }
    .muted { color: #6e7382; }
    table.dados { width: 100%; border-collapse: collapse; }
    table.dados th { background: {{ $cor['fundo'] }}; color: {{ $cor['texto'] }}; text-align: left; padding: 4px 5px; }
    table.dados td { padding: 3px 5px; border-bottom: 1px solid #e3e6eb; }
    table.dados tr:nth-child(even) td { background: #f7f8fa; }
    .dir { text-align: right; white-space: nowrap; }
    .resumo { margin-top: 12px; border-collapse: collapse; }
    .resumo td { padding: 3px 8px; }
    .resumo td:first-child { font-weight: bold; color: #6e7382; }
    footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #6e7382; }
</style>
</head>
<body>
<footer>{{ $oficina->nome }} · {{ $r['titulo'] }} · gerado em {{ now()->format('d/m/Y H:i') }}</footer>
<table class="cab">
    <tr>
        @if ($oficina->logo)<td style="width: 120px;"><img class="logo" src="{{ $oficina->logo }}" alt=""></td>@endif
        <td><h1>{{ $r['titulo'] }}</h1><div class="muted">{{ $oficina->nome }} · {{ $r['periodo'] }}</div></td>
    </tr>
</table>

@if (empty($r['linhas']))
    <p class="muted">Nenhum registro no período.</p>
@else
    <table class="dados">
        <thead>
            <tr>@foreach ($r['colunas'] as $i => $c)<th class="{{ in_array($i, $r['moeda'] ?? [], true) ? 'dir' : '' }}">{{ $c }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($r['linhas'] as $linha)
                <tr>@foreach ($linha as $i => $v)<td class="{{ is_numeric($v) ? 'dir' : '' }}">{{ $fmt($v, $i) }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="resumo">
    @foreach ($r['resumo'] as $rotulo => $valor)
        <tr><td>{{ $rotulo }}</td><td class="dir">{{ in_array($rotulo, $r['inteiros'] ?? [], true) ? $valor : $brl($valor) }}</td></tr>
    @endforeach
</table>
</body>
</html>
