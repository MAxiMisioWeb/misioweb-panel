<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectRequests;
use App\Services\ProposalCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Inicio de la agencia: pedidos para revisar y proyectos activos. */
class AgencyController extends Controller
{
    public function __construct(private ProjectRequests $requests) {}

    public function index(ProposalCalculator $calculator): View
    {
        $requested = Project::with('client.users', 'widgets')->where('status', 'requested')->orderBy('requested_at')->get();
        $projects = Project::with('client')->whereIn('status', ['in_progress', 'published'])->latest()->get();

        return view('agency.index', [
            'requested' => $requested->map(fn (Project $p) => [$p, $calculator->forProject($p)]),
            'projects' => $projects->map(fn (Project $p) => [$p, $p->progress()]),
        ]);
    }

    public function approve(Request $request, Project $project): RedirectResponse
    {
        try {
            $mailed = $this->requests->approve($project, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        $name = $project->client->name;

        return $mailed
            ? back()->with('status', "Pedido de {$name} aprobado. Le mandamos el mail con el acceso.")
            : back()->with('status', "Pedido de {$name} aprobado.")
                ->withErrors(['review' => 'No se pudo mandar el mail al cliente. Revisá la configuración de correo y avisale por otro medio.']);
    }

    public function reject(Request $request, Project $project): RedirectResponse
    {
        try {
            $this->requests->reject($project, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', "Pedido de {$project->client->name} rechazado.");
    }
}
