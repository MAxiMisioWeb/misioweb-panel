<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al cliente: su pedido fue aprobado y el panel está listo. */
class ProjectApproved extends Notification
{
    public function __construct(public Project $project) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $from = config('panel.sales_from');

        return (new MailMessage)
            ->when($from['address'], fn (MailMessage $m) => $m->from($from['address'], $from['name'])->replyTo($from['address'], $from['name']))
            ->subject('Tu panel de MisioWeb está listo')
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Aprobamos el pedido de {$this->project->client->name}. Ya tenés tu panel para ver el avance del sitio y completar lo que necesitamos.")
            ->action('Entrar a mi panel', route('client.home'))
            ->line('Cualquier duda, respondé este mail.')
            ->salutation('Equipo MisioWeb');
    }
}
