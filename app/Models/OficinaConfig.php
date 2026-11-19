<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Configuração única da oficina (linha id = 1) */
class OficinaConfig extends Model
{
    protected $table = 'oficina_config';

    protected $fillable = [
        'nome', 'cnpj', 'telefone', 'email', 'endereco', 'garantia_dias', 'logo_path',
        'cor_documento', 'validade_orcamento_dias', 'condicoes_orcamento', 'email_alertas',
    ];

    protected $hidden = ['id', 'logo_path', 'created_at', 'updated_at'];

    protected $appends = ['logo'];

    protected function casts(): array
    {
        return ['garantia_dias' => 'integer', 'validade_orcamento_dias' => 'integer'];
    }

    public static function atual(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'nome' => config('app.name'),
            'cor_documento' => '#d92d20',
            'validade_orcamento_dias' => 7,
            'garantia_dias' => 90,
        ]);
    }

    /** Logo como data URL (já é um PNG pequeno, regravado no upload) */
    public function getLogoAttribute(): ?string
    {
        $disco = Storage::disk('local');
        if (! $this->logo_path || ! $disco->exists($this->logo_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($disco->get($this->logo_path));
    }
}
