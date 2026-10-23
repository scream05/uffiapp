/* Neuigkeiten: Popup einmal zeigen; Schließen (×, Hintergrund, Esc, „Verstanden“) oder ein Link darin merkt es als gesehen */
window.nkPopup = function () {
    var el = document.getElementById('nk-popup');
    if (!el || el.dataset.nkBereit) {
        return;
    }
    el.dataset.nkBereit = 1;
    // Aus der Seitenleiste lösen (am Handy ist sie ausgeblendet bzw. wandert ins Fach)
    document.querySelectorAll('body > .nk-popup').forEach(function (alt) { if (alt !== el) alt.remove(); });
    document.body.appendChild(el);
    var vorher = null;
    var gemerkt = false;
    function merken() {
        if (gemerkt) {
            return;
        }
        gemerkt = true;
        var daten = new FormData();
        if (window.yii && yii.getCsrfParam()) {
            daten.append(yii.getCsrfParam(), yii.getCsrfToken());
        }
        try {
            fetch(el.dataset.gesehenUrl, {method: 'POST', body: daten, credentials: 'same-origin', keepalive: true});
        } catch (e) { /* nichts */ }
    }
    function zu() {
        merken();
        el.classList.remove('nk-auf');
        document.documentElement.classList.remove('nk-offen');
        document.removeEventListener('keydown', taste);
        setTimeout(function () { el.remove(); }, 250);
        if (vorher && vorher.focus) {
            vorher.focus();
        }
    }
    function taste(e) {
        if (e.key === 'Escape') {
            zu();
        } else if (e.key === 'Tab') {
            // Fokus im Dialog halten
            var f = el.querySelectorAll('a[href], button');
            if (!f.length) return;
            if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
            else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        }
    }
    el.querySelectorAll('[data-nk-zu]').forEach(function (b) { b.addEventListener('click', zu); });
    el.querySelectorAll('[data-nk-gesehen]').forEach(function (a) {
        a.addEventListener('click', function () {
            merken();
            el.remove();
            document.documentElement.classList.remove('nk-offen');
            document.removeEventListener('keydown', taste);
        });
    });
    // Kurz nach dem Laden einblenden, damit die Startseite schon steht
    setTimeout(function () {
        if (!document.body.contains(el)) return;
        vorher = document.activeElement;
        el.hidden = false;
        document.documentElement.classList.add('nk-offen');
        requestAnimationFrame(function () { el.classList.add('nk-auf'); });
        document.addEventListener('keydown', taste);
        var knopf = el.querySelector('.nk-popup-fuss .btn-primary');
        if (knopf) knopf.focus({preventScroll: true});
    }, 600);
};

/* Willkommensrundgang: Schritte mit Weiter/Zurück; Fertig, Überspringen (×, Esc) oder ein Zielknopf merkt ihn als gesehen */
window.nkRundgang = function () {
    var el = document.getElementById('nk-rundgang');
    if (!el || el.dataset.nkBereit) {
        return;
    }
    el.dataset.nkBereit = 1;
    document.querySelectorAll('body > .nk-rundgang').forEach(function (alt) { if (alt !== el) alt.remove(); });
    document.body.appendChild(el);
    var schritte = el.querySelectorAll('[data-nk-rg-schritt]');
    var punkte = el.querySelectorAll('.nk-rg-punkte span');
    var zurueck = el.querySelector('[data-nk-rg-zurueck]');
    var weiter = el.querySelector('[data-nk-rg-weiter]');
    var stand = el.querySelector('[data-nk-rg-stand]');
    var nr = 0;
    var gemerkt = false;
    function merken() {
        if (gemerkt) return;
        gemerkt = true;
        var daten = new FormData();
        if (window.yii && yii.getCsrfParam()) {
            daten.append(yii.getCsrfParam(), yii.getCsrfToken());
        }
        try {
            fetch(el.dataset.gesehenUrl, {method: 'POST', body: daten, credentials: 'same-origin', keepalive: true});
        } catch (e) { /* nichts */ }
    }
    function zeigen(neu) {
        nr = Math.max(0, Math.min(schritte.length - 1, neu));
        schritte.forEach(function (s, i) { s.hidden = i !== nr; });
        punkte.forEach(function (p, i) { p.classList.toggle('aktiv', i === nr); });
        zurueck.hidden = nr === 0;
        weiter.textContent = nr === schritte.length - 1 ? weiter.dataset.fertig : weiter.dataset.weiter;
        el.setAttribute('aria-labelledby', 'nk-rg-titel-' + nr);
        stand.textContent = stand.dataset.vorlage.replace('{nr}', nr + 1);
        el.querySelector('.nk-popup-karte').scrollTop = 0;
    }
    function zu(sanft) {
        merken();
        document.documentElement.classList.remove('nk-offen');
        document.removeEventListener('keydown', taste);
        if (!sanft) {
            el.remove();
            return;
        }
        el.classList.remove('nk-auf');
        setTimeout(function () { el.remove(); }, 250);
    }
    function taste(e) {
        if (e.key === 'Escape') {
            zu(true);
        } else if (e.key === 'ArrowRight' && !e.target.closest('input, textarea')) {
            zeigen(nr + 1);
        } else if (e.key === 'ArrowLeft' && !e.target.closest('input, textarea')) {
            zeigen(nr - 1);
        } else if (e.key === 'Tab') {
            var f = Array.prototype.filter.call(el.querySelectorAll('a[href], button'), function (b) { return b.offsetParent !== null; });
            if (!f.length) return;
            if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
            else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        }
    }
    weiter.addEventListener('click', function () {
        if (nr === schritte.length - 1) zu(true); else zeigen(nr + 1);
    });
    zurueck.addEventListener('click', function () { zeigen(nr - 1); });
    el.querySelector('[data-nk-rg-zu]').addEventListener('click', function () { zu(true); });
    el.querySelectorAll('.nk-rg-ziel').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var fab = document.getElementById('ki-fab');
            if (a.hasAttribute('data-nk-rg-uffi') && fab && fab.offsetParent !== null) { // Uffi im Chatfenster öffnen
                e.preventDefault();
                zu(false);
                fab.click();
                return;
            }
            zu(false);
        });
    });
    // Wischen am Handy
    var startX = null;
    el.addEventListener('touchstart', function (e) { startX = e.touches.length === 1 ? e.touches[0].clientX : null; }, {passive: true});
    el.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var dx = e.changedTouches[0].clientX - startX;
        startX = null;
        if (Math.abs(dx) > 60) zeigen(nr + (dx < 0 ? 1 : -1));
    }, {passive: true});
    zeigen(0);
    setTimeout(function () {
        if (!document.body.contains(el)) return;
        el.hidden = false;
        document.documentElement.classList.add('nk-offen');
        requestAnimationFrame(function () { el.classList.add('nk-auf'); });
        document.addEventListener('keydown', taste);
        weiter.focus({preventScroll: true});
    }, 600);
};
