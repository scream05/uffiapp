/**
 * Barrierefreiheit (WCAG 2.1 AA) für Teile, die aus dem HumHub-Kern oder aus Zusatzmodulen kommen.
 * Der Kern bleibt unverändert; wir ergänzen nur Namen, Landmarken und Hilfen im Browser.
 * Läuft beim Laden, nach jedem PJAX-Seitenwechsel (humhub:ready) und für nachgeladene Teile (MutationObserver).
 */
(function () {
    // Sprunglink zum Inhalt und Hauptbereich (2.4.1, 1.3.1)
    function landmarken() {
        var inhalt = document.getElementById('layout-content')
            || document.querySelector('.container-login') || document.querySelector('.login-container .container');
        if (inhalt) {
            inhalt.setAttribute('role', 'main');
            if (!inhalt.id) inhalt.id = 'bf-inhalt';
            if (!inhalt.hasAttribute('tabindex')) inhalt.setAttribute('tabindex', '-1');
        }
        if (inhalt && !document.querySelector('.bf-skip')) {
            var a = document.createElement('a');
            a.className = 'bf-skip';
            a.href = '#' + inhalt.id;
            a.textContent = 'Zum Inhalt springen';
            a.setAttribute('data-pjax-prevent', '');
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var ziel = document.getElementById(inhalt.id);
                if (ziel) ziel.focus();
            });
            // ganz vorn, noch vor dem Cookie-Hinweis: erster Halt mit der Tab-Taste
            document.body.insertBefore(a, document.body.firstChild);
        }
        // Der Cookie-Hinweis setzt sich später selbst an den Anfang: Sprunglink wieder davor
        var skip = document.querySelector('.bf-skip');
        if (skip && document.body.firstElementChild !== skip) document.body.insertBefore(skip, document.body.firstChild);
        var topbar = document.getElementById('topbar');
        if (topbar) topbar.setAttribute('role', 'banner');
    }

    function name(el, text) {
        if (!el.getAttribute('aria-label') && !el.textContent.trim()) el.setAttribute('aria-label', text);
    }

    // Namen für Bedienelemente ohne Text (2.4.4, 4.1.2), Tabulatorreihenfolge, scrollbare Bereiche
    function namen(root) {
        root.querySelectorAll('#search-menu').forEach(function (el) { name(el, 'Suche'); });
        root.querySelectorAll('.form-button-search').forEach(function (el) { name(el, 'Suchen'); });
        // Avatar-Link neben dem Namen führt zum selben Ziel: für Tastatur und Screenreader überspringen
        root.querySelectorAll('.wall-entry-header-image a, .media-left > a, .comment .media > a').forEach(function (a) {
            if (!a.textContent.trim() && !a.getAttribute('aria-label')) {
                var bild = a.querySelector('img[alt]');
                if (bild && bild.alt.trim()) {
                    a.setAttribute('aria-label', bild.alt.trim());
                } else {
                    a.setAttribute('tabindex', '-1');
                    a.setAttribute('aria-hidden', 'true');
                }
            }
        });
        root.querySelectorAll('a[href]:not([aria-label])').forEach(function (a) {
            if (a.textContent.trim() || a.querySelector('img[alt]:not([alt=""])')) return;
            if (a.title) a.setAttribute('aria-label', a.title);
        });
        // Symbol-Knöpfe ohne Text: Name aus dem Titel oder dem Symbol
        var symbole = {filter: 'Filter', search: 'Suchen', times: 'Schließen', close: 'Schließen', bars: 'Menü',
            'ellipsis-h': 'Weitere Aktionen', 'ellipsis-v': 'Weitere Aktionen', plus: 'Hinzufügen', pencil: 'Bearbeiten',
            trash: 'Löschen', cog: 'Einstellungen', bell: 'Benachrichtigungen', envelope: 'Nachrichten'};
        root.querySelectorAll('button:not([aria-label]):not([aria-labelledby]), a.btn:not([aria-label])').forEach(function (b) {
            if (b.textContent.trim()) return;
            var titel = b.title || b.getAttribute('data-bs-original-title') || b.getAttribute('data-original-title');
            var icon = !titel && b.querySelector('i.fa, i[class*="fa-"]');
            var m = icon && icon.className.match(/fa-([a-z-]+)/g);
            var text = titel || (m || []).map(function (c) { return symbole[c.slice(3)]; }).filter(Boolean)[0];
            if (text) b.setAttribute('aria-label', text);
        });
        root.querySelectorAll('[tabindex]').forEach(function (el) {
            if (+el.getAttribute('tabindex') > 0) el.setAttribute('tabindex', '0');
        });
        // Auswahlfelder (select2): Namen vom Original-Feld übernehmen
        root.querySelectorAll('select.select2-hidden-accessible').forEach(function (sel) {
            var box = sel.nextElementSibling && sel.nextElementSibling.querySelector('[role="combobox"]');
            if (!box) return;
            if (box.getAttribute('aria-label')) {
                box.querySelectorAll('[role="textbox"]:not([aria-label])').forEach(function (tb) { tb.setAttribute('aria-label', box.getAttribute('aria-label')); });
                return;
            }
            var label = sel.id && document.querySelector('label[for="' + sel.id + '"]');
            var text = (label && label.textContent.trim()) || sel.getAttribute('aria-label') || sel.title
                || ({sort: 'Sortierung', connection: 'Filter', 'ChooseLanguage[language]': 'Sprache'})[sel.name] || 'Auswahl';
            box.setAttribute('aria-label', text);
            box.querySelectorAll('[role="textbox"]').forEach(function (tb) { tb.setAttribute('aria-label', text); });
        });
        // Breite Tabellen (z. B. Rechtstexte) per Tastatur scrollbar
        root.querySelectorAll('.table-responsive:not([tabindex])').forEach(function (el) {
            if (el.scrollWidth > el.clientWidth + 1) {
                el.setAttribute('tabindex', '0');
                el.setAttribute('role', 'region');
                el.setAttribute('aria-label', 'Tabelle, seitlich scrollbar');
            }
        });
        // Captcha: englischer Linkname
        root.querySelectorAll('a[href*="altcha.org"]').forEach(function (a) {
            a.setAttribute('aria-label', 'ALTCHA-Website öffnen');
            a.title = 'ALTCHA-Website öffnen';
        });
    }

    // Kalender: Textfarbe je nach Terminfarbe Schwarz oder Weiß, damit der Kontrast immer reicht (1.4.3)
    function hell(rgb) {
        var m = rgb && rgb.match(/\d+(\.\d+)?/g);
        if (!m || m.length < 3 || (m.length > 3 && +m[3] === 0)) return null;
        var l = m.slice(0, 3).map(function (v) {
            v = v / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
    }
    function kalender(root) {
        root.querySelectorAll('.fc-event').forEach(function (ev) {
            var L = hell(getComputedStyle(ev).backgroundColor);
            if (L === null) return;
            // Kontrast zu Weiß (1.05 / (L + 0.05)) gegen Kontrast zu Schwarz ((L + 0.05) / 0.05)
            var farbe = 1.05 / (L + 0.05) >= (L + 0.05) / 0.05 ? '#fff' : '#111';
            ev.style.setProperty('color', farbe, 'important');
            ev.querySelectorAll('.fc-time, .fc-title, .fc-content').forEach(function (t) { t.style.setProperty('color', farbe, 'important'); });
        });
    }

    // Cookie-Hinweis und Tab-Leiste sollen den Tastaturfokus nicht verdecken (2.4.11):
    // Abstand unten, um den der Browser beim Fokussieren mitscrollt
    function unten() {
        var h = 0;
        var cc = document.querySelector('.cc-window:not(.cc-invisible)');
        if (cc && cc.offsetParent !== null) {
            var r = cc.getBoundingClientRect();
            h = Math.max(h, window.innerHeight - r.top);
        }
        var leiste = document.getElementById('top-menu-nav'); // am Handy die Tab-Leiste unten
        if (leiste && leiste.offsetParent !== null) {
            var rl = leiste.getBoundingClientRect();
            if (rl.top > window.innerHeight / 2) h = Math.max(h, window.innerHeight - rl.top);
        }
        document.documentElement.style.scrollPaddingBottom = h ? (Math.ceil(h) + 12) + 'px' : '';
    }

    function alles(root) {
        try {
            landmarken();
            namen(root || document);
            kalender(root || document);
            unten();
        } catch (e) {
            if (window.console) console.warn('barrierefrei', e);
        }
    }

    var geplant = null;
    function spaeter() {
        if (geplant) return;
        geplant = setTimeout(function () { geplant = null; alles(document); }, 150);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { alles(document); });
    } else {
        alles(document);
    }
    if (window.jQuery) jQuery(document).on('humhub:ready', function () { alles(document); });
    window.addEventListener('resize', unten);
    document.addEventListener('click', function () { setTimeout(unten, 300); });
    if (window.MutationObserver) {
        new MutationObserver(spaeter).observe(document.documentElement, {childList: true, subtree: true});
    }
})();
