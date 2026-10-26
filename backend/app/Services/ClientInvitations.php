<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ClientInvited;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;
use Throwable;

/**
 * Invita a un cliente que ya trabaja con MisioWeb a su proyecto, sin pasar por el pedido:
 * crea (o vincula) su cuenta como client y le manda un enlace para elegir contraseña.
 */
class ClientInvitations
{
    /**
     * @return array{user: User, url: string, mailed: bool}
     */
    public function invite(Project $project, string $email, string $name): array
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();

        if ($user?->isAgency()) {
            throw new InvalidArgumentException('Ese email es de una cuenta de la agencia.');
        }
        if ($user && $user->client_id && $user->client_id !== $project->client_id) {
            throw new InvalidArgumentException('Ese email ya tiene cuenta con otro cliente.');
        }

        $user ??= new User(['email' => $email, 'name' => $name]);
        $user->role = User::ROLE_CLIENT;
        $user->client_id = $project->client_id;
        $user->email_verified_at ??= now(); // el enlace llega a ese email: con eso alcanza
        $user->save();

        $url = route('password.reset', ['token' => Password::createToken($user), 'email' => $email]);

        try {
            $user->notify(new ClientInvited($project, $url));
            $mailed = true;
        } catch (Throwable $e) {
            report($e);
            $mailed = false;
        }

        return ['user' => $user, 'url' => $url, 'mailed' => $mailed];
    }
}
