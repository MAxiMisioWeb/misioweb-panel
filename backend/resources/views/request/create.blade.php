@php use App\Support\Money; @endphp
@extends('layouts.app')
@section('title', 'Tu pedido')
@section('heading', 'Armá tu sitio')
@section('content')
    @if ($pending)
        <section class="g">
            <div class="gh"><h2>Pedido enviado</h2><span class="chip part">Esperando revisión</span></div>
            <p>Lo mandaste el {{ $pending->requested_at->format('d/m/Y') }}. Lo revisamos y te avisamos por mail cuando tu panel esté listo.</p>
            <p class="muted">Elegiste: sitio base{{ $pending->widgets->isNotEmpty() ? ', '.$pending->widgets->pluck('name')->join(', ') : '' }}.</p>
        </section>
    @else
        <form method="POST" action="{{ route('request.store') }}" id="pedido">
            @csrf
            <section class="g">
                <h2>Tu emprendimiento</h2>
                <label class="fl"><span>Nombre del emprendimiento o empresa</span>
                    <input type="text" name="business_name" value="{{ $businessName }}" required maxlength="120">
                </label>
                @error('business_name')<div class="err">{{ $message }}</div>@enderror
            </section>

            <section class="g">
                <h2>Elegí los widgets de tu web</h2>
                <p>Sumá lo que necesitás. La inversión se actualiza sola y cada widget pide solo la información que necesita.</p>
                @foreach ($widgets as $w)
                    <label class="ck">
                        <input type="checkbox" name="widgets[]" value="{{ $w->key }}" @checked(in_array($w->key, $selected, true))
                            data-impl="{{ Money::toScaled($w->price_usd_impl, 2) }}" data-month="{{ Money::toScaled($w->price_usd_month, 2) }}">
                        <div>{{ $w->name }}<small>{{ $w->description }} · suma {{ Money::usd(Money::toScaled($w->price_usd_impl, 2)) }} y {{ Money::usd(Money::toScaled($w->price_usd_month, 2)) }} por mes</small></div>
                    </label>
                @endforeach
                @error('widgets.*')<div class="err">{{ $message }}</div>@enderror

                <div class="banner ok" style="margin:14px 0 0" id="estimado"
                    data-base-impl="{{ Money::toScaled($base['price_usd_impl'], 2) }}" data-base-month="{{ Money::toScaled($base['price_usd_month'], 2) }}"
                    data-fx="{{ $proposal->fxRate10k }}">
                    Sitio base incluido ({{ Money::usd(Money::toScaled($base['price_usd_impl'], 2)) }} y {{ Money::usd(Money::toScaled($base['price_usd_month'], 2)) }} por mes).
                    Inversión estimada: <b data-out="impl">{{ Money::usd($proposal->implUsd) }}</b> de implementación y <b data-out="month">{{ Money::usd($proposal->monthUsd) }}</b> por mes
                    (<span data-out="ars">{{ Money::ars($proposal->implArs) }} y {{ Money::ars($proposal->monthArs) }}</span> al dólar de hoy).
                </div>
            </section>

            <section class="g">
                <h2>¿Algo más que quieras contarnos?</h2>
                <label class="fl"><span>Comentario (opcional)</span>
                    <textarea name="note" maxlength="2000">{{ old('note') }}</textarea>
                </label>
                <div class="act">
                    <button class="btn" type="submit">Enviar pedido</button>
                    <span class="hint">Lo revisamos y te mandamos el acceso a tu panel.</span>
                </div>
            </section>
        </form>

        <script>
            // Recalcula la inversión al marcar widgets (centavos enteros, igual que el servidor).
            (function () {
                var box = document.getElementById('estimado');
                var fx = +box.dataset.fx;
                function fmt(cents) {
                    var n = cents / 100;
                    return n.toLocaleString('es-AR', { minimumFractionDigits: cents % 100 ? 2 : 0, maximumFractionDigits: 2 });
                }
                function conv(cents) { return Math.floor((cents * fx + 5000) / 10000); }
                function update() {
                    var impl = +box.dataset.baseImpl, month = +box.dataset.baseMonth;
                    document.querySelectorAll('#pedido input[name="widgets[]"]:checked').forEach(function (c) {
                        impl += +c.dataset.impl; month += +c.dataset.month;
                    });
                    box.querySelector('[data-out=impl]').textContent = 'USD ' + fmt(impl);
                    box.querySelector('[data-out=month]').textContent = 'USD ' + fmt(month);
                    box.querySelector('[data-out=ars]').textContent = '$' + fmt(conv(impl)) + ' y $' + fmt(conv(month));
                }
                document.getElementById('pedido').addEventListener('change', update);
                update();
            })();
        </script>
    @endif
@endsection
