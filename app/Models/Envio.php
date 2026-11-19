<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de cada e-mail/WhatsApp enviado por um job */
class Envio extends Model
{
    use HasUuids;

    public const PENDENTE = 'pendente';
    public const ENVIANDO = 'enviando';
    public const ENVIADO = 'enviado';
    public const FALHOU = 'falhou';

    protected $table = 'envios';

    protected $fillable = [
        'documento_tipo', 'documento_id', 'canal', 'destino', 'mensagem',
        'status', 'tentativas', 'erro', 'enviado_em', 'user_id',
    ];

    protected function casts(): array
    {
        return ['enviado_em' => 'datetime', 'tentativas' => 'integer'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Orçamento, OS ou agendamento ao qual o envio se refere */
    public function documento(): Orcamento|OrdemServico|Agendamento|null
    {
        return match ($this->documento_tipo) {
            'orcamento' => Orcamento::with(['itens', 'cliente', 'veiculo'])->find($this->documento_id),
            'ordem' => OrdemServico::with(['itens', 'cliente', 'veiculo', 'mecanico'])->find($this->documento_id),
            'agendamento' => Agendamento::with(['cliente', 'veiculo'])->find($this->documento_id),
            default => null,
        };
    }
}
