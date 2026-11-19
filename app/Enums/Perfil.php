<?php

namespace App\Enums;

enum Perfil: string
{
    case Admin = 'admin';
    case Atendente = 'atendente';
    case Mecanico = 'mecanico';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Atendente => 'Atendente',
            self::Mecanico => 'Mecânico',
        };
    }
}
