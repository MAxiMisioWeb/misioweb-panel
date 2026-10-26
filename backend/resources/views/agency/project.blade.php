@php use App\Support\Money; @endphp
@extends('layouts.app')
@section('title', $project->client->name)
@section('heading', $project->client->name)
@section('content')
    <p class="muted" style="margin:0 0 12px"><a href="{{ route('agency.home') }}">← Agencia</a> · {{ $project->name }} · {{ $project->client->users->pluck('email')->join(', ') }}</p>

    <div class="sum">
        <div><small>Estado</small><b>{{ ['in_progress' => 'En curso', 'published' => 'Publicado'][$project->status] ?? $project->status }}</b>
            @if ($project->published_at)<small>desde el {{ $project->published_at->format('d/m/Y') }}</small>@endif</div>
        <div><small>Implementación</small><b>{{ Money::ars($proposal->implArs) }}</b></div>
        <div><small>Abono mensual</small><b>{{ Money::ars($proposal->monthArs) }}</b></div>
        <div style="grid-column:1/-1"><small>Información recibida: <b data-total-pct>{{ $totals['percent'] }}%</b></small><div class="bar"><i data-total-bar style="width:{{ $totals['percent'] }}%"></i></div></div>
    </div>

    <div class="banner{{ count($ready) === count($groups) ? ' ok' : '' }}">
        <b>Podemos trabajar hoy:</b> la maquetación base del sitio (no depende del cliente){{ $ready ? ' y '.implode(', ', array_column($ready, 'name')).', que ya tiene todo.' : '.' }}
    </div>

    @include('projects.sections', ['agency' => true])

    <section class="g">
        <h2>Propuesta según los widgets elegidos</h2>
        <p>Cambiar la cotización o los widgets recalcula la propuesta y lo que se le pide al cliente.</p>
        <form method="POST" action="{{ route('agency.project.update', $project) }}">
            @csrf @method('PUT')
            <label class="fl"><span>Cotización del dólar (Banco Nación venta)</span>
                <input type="text" name="fx_rate" class="sel" inputmode="decimal" value="{{ old('fx_rate', rtrim(rtrim((string) $project->fx_rate, '0'), '.')) }}">
            </label>
            @error('fx_rate')<div class="err">{{ $message }}</div>@enderror
            @foreach ($widgets as $w)
                <label class="ck"><input type="checkbox" name="widgets[]" value="{{ $w->key }}" @checked($project->widgets->contains('key', $w->key))>
                    <div>{{ $w->name }}<small>{{ Money::usd(Money::toScaled($w->price_usd_impl, 2)) }} y {{ Money::usd(Money::toScaled($w->price_usd_month, 2)) }} por mes</small></div></label>
            @endforeach
            <div class="act"><button class="btn" type="submit">Actualizar propuesta</button></div>
        </form>

        <table class="tb" style="margin-top:16px">
            <tr><th>Concepto</th><th>Implementación</th><th>Abono mensual</th></tr>
            @foreach ($proposal->lines as $l)
                <tr><td>{{ $l['name'] }}</td><td>{{ Money::usd($l['impl_usd']) }}</td><td>{{ Money::usd($l['month_usd']) }}</td></tr>
            @endforeach
            <tr class="t"><td>Total en dólares</td><td>{{ Money::usd($proposal->implUsd) }}</td><td>{{ Money::usd($proposal->monthUsd) }}</td></tr>
            <tr class="t"><td>Total en pesos</td><td>{{ Money::ars($proposal->implArs) }}</td><td>{{ Money::ars($proposal->monthArs) }}</td></tr>
        </table>
        <textarea readonly id="propuesta" style="min-height:200px;margin-top:12px">{{ $proposalText }}</textarea>
        <div class="act"><button class="btn" type="button" data-copy="propuesta">Copiar propuesta</button><span class="hint" data-copied="propuesta"></span></div>
    </section>

    <section class="g">
        <h2>Mensaje para el cliente</h2>
        <p>Lista de lo que falta, lista para pegar en WhatsApp o mail.</p>
        <textarea readonly id="mensaje" style="min-height:160px;margin-top:10px">{{ $message }}</textarea>
        <div class="act"><button class="btn" type="button" data-copy="mensaje">Copiar mensaje</button><span class="hint" data-copied="mensaje"></span></div>
    </section>

    <section class="g" id="sitio">
        <h2>Sitio WordPress</h2>
        <p>El plugin MisioWeb Progreso revisa el sitio cada hora y marca solo lo que puede verificar: entradas del blog, páginas legales, productos, Mercado Pago y ReelsLoop.</p>

        @if (session('site_token'))
            <div class="banner ok" style="margin-top:12px">
                <b>Configuración del plugin</b> (el token se muestra una sola vez):
                <textarea readonly id="config-plugin" style="min-height:120px;margin-top:8px;font-family:monospace">URL del panel: {{ url('/api') }}
ID del sitio: {{ $site->id }}
Token: {{ session('site_token') }}

O en wp-config.php:
define( 'MW_PROGRESO_TOKEN', '{{ session('site_token') }}' );</textarea>
                <div class="act"><button class="btn" type="button" data-copy="config-plugin">Copiar</button><span class="hint" data-copied="config-plugin"></span></div>
            </div>
        @endif

        @if ($site)
            <p style="margin-top:8px">
                <b>{{ $site->url }}</b> · ID {{ $site->id }} ·
                {{ $site->last_sync_at ? 'última sincronización '.$site->last_sync_at->format('d/m/Y H:i') : 'todavía no sincronizó' }}
            </p>
            @if ($signals->isNotEmpty())
                <ul class="m">
                    @foreach ($signals as $sig)
                        <li>{{ $sig->ok ? '✔' : '✖' }} {{ $sig->key }}: {{ $sig->detail }}</li>
                    @endforeach
                </ul>
            @endif
        @endif

        <form method="POST" action="{{ route('agency.project.site', $project) }}" @if ($site) onsubmit="return confirm('Se genera un token nuevo y el anterior deja de funcionar. ¿Seguir?')" @endif>
            @csrf
            <label class="fl"><span>Dirección del sitio</span>
                <input type="text" name="url" value="{{ old('url', $site?->url) }}" placeholder="https://sankalpa.com.ar" inputmode="url">
            </label>
            @error('url')<div class="err">{{ $message }}</div>@enderror
            <div class="act"><button class="btn {{ $site ? 'ghost' : '' }}" type="submit">{{ $site ? 'Generar token nuevo' : 'Registrar sitio y generar token' }}</button></div>
        </form>
    </section>

    @if ($project->status === 'in_progress')
        <section class="g">
            <h2>Publicación</h2>
            <p>Al marcarlo como publicado, el abono se empieza a cobrar desde el mes siguiente.</p>
            <form method="POST" action="{{ route('agency.project.publish', $project) }}" class="act" onsubmit="return confirm('¿Marcar el sitio como publicado?')">
                @csrf
                <button class="btn ghost" type="submit">Marcar como publicado</button>
            </form>
        </section>
    @endif
@endsection
