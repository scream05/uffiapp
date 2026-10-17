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

    function svg(icon) {
        return '<svg viewBox="0 0 24 24" aria-hidden="true">' + (symbole[icon] || wolke) + '</svg>';
    }

    function esc(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    var aktuell = null; // zuletzt geladenes aktuelles Wetter (für das Fenster)

    function wetter(teil, url) {
        var feld = teil.querySelector('.dn-wetter');
        if (!feld || !url) return;
        fetch(url, {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (w) {
                if (w.fehler || typeof w.temperatur !== 'number') return;
                aktuell = w;
                feld.innerHTML = svg(w.icon)
                    + '<span class="dn-wetter-grad">' + w.temperatur + '°</span>'
                    + '<span class="dn-wetter-text">' + esc(w.text) + '</span>';
                feld.title = 'Vorhersage für heute und morgen';
                feld.setAttribute('aria-label', 'Wetter in Uffenheim: ' + w.text + ', ' + w.temperatur + ' Grad. Vorhersage öffnen');
                feld.hidden = false;
            })
            .catch(function () { /* ohne Wetter weiter */ });
    }

    // --- Fenster mit Vorhersage für heute und morgen (Handy: von unten, Computer: unter der Kopfleiste) ---
    var vorhersage = null, vorhersageZeit = 0, wetterKnopf = null;

    function wetterFach() { return document.getElementById('dn-wetterfach'); }

    function prozent(p) {
        return p > 0 ? '<span class="dn-wf-regen">' + p + ' %</span>' : '<span class="dn-wf-regen dn-wf-leer">–</span>';
    }

    function tagKarte(titel, t, mitZeiten) {
        if (!t) return '';
        var h = '<section class="dn-wf-tag"><h3>' + titel + '</h3>'
            + '<div class="dn-wf-uebersicht">' + svg(t.icon)
            + '<div><span class="dn-wf-maxmin"><strong>' + t.max + '°</strong> / ' + t.min + '°</span>'
            + '<span class="dn-wf-lage">' + esc(t.text) + '</span></div></div>'
            + '<ul class="dn-wf-werte">'
            + '<li><i class="fa fa-tint" aria-hidden="true"></i>Regen ' + (t.regen > 0 ? 'bis ' + t.regen + ' %' : 'kaum')
            + (t.niederschlag >= 0.1 ? ', ' + String(t.niederschlag).replace('.', ',') + ' l/m²' : '') + '</li>'
            + '<li><i class="fa fa-flag-o" aria-hidden="true"></i>Wind bis ' + t.wind + ' km/h'
            + (t.boeen >= 40 ? ', Böen ' + t.boeen + ' km/h' : '') + '</li>'
            + (t.aufgang ? '<li><i class="fa fa-sun-o" aria-hidden="true"></i>' + t.aufgang + ' – ' + t.untergang + ' Uhr</li>' : '')
            + '</ul>';
        if (mitZeiten && t.zeiten.length) {
            h += '<ul class="dn-wf-zeiten">' + t.zeiten.map(function (z) {
                return '<li title="' + esc(z.text) + '"><span class="dn-wf-zeit">' + z.name + '</span>' + svg(z.icon)
                    + '<strong>' + z.temperatur + '°</strong>' + prozent(z.regen) + '</li>';
            }).join('') + '</ul>';
        }
        return h + '</section>';
    }

    function wetterZeigen(d) {
        var morgen = new Date();
        morgen.setDate(morgen.getDate() + 1);
        var h = '';
        if (aktuell) {
            h += '<div class="dn-wf-jetzt">' + svg(aktuell.icon) + '<span class="dn-wf-grad">' + aktuell.temperatur + '°</span>'
                + '<span><strong>' + esc(aktuell.text) + '</strong><small>Gemessen in ' + esc(aktuell.station)
                + (aktuell.zeit ? ' um ' + esc(aktuell.zeit) + ' Uhr' : '') + '</small></span></div>';
        }
        if (d.warnungen && d.warnungen.length) {
            h += d.warnungen.map(function (w) {
                return '<details class="dn-wf-warnung dn-wf-' + esc(w.stufe) + '"><summary><i class="fa fa-exclamation-triangle" aria-hidden="true"></i>'
                    + '<span><strong>' + esc(w.titel) + '</strong><small>' + esc(w.von) + (w.bis ? ' bis ' + esc(w.bis) : '') + ' Uhr</small></span></summary>'
                    + '<p>' + esc(w.text) + '</p></details>';
            }).join('');
        } else if (d.warnungen) {
            h += '<p class="dn-wf-ruhig"><i class="fa fa-check-circle" aria-hidden="true"></i>Keine Wetterwarnungen für Uffenheim</p>';
        }
        h += tagKarte('Heute', d.heute, false);
        if (d.stunden.length) {
            h += '<ul class="dn-wf-stunden" aria-label="Stündliche Vorhersage">' + d.stunden.map(function (s) {
                return '<li title="' + esc(s.text) + '"><span class="dn-wf-zeit">' + s.zeit + '</span>' + svg(s.icon)
                    + '<strong>' + s.temperatur + '°</strong>' + prozent(s.regen) + '</li>';
            }).join('') + '</ul>';
        }
        h += tagKarte('Morgen, ' + tage[morgen.getDay()] + ' ' + morgen.getDate() + '. ' + monate[morgen.getMonth()], d.morgen, true);
        h += '<p class="dn-wf-quelle">Vorhersage und Warnungen: Deutscher Wetterdienst (über Bright Sky), Stand ' + esc(d.stand)
            + ' Uhr. Prozentwerte: Regenwahrscheinlichkeit.</p>';
        return h;
    }

    function wetterFenster(auf) {
        var f = wetterFach();
        if (!f) return;
        f.hidden = !auf;
        document.documentElement.classList.toggle('dn-wetter-offen', auf);
        if (wetterKnopf) wetterKnopf.setAttribute('aria-expanded', auf ? 'true' : 'false');
        if (!auf) {
            if (wetterKnopf && document.contains(wetterKnopf)) wetterKnopf.focus({preventScroll: true});
            return;
        }
        f.querySelector('.dn-mehr-zu').focus({preventScroll: true});
        var inhalt = f.querySelector('.dn-wf-inhalt');
        if (vorhersage && Date.now() - vorhersageZeit < 15 * 60 * 1000) {
            inhalt.innerHTML = wetterZeigen(vorhersage);
            return;
        }
        var vorlage = document.getElementById('dn-hero-vorlage');
        inhalt.innerHTML = '<p class="dn-wf-hinweis"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Vorhersage wird geladen …</p>';
        fetch(vorlage.getAttribute('data-vorhersage-url'), {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.fehler) throw new Error();
                vorhersage = d;
                vorhersageZeit = Date.now();
                inhalt.innerHTML = wetterZeigen(d);
            })
            .catch(function () {
                inhalt.innerHTML = '<p class="dn-wf-hinweis">Die Vorhersage ist gerade nicht erreichbar. Bitte später noch einmal versuchen.</p>';
            });
    }

    document.addEventListener('click', function (e) {
        var knopf = e.target.closest('.dn-wetter');
        if (knopf) {
            wetterKnopf = knopf;
            wetterFenster(true);
        } else if (e.target.closest('[data-dn-wetter-zu]')) {
            wetterFenster(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && wetterFach() && !wetterFach().hidden) wetterFenster(false);
    });

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
