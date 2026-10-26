<?php

namespace App\Http\Controllers;

use App\Services\Checklist;
use App\Services\ProposalCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Panel del cliente: completa la información de cada sección y ve su propuesta. */
class ClientController extends Controller
{
    public function index(Request $request, ProposalCalculator $calculator, Checklist $checklist): View
    {
        $project = $request->user()->client?->projects()
            ->whereIn('status', ['in_progress', 'published'])
            ->latest()
            ->first();

        if (! $project) {
            return view('client.index', ['project' => null]);
        }

        $groups = $checklist->groups($project);

        return view('client.index', [
            'project' => $project,
            'groups' => $groups,
            'totals' => Checklist::totals($groups),
            'proposal' => $calculator->forProject($project),
        ]);
    }
}
