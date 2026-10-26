@php use App\Support\Money; @endphp
@extends('layouts.app')
@section('title', 'Mi panel')
@section('heading', $project?->client->name ?? 'Mi panel')
@section('content')
    @if (! $project)
        <section class="g"><p>Todavía no hay un proyecto activo en tu cuenta. Si creés que es un error, escribinos a contacto@misioweb.com.</p></section>
    @else
        <div class="sum">
            <div><small>Proyecto</small><b>{{ $project->name }}</b></div>
            <div><small>Implementación</small><b>{{ Money::ars($proposal->implArs) }}</b></div>
            <div><small>Abono mensual</small><b>{{ Money::ars($proposal->monthArs) }}</b></div>
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
            <h2>Tu propuesta</h2>
            <p>Sitio base{{ $project->widgets->isNotEmpty() ? ', '.$project->widgets->pluck('name')->join(', ') : '' }}. Si querés sumar o sacar widgets, escribinos.</p>
            <textarea readonly style="min-height:220px;margin-top:10px">{{ $proposal->text($project->client->name) }}</textarea>
        </section>
    @endif
@endsection
