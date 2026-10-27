<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Checklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Carga de requisitos por el cliente o la agencia. Con JavaScript se guarda campo por campo
 * (JSON); sin JavaScript, el botón "Guardar" de cada sección manda todos sus campos juntos.
 */
class RequirementController extends Controller
{
    public function __construct(private Checklist $checklist) {}

    public function update(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'key' => ['required_without:values', 'string', 'max:32'],
            'value' => ['nullable'],
            'values' => ['array'],
            'values.*' => ['nullable'],
        ]);
        $values = isset($data['key']) ? [$data['key'] => $data['value'] ?? null] : $data['values'];

        try {
            DB::transaction(function () use ($project, $values) {
                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        throw new InvalidArgumentException('Valor inválido.');
                    }
                    $this->checklist->update($project, (string) $key, $value);
                }
            });
        } catch (InvalidArgumentException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['review' => $e->getMessage()]);
        }

        if (! $request->expectsJson()) {
            return back()->with('status', 'Cambios guardados.');
        }

        return response()->json($this->checklist->summary($project));
    }
}
