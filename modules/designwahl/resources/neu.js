/**
 * Design „Uffi frisch“: Begrüßungskarte mit Wetter oben auf der Startseite (auch nach PJAX-Wechsel)
 * und das Fach „Mehr“ des Hauptmenüs.
 */
(function () {
    var tage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    var monate = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    function gruss(h) {
        if (h < 5) return 'Gute Nacht,';
        if (h < 11) return 'Guten Morgen,';
        if (h < 18) return 'Hallo,';
        return 'Guten Abend,';
    }

    // Wettersymbole (24×24, Linien in Textfarbe)
    var sonne = '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M5.3 18.7l1.6-1.6M17.1 6.9l1.6-1.6"/>';
    var mond = '<path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/>';
    var wolke = '<path d="M7 18.5h10a4 4 0 0 0 .6-7.95A5.5 5.5 0 0 0 7.1 10.6 4 4 0 0 0 7 18.5z"/>';
    var wolkeOben = '<path d="M7 15h10a4 4 0 0 0 .6-7.95A5.5 5.5 0 0 0 7.1 7.1 4 4 0 0 0 7 15z"/>'; // Platz für Niederschlag
    var symbole = {
        'clear-day': sonne,
        'clear-night': mond,
        'partly-cloudy-day': '<circle cx="8.5" cy="8.5" r="3"/><path d="M8.5 2.8v1.4M2.8 8.5h1.4M4.5 4.5l1 1M12.5 4.5l-1 1"/><path d="M9 20h8.5a3.5 3.5 0 0 0 .5-6.96 4.8 4.8 0 0 0-9.1-1.1A3.6 3.6 0 0 0 9 20z"/>',
        'partly-cloudy-night': '<path d="M11 6.5A4.5 4.5 0 0 1 5.6 2.3 4.6 4.6 0 1 0 11 8"/><path d="M9 20h8.5a3.5 3.5 0 0 0 .5-6.96 4.8 4.8 0 0 0-9.1-1.1A3.6 3.6 0 0 0 9 20z"/>',
        'cloudy': wolke,
        'fog': '<path d="M4 9h16M3 13h18M5 17h14"/>',
        'wind': '<path d="M3 9h11a3 3 0 1 0-3-3M3 15h15a3 3 0 1 1-3 3M3 12h8"/>',
        'rain': wolkeOben + '<path d="M8 18l-1 3M12 18l-1 3M16 18l-1 3"/>',
        'sleet': wolkeOben + '<path d="M8 18l-1 3M16 18l-1 3"/><circle cx="12" cy="19.5" r=".6"/>',
        'snow': wolkeOben + '<circle cx="8" cy="19" r=".7"/><circle cx="12" cy="20.5" r=".7"/><circle cx="16" cy="19" r=".7"/>',
        'hail': wolkeOben + '<circle cx="8" cy="19" r="1"/><circle cx="12" cy="20.5" r="1"/><circle cx="16" cy="19" r="1"/>',
        'thunderstorm': wolkeOben + '<path d="M12.5 16l-2 3.5h3l-2 3.5"/>'
    };

    function wetter(teil, url) {
        var feld = teil.querySelector('.dn-wetter');
        if (!feld || !url) return;
        fetch(url, {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (w) {
                if (w.fehler || typeof w.temperatur !== 'number') return;
                feld.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + (symbole[w.icon] || wolke) + '</svg>'
                    + '<span class="dn-wetter-grad">' + w.temperatur + '°</span>'
                    + '<span class="dn-wetter-text">' + w.text + '</span>';
                feld.title = 'Uffenheim, ' + w.text + ', ' + w.temperatur + ' °C – Wetterstation ' + w.station
                    + (w.zeit ? ', ' + w.zeit + ' Uhr' : '') + '. Quelle: Deutscher Wetterdienst (über Bright Sky)';
                feld.setAttribute('aria-label', 'Wetter in Uffenheim: ' + w.text + ', ' + w.temperatur + ' Grad');
                feld.hidden = false;
            })
            .catch(function () { /* ohne Wetter weiter */ });
    }

    // Gäste: Hinweis in der Begrüßungskarte einklappbar, Zustand bleibt im Browser gespeichert
    function klappe(karte) {
        var knopf = karte && karte.querySelector('.dn-hero-klapp');
        if (!knopf) return;
        function setzen(zu) {
            karte.classList.toggle('dn-hero-zu', zu);
            knopf.setAttribute('aria-expanded', zu ? 'false' : 'true');
            var t = zu ? 'Hinweis aufklappen' : 'Hinweis einklappen';
            knopf.setAttribute('aria-label', t);
            knopf.title = t;
        }
        var zu = false;
        try { zu = localStorage.getItem('dn-hero-zu') === '1'; } catch (e) { /* ohne Speicher */ }
        setzen(zu);
        knopf.addEventListener('click', function () {
            zu = !zu;
            setzen(zu);
            try { localStorage.setItem('dn-hero-zu', zu ? '1' : '0'); } catch (e) { /* ohne Speicher */ }
        });
    }

    function einsetzen() {
        var vorlage = document.getElementById('dn-hero-vorlage');
        var ziel = document.querySelector('#layout-content .layout-content-container');
        var startseite = /^\/(dashboard(\/dashboard)?)?\/?$/.test(location.pathname);
        if (!vorlage || !ziel || !startseite || ziel.querySelector('.dn-hero')) return;

        var teil = vorlage.content.cloneNode(true);
        var jetzt = new Date();
        teil.querySelector('.dn-hero-datum').textContent = tage[jetzt.getDay()] + ', ' + jetzt.getDate() + '. ' + monate[jetzt.getMonth()];
        var g = teil.querySelector('.dn-hero-gruss'); // fehlt bei Gästen
        if (g) g.textContent = gruss(jetzt.getHours());
        ziel.insertBefore(teil, ziel.firstChild);
        klappe(ziel.querySelector('.dn-hero'));
        wetter(ziel.querySelector('.dn-hero'), vorlage.getAttribute('data-wetter-url'));
    }

    // --- Handy: Tab-Leiste unten zeigt alle Reiter, die Platz haben ---
    // HumHubs Überlaufprüfung (ui.topNavigation) misst am Handy falsch und schiebt Reiter in ein
    // „Menü“-Symbol oder lässt sie rechts abschneiden. Hier: alle Reiter zurück in die Leiste (sie teilen
    // sich die Breite, siehe neu.css); nur wenn es dann noch zu eng ist, wandern die letzten Reiter vor
    // „Mehr“ ins Menü-Symbol – „Mehr“ bleibt immer sichtbar.
    var handy = window.matchMedia('(max-width: 575.98px)');

    function leiste() {
        var nav = document.getElementById('top-menu-nav');
        var sub = document.getElementById('top-menu-sub');
        var liste = document.getElementById('top-menu-sub-dropdown');
        if (!handy.matches || !nav || !sub || !liste) return;
        Array.prototype.slice.call(liste.children).forEach(function (li) {
            var i = li.querySelector('a > i');
            if (i && !(i.nextElementSibling && i.nextElementSibling.tagName === 'BR')) {
                i.insertAdjacentElement('afterend', document.createElement('br'));
            }
            nav.insertBefore(li, sub);
        });
        sub.style.display = 'none';
        var mehrLi = nav.querySelector('[data-dn-mehr]');
        mehrLi = mehrLi ? mehrLi.closest('li') : null;
        if (mehrLi && mehrLi !== sub.previousElementSibling) nav.insertBefore(mehrLi, sub); // „Mehr“ zuletzt
        var zuEng = function () { return nav.scrollWidth > nav.clientWidth + 1; };
        while (zuEng()) {
            var reiter = nav.querySelectorAll(':scope > li.top-menu-item');
            var letzter = null;
            for (var k = reiter.length - 1; k >= 0; k--) {
                if (reiter[k] !== mehrLi) { letzter = reiter[k]; break; }
            }
            if (!letzter) break;
            sub.style.display = '';
            letzter.querySelectorAll('br').forEach(function (br) { br.remove(); });
            liste.insertBefore(letzter, liste.firstChild);
        }
    }

    function leisteSpaeter() { setTimeout(leiste, 120); } // nach HumHubs eigener Prüfung (50 ms bzw. resize)
    window.addEventListener('resize', leisteSpaeter);
    window.addEventListener('load', leisteSpaeter);
    if (handy.addEventListener) handy.addEventListener('change', leisteSpaeter);
    leisteSpaeter();

    // --- Fach „Mehr“ (Handy: von unten, Computer: unter der Kopfleiste) ---
    function fach() { return document.getElementById('dn-mehr'); }

    function mehr(auf) {
        var f = fach();
        if (!f) return;
        f.hidden = !auf;
        document.documentElement.classList.toggle('dn-mehr-offen', auf);
        document.querySelectorAll('[data-dn-mehr]').forEach(function (a) { a.setAttribute('aria-expanded', auf ? 'true' : 'false'); });
        if (auf) {
            var erstes = f.querySelector('.dn-ziel');
            if (erstes) erstes.focus({preventScroll: true});
        }
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-dn-mehr]')) {
            e.preventDefault();
            e.stopPropagation();
            mehr(fach() && fach().hidden);
            return;
        }
        var f = fach();
        if (!f || f.hidden) return;
        if (e.target.closest('[data-dn-zu]')) {
            mehr(false);
            return;
        }
        var uffi = e.target.closest('[data-dn-uffi]');
        var fab = document.getElementById('ki-fab');
        if (uffi && fab) { // Uffi im Chatfenster öffnen statt auf die Chat-Seite
            e.preventDefault();
            mehr(false);
            fab.click();
            return;
        }
        if (e.target.closest('.dn-mehr a[href]')) mehr(false);
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && fach() && !fach().hidden) mehr(false);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', einsetzen);
    } else {
        einsetzen();
    }
    // HumHub meldet jeden PJAX-Seitenwechsel
    if (window.jQuery) jQuery(document).on('humhub:ready', einsetzen);
})();
