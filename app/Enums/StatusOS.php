<?php

namespace App\Enums;

enum StatusOS: string
{
    case Orcamento = 'orcamento';
    case Aprovada = 'aprovada';
    case EmAndamento = 'em_andamento';
    case AguardandoPeca = 'aguardando_peca';
    case Concluida = 'concluida';
    case Entregue = 'entregue';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Orcamento => 'Orçamento',
            self::Aprovada => 'Aprovada',
            self::EmAndamento => 'Em andamento',
            self::AguardandoPeca => 'Aguardando peça',
            self::Concluida => 'Concluída',
            self::Entregue => 'Entregue',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Status em que as peças da OS já saíram do estoque */
    public function baixaEstoque(): bool
    {
        return in_array($this, [self::Concluida, self::Entregue], true);
    }

    public function emAberto(): bool
    {
        return in_array($this, [self::Orcamento, self::Aprovada, self::EmAndamento, self::AguardandoPeca], true);
    }

    /** Transições permitidas (mesmo fluxo da tela) */
    public function podeIrPara(self $novo): bool
    {
        if ($novo === self::Cancelada) {
            return ! in_array($this, [self::Cancelada, self::Entregue], true);
        }

        $permitidos = match ($this) {
            self::Orcamento => [self::Aprovada],
            self::Aprovada => [self::EmAndamento],
            self::EmAndamento => [self::Concluida, self::AguardandoPeca],
            self::AguardandoPeca => [self::EmAndamento],
            self::Concluida => [self::Entregue, self::EmAndamento],
            self::Entregue => [self::EmAndamento],
            self::Cancelada => [self::Orcamento],
        };

        return in_array($novo, $permitidos, true);
    }

    /** O que o mecânico pode fazer sozinho */
    public static function permitidosMecanico(): array
    {
        return [self::EmAndamento, self::AguardandoPeca, self::Concluida];
    }
}
