<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * Caso de ejemplo del prototipo: Sankalpa, con blog, calendario, ReelsLoop, eventos y tienda,
 * cotización 1530. No corre con db:seed; se carga a mano:
 *   php artisan db:seed --class=SankalpaDemoSeeder
 */
class SankalpaDemoSeeder extends Seeder
{
    public const VALUES = [
        's1t' => 'Yoga y movimiento consciente',
        's1d' => 'Un espacio para explorar el yoga, el bienestar y nuevas formas de conectar con vos y con otros.',
        's2t' => 'Meditación',
        's3t' => 'Encuentros de bienestar',
        'b1t' => 'Pequeñas pausas para volver al presente',
        'b2t' => 'Encontrarnos: el valor de compartir la práctica',
        'igu' => '@sankalpa_terapias_holisticas',
    ];

    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        $client = Client::firstOrCreate(['name' => 'Sankalpa']);
        $project = Project::firstOrCreate(
            ['client_id' => $client->id, 'name' => 'Sitio web Sankalpa'],
            ['fx_rate' => '1530', 'status' => 'in_progress'],
        );
        $project->widgets()->sync(
            Widget::whereIn('key', ['blog', 'calendario', 'reels', 'eventos', 'tienda'])->pluck('id')
        );
        foreach (self::VALUES as $key => $value) {
            ProjectRequirement::updateOrCreate(
                ['project_id' => $project->id, 'template_key' => $key],
                ['value' => $value, 'done' => true, 'source' => ProjectRequirement::SOURCE_MANUAL],
            );
        }
    }
}
