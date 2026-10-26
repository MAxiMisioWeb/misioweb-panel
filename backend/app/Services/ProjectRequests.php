<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Widget;
use App\Notifications\NewProjectRequest;
use App\Notifications\ProjectApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

/**
 * Pedidos de prospectos: el prospecto elige widgets y manda el pedido; la agencia lo aprueba
 * (pasa a cliente con su proyecto) o lo rechaza.
 */
class ProjectRequests
{
    /**
     * @param  list<string>  $widgetKeys
     */
    public function submit(User $user, string $businessName, array $widgetKeys, ?string $note = null): Project
    {
        if ($user->role !== User::ROLE_PROSPECT) {
            throw new InvalidArgumentException('Solo un prospecto puede mandar un pedido.');
        }
        if ($this->pending($user)) {
            throw new InvalidArgumentException('Ya hay un pedido esperando revisión.');
        }

        $project = DB::transaction(function () use ($user, $businessName, $widgetKeys, $note) {
            $client = $user->client ?? Client::create(['name' => $businessName]);
            $client->update(['name' => $businessName]);
            $user->client()->associate($client)->save();

            $project = Project::create([
                'client_id' => $client->id,
                'name' => "Sitio web {$businessName}",
                'fx_rate' => config('panel.fx_rate'),
                'status' => 'requested',
                'request_note' => $note,
                'requested_at' => now(),
            ]);
            $project->widgets()->sync(Widget::active()->whereIn('key', $widgetKeys)->pluck('id'));

            return $project;
        });

        $this->notify(fn () => Notification::send(User::where('role', User::ROLE_AGENCY)->get(), new NewProjectRequest($project)));

        return $project;
    }

    public function pending(User $user): ?Project
    {
        return $user->client_id
            ? Project::where('client_id', $user->client_id)->where('status', 'requested')->latest('requested_at')->first()
            : null;
    }

    /**
     * @return bool Si se pudo mandar el mail de aviso al cliente.
     */
    public function approve(Project $project, User $agency): bool
    {
        $users = DB::transaction(function () use ($project, $agency) {
            $this->review($project, $agency, 'in_progress');
            $users = $project->client->users()->where('role', User::ROLE_PROSPECT)->get();
            foreach ($users as $user) {
                $user->role = User::ROLE_CLIENT;
                $user->save();
            }

            return $users;
        });

        return $this->notify(fn () => Notification::send($users, new ProjectApproved($project)));
    }

    public function reject(Project $project, User $agency): void
    {
        $this->review($project, $agency, 'rejected');
    }

    /**
     * Un mail que no sale (SMTP caído o sin configurar) no deshace el pedido ni la aprobación:
     * queda en el log y se avisa en pantalla.
     */
    private function notify(callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function review(Project $project, User $agency, string $status): void
    {
        if ($project->status !== 'requested') {
            throw new InvalidArgumentException('El pedido ya fue revisado.');
        }
        $project->forceFill([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $agency->id,
        ])->save();
    }
}
