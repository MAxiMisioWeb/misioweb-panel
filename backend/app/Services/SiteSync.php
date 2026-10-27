<?php

namespace App\Services;

use App\Models\Signal;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Sincronización del plugin de WordPress (POST /api/v1/sync): guarda las señales, marca los
 * requisitos con SignalMapper y devuelve el avance para el escritorio de WordPress.
 */
class SiteSync
{
    /** Días que se guardan las señales de cada sitio. */
    public const KEEP_DAYS = 90;

    public function __construct(private SignalMapper $mapper, private Checklist $checklist) {}

    /**
     * @param  array<string, array{ok: bool, detail?: string}>  $signals
     * @return array{progress: int, message: string, items: list<array{label: string, status: string}>}
     */
    public function sync(Site $site, array $signals): array
    {
        $project = $site->project;

        DB::transaction(function () use ($site, $signals, $project) {
            $now = now();
            Signal::insert(array_map(fn ($key, $s) => [
                'site_id' => $site->id,
                'key' => $key,
                'ok' => (bool) $s['ok'],
                'detail' => mb_substr((string) ($s['detail'] ?? ''), 0, 255),
                'created_at' => $now,
            ], array_keys($signals), $signals));
            Signal::where('site_id', $site->id)->where('created_at', '<', $now->copy()->subDays(self::KEEP_DAYS))->delete();

            $this->mapper->apply($project, $signals);
            $site->forceFill(['last_sync_at' => $now])->save();
        });

        $groups = $this->checklist->groups($project);
        $totals = Checklist::totals($groups);
        $left = $totals['total'] - $totals['done'];

        return [
            'progress' => $totals['percent'],
            'message' => $left
                ? "Faltan {$left} puntos de información. Completalos en ".route('client.home').'.'
                : 'Tenemos toda la información. ¡Gracias!',
            'items' => array_map(fn ($g) => [
                'label' => "{$g['name']} ({$g['done']} de {$g['total']})",
                'status' => $g['done'] === $g['total'] ? 'done' : 'pending',
            ], $groups),
        ];
    }
}
