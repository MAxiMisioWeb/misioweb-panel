<?php

use App\Models\Project;
use App\Services\ProposalCalculator;
use Illuminate\Support\Facades\Artisan;

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
