<?php

namespace App\Enums;

enum StatusOrcamento: string
{
    case Rascunho = 'rascunho';
    case Enviado = 'enviado';
    case Aprovado = 'aprovado';
    case Recusado = 'recusado';
    case Expirado = 'expirado';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Enviado => 'Enviado',
            self::Aprovado => 'Aprovado',
            self::Recusado => 'Recusado',
            self::Expirado => 'Expirado',
        };
    }

    /** Aguardando resposta do cliente */
    public function emAberto(): bool
    {
        return in_array($this, [self::Rascunho, self::Enviado], true);
    }

    /** Itens e valores não podem mais ser editados */
    public function travado(): bool
    {
        return in_array($this, [self::Aprovado, self::Recusado], true);
    }

    public function podeIrPara(self $novo): bool
    {
        $permitidos = match ($this) {
            self::Rascunho => [self::Enviado, self::Aprovado, self::Recusado],
            self::Enviado, self::Expirado => [self::Aprovado, self::Recusado, self::Rascunho],
            self::Aprovado, self::Recusado => [self::Rascunho],
        };

        return in_array($novo, $permitidos, true);
    }
}
