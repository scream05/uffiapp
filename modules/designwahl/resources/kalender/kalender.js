/**
 * Kalender: beim Aufruf zum heutigen Tag springen, damit man nicht erst nach unten scrollen muss.
 * Nur einmal je Seitenaufruf (nicht beim Blättern) und nur, wenn die heutige Zeile nicht schon ganz zu sehen ist.
 */
(function () {
    var lauf = 0;

    function iso(d) {
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }

    // Unterkante der festen Kopfleiste (sonst läge „heute“ darunter)
    function obenFrei() {
        var unten = 0;
        document.querySelectorAll('#topbar, #topbar-first, .topbar').forEach(function (el) {
            var stil = getComputedStyle(el);
            if (stil.position !== 'fixed' && stil.position !== 'sticky') return;
            var r = el.getBoundingClientRect();
            if (r.top <= 0 && r.bottom > unten) unten = r.bottom;
        });
        return unten;
    }

    // Monat/Woche: ganze Wochenzeile, Liste: Tagesüberschrift
    function heuteZeile() {
        var el = document.querySelector('.fc [data-date="' + iso(new Date()) + '"]');
        if (!el) return null;
        return el.closest('.fc-row') || el.closest('tr') || el;
    }

    function springen() {
        // #calendar steht im HTML, FullCalendar (.fc) zeichnet erst danach
        if (!document.querySelector('[data-ui-widget="calendar.Calendar"], .fc')) return;
        var meinLauf = ++lauf;
        var benutzt = false;
        var merken = function () { benutzt = true; };
        ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(function (typ) {
            window.addEventListener(typ, merken, {once: true, passive: true});
        });

        // Termine kommen per AJAX nach und machen die Zeilen höher – darum ~8 s lang nachsehen, ob „heute“
        // noch zu sehen ist (auch nach dem eigenen Sprung), bis jemand selbst scrollt oder tippt
        var hoehe = -1, versuche = 0;
        (function pruefen() {
            if (meinLauf !== lauf || benutzt || ++versuche > 55) return;
            setTimeout(pruefen, 150);
            var fc = document.querySelector('.fc');
            var zeile = heuteZeile();
            var h = fc ? fc.offsetHeight : 0;
            var stabil = h === hoehe;
            hoehe = h;
            if (!zeile || !stabil) return;
            var r = zeile.getBoundingClientRect();
            var frei = obenFrei();
            if (r.top >= frei && r.bottom <= window.innerHeight) return; // schon sichtbar
            window.scrollTo({top: window.scrollY + r.top - frei - 12, behavior: 'auto'});
        })();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', springen);
    } else {
        springen();
    }
    // HumHub meldet jeden PJAX-Seitenwechsel
    if (window.jQuery) jQuery(document).on('humhub:ready', springen);
})();
