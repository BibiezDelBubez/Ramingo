/* Ramingo - Glossario: ogni riga si salva da sola quando la lasci, senza ricaricare la pagina. */
(function () {
    'use strict';
    var form = document.querySelector('[data-glossary]');
    if (!form) return;
    var csrf = form.querySelector('input[name="_csrf"]').value;
    var kind = form.querySelector('input[name="kind"]').value;
    var url = form.dataset.saveUrl;
    // Un salvataggio alla volta: ognuno rifà sintesi e indice di ricerca, in parallelo si intralcerebbero
    var queue = Promise.resolve();

    // Con JavaScript il pulsante «Salva» non serve: resta solo come ripiego
    form.querySelector('[data-gl-manual]').hidden = true;
    form.querySelector('[data-gl-auto]').hidden = false;
    form.addEventListener('submit', function (e) { e.preventDefault(); });

    function grow(t) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 2 + 'px'; }

    form.querySelectorAll('[data-gl-row]').forEach(function (row) {
        var label = row.querySelector('[data-gl-label]');
        var desc = row.querySelector('[data-gl-desc]');
        var status = row.querySelector('[data-gl-status]');
        var saved = { label: label.value.trim(), desc: desc.value.trim() };
        var timer = null;

        grow(desc);
        desc.addEventListener('input', function () { grow(desc); });

        function show(state, text) {
            status.className = 'rg-gl-status is-' + state;
            status.innerHTML = state === 'saving' ? '<i class="fa-solid fa-spinner fa-spin"></i>'
                : state === 'ok' ? '<i class="fa-solid fa-check"></i>'
                : state === 'error' ? '<i class="fa-solid fa-triangle-exclamation"></i>' : '';
            status.title = text || '';
        }

        function save() {
            var now = { label: label.value.trim(), desc: desc.value.trim() };
            if (now.label === saved.label && now.desc === saved.desc) return;
            saved = now; // evita un secondo invio identico mentre questo è in coda
            var data = new FormData();
            data.append('_csrf', csrf);
            data.append('kind', kind);
            data.append('term', row.dataset.term);
            data.append('label', now.label);
            data.append('description', now.desc);
            show('saving', 'Salvataggio…');
            queue = queue.then(function () { return fetch(url, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.j.message || res.j.error || 'Non salvato');
                    show(now.label || now.desc ? 'ok' : '', now.label || now.desc ? 'Salvato' : 'Voce tolta');
                })
                .catch(function (err) { saved = { label: null, desc: null }; show('error', 'Non salvato: ' + err.message + '. Riprova.'); }); });
        }

        // Si salva quando si esce dalla riga (non a ogni campo: nome e descrizione vanno insieme)
        row.addEventListener('focusout', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { if (!row.contains(document.activeElement)) save(); }, 0);
        });
        // Invio nel nome: salva e passa alla descrizione
        label.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); desc.focus(); } });

        var use = row.querySelector('[data-gl-use]');
        if (use) {
            use.addEventListener('click', function () {
                desc.value = row.querySelector('[data-gl-pbi]').textContent.trim();
                grow(desc);
                desc.focus();
            });
        }
    });

    // Avviso se si chiude la pagina mentre una riga sta ancora salvando
    window.addEventListener('beforeunload', function (e) {
        if (form.querySelector('.rg-gl-status.is-saving')) { e.preventDefault(); e.returnValue = ''; }
    });
})();
