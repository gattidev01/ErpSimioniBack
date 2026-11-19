<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OficinaConfig;
use App\Services\WhatsAppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConfiguracaoController extends Controller
{
    private const LOGO = 'oficina/logo.png';
    private const LADO_MAX = 600;

    public function show(WhatsAppClient $wa): JsonResponse
    {
        return response()->json([
            ...OficinaConfig::atual()->toArray(),
            // o front usa para habilitar/ocultar o botão de WhatsApp (sem expor credenciais)
            'whatsapp_configurado' => $wa->configurado(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'endereco' => ['nullable', 'string', 'max:200'],
            'garantia_dias' => ['required', 'integer', 'min:0', 'max:3650'],
            'cor_documento' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'validade_orcamento_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'condicoes_orcamento' => ['nullable', 'string', 'max:2000'],
            'email_alertas' => ['nullable', 'email', 'max:120'],
        ], ['cor_documento.regex' => 'Cor inválida. Use o formato #RRGGBB.']);

        $config = OficinaConfig::atual();
        $config->update(array_map(fn ($v) => $v ?? '', $d));

        return response()->json($config);
    }

    /**
     * Recebe o logo, redimensiona e regrava como PNG com GD.
     * Regravar descarta qualquer conteúdo que não seja pixel (metadados, payloads embutidos).
     */
    public function enviarLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'max:5120', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp'],
        ], ['logo.mimes' => 'Envie PNG, JPG ou WEBP.', 'logo.max' => 'Imagem muito grande (máximo 5 MB).']);

        $origem = @imagecreatefromstring(file_get_contents($request->file('logo')->getRealPath()));
        if (! $origem) {
            throw ValidationException::withMessages(['logo' => 'Não foi possível ler a imagem.']);
        }

        $w = imagesx($origem);
        $h = imagesy($origem);
        $escala = min(1, self::LADO_MAX / max($w, $h));
        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));

        $destino = imagecreatetruecolor($nw, $nh);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $origem, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($destino, null, 9);
        $png = ob_get_clean();
        imagedestroy($origem);
        imagedestroy($destino);

        Storage::disk('local')->put(self::LOGO, $png);
        $config = OficinaConfig::atual();
        $config->update(['logo_path' => self::LOGO]);

        return response()->json($config->fresh());
    }

    public function removerLogo(): JsonResponse
    {
        Storage::disk('local')->delete(self::LOGO);
        $config = OficinaConfig::atual();
        $config->update(['logo_path' => null]);

        return response()->json($config->fresh());
    }
}
