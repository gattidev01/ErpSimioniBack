<?php

namespace App\Notifications;

use App\Enums\Perfil;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerta operacional (estoque baixo, contas vencidas, orçamentos expirados...) */
class AlertaEquipe extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $linhas */
    public function __construct(
        public string $tipo,
        public string $titulo,
        public string $mensagem,
        public array $linhas = [],
        public ?string $link = null,
    ) {}

    public function via(object $notifiable): array
    {
        // sininho no sistema para a equipe; e-mail só para administradores e destino extra configurado
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        return $notifiable->perfil === Perfil::Admin ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->titulo)->line($this->mensagem);
        foreach (array_slice($this->linhas, 0, 30) as $l) {
            $mail->line('• '.$l);
        }
        if (count($this->linhas) > 30) {
            $mail->line('… e mais '.(count($this->linhas) - 30).'.');
        }
        if ($this->link) {
            $mail->action('Abrir no sistema', rtrim(config('oficina.frontend_url'), '/').$this->link);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'linhas' => array_slice($this->linhas, 0, 50),
            'link' => $this->link,
        ];
    }
}
