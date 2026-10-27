<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectSection;
use App\Models\RequirementTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Requisitos de un proyecto agrupados por sección, como los muestra el prototipo:
 * qué hay cargado, qué falta y en qué estado está el trabajo de la agencia.
 */
class Checklist
{
    /**
     * @return list<array{key: string, name: string, description: string, done: int, total: int, percent: int,
     *     work_status: string, fields: list<array<string, mixed>>, missing: list<string>}>
     */
    public function groups(Project $project): array
    {
        $values = $project->requirements()->get()->keyBy('template_key');
        $work = ProjectSection::where('project_id', $project->id)->pluck('work_status', 'group');

        return $project->requirementGroups()->map(function ($group) use ($project, $values, $work) {
            $fields = $group->templates->map(function (RequirementTemplate $t) use ($project, $values) {
                $req = $values->get($t->key);

                $field = [
                    'key' => $t->key,
                    'label' => $t->label,
                    'subgroup' => $t->subgroup,
                    'type' => $t->type,
                    'hint' => $t->hint,
                    'value' => $req?->value,
                    'done' => (bool) $req?->done,
                    'source' => $req?->source,
                    'aspect' => $t->aspect,
                    'size' => RequirementTemplate::ASPECT_SIZES[$t->aspect] ?? null,
                    'image_url' => null,
                    'original_url' => null,
                    'aspect_ok' => true,
                ];
                if ($t->type === 'image' && self::isStoredImage($req?->value)) {
                    $url = route('requirements.image', [$project, $t->key]);
                    $field['image_url'] = $url.'?v='.$req->updated_at?->timestamp;
                    $field['original_url'] = self::originalPath($req->value) ? $url.'?original=1&v='.$req->updated_at?->timestamp : null;
                    $field['aspect_ok'] = self::matchesAspect($req->value, $t->aspect);
                }

                return $field;
            })->all();

            $done = count(array_filter($fields, fn ($f) => $f['done']));
            $total = count($fields);

            return [
                'key' => $group->key,
                'name' => $group->name,
                'description' => $group->description,
                'done' => $done,
                'total' => $total,
                'percent' => $total ? (int) round($done / $total * 100) : 100,
                'work_status' => $work->get($group->key, 'todo'),
                'fields' => $fields,
                'missing' => array_values(array_map(
                    fn ($f) => ($f['subgroup'] ? $f['subgroup'].' · ' : '').$f['label'],
                    array_filter($fields, fn ($f) => ! $f['done'])
                )),
            ];
        })->all();
    }

    /**
     * Resumen para actualizar la pantalla después de guardar (chips, puntos, avance y faltantes).
     *
     * @return array{groups: list<array<string, mixed>>, totals: array<string, int>, done: list<string>}
     */
    public function summary(Project $project): array
    {
        $groups = $this->groups($project);

        return [
            'groups' => array_map(fn ($g) => ['key' => $g['key'], 'done' => $g['done'], 'total' => $g['total'], 'missing' => $g['missing']], $groups),
            'totals' => self::totals($groups),
            'done' => array_values(array_map(fn ($f) => $f['key'], array_filter(array_merge(...array_column($groups, 'fields')), fn ($f) => $f['done']))),
        ];
    }

    /** @param list<array<string, mixed>> $groups */
    public static function totals(array $groups): array
    {
        $done = array_sum(array_column($groups, 'done'));
        $total = array_sum(array_column($groups, 'total'));

        return ['done' => $done, 'total' => $total, 'percent' => $total ? (int) round($done / $total * 100) : 0];
    }

    /**
     * Guarda un requisito cargado por el cliente o la agencia. Un texto cuenta como hecho si
     * no está vacío; una casilla, si está marcada. Lo que se carga a mano queda como manual,
     * aunque antes lo hubiera marcado el plugin.
     */
    public function update(Project $project, string $key, mixed $value): ProjectRequirement
    {
        $template = $project->requirementTemplates()->firstWhere('key', $key)
            ?? throw new InvalidArgumentException("El requisito {$key} no corresponde a este proyecto.");

        if ($template->type === 'image') {
            throw new InvalidArgumentException('Las imágenes se suben con el botón "Subir foto".');
        }

        if ($template->type === 'check') {
            $done = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $stored = null;
        } else {
            $stored = trim((string) $value);
            if (mb_strlen($stored) > 5000) {
                throw new InvalidArgumentException('El texto es demasiado largo (máximo 5000 caracteres).');
            }
            $done = $stored !== '';
            $stored = $done ? $stored : null;
        }

        return ProjectRequirement::updateOrCreate(
            ['project_id' => $project->id, 'template_key' => $key],
            ['value' => $stored, 'done' => $done, 'source' => ProjectRequirement::SOURCE_MANUAL],
        );
    }

