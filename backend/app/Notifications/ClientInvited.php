<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invitación a un cliente existente: link para elegir contraseña (o entrar con Google). */
class ClientInvited extends Notification
{
    public function __construct(public Project $project, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $from = config('panel.sales_from');

        return (new MailMessage)
            ->when($from['address'], fn (MailMessage $m) => $m->from($from['address'], $from['name'])->replyTo($from['address'], $from['name']))
            ->subject('Tu panel de MisioWeb')
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Te sumamos al panel de {$this->project->client->name}. Ahí vas a ver el avance del sitio y completar lo que necesitamos.")
            ->action('Elegir mi contraseña', $this->url)
            ->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos. Si tu email es de Google, también podés entrar con "Ingresar con Google".')
            ->salutation('Equipo MisioWeb');
    }
}
