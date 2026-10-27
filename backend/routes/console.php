<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ProposalCalculator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;

Artisan::command('panel:propuesta {project? : ID del proyecto (por defecto, el primero)}', function (ProposalCalculator $calculator) {
    $project = $this->argument('project')
        ? Project::find($this->argument('project'))
        : Project::orderBy('id')->first();

    if (! $project) {
        $this->error('No hay proyectos. Para cargar el ejemplo: php artisan db:seed --class=SankalpaDemoSeeder');

        return 1;
    }

    $progress = $project->progress();
    $this->info("Proyecto #{$project->id}: {$project->name} · avance {$progress['percent']}% ({$progress['done']} de {$progress['total']})");
    $this->newLine();
    foreach (explode("\n", $calculator->forProject($project)->text($project->client->name)) as $line) {
        $this->output->writeln($line);
    }

    return 0;
})->purpose('Muestra la propuesta calculada de un proyecto');

Artisan::command('panel:agencia {email : Email de la persona de la agencia} {--name= : Nombre (por defecto, el email)}', function () {
    $email = strtolower(trim($this->argument('email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error("Email inválido: {$email}");

        return 1;
    }

    $user = User::firstOrNew(['email' => $email]);
    $user->name = $this->option('name') ?: ($user->name ?? $email);
    $user->role = User::ROLE_AGENCY;
    $user->email_verified_at ??= now();
    $user->save();

    $url = route('password.reset', ['token' => Password::createToken($user), 'email' => $email]);
    $this->info("{$email} es cuenta de agencia.");
    $this->line('Puede ingresar con Google (si es una cuenta de Google) o elegir contraseña en este enlace, que vence en '.config('auth.passwords.users.expire').' minutos:');
    $this->line($url);

    return 0;
})->purpose('Crea una cuenta de agencia (o convierte una existente) y muestra un enlace para elegir contraseña');
