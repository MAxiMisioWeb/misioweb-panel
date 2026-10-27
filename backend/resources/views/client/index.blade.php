@php use App\Support\Money; @endphp
@php $showPrices = config('panel.show_prices'); @endphp
@extends('layouts.app')
@section('title', 'Mi panel')
@section('heading', $project?->client->name ?? 'Mi panel')
@section('content')
    @if ($preview ?? false)
        <div class="banner" role="note">
            <b>Vista del cliente.</b> Así ve {{ $project?->client->name ?? 'el cliente' }} su panel. Lo que cargues acá se guarda de verdad.
            <a href="{{ route('agency.project', $project) }}">← Volver a la vista de agencia</a>
        </div>
    @endif
    @if (! $project)
        <section class="g"><p>Todavía no hay un proyecto activo en tu cuenta. Si creés que es un error, escribinos a contacto@misioweb.com.</p></section>
    @else
        <div class="sum">
            <div><small>Proyecto</small><b>{{ $project->name }}</b></div>
            @if ($showPrices)
                <div><small>Implementación</small><b>{{ Money::ars($proposal->implArs) }}</b></div>
                <div><small>Abono mensual</small><b>{{ Money::ars($proposal->monthArs) }}</b></div>
            @endif
            <div style="grid-column:1/-1"><small>Información recibida: <b data-total-pct>{{ $totals['percent'] }}%</b></small><div class="bar"><i data-total-bar style="width:{{ $totals['percent'] }}%"></i></div></div>
        </div>

        @php $left = $totals['total'] - $totals['done']; @endphp
        <div class="banner{{ $left ? '' : ' ok' }}" data-banner>
            @if ($left)
                Faltan <b data-left>{{ $left }}</b> puntos por completar. Cada sección muestra qué falta. Se guarda solo mientras escribís.
            @else
                Está todo completo. Ya podemos armar y publicar tu sitio.
            @endif
        </div>

        @include('projects.sections', ['agency' => false])

        <section class="g">
            <h2>{{ $showPrices ? 'Tu propuesta' : 'Tu sitio incluye' }}</h2>
            <p>Sitio base{{ $project->widgets->isNotEmpty() ? ', '.$project->widgets->pluck('name')->join(', ') : '' }}. Si querés sumar o sacar widgets, escribinos.</p>
            @if ($showPrices)
                <textarea readonly style="min-height:220px;margin-top:10px">{{ $proposal->text($project->client->name) }}</textarea>
            @endif
        </section>
    @endif
@endsection
