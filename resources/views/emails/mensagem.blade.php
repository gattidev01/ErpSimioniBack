@php $cor = app(\App\Services\PdfService::class)->cor($oficina->cor_documento); @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>{{ $assunto }}</title></head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1d2330;">
    <table role="presentation" width="100%" style="max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e3e6eb;">
        <tr>
            <td style="background:{{ $cor['fundo'] }};color:{{ $cor['texto'] }};padding:18px 22px;">
                @if ($oficina->logo)
                    <img src="{{ $message->embedData(base64_decode(substr($oficina->logo, strlen('data:image/png;base64,'))), 'logo.png', 'image/png') }}" alt="" style="max-height:48px;max-width:160px;background:#fff;border-radius:6px;padding:4px;display:block;margin-bottom:8px;">
                @endif
                <strong style="font-size:18px;">{{ $oficina->nome }}</strong>
            </td>
        </tr>
        <tr>
            <td style="padding:22px;font-size:14px;line-height:1.55;white-space:pre-line;">{{ $texto }}</td>
        </tr>
        <tr>
            <td style="padding:14px 22px;border-top:1px solid #e3e6eb;font-size:12px;color:#6e7382;">
                {{ $oficina->nome }}@if ($oficina->telefone) · {{ $oficina->telefone }}@endif @if ($oficina->endereco)<br>{{ $oficina->endereco }}@endif
            </td>
        </tr>
    </table>
</body>
</html>
