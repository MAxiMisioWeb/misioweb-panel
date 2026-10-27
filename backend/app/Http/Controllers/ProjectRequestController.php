<?php

namespace App\Http\Controllers;

use App\Models\Widget;
use App\Services\ProjectRequests;
use App\Services\ProposalCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** El prospecto elige widgets, ve la inversión estimada y manda el pedido. */
class ProjectRequestController extends Controller
{
    public function __construct(private ProjectRequests $requests) {}

    public function create(Request $request, ProposalCalculator $calculator): View
    {
        $user = $request->user();
        $widgets = Widget::active()->orderBy('sort')->get();
        $selected = (array) $request->old('widgets', []);

        return view('request.create', [
            'pending' => $this->requests->pending($user),
            'widgets' => $widgets,
            'selected' => $selected,
            'businessName' => $request->old('business_name', $user->client?->name),
            'proposal' => $calculator->calculate($widgets->whereIn('key', $selected)->values()->all(), (string) config('panel.fx_rate')),
            'base' => config('panel.base'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'widgets' => ['array'],
            'widgets.*' => ['string', Rule::exists('widgets', 'key')->where('active', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['business_name' => 'nombre del emprendimiento']);

        if ($this->requests->pending($request->user())) {
            return redirect()->route('request.create');
        }

        $this->requests->submit($request->user(), $data['business_name'], $data['widgets'] ?? [], $data['note'] ?? null);

        return redirect()->route('request.create')->with('status', '¡Pedido enviado! Lo revisamos y te avisamos por mail.');
    }
}
