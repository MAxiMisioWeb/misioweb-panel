<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Widget;
use App\Services\Checklist;
use App\Services\ProposalCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Vista de la agencia de un proyecto: qué se puede hacer hoy, qué bloquea cada sección,
 * estado interno del trabajo, propuesta (cotización y widgets) y mensaje para el cliente.
 */
class AgencyProjectController extends Controller
{
    public function __construct(private Checklist $checklist) {}

    public function show(Project $project, ProposalCalculator $calculator): View
    {
        Gate::authorize('manage', $project);
        $groups = $this->checklist->groups($project);
        $proposal = $calculator->forProject($project);
        $site = $project->sites()->first();

        return view('agency.project', [
            'project' => $project->load('client.users', 'widgets'),
            'groups' => $groups,
            'totals' => Checklist::totals($groups),
            'ready' => array_values(array_filter($groups, fn ($g) => $g['done'] === $g['total'])),
            'proposal' => $proposal,
            'proposalText' => $proposal->text($project->client->name),
            'message' => $this->checklist->message($project->client->name, $groups),
            'widgets' => Widget::active()->orderBy('sort')->get(),
            'site' => $site,
            // Última señal de cada tipo.
            'signals' => $site
                ? $site->signals()->latest('created_at')->latest('id')->limit(200)->get()->unique('key')->sortBy('key')->values()
                : collect(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);

        $data = $request->validate([
            'fx_rate' => ['required', 'regex:/^\d{1,8}([.,]\d{1,4})?$/'],
            'widgets' => ['array'],
            'widgets.*' => ['string', Rule::exists('widgets', 'key')],
        ], ['fx_rate.regex' => 'La cotización tiene que ser un número, por ejemplo 1530 o 1530,50.']);

        $project->update(['fx_rate' => str_replace(',', '.', $data['fx_rate'])]);
        $project->widgets()->sync(Widget::whereIn('key', $data['widgets'] ?? [])->pluck('id'));

        return back()->with('status', 'Propuesta actualizada.');
    }

    public function section(Request $request, Project $project, string $group): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage', $project);
        $status = (string) $request->input('work_status');

        try {
            $this->checklist->setWorkStatus($project, $group, $status);
        } catch (InvalidArgumentException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['review' => $e->getMessage()]);
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Estado guardado.');
    }

    /** Registra (o cambia) el sitio WordPress del proyecto y genera un token nuevo. */
    public function site(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:255'],
        ], ['url.url' => 'Tiene que ser una dirección https://, por ejemplo https://sankalpa.com.ar']);

        $site = $project->sites()->first() ?? $project->sites()->make();
        $site->url = rtrim($data['url'], '/');
        $token = $site->issueToken();

        return back()->with('site_token', $token)->with('status', 'Token generado. Copialo ahora: no se vuelve a mostrar.');
    }

    public function publish(Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);

        if ($project->status !== 'in_progress') {
            return back()->withErrors(['review' => 'Solo se puede publicar un proyecto en curso.']);
        }
        $project->update(['status' => 'published', 'published_at' => $project->published_at ?? now()]);

        return back()->with('status', 'Proyecto marcado como publicado. El abono se cobra desde el mes que viene.');
    }
}
