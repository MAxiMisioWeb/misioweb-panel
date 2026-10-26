<?php

namespace App\Notifications;

use App\Models\Project;
use App\Services\ProposalCalculator;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso a la agencia: llegó un pedido nuevo. */
class NewProjectRequest extends Notification
{
    public function __construct(public Project $project) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $proposal = app(ProposalCalculator::class)->forProject($this->project);
        $widgets = $this->project->widgets->pluck('name')->join(', ') ?: 'solo el sitio base';

        return (new MailMessage)
            ->subject("Nuevo pedido: {$this->project->client->name}")
            ->greeting('Nuevo pedido en el panel')
            ->line("{$this->project->client->name} eligió: {$widgets}.")
            ->line('Inversión estimada: '.Money::usd($proposal->implUsd).' de implementación y '.Money::usd($proposal->monthUsd).' por mes.')
            ->when($this->project->request_note, fn ($m) => $m->line('Comentario: '.$this->project->request_note))
            ->action('Revisar pedido', route('agency.home'));
    }
}
