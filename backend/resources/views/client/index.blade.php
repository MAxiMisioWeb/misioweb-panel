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
            <div style="grid-column:1/-1"><small>Información recibida: <b>{{ $progress['percent'] }}%</b></small><div class="bar"><i style="width:{{ $progress['percent'] }}%"></i></div></div>
        </div>
        <section class="g">
            <h2>Tu propuesta</h2>
            <textarea readonly style="min-height:220px;margin-top:10px">{{ $proposal->text($project->client->name) }}</textarea>
        </section>
        <section class="g">
            <h2>Próximamente</h2>
            <p>Acá vas a poder completar la información de cada sección de tu sitio: textos, fotos y accesos.</p>
        </section>
    @endif
@endsection
