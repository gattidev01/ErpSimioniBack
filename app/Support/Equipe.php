<?php

namespace App\Support;

use App\Models\OficinaConfig;
use App\Models\User;
use App\Notifications\AlertaEquipe;
use Illuminate\Support\Facades\Notification;

class Equipe
{
    /** Envia o alerta para admins/atendentes e para o e-mail extra da configuração */
    public static function alertar(AlertaEquipe $alerta): void
    {
        Notification::send(User::query()->equipeEscritorio()->get(), $alerta);

        $extra = OficinaConfig::atual()->email_alertas;
        if ($extra && filter_var($extra, FILTER_VALIDATE_EMAIL)) {
            Notification::route('mail', $extra)->notify($alerta);
        }
    }
}
