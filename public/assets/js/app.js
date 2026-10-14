/* Ramingo - JavaScript vanilla, nessuna dipendenza. */
(function () {
    'use strict';

    // Select che inviano il form al cambio (filtri, assegnazione aree)
    document.querySelectorAll('select[data-autosubmit], input[data-autosubmit-check]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    // Form che chiamano l'AI: blocca il doppio invio e mostra l'attesa
    document.querySelectorAll('form[data-busy]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button');
            if (!btn) return;
            btn.disabled = true;
            var label = btn.querySelector('span');
            if (label) label.textContent = 'Attendere…';
        });
    });

    // Impostazioni AI: campo indirizzo ed etichetta del modello in base al fornitore
    var provider = document.querySelector('[data-ai-provider]');
    if (provider) {
        var sync = function () {
            var opt = provider.options[provider.selectedIndex];
            var box = document.querySelector('[data-ai-endpoint]');
            var lbl = document.querySelector('[data-ai-model-label]');
            if (box) box.hidden = opt.dataset.endpointEditable !== '1';
            if (lbl) lbl.textContent = opt.dataset.modelLabel;
        };
        provider.addEventListener('change', sync);
        sync();
    }

    // Modulo descrizioni: clic su un tag già usato = lo aggiunge al campo (se non c'è già)
    document.querySelectorAll('[data-add-tag]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.closest('form').querySelector('[data-tag-input]');
            if (!input) return;
            var tags = input.value.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
            var tag = btn.dataset.addTag;
            if (tags.map(function (t) { return t.toLowerCase(); }).indexOf(tag.toLowerCase()) === -1) tags.push(tag);
            input.value = tags.join(', ');
            input.focus();
        });
    });

    // Pagine con operazioni in corso (es. sincronizzazione): ricarica automatica
    var auto = document.querySelector('[data-autorefresh]');
    if (auto) {
        setTimeout(function () { window.location.reload(); }, (parseInt(auto.dataset.autorefresh, 10) || 5) * 1000);
    }

    // Filtri a tendina del catalogo (<details data-drop>): uno aperto alla volta,
    // chiusura con clic fuori o Esc, casella per filtrare le voci lunghe
    var drops = document.querySelectorAll('details[data-drop]');
    drops.forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (!d.open) return;
            drops.forEach(function (o) { if (o !== d) o.open = false; });
            var find = d.querySelector('[data-drop-find]');
            if (find) find.focus();
        });
        var find = d.querySelector('[data-drop-find]');
        if (find) {
            find.addEventListener('input', function () {
                var q = find.value.trim().toLowerCase();
                d.querySelectorAll('[data-drop-item]').forEach(function (a) {
                    a.hidden = q !== '' && a.textContent.toLowerCase().indexOf(q) === -1;
                });
            });
        }
    });
    if (drops.length) {
        document.addEventListener('click', function (e) {
            drops.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            drops.forEach(function (d) {
                if (d.open) { d.open = false; d.querySelector('summary').focus(); }
            });
        });
    }

    // Pagina iniziale: la ricerca dell'intestazione compare quando quella grande esce dallo schermo
    var homeForm = document.querySelector('[data-home-search]');
    var docked = document.querySelector('.rg-search-docked');
    if (homeForm && docked) {
        var homeInput = homeForm.querySelector('input[name="q"]');
        var dockInput = docked.querySelector('input[name="q"]');
        var toggle = function (show) {
            docked.classList.toggle('is-visible', show);
            if (show && dockInput.value === '' && homeInput.value !== '') dockInput.value = homeInput.value;
            if (!show && homeInput.value === '' && dockInput.value !== '') homeInput.value = dockInput.value;
        };
        if ('IntersectionObserver' in window) {
            var header = document.querySelector('.rg-header');
            new IntersectionObserver(function (entries) {
                toggle(!entries[0].isIntersecting);
            }, { rootMargin: '-' + (header ? header.offsetHeight : 0) + 'px 0px 0px 0px' }).observe(homeForm);
        } else {
            toggle(true);
        }
    }

    // Suggerimenti istantanei: per ogni barra di ricerca (intestazione e pagina iniziale)
    var base = document.body.dataset.base.replace(/\/$/, '');

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    document.querySelectorAll('form[data-suggest]').forEach(function (form) {
        var input = form.querySelector('input[name="q"]');
        var box = form.querySelector('.rg-suggest');
        if (!input || !box) return;
        var timer = null;
        var controller = null;

        function render(data) {
            if (!data.results.length) {
                box.innerHTML = '<div class="rg-suggest-empty">Nessun risultato immediato: premi Invio per la ricerca completa.</div>';
            } else {
                var head = data.concepts.length
                    ? '<div class="rg-suggest-head"><span class="src-badge src-auto"><i class="fa-solid fa-wand-magic-sparkles"></i> Interpretazione automatica</span> '
                      + data.concepts.map(function (c) { return esc(c.label); }).join(data.mode === 'all' ? ' + ' : ' / ') + '</div>'
                    : '';
                box.innerHTML = head + data.results.map(function (r) {
                    return '<a class="rg-suggest-item" href="' + esc(r.url) + '"><strong>' + esc(r.name) + '</strong>'
                        + '<span>' + esc(r.kind) + ' · ' + esc(r.workspace) + '</span></a>';
                }).join('');
            }
            box.hidden = false;
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { box.hidden = true; return; }
            timer = setTimeout(function () {
                if (controller) controller.abort();
                controller = new AbortController();
                fetch(base + '/api/search?q=' + encodeURIComponent(q), { signal: controller.signal, headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                    .then(render)
                    .catch(function () { /* silenzioso: resta la ricerca classica */ });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!form.contains(e.target)) box.hidden = true;
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') box.hidden = true;
        });
    });
})();
