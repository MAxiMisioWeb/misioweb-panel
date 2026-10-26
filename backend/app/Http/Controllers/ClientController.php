<?php

namespace App\Http\Controllers;

use App\Services\ProposalCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inicio del cliente: su proyecto, el avance y la propuesta.
 * La carga de requisitos llega con la tarea 4 (panel conectado a datos).
 */
class ClientController extends Controller
{
    public function index(Request $request, ProposalCalculator $calculator): View
    {
        $project = $request->user()->client?->projects()
            ->whereIn('status', ['in_progress', 'published'])
            ->latest()
            ->first();

        return view('client.index', [
            'project' => $project,
            'progress' => $project?->progress(),
            'proposal' => $project ? $calculator->forProject($project) : null,
        ]);
    }
}
