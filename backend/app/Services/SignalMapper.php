<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRequirement;

/**
 * Aplica las señales del plugin de WordPress a los requisitos del proyecto (docs/spec.md).
 *
 * - ok = true marca los requisitos como hechos (source = plugin).
 * - ok = false solo desmarca lo que había marcado el plugin; nunca lo que se completó a mano.
 * - Solo toca requisitos de secciones que aplican al proyecto (widgets elegidos).
 * - Señales desconocidas se ignoran. El detalle ("3 productos") no se copia al requisito:
 *   queda guardado en la tabla signals.
 */
class SignalMapper
{
    /** Señal del plugin => requisitos que marca. */
    public const MAP = [
        'blog_entradas' => ['b1t', 'b1x', 'b1i', 'b2t', 'b2x', 'b2i'],
        'paginas_legales' => ['mkp'],
        'tienda_productos' => ['wcp'],
        'tienda_pasarela' => ['wcm', 'wcc'],
        'reels_plugin' => ['igp'],
    ];

    /**
     * @param  array<string, array{ok: bool, detail?: string}>  $signals
     * @return list<string> Claves de requisitos que cambiaron.
     */
    public function apply(Project $project, array $signals): array
    {
        $applicable = $project->requirementTemplates()->pluck('key')->flip();
        $changed = [];

        foreach ($signals as $signal => $data) {
            foreach (self::MAP[$signal] ?? [] as $key) {
                if (! isset($applicable[$key])) {
                    continue;
                }
                $req = ProjectRequirement::firstOrNew(['project_id' => $project->id, 'template_key' => $key]);
                $ok = (bool) ($data['ok'] ?? false);

                if ($ok && ! $req->done) {
                    $req->fill(['done' => true, 'source' => ProjectRequirement::SOURCE_PLUGIN]);
                } elseif (! $ok && $req->done && $req->source === ProjectRequirement::SOURCE_PLUGIN) {
                    $req->done = false;
                } else {
                    continue;
                }
                $req->save();
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
