// Panel MisioWeb: guardado automático de requisitos, estado interno de secciones y botones de copiar.
// Sin JavaScript todo funciona igual con los botones "Guardar" de cada sección.
(function () {
    document.documentElement.classList.add('js');
    var token = document.querySelector('meta[name=csrf-token]');
    token = token ? token.content : '';

    function send(url, body) {
        return fetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.message || 'No se pudo guardar.');
                return data;
            });
        });
    }

    function refresh(data) {
        data.groups.forEach(function (g) {
            var sec = document.querySelector('[data-group="' + g.key + '"]');
            if (!sec) return;
            var cls = g.done === g.total ? 'ok' : (g.done > 0 ? 'part' : '');
            var chip = sec.querySelector('[data-chip]');
            if (chip) { chip.textContent = g.done + ' de ' + g.total; chip.className = 'chip ' + cls; }
            var chipA = sec.querySelector('[data-chip-agency]');
            if (chipA) {
                chipA.textContent = g.done === g.total ? 'Listo para armar' : (g.done > 0 ? 'Parcial, falta info' : 'Esperando al cliente');
                chipA.className = 'chip ' + cls;
            }
            var bar = sec.querySelector('[data-bar]');
            if (bar) bar.style.width = Math.round(g.done / g.total * 100) + '%';
            var ul = sec.querySelector('[data-missing]');
            if (ul) {
                ul.textContent = '';
                g.missing.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
            }
        });
        document.querySelectorAll('[data-dot]').forEach(function (d) {
            d.classList.toggle('ok', data.done.indexOf(d.dataset.dot) !== -1);
        });
        var t = data.totals;
        document.querySelectorAll('[data-total-pct]').forEach(function (e) { e.textContent = t.percent + '%'; });
        document.querySelectorAll('[data-total-bar]').forEach(function (e) { e.style.width = t.percent + '%'; });
        var left = document.querySelector('[data-left]');
        var banner = document.querySelector('[data-banner]');
        if (banner) {
            var n = t.total - t.done;
            banner.classList.toggle('ok', n === 0);
            if (n === 0) banner.textContent = 'Está todo completo. Ya podemos armar y publicar tu sitio.';
            else if (left) left.textContent = n;
        }
    }

    var timers = {};
    function save(field) {
        var form = field.closest('[data-req-form]');
        var status = form.querySelector('[data-saved]');
        var value = field.type === 'checkbox' ? field.checked : field.value;
        status.textContent = 'Guardando…';
        send(form.action, { key: field.dataset.req, value: value })
            .then(function (data) { refresh(data); status.textContent = 'Guardado'; })
            .catch(function (e) { status.textContent = e.message + ' Probá de nuevo.'; });
    }

    document.addEventListener('input', function (e) {
        var f = e.target.closest('[data-req]');
        if (!f || f.type === 'checkbox') return;
        clearTimeout(timers[f.dataset.req]);
        timers[f.dataset.req] = setTimeout(function () { timers[f.dataset.req] = null; save(f); }, 700);
    });
    document.addEventListener('change', function (e) {
        var f = e.target.closest('[data-req]');
        if (f) { clearTimeout(timers[f.dataset.req]); timers[f.dataset.req] = null; save(f); return; }
        var w = e.target.closest('[data-work-status]');
        if (w) {
            send(w.form.action, { work_status: w.value }).catch(function (err) { alert(err.message); });
        }
    });
    // Si quedó algo sin guardar, avisar antes de salir.
    window.addEventListener('beforeunload', function (e) {
        for (var k in timers) { if (timers[k]) { e.preventDefault(); return; } }
    });
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]');
        if (!b) return;
        var area = document.getElementById(b.dataset.copy);
        var note = document.querySelector('[data-copied="' + b.dataset.copy + '"]');
        var done = function () { if (note) note.textContent = 'Copiado'; };
        if (navigator.clipboard) navigator.clipboard.writeText(area.value).then(done, function () { area.select(); document.execCommand('copy'); done(); });
        else { area.select(); document.execCommand('copy'); done(); }
    });
})();
