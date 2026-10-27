@php use App\Support\Money; @endphp
@extends('layouts.app')
@section('title', 'Agencia')
@section('heading', 'Agencia')
@section('content')
    <section class="g">
        <div class="gh"><h2>Pedidos para revisar</h2><span class="chip {{ $requested->isEmpty() ? 'ok' : 'part' }}">{{ $requested->count() }}</span></div>
        @forelse ($requested as [$project, $proposal])
            <div style="border-top:1px solid var(--line);margin-top:14px;padding-top:14px">
                <div class="gh">
                    <h3 style="margin:0">{{ $project->client->name }}</h3>
                    <span class="muted">{{ $project->requested_at?->format('d/m/Y H:i') }}</span>
                </div>
                <p class="muted" style="margin:4px 0">
                    {{ $project->client->users->pluck('email')->join(', ') }}
                </p>
                <p style="margin:4px 0">Sitio base{{ $project->widgets->isNotEmpty() ? ', '.$project->widgets->pluck('name')->join(', ') : '' }}.</p>
                <p style="margin:4px 0">
                    <b>{{ Money::usd($proposal->implUsd) }}</b> de implementación y <b>{{ Money::usd($proposal->monthUsd) }}</b> por mes
                    <span class="muted">({{ Money::ars($proposal->implArs) }} y {{ Money::ars($proposal->monthArs) }} con cotización {{ Money::ars(intdiv($proposal->fxRate10k, 100)) }})</span>
                </p>
                @if ($project->request_note)
                    <p class="muted" style="margin:4px 0">“{{ $project->request_note }}”</p>
                @endif
                <div class="act">
                    <form method="POST" action="{{ route('agency.approve', $project) }}">
                        @csrf
                        <button class="btn" type="submit">Aprobar y mandar acceso</button>
                    </form>
                    <form method="POST" action="{{ route('agency.reject', $project) }}" onsubmit="return confirm(@js('¿Rechazar el pedido de '.$project->client->name.'?'))">
                        @csrf
                        <button class="btn danger" type="submit">Rechazar</button>
                    </form>
                </div>
            </div>
        @empty
            <p>No hay pedidos nuevos.</p>
        @endforelse
    </section>

    <section class="g">
        <h2>Proyectos activos</h2>
        @forelse ($projects as [$project, $progress])
            <div style="margin-top:12px">
                <div class="gh"><a href="{{ route('agency.project', $project) }}">{{ $project->client->name }} · {{ $project->name }}</a><span class="chip {{ $progress['percent'] === 100 ? 'ok' : ($progress['percent'] > 0 ? 'part' : '') }}">{{ $progress['done'] }} de {{ $progress['total'] }}</span></div>
                <div class="bar" style="margin-top:6px"><i style="width:{{ $progress['percent'] }}%"></i></div>
                <p class="muted" style="margin:4px 0 0"><a href="{{ route('agency.project', $project) }}">Gestionar</a> · <a href="{{ route('agency.project.preview', $project) }}">Ver como cliente</a></p>
            </div>
        @empty
            <p>Todavía no hay proyectos activos.</p>
        @endforelse
    </section>
@endsection
