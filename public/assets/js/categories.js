/* Ramingo - gestione categorie: trascinamento, rinomina con doppio clic, filtro. Nessuna dipendenza. */
(function () {
    'use strict';
    var base = document.body.dataset.base.replace(/\/$/, '');
    var csrf = document.getElementById('rg-csrf');
    var toast = document.getElementById('rg-toast');
    if (!csrf) return;

    // Mantiene la posizione di scorrimento dopo il ricaricamento
    try {
        var y = sessionStorage.getItem('rg-cat-scroll');
        if (y !== null) { window.scrollTo(0, parseInt(y, 10)); sessionStorage.removeItem('rg-cat-scroll'); }
    } catch (e) { /* storage non disponibile: pazienza */ }

    function show(msg, ok) {
        toast.textContent = msg;
        toast.className = 'rg-toast ' + (ok ? 'ok' : 'ko');
        toast.hidden = false;
        setTimeout(function () { toast.hidden = true; }, 4000);
    }

    function post(path, fields) {
        var data = new FormData();
        data.append('_csrf', csrf.value);
        Object.keys(fields).forEach(function (k) { data.append(k, fields[k] == null ? '' : fields[k]); });
        return fetch(base + '/admin/categorie/' + path, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    try { sessionStorage.setItem('rg-cat-scroll', String(window.scrollY)); } catch (e) {}
                    window.location.reload();
                } else {
                    show(res.message || 'Operazione non riuscita.', false);
                }
            })
            .catch(function () { show('Errore di comunicazione con il server.', false); });
    }

    // ---- Trascinamento ----
    var dragged = null;
    document.querySelectorAll('[data-drag-kind]').forEach(function (el) {
        el.addEventListener('dragstart', function (e) {
            e.stopPropagation(); // gli elementi sono annidati: prende solo il più interno
            dragged = { kind: el.dataset.dragKind, id: el.dataset.dragId };
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', el.dataset.dragId);
            el.classList.add('dragging');
            document.body.classList.add('rg-dragging-' + (dragged.kind === 'area' ? 'area' : 'item'));
        });
        el.addEventListener('dragend', function () {
            el.classList.remove('dragging');
            document.body.classList.remove('rg-dragging-area', 'rg-dragging-item');
            dragged = null;
        });
    });

    function accepts(target) {
        if (!dragged) return false;
        if (target.hasAttribute('data-drop-root')) return dragged.kind === 'area';
        if (target.hasAttribute('data-drop-none')) return dragged.kind !== 'area';
        return !(dragged.kind === 'area' && target.dataset.dropArea === dragged.id);
    }

    // Categoria sopra un'altra categoria: fascia alta = prima, fascia bassa = dopo, centro = dentro.
    // Le fasce sono calcolate sulla sola riga, non sulle sottocategorie.
    function where(zone, e) {
        if (!dragged || dragged.kind !== 'area' || !zone.hasAttribute('data-drop-area')) return 'inside';
        var row = zone.querySelector('.rg-node-row');
        var r = row.getBoundingClientRect();
        if (e.clientY > r.bottom) return 'inside';
        var y = (e.clientY - r.top) / r.height;
        return y < 0.33 ? 'before' : (y > 0.67 ? 'after' : 'inside');
    }

    function mark(zone, pos) {
        zone.classList.remove('drop-hover', 'drop-before', 'drop-after');
        if (pos) zone.classList.add(pos === 'inside' ? 'drop-hover' : 'drop-' + pos);
    }

    document.querySelectorAll('[data-drop-area], [data-drop-root], [data-drop-none]').forEach(function (zone) {
        zone.addEventListener('dragover', function (e) {
            if (!accepts(zone)) return;
            e.preventDefault();
            e.stopPropagation();
            mark(zone, where(zone, e));
        });
        zone.addEventListener('dragleave', function (e) {
            e.stopPropagation();
            mark(zone, null);
        });
        zone.addEventListener('drop', function (e) {
            if (!accepts(zone)) return;
            e.preventDefault();
            e.stopPropagation();
            var pos = where(zone, e);
            mark(zone, null);
            var d = dragged;
            if (pos === 'before' || pos === 'after') {
                // Riordino: stessa "cartella" della categoria di riferimento, prima o dopo di lei
                var parent = zone.parentElement.closest('[data-drop-area]');
                var ref = zone;
                if (pos === 'after') {
                    ref = zone.nextElementSibling;
                    if (ref && ref.dataset.dropArea === d.id) ref = ref.nextElementSibling; // dopo di sé = resta dov'è
                }
                post('sposta', { id: d.id, parent_id: parent ? parent.dataset.dropArea : '', before_id: ref ? ref.dataset.dropArea : '' });
            } else if (zone.hasAttribute('data-drop-root')) {
                post('sposta', { id: d.id, parent_id: '' });
            } else if (zone.hasAttribute('data-drop-none')) {
                post('assegna', { kind: d.kind, id: d.id, area_id: '' });
            } else if (d.kind === 'area') {
                post('sposta', { id: d.id, parent_id: zone.dataset.dropArea });
            } else {
                post('assegna', { kind: d.kind, id: d.id, area_id: zone.dataset.dropArea });
            }
        });
    });

    // ---- Frecce su/giù: riordino senza trascinare ----
    document.querySelectorAll('[data-order]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var li = btn.closest('[data-drop-area]');
            var parent = li.parentElement.closest('[data-drop-area]');
            var ref;
            if (btn.dataset.order === 'up') {
                ref = li.previousElementSibling;
                if (!ref) return;
            } else {
                var next = li.nextElementSibling;
                if (!next) return;
                ref = next.nextElementSibling; // prima di quella dopo la successiva (null = in fondo)
            }
            post('sposta', { id: li.dataset.dropArea, parent_id: parent ? parent.dataset.dropArea : '', before_id: ref ? ref.dataset.dropArea : '' });
        });
    });

    // ---- Rinomina con doppio clic ----
    document.querySelectorAll('[data-rename]').forEach(function (span) {
        span.addEventListener('dblclick', function () {
            var old = span.textContent;
            var input = document.createElement('input');
            input.className = 'form-control form-control-sm rg-rename';
            input.value = old;
            input.maxLength = 80;
            span.replaceWith(input);
            input.focus();
            input.select();
            var done = false;
            function finish(save) {
                if (done) return;
                done = true;
                var name = input.value.trim();
                if (save && name && name !== old) {
                    post('modifica', { id: span.dataset.rename, name: name });
                } else {
                    input.replaceWith(span);
                }
            }
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); finish(true); }
                if (e.key === 'Escape') { finish(false); }
            });
            input.addEventListener('blur', function () { finish(true); });
        });
    });

    // ---- Filtro workspace/report ----
    document.querySelectorAll('[data-filter-list]').forEach(function (input) {
        var list = document.querySelector(input.dataset.filterList);
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            list.querySelectorAll('[data-filter-text]').forEach(function (el) {
                el.hidden = q !== '' && el.dataset.filterText.indexOf(q) === -1;
                // gruppo trovato: aperto, e dentro si vedono solo i report che corrispondono
                if (q !== '' && !el.hidden && el.tagName === 'DETAILS') {
                    el.open = true;
                    // solo il nome del workspace e quello del report: il testo dei menu (nomi delle categorie) non conta
                    var wsMatch = (el.dataset.filterWs || '').indexOf(q) !== -1;
                    el.querySelectorAll('.rg-group-items li').forEach(function (li) {
                        li.hidden = !wsMatch && (li.dataset.filterName || '').indexOf(q) === -1;
                    });
                } else if (q === '') {
                    el.querySelectorAll('.rg-group-items li').forEach(function (li) { li.hidden = false; });
                }
            });
        });
    });
})();
