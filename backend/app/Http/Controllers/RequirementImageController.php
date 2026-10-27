<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Services\Checklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Imágenes de los requisitos (fotos de la portada, imágenes de las entradas). Se guardan fuera
 * de public/ y solo las ven el cliente del proyecto y la agencia.
 */
class RequirementImageController extends Controller
{
    /** Tamaño máximo en KB. El navegador achica las fotos grandes antes de subirlas. */
    public const MAX_KB = 10240;

    public function __construct(private Checklist $checklist) {}

    public function store(Request $request, Project $project, string $key): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);
        $request->validate(
            [
                'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB],
                'original' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB],
            ],
            [
                'image.mimes' => 'La foto tiene que ser JPG, PNG o WebP.',
                'image.max' => 'La foto pesa más de 10 MB.',
                'image.required' => 'Elegí una foto.',
                'image.uploaded' => 'No se pudo subir la foto. Probá con una más liviana.',
            ],
        );

        return $this->respond($request, $project, $key, fn () => $this->checklist->storeImage($project, $key, $request->file('image'), $request->file('original')), 'Foto guardada.');
    }

    public function destroy(Request $request, Project $project, string $key): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);

        return $this->respond($request, $project, $key, fn () => $this->checklist->deleteImage($project, $key), 'Foto quitada.');
    }

    /** La foto recortada, o la original con ?original=1. */
    public function show(Request $request, Project $project, string $key): StreamedResponse
    {
        Gate::authorize('update', $project);
        $path = ProjectRequirement::where(['project_id' => $project->id, 'template_key' => $key])->value('value');
        abort_unless(Checklist::isStoredImage($path), 404);
        if ($request->boolean('original')) {
            $path = Checklist::originalPath($path);
        }
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=31536000', // la URL cambia (?v=) cuando se reemplaza
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function respond(Request $request, Project $project, string $key, callable $action, string $message): JsonResponse|RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['review' => $e->getMessage()]);
        }

        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        $field = collect($this->checklist->groups($project))->flatMap(fn ($g) => $g['fields'])->firstWhere('key', $key);

        return response()->json($this->checklist->summary($project) + [
            'image_url' => $field['image_url'] ?? null,
            'aspect_ok' => $field['aspect_ok'] ?? true,
        ]);
    }
}