    /** Carpeta donde se guardan las imágenes de un proyecto (disco local, fuera de public/). */
    public const IMAGE_DIR = 'projects';

    public static function isStoredImage(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::IMAGE_DIR.'/');
    }

    /**
     * Guarda la imagen de un requisito de tipo image y borra la anterior. Lo que se sube a mano
     * queda como manual.
     */
    public function storeImage(Project $project, string $key, UploadedFile $file, ?UploadedFile $original = null): ProjectRequirement
    {
        $this->imageTemplate($project, $key);
        $req = ProjectRequirement::firstOrNew(['project_id' => $project->id, 'template_key' => $key]);
        $old = $req->value;

        $dir = self::IMAGE_DIR.'/'.$project->id;
        $base = $key.'-'.Str::random(10);
        $path = $file->storeAs($dir, $base.'.'.self::extension($file), 'local');
        if ($original) {
            // La foto sin recortar, por si la agencia necesita otro encuadre.
            $original->storeAs($dir, $base.'-original.'.self::extension($original), 'local');
        }

        $req->fill(['value' => $path, 'done' => true, 'source' => ProjectRequirement::SOURCE_MANUAL])->save();
        if (self::isStoredImage($old) && $old !== $path) {
            self::deleteFiles($old);
        }

        return $req;
    }

    /** Ruta de la foto original (sin recortar) de una imagen guardada, si existe. */
    public static function originalPath(string $path): ?string
    {
        $prefix = preg_replace('/\.\w+$/', '', $path).'-original.';
        foreach (Storage::disk('local')->files(dirname($path)) as $file) {
            if (str_starts_with($file, $prefix)) {
                return $file;
            }
        }

        return null;
    }

    /** ¿La imagen tiene el formato pedido? Tolera 2 % de diferencia (redondeos del recorte). */
    public static function matchesAspect(string $path, ?string $aspect): bool
    {
        if (! $aspect || ! Storage::disk('local')->exists($path)) {
            return true;
        }
        $size = @getimagesize(Storage::disk('local')->path($path));
        if (! $size || ! $size[1]) {
            return true;
        }
        [$w, $h] = array_map('intval', explode(':', $aspect));

        return abs(($size[0] / $size[1]) / ($w / $h) - 1) <= 0.02;
    }

    private static function extension(UploadedFile $file): string
    {
        return strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
    }

    private static function deleteFiles(string $path): void
    {
        $original = self::originalPath($path);
        Storage::disk('local')->delete(array_filter([$path, $original]));
    }

    public function deleteImage(Project $project, string $key): void
    {
        $this->imageTemplate($project, $key);
        $req = ProjectRequirement::where(['project_id' => $project->id, 'template_key' => $key])->first();
        if (! $req) {
            return;
        }
        if (self::isStoredImage($req->value)) {
            self::deleteFiles($req->value);
        }
        $req->fill(['value' => null, 'done' => false, 'source' => ProjectRequirement::SOURCE_MANUAL])->save();
    }

    private function imageTemplate(Project $project, string $key): RequirementTemplate
    {
        $template = $project->requirementTemplates()->firstWhere('key', $key);
        if (! $template || $template->type !== 'image') {
            throw new InvalidArgumentException("El requisito {$key} no es una imagen de este proyecto.");
        }

        return $template;
    }

    public function setWorkStatus(Project $project, string $group, string $status): void
    {
        if (! array_key_exists($status, ProjectSection::WORK_STATUSES)) {
            throw new InvalidArgumentException("Estado inválido: {$status}");
        }
        if (! $project->requirementGroups()->contains('key', $group)) {
            throw new InvalidArgumentException("La sección {$group} no corresponde a este proyecto.");
        }

        ProjectSection::upsert(
            [['project_id' => $project->id, 'group' => $group, 'work_status' => $status, 'updated_at' => now()]],
            ['project_id', 'group'],
            ['work_status', 'updated_at'],
        );
    }

    /**
     * Mensaje para pedirle al cliente lo que falta, listo para WhatsApp o mail.
     *
     * @param  list<array<string, mixed>>  $groups
     */
    public function message(string $clientName, array $groups): string
    {
        $pending = array_filter($groups, fn ($g) => $g['missing']);
        if (! $pending) {
            return "Hola! Ya tenemos todo lo que necesitamos para la web de {$clientName}. ¡Gracias!";
        }

        $text = "Hola! Para avanzar con la web de {$clientName} nos falta:\n";
        foreach ($pending as $g) {
            $text .= "\n*{$g['name']}*\n".implode("\n", array_map(fn ($m) => "- {$m}", $g['missing']))."\n";
        }

        return $text."\nCon eso podemos seguir. Gracias!";
    }
}
