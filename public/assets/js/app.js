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
            // Attese lunghe (AI): logo con le lancette che cercano il nord + testo per i lettori di schermo
            var wait = form.getAttribute('data-busy-wait');
            if (wait && !form.querySelector('.rg-wait')) {
                var box = document.createElement('div');
                box.className = 'rg-wait';
                box.setAttribute('role', 'status');
                var img = document.createElement('img');
                img.src = document.body.dataset.base.replace(/\/$/, '') + '/assets/img/logo_attesa.svg';
                img.alt = ''; img.width = 40; img.height = 38;
                var txt = document.createElement('span');
                txt.textContent = wait;
                box.append(img, txt);
                form.appendChild(box);
            }
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

    // Azioni irreversibili: chiedono conferma
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    // Sinonimi di ricerca: cerca una parola tra i gruppi
    var synFind = document.querySelector('[data-syn-find]');
    if (synFind) {
        synFind.addEventListener('input', function () {
            var q = synFind.value.trim().toLowerCase();
            document.querySelectorAll('[data-syn-row]').forEach(function (row) {
                row.hidden = q !== '' && row.querySelector('input').value.toLowerCase().indexOf(q) === -1;
            });
        });
        synFind.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    }

    // Pagine con operazioni in corso (es. sincronizzazione): ricarica automatica
    var auto = document.querySelector('[data-autorefresh]');
    if (auto) {
        setTimeout(function () { window.location.reload(); }, (parseInt(auto.dataset.autorefresh, 10) || 5) * 1000);
    }

    // Colonna filtri: elenchi lunghi accorciati («Mostra tutti») e casella per cercarvi dentro
    function showList(box, all) {
        box.querySelectorAll('[data-extra]').forEach(function (item) { item.hidden = !all; });
        var more = box.querySelector('[data-facet-more]');
        if (more) {
            if (!more.dataset.more) more.dataset.more = more.textContent;
            more.textContent = all ? more.dataset.less : more.dataset.more;
            more.dataset.open = all ? '1' : '';
            more.hidden = false;
        }
    }
    document.querySelectorAll('[data-facet-more]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showList(btn.closest('details'), btn.dataset.open !== '1');
            stickyCheck();
        });
    });
    document.querySelectorAll('[data-facet-find]').forEach(function (input) {
        var box = input.closest('details');
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            if (q === '') { // si torna all'elenco accorciato
                box.querySelectorAll('[data-facet-item]').forEach(function (item) { item.hidden = item.hasAttribute('data-extra'); });
                showList(box, false);
            } else {        // si cerca in tutte le voci, anche quelle nascoste
                box.querySelectorAll('[data-facet-item]').forEach(function (item) {
                    item.hidden = item.textContent.toLowerCase().indexOf(q) === -1;
                });
                var more = box.querySelector('[data-facet-more]');
                if (more) more.hidden = true;
            }
            stickyCheck();
        });
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    });

    // La colonna resta ferma solo se ci sta tutta nello schermo: altrimenti scorre con la pagina
    var sideCol = document.querySelector('.rg-browse-side');
    function stickyCheck() {
        if (!sideCol) return;
        sideCol.classList.remove('too-tall');
        var top = parseFloat(getComputedStyle(sideCol).top) || 0;
        if (sideCol.offsetHeight + top > window.innerHeight) sideCol.classList.add('too-tall');
    }
    if (sideCol) {
        stickyCheck();
        window.addEventListener('resize', stickyCheck);
        sideCol.querySelectorAll('details').forEach(function (d) { d.addEventListener('toggle', stickyCheck); });
    }

    // Nascondi / mostra la colonna filtri (su computer); la scelta resta in un cookie
    var browse = document.querySelector('[data-browse]');
    function setSide(hidden) {
        browse.classList.toggle('side-hidden', hidden);
        document.cookie = 'rg_side=' + (hidden ? 'hidden' : 'shown') + '; path=/; max-age=31536000; SameSite=Lax';
        if (!hidden) stickyCheck();
    }
    document.querySelectorAll('[data-side-hide]').forEach(function (b) { b.addEventListener('click', function () { setSide(true); }); });
    document.querySelectorAll('[data-side-show]').forEach(function (b) { b.addEventListener('click', function () { setSide(false); }); });

    // Su schermi stretti la colonna filtri parte chiusa (pulsante «Filtri»), se non ci sono filtri attivi
    var side = document.querySelector('[data-side]');
    if (side && window.matchMedia('(max-width: 991px)').matches && !side.querySelector('input:checked:not([value=""])')) {
        side.open = false;
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
            var scopes = (data.scopes || []).map(function (sc) {
                return '<a class="rg-suggest-item rg-suggest-scope" href="' + esc(sc.url) + '">'
                    + '<span class="rg-scope-type">' + esc(sc.type) + '</span> <strong>' + esc(sc.label) + '</strong>'
                    + ' <span class="rg-scope-n">' + esc(sc.n) + ' report</span></a>';
            }).join('');
            if (scopes) scopes = '<div class="rg-suggest-group">Vai ai report con</div>' + scopes;
            if (!data.results.length && scopes) {
                box.innerHTML = scopes;
            } else if (!data.results.length) {
                box.innerHTML = '<div class="rg-suggest-empty">Nessun risultato immediato: premi Invio per la ricerca completa.</div>';
            } else {
                var head = data.concepts.length
                    ? '<div class="rg-suggest-head"><span class="src-badge src-auto"><i class="fa-solid fa-wand-magic-sparkles"></i> Interpretazione automatica</span> '
                      + data.concepts.map(function (c) { return esc(c.label); }).join(data.mode === 'all' ? ' + ' : ' / ') + '</div>'
                    : '';
                box.innerHTML = scopes + head + data.results.map(function (r) {
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
