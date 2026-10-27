<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Widget;
use App\Services\Checklist;
use App\Services\ClientInvitations;
use App\Services\ProposalCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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

    /** "Ver como cliente": la pantalla del cliente de este proyecto, sin cambiar de cuenta. */
    public function preview(Project $project, ClientController $client): View
    {
        Gate::authorize('manage', $project);

        return $client->panel($project, preview: true);
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

    public function invite(Request $request, Project $project, ClientInvitations $invitations): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $invitations->invite($project, $data['email'], $data['name']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['invite' => $e->getMessage()]);
        }

        return $result['mailed']
            ? back()->with('status', "Invitación enviada a {$result['user']->email}.")
            : back()->with('invite_url', $result['url'])
                ->withErrors(['review' => 'La cuenta quedó creada, pero no se pudo mandar el mail. Mandale el enlace de abajo por otro medio.']);
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

    /** Zip del plugin MisioWeb Progreso armado desde el repo (wp-plugin/misioweb-progreso). */
    public function plugin(): BinaryFileResponse
    {
        $dir = base_path('../wp-plugin/misioweb-progreso');
        abort_unless(is_dir($dir), 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'mwp');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach (glob($dir.'/*.php') as $file) {
            $zip->addFile($file, 'misioweb-progreso/'.basename($file));
        }
        $zip->close();

        return response()->download($zipPath, 'misioweb-progreso.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
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
