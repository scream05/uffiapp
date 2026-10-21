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
