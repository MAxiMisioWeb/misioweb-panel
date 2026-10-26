{{--
    Editor de requisitos por sección. Variables: $project, $groups, $agency (bool).
    Con JS se guarda campo por campo (public/js/panel.js); sin JS, con el botón de cada sección.
--}}
@foreach ($groups as $g)
    @php $chip = $g['done'] === $g['total'] ? 'ok' : ($g['done'] > 0 ? 'part' : ''); @endphp
    <section class="g" data-group="{{ $g['key'] }}">
        <div class="gh">
            <h2>{{ $g['name'] }}</h2>
            @if ($agency)
                <span class="chip {{ $chip }}" data-chip-agency>{{ $g['done'] === $g['total'] ? 'Listo para armar' : ($g['done'] > 0 ? 'Parcial, falta info' : 'Esperando al cliente') }}</span>
            @else
                <span class="chip {{ $chip }}" data-chip>{{ $g['done'] }} de {{ $g['total'] }}</span>
            @endif
        </div>
        <p>{{ $g['description'] }}</p>

        @if ($agency)
            <div class="bar" style="margin-top:10px"><i data-bar style="width:{{ $g['percent'] }}%"></i></div>
            <ul class="m" data-missing>
                @foreach ($g['missing'] as $m)<li>{{ $m }}</li>@endforeach
            </ul>
            <form method="POST" action="{{ route('agency.project.section', [$project, $g['key']]) }}" class="fl">
                @csrf @method('PUT')
                <span>Estado del trabajo (interno)</span>
                <select class="sel" name="work_status" data-work-status>
                    @foreach (\App\Models\ProjectSection::WORK_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($g['work_status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn ghost nojs" type="submit">Guardar estado</button>
            </form>
            <details style="margin-top:12px"><summary class="muted">Ver y editar lo cargado</summary>
        @endif

        <form method="POST" action="{{ route('requirements.update', $project) }}" data-req-form>
            @csrf @method('PUT')
            @php $last = null; @endphp
            @foreach ($g['fields'] as $f)
                @if ($f['subgroup'] && $f['subgroup'] !== $last)
                    <h3>{{ $f['subgroup'] }}</h3>
                    @php $last = $f['subgroup']; @endphp
                @endif
                @if ($f['type'] === 'check')
                    <input type="hidden" name="values[{{ $f['key'] }}]" value="0">
                    <label class="ck">
                        <input type="checkbox" name="values[{{ $f['key'] }}]" value="1" data-req="{{ $f['key'] }}" @checked($f['done'])>
                        <div>{{ $f['label'] }}
                            @if ($f['hint'])<small>{{ $f['hint'] }}</small>@endif
                            @if ($f['source'] === 'plugin' && $f['done'])<small>Lo verificó el plugin en tu WordPress.</small>@endif
                        </div>
                    </label>
                @else
                    <label class="fl">
                        <span><i class="dot{{ $f['done'] ? ' ok' : '' }}" data-dot="{{ $f['key'] }}"></i>{{ $f['label'] }}</span>
                        @if ($f['type'] === 'area')
                            <textarea name="values[{{ $f['key'] }}]" data-req="{{ $f['key'] }}" maxlength="5000">{{ $f['value'] }}</textarea>
                        @else
                            <input type="text" name="values[{{ $f['key'] }}]" value="{{ $f['value'] }}" data-req="{{ $f['key'] }}" maxlength="5000">
                        @endif
                        @if ($f['hint'])<div class="hint">{{ $f['hint'] }}</div>@endif
                        @if ($f['source'] === 'plugin' && $f['done'])<div class="hint">Lo verificó el plugin en el WordPress.</div>@endif
                    </label>
                @endif
            @endforeach
            <div class="act">
                <button class="btn nojs" type="submit">Guardar</button>
                <span class="hint" data-saved></span>
            </div>
        </form>

        @if ($agency)
            </details>
        @endif
    </section>
@endforeach
