/**
 * Barrierefreiheit (WCAG 2.1 AA) für Teile, die aus dem HumHub-Kern oder aus Zusatzmodulen kommen.
 * Der Kern bleibt unverändert; wir ergänzen nur Namen, Landmarken und Hilfen im Browser.
 * Läuft beim Laden, nach jedem PJAX-Seitenwechsel (humhub:ready) und für nachgeladene Teile (MutationObserver).
 */
(function () {
    // Übersetzungen vom Server (Events::barrierefreiTexte, window.bfText), Schlüssel = deutscher Text
    function t(text) { return (window.bfText && window.bfText[text]) || text; }

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
            a.textContent = t('Zum Inhalt springen');
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
        // Fußzeile im Login-Layout (Anmeldung, Rechtstexte für Gäste) liegt außerhalb jeder Landmarke
        var fuss = document.querySelector('body > .text.powered');
        if (fuss && !document.querySelector('footer, [role="contentinfo"]')) fuss.setAttribute('role', 'contentinfo');
        // Sprunglink des Kerns (seit 1.19) zielt auf #main-content, das es im Clean-Theme nicht gibt: unserer reicht
        document.querySelectorAll('a[href="#main-content"].visually-hidden-focusable').forEach(function (a) {
            if (!document.getElementById('main-content')) a.remove();
        });
        // Linke und rechte Seitenleiste heißen im Kern beide „Seitenleiste“: Menü links eigens benennen (1.3.1)
        var nav = document.querySelector('aside.layout-nav-container');
        var rechts = document.querySelector('aside.layout-sidebar-container');
        if (nav && rechts && nav.getAttribute('aria-label') === rechts.getAttribute('aria-label')) {
            nav.setAttribute('aria-label', t('Menü des Bereichs'));
        }
    }

    function name(el, text) {
        if (!el.getAttribute('aria-label') && !el.textContent.trim()) el.setAttribute('aria-label', text);
    }

    // Namen für Bedienelemente ohne Text (2.4.4, 4.1.2), Tabulatorreihenfolge, scrollbare Bereiche
    function namen(root) {
        root.querySelectorAll('#search-menu').forEach(function (el) { name(el, t('Suche')); });
        root.querySelectorAll('.form-button-search').forEach(function (el) { name(el, t('Suchen')); });
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
        root.querySelectorAll('button:not([aria-label]):not([aria-labelledby]), a.btn:not([aria-label]), #icon-messages, a[data-bs-toggle="dropdown"]:not([aria-label])').forEach(function (b) {
            if (b.textContent.trim() || b.querySelector('img[alt]:not([alt=""])')) return;
            var titel = b.title || b.getAttribute('data-bs-original-title') || b.getAttribute('data-original-title');
            var icon = !titel && b.querySelector('i.fa, i[class*="fa-"]');
            var m = icon && icon.className.match(/fa-([a-z-]+)/g);
            var text = titel || (m || []).map(function (c) { return symbole[c.slice(3)]; }).filter(Boolean)[0];
            if (text) b.setAttribute('aria-label', titel ? text : t(text));
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
                || t(({sort: 'Sortierung', connection: 'Filter', 'ChooseLanguage[language]': 'Sprache'})[sel.name] || 'Auswahl');
            box.setAttribute('aria-label', text);
            box.querySelectorAll('[role="textbox"]').forEach(function (tb) { tb.setAttribute('aria-label', text); });
        });
        // Breite Tabellen (z. B. Rechtstexte) per Tastatur scrollbar
        root.querySelectorAll('.table-responsive:not([tabindex])').forEach(function (el) {
            if (el.scrollWidth > el.clientWidth + 1) {
                el.setAttribute('tabindex', '0');
                el.setAttribute('role', 'region');
                el.setAttribute('aria-label', t('Tabelle, seitlich scrollbar'));
            }
        });
        // „Gefällt mir“-Zähler: ohne Zahl unsichtbar und nicht anwählbar, mit Zahl benannt (2.4.4)
        root.querySelectorAll('a[href*="/like/like/user-list"]').forEach(function (a) {
            var n = a.textContent.trim();
            a.hidden = !n;
            if (n) a.setAttribute('aria-label', t('Wem das gefällt') + ': ' + n);
        });
        // Leere Überschriften (z. B. Profilkopf ohne Titel) nicht als Überschrift ansagen (1.3.1)
        root.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(function (h) {
            var leer = !h.textContent.trim() && !h.querySelector('img[alt]:not([alt=""])');
            if (leer && !h.hasAttribute('aria-hidden')) {
                h.setAttribute('aria-hidden', 'true');
                h.setAttribute('data-bf-leer', '');
            } else if (!leer && h.hasAttribute('data-bf-leer')) { // inzwischen gefüllt
                h.removeAttribute('aria-hidden');
                h.removeAttribute('data-bf-leer');
            }
        });
        // Auswahlfelder mit Suche (select2, mehrfach): Eingabefeld benennen, Liste der gewählten Einträge ist keine echte Liste
        root.querySelectorAll('.select2-container').forEach(function (c) {
            var box = c.querySelector('[role="combobox"], .select2-selection');
            var text = box && box.getAttribute('aria-label');
            c.querySelectorAll('.select2-search__field:not([aria-label])').forEach(function (f) {
                f.setAttribute('aria-label', text || f.getAttribute('placeholder') || t('Auswahl'));
            });
            c.querySelectorAll('ul.select2-selection__rendered').forEach(function (ul) { ul.setAttribute('role', 'none'); });
        });
        // Passwort anzeigen (Auge im Passwortfeld)
        root.querySelectorAll('.humhub-pw-show[role="button"]').forEach(function (b) {
            if (!b.getAttribute('aria-label')) b.setAttribute('aria-label', t('Passwort anzeigen'));
        });
        // Tabelle der Benachrichtigungs-Einstellungen: Kästchen nach Zeile und Spalte benennen (1.3.1, 4.1.2)
        root.querySelectorAll('table input[type="checkbox"][name^="NotificationSettings"]:not([aria-label])').forEach(function (c) {
            var zelle = c.closest('td'), zeile = c.closest('tr'), tabelle = c.closest('table');
            if (!zelle || !zeile || !tabelle) return;
            var kopf = tabelle.querySelectorAll('thead th, tr:first-child th')[zelle.cellIndex];
            var name = zeile.cells[0] ? zeile.cells[0].textContent.trim() : '';
            c.setAttribute('aria-label', name + (kopf && kopf.textContent.trim() ? ' – ' + kopf.textContent.trim() : ''));
        });
        // Profilbild neben dem gleichen Namen: Alt-Text doppelt vorgelesen
        root.querySelectorAll('img.profile-user-photo[alt], img[class*="space-profile-image"][alt]').forEach(function (img) {
            var a = img.closest('a, label, li, td, .media') || (img.parentElement && img.parentElement.parentElement);
            if (img.alt && a && a.textContent.replace(/\s+/g, ' ').indexOf(img.alt.trim()) !== -1) img.alt = '';
        });
        // Cookie-Hinweis: Knopf „Verstanden“ heißt für Screenreader englisch „dismiss cookie message“ (2.5.3: Name = sichtbarer Text)
        root.querySelectorAll('.cc-btn[aria-label]').forEach(function (b) {
            if (b.textContent.trim()) b.removeAttribute('aria-label');
        });
        // Captcha: englischer Linkname
        root.querySelectorAll('a[href*="altcha.org"]').forEach(function (a) {
            a.setAttribute('aria-label', t('ALTCHA-Website öffnen'));
            a.title = t('ALTCHA-Website öffnen');
        });
    }

    // Überschriften im Stream (1.3.1): der Kern nimmt h4 für den Autor und h5 für Zeit/Ort, das Kalendermodul h1
    // für Datum und „Ort“. Ebene passend zur Seite setzen: unter h1+h2 (Space-Kopf) Ebene 3, sonst 2.
    function ueberschriften(root) {
        var kopf = Array.prototype.filter.call(document.querySelectorAll('#layout-content h2, .space-layout-container h2, .container-fluid h2'),
            function (h) { return !h.closest('.wall-entry') && h.textContent.trim() && h.getAttribute('aria-hidden') !== 'true'; })[0];
        var ebene = kopf ? 3 : 2;
        root.querySelectorAll('.wall-entry-header-info > h4').forEach(function (h) { h.setAttribute('aria-level', ebene); });
        // Zeile mit Zeit und Bereich ist keine Überschrift
        root.querySelectorAll('.wall-entry-header-info > h5').forEach(function (h) { h.setAttribute('role', 'none'); });
        root.querySelectorAll('.wall-entry .event-info-section-content > h1').forEach(function (h) { h.setAttribute('aria-level', ebene + 1); });
        root.querySelectorAll('.comment-heading').forEach(function (h) { h.setAttribute('aria-level', ebene + 1); });
        // Seiten ohne Hauptüberschrift (Benachrichtigungen, Nachrichten, Suche): unsichtbare h1 aus dem Seitentitel (2.4.6)
        var inhalt = document.getElementById('layout-content');
        if (inhalt && !document.querySelector('h1, [role="heading"][aria-level="1"]')) {
            var h1 = document.createElement('h1');
            h1.className = 'visually-hidden bf-h1';
            h1.textContent = document.title.replace(/\s+[-–|]\s+[^-–|]*$/, '').trim() || document.title;
            inhalt.insertBefore(h1, inhalt.firstChild);
        }
        // nach PJAX-Wechsel alte Ersatz-Überschrift entfernen, wenn die Seite jetzt eine eigene h1 hat
        document.querySelectorAll('h1.bf-h1').forEach(function (h) {
            if (document.querySelectorAll('h1').length > 1) h.remove();
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
        // Am Seitenende kann der Browser nicht weiter scrollen: Platz unter der Fußzeile, damit der Cookie-Hinweis
        // die letzten Links nicht verdeckt
        var ccH = cc && cc.offsetParent !== null ? Math.ceil(window.innerHeight - cc.getBoundingClientRect().top) : 0;
        document.body.style.paddingBottom = ccH > 0 ? (ccH + 12) + 'px' : '';
    }

    function alles(root) {
        try {
            landmarken();
            namen(root || document);
            ueberschriften(root || document);
            kalender(root || document);
            unten();
        } catch (e) {
            if (window.console) console.warn('barrierefrei', e);
        }
    }

    // HumHub-Fenster (Modals) setzen data-bs-focus="false": Bootstrap hält den Fokus dann nicht im Fenster, und Esc
    // greift nur, solange der Fokus darin ist. Hier: Tab bleibt im Fenster, Esc schließt es, danach Fokus zurück (2.1.2, 2.4.3)
    var FOKUSSIERBAR = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), '
        + 'textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';
    function offenesFenster() {
        var alle = document.querySelectorAll('.modal.show');
        return alle.length ? alle[alle.length - 1] : null;
    }
    var oeffner = null;
    document.addEventListener('focusin', function (e) {
        if (!offenesFenster() && !e.target.closest('.modal')) oeffner = e.target;
    });
    document.addEventListener('keydown', function (e) {
        var md = offenesFenster();
        if (!md) return;
        if (e.key === 'Escape' && md.getAttribute('data-bs-keyboard') !== 'false' && !md.contains(document.activeElement)) {
            var zu = md.querySelector('[data-bs-dismiss="modal"], [data-modal-close], .btn-close');
            if (zu) { e.preventDefault(); zu.click(); }
            return;
        }
        if (e.key !== 'Tab') return;
        var ziele = Array.prototype.filter.call(md.querySelectorAll(FOKUSSIERBAR), function (el) {
            return el.offsetParent !== null && !el.closest('[hidden], .select2-hidden-accessible');
        });
        if (!ziele.length) return;
        var erstes = ziele[0], letztes = ziele[ziele.length - 1], aktiv = document.activeElement;
        if (!md.contains(aktiv)) { e.preventDefault(); (e.shiftKey ? letztes : erstes).focus(); }
        else if (e.shiftKey && aktiv === erstes) { e.preventDefault(); letztes.focus(); }
        else if (!e.shiftKey && aktiv === letztes) { e.preventDefault(); erstes.focus(); }
    }, true);
    if (window.jQuery) {
        jQuery(document).on('hidden.bs.modal', '.modal', function () {
            if (offenesFenster() || !oeffner || !document.contains(oeffner)) return;
            if (document.activeElement === document.body || !document.activeElement) oeffner.focus({preventScroll: true});
        });
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
