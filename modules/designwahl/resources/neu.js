/**
 * Design „Uffi frisch“: Begrüßungskarte mit Wetter oben auf der Startseite (auch nach PJAX-Wechsel)
 * und das Fach „Mehr“ des Hauptmenüs.
 */
(function () {
    var tage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    var monate = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    // Übersetzungen vom Server (widgets/DesignNeu::texte, window.dnText); bei Deutsch leer → deutsche Texte hier
    var dn = window.dnText || {};
    function t(text, werte) {
        var s = (dn.t && dn.t[text]) || text;
        Object.keys(werte || {}).forEach(function (k) { s = s.split('{' + k + '}').join(werte[k]); });
        return s;
    }
    var tx = t; // in tagKarte ist t der Tag

    function iso(d) {
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }

    // „Sonntag, 18. Oktober“ – andere Sprachen: vom Server (ICU), sonst Intl des Browsers
    function datum(d, morgen) {
        if (!dn.sprache) return tage[d.getDay()] + (morgen ? ' ' : ', ') + d.getDate() + '. ' + monate[d.getMonth()];
        var heute = new Date();
        var plus = Math.round((new Date(d.getFullYear(), d.getMonth(), d.getDate()) - new Date(heute.getFullYear(), heute.getMonth(), heute.getDate())) / 864e5);
        if (dn.tag === iso(heute) && plus === 0 && dn.heute) return dn.heute;
        if (dn.tag === iso(heute) && plus === 1 && dn.morgen) return dn.morgen;
        try {
            return d.toLocaleDateString(dn.sprache, {weekday: 'long', day: 'numeric', month: 'long'});
        } catch (e) {
            return d.toLocaleDateString();
        }
    }

    function gruss(h) {
        if (h < 5) return t('Gute Nacht,');
        if (h < 11) return t('Guten Morgen,');
        if (h < 18) return t('Hallo,');
        return t('Guten Abend,');
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
                feld.title = t('Vorhersage für heute und morgen');
                feld.setAttribute('aria-label', t('Wetter in Uffenheim: {text}, {grad} Grad. Vorhersage öffnen', {text: w.text, grad: w.temperatur}));
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
            + '<li><i class="fa fa-tint" aria-hidden="true"></i>' + esc(t.regen > 0 ? tx('Regen bis {prozent} %', {prozent: t.regen}) : tx('Regen kaum'))
            + (t.niederschlag >= 0.1 ? ', ' + esc(tx('{menge} l/m²', {menge: String(t.niederschlag).replace('.', dn.dezimal || ',')})) : '') + '</li>'
            + '<li><i class="fa fa-flag-o" aria-hidden="true"></i>' + esc(tx('Wind bis {wind} km/h', {wind: t.wind}))
            + (t.boeen >= 40 ? ', ' + esc(tx('Böen {boeen} km/h', {boeen: t.boeen})) : '') + '</li>'
            + (t.aufgang ? '<li><i class="fa fa-sun-o" aria-hidden="true"></i>' + esc(tx('{von} – {bis} Uhr', {von: t.aufgang, bis: t.untergang})) + '</li>' : '')
            + '</ul>';
        if (mitZeiten && t.zeiten.length) {
            h += '<ul class="dn-wf-zeiten">' + t.zeiten.map(function (z) {
                return '<li title="' + esc(z.text) + '"><span class="dn-wf-zeit">' + esc(z.name) + '</span>' + svg(z.icon)
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
                + '<span><strong>' + esc(aktuell.text) + '</strong><small>'
                + esc(aktuell.zeit ? t('Gemessen in {station} um {zeit} Uhr', {station: aktuell.station, zeit: aktuell.zeit})
                    : t('Gemessen in {station}', {station: aktuell.station})) + '</small></span></div>';
        }
        if (d.warnungen && d.warnungen.length) {
            h += d.warnungen.map(function (w) {
                return '<details class="dn-wf-warnung dn-wf-' + esc(w.stufe) + '"><summary><i class="fa fa-exclamation-triangle" aria-hidden="true"></i>'
                    + '<span><strong>' + esc(w.titel) + '</strong><small>' + esc(w.bis ? t('{von} bis {bis} Uhr', {von: w.von, bis: w.bis})
                        : t('{von} Uhr', {von: w.von})) + '</small></span></summary>'
                    + '<p>' + esc(w.text) + '</p></details>';
            }).join('');
        } else if (d.warnungen) {
            h += '<p class="dn-wf-ruhig"><i class="fa fa-check-circle" aria-hidden="true"></i>' + esc(t('Keine Wetterwarnungen für Uffenheim')) + '</p>';
        }
        h += tagKarte(esc(t('Heute')), d.heute, false);
        if (d.stunden.length) {
            h += '<ul class="dn-wf-stunden" aria-label="' + esc(t('Stündliche Vorhersage')) + '">' + d.stunden.map(function (s) {
                return '<li title="' + esc(s.text) + '"><span class="dn-wf-zeit">' + esc(s.zeit) + '</span>' + svg(s.icon)
                    + '<strong>' + s.temperatur + '°</strong>' + prozent(s.regen) + '</li>';
            }).join('') + '</ul>';
        }
        h += tagKarte(esc(t('Morgen, {datum}', {datum: datum(morgen, true)})), d.morgen, true);
        h += '<p class="dn-wf-quelle">' + esc(t('Vorhersage und Warnungen: Deutscher Wetterdienst (über Bright Sky), Stand {zeit} Uhr. Prozentwerte: Regenwahrscheinlichkeit.', {zeit: d.stand}))
            + '</p>';
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
        inhalt.innerHTML = '<p class="dn-wf-hinweis"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + esc(t('Vorhersage wird geladen …')) + '</p>';
        fetch(vorlage.getAttribute('data-vorhersage-url'), {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.fehler) throw new Error();
                vorhersage = d;
                vorhersageZeit = Date.now();
                inhalt.innerHTML = wetterZeigen(d);
            })
            .catch(function () {
                inhalt.innerHTML = '<p class="dn-wf-hinweis">' + esc(t('Die Vorhersage ist gerade nicht erreichbar. Bitte später noch einmal versuchen.')) + '</p>';
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
            var text = zu ? t('Hinweis aufklappen') : t('Hinweis einklappen');
            knopf.setAttribute('aria-label', text);
            knopf.title = text;
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

    // --- Handy/Tablet: Kästen der Seitenleiste (Notdienste, Spritpreise, Termine) stehen sonst unter dem Stream ---
    // Knopf in der Begrüßungskarte mit den Symbolen der Kästen, die Kästen wandern beim Öffnen ins Fach und danach zurück
    var kastenKnopf = null, kastenLeiste = null;

    function kastenFach() { return document.getElementById('dn-kastenfach'); }

    function kastenFenster(auf) {
        var f = kastenFach();
        if (!f) return;
        var inhalt = f.querySelector('.dn-kf-inhalt');
        if (auf && kastenLeiste) {
            kastenLeiste.querySelectorAll(':scope > .panel').forEach(function (p) { inhalt.appendChild(p); });
        } else if (!auf) {
            var zurueck = kastenLeiste && document.contains(kastenLeiste);
            Array.prototype.slice.call(inhalt.children).forEach(function (p) {
                if (zurueck) kastenLeiste.appendChild(p); else p.remove();
            });
        }
        f.hidden = !auf;
        document.documentElement.classList.toggle('dn-kasten-offen', auf);
        if (kastenKnopf) kastenKnopf.setAttribute('aria-expanded', auf ? 'true' : 'false');
        if (auf) {
            f.querySelector('.dn-mehr-zu').focus({preventScroll: true});
        } else if (kastenKnopf && document.contains(kastenKnopf)) {
            kastenKnopf.focus({preventScroll: true});
        }
    }

    function kaesten(hero) {
        var leiste = document.querySelector('#layout-content .layout-sidebar-container');
        var panels = leiste ? leiste.querySelectorAll(':scope > .panel') : [];
        if (!hero || !panels.length) return false;
        var knopf = document.createElement('button');
        knopf.type = 'button';
        knopf.className = 'dn-kasten-knopf';
        knopf.setAttribute('aria-haspopup', 'dialog');
        knopf.setAttribute('aria-expanded', 'false');
        knopf.setAttribute('aria-controls', 'dn-kastenfach');
        knopf.setAttribute('aria-label', t('Notdienste, Spritpreise und Termine'));
        knopf.title = t('Notdienste, Spritpreise und Termine');
        panels.forEach(function (p) {
            var i = p.querySelector('.panel-heading .fa');
            if (i) knopf.appendChild(i.cloneNode(false)).setAttribute('aria-hidden', 'true');
        });
        if (!knopf.children.length) knopf.innerHTML = '<i class="fa fa-th-large" aria-hidden="true"></i>';
        knopf.insertAdjacentHTML('beforeend', '<i class="fa fa-chevron-down dn-kasten-pfeil" aria-hidden="true"></i>');
        var zeile = hero.querySelector('.dn-hero-zeile');
        zeile.insertBefore(knopf, zeile.querySelector('.dn-wetter'));
        kastenKnopf = knopf;
        kastenLeiste = leiste;
        return true;
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.dn-kasten-knopf')) {
            kastenFenster(true);
        } else if (e.target.closest('[data-dn-kasten-zu]')) {
            kastenFenster(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && kastenFach() && !kastenFach().hidden) kastenFenster(false);
    });

    function einsetzen() {
        var vorlage = document.getElementById('dn-hero-vorlage');
        var ziel = document.querySelector('#layout-content .layout-content-container');
        var startseite = /^\/(dashboard(\/dashboard)?)?\/?$/.test(location.pathname);
        if (kastenFach() && !kastenFach().hidden) kastenFenster(false); // PJAX-Wechsel bei offenem Fach
        if (!startseite) document.documentElement.classList.remove('dn-kasten-start');
        if (!vorlage || !ziel || !startseite || ziel.querySelector('.dn-hero')) return;

        var teil = vorlage.content.cloneNode(true);
        var jetzt = new Date();
        teil.querySelector('.dn-hero-datum').textContent = datum(jetzt, false);
        var g = teil.querySelector('.dn-hero-gruss'); // fehlt bei Gästen
        if (g) g.textContent = gruss(jetzt.getHours());
        ziel.insertBefore(teil, ziel.firstChild);
        klappe(ziel.querySelector('.dn-hero'));
        document.documentElement.classList.toggle('dn-kasten-start', kaesten(ziel.querySelector('.dn-hero')));
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
