<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Checklist;
use App\Services\ProposalCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Panel del cliente: completa la información de cada sección y ve su propuesta. */
class ClientController extends Controller
{
    public function __construct(private ProposalCalculator $calculator, private Checklist $checklist) {}

    public function index(Request $request): View
    {
        $project = $request->user()->client?->projects()
            ->whereIn('status', ['in_progress', 'published'])
            ->latest()
            ->first();

        return $this->panel($project);
    }

    /**
     * Pantalla del cliente para un proyecto. Con $preview, la agencia la ve tal cual la ve el
     * cliente ("Ver como cliente"); lo que cargue se guarda igual que desde su propia vista.
     */
    public function panel(?Project $project, bool $preview = false): View
    {
        if (! $project) {
            return view('client.index', ['project' => null, 'preview' => $preview]);
        }

        $groups = $this->checklist->groups($project);

        return view('client.index', [
            'project' => $project,
            'groups' => $groups,
            'totals' => Checklist::totals($groups),
            'proposal' => $this->calculator->forProject($project),
            'preview' => $preview,
        ]);
    }
}
