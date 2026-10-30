/**
 * Kalender (alle außer Abfallkalender) mit eigener Leiste über dem Kalender, wie im Entwurf vom 30.10.2026:
 *  - Umschalter Liste | Monat (ersetzt die Ansichtsknöpfe von FullCalendar; Woche und Tag gibt es nirgends mehr)
 *  - in der Liste Zeit-Knöpfe Heute, Morgen, Wochenende, 7 Tage (nochmal tippen = wieder die nächsten 30 Tage)
 *  - Kategorie-Knöpfe (aus widgets/views/kalenderLegende.php hierher verschoben)
 *  - „Läuft gerade“: mehrtägige Termine, die schon vor dem ersten Tag der Liste begonnen haben
 * Liste = 30 Tage ab heute, am Handy Standard. Mehrtägige Termine (Kerwa …) stehen nur an ihrem ersten Tag, mit „bis …“.
 * Im Abfallkalender bleiben Monat und die Knöpfe von FullCalendar (ohne Woche/Tag).
 *
 * Läuft nach dem Kalendermodul (KalenderListeAsset hängt an CalendarAsset, defer) und vor dem Start des Kalenders.
 */
(function () {
    var Calendar = humhub.require('calendar.Calendar');
    if (!Calendar || Calendar.prototype.dnListe) return; // Seitenwechsel per PJAX: nur einmal anpassen
    Calendar.prototype.dnListe = true;

    var view = humhub.require('ui.view');
    var LISTE = 'listDemnaechst';
    var T = $.extend({
        bis: 'bis {datum}', liste: 'Liste', monat: 'Monat', heute: 'Heute', morgen: 'Morgen', wochenende: 'Wochenende',
        woche: '7 Tage', laeuft: 'Läuft gerade', frueher: 'Frühere Termine', spaeter: 'Spätere Termine',
        ansicht: 'Ansicht', zeitraum: 'Zeitraum', kategorie: 'Kategorie'
    }, window.dnKalText || {});

    function mitListe() {
        return location.pathname.indexOf('/s/abfall-and-termine/') === -1;
    }

    function ersetzen(o, muster, durch) {
        ['header', 'footer'].forEach(function (k) {
            if (!o[k]) return;
            Object.keys(o[k]).forEach(function (pos) {
                if (typeof o[k][pos] === 'string') o[k][pos] = o[k][pos].replace(muster, durch).replace(/ ,/g, ' ').replace(/^,|,$/g, '').trim();
            });
        });
    }

    var optionen = Calendar.prototype.getDefaultOptions;
    Calendar.prototype.getDefaultOptions = function () {
        var o = optionen.apply(this, arguments);
        // Ansichten Woche und Tag braucht hier niemand – nur Monat und Liste (alle Kalender)
        ersetzen(o, /,?timeGrid(Week|Day)\b/g, '');
        if (!mitListe()) return o;
        var liste = {type: 'list', listDayFormat: {weekday: 'long', day: 'numeric', month: 'long'}, listDayAltFormat: false};
        o.views = $.extend({}, o.views);
        o.views[LISTE] = $.extend({duration: {days: 30}}, liste);
        o.views.listTag = $.extend({duration: {days: 1}}, liste);
        o.views.listZwei = $.extend({duration: {days: 2}}, liste);
        o.views.listWoche = $.extend({duration: {days: 7}}, liste);
        // Umschalten übernimmt die eigene Leiste
        ersetzen(o, /,?(dayGridMonth|list(Week|Month|Year))\b/g, '');
        o.datesRender = function (info) {
            leisteAktualisieren(info.view);
        };
        return o;
    };

    var kalender = null; // FullCalendar der aktuellen Seite

    // Standardansicht kommt aus den Space-Einstellungen (data-default-view) und überschreibt getDefaultOptions
    var starten = Calendar.prototype.initFullCalendar;
    Calendar.prototype.initFullCalendar = function (reload) {
        var erstes = !this.fullCalendar;
        if (erstes && mitListe()) {
            if (view.isSmall() || /^list/.test(this.options.defaultView || '')) this.options.defaultView = LISTE;
            zeit = null;
            leisteBauen(this.$[0]);
        }
        var r = starten.apply(this, arguments);
        if (erstes && mitListe()) {
            kalender = this.fullCalendar;
            leisteAktualisieren(kalender.view);
        }
        return r;
    };

    /* ---------- Leiste ---------- */

    var zeit = null; // null = 30 Tage, sonst heute | morgen | we | woche
    var ZEITEN = [['heute', T.heute], ['morgen', T.morgen], ['we', T.wochenende], ['woche', T.woche]];

    function knopf(attr, wert, text) {
        return $('<button type="button" aria-pressed="false">').attr(attr, wert).text(text);
    }

    function leisteBauen(el) {
        var $kal = $(el), $box = $kal.parent();
        if ($box.children('.dn-kal-leiste').length) return;
        $box.addClass('dn-kal');
        var $leiste = $('<div class="dn-kal-leiste">');
        var $zeile = $('<div class="dn-kal-zeile">').appendTo($leiste);
        $('<div class="dn-kal-umschalter" role="group">').attr('aria-label', T.ansicht)
            .append(knopf('data-ansicht', 'liste', T.liste), knopf('data-ansicht', 'monat', T.monat)).appendTo($zeile);
        $('<span class="dn-kal-titel" aria-live="polite">').appendTo($zeile);
        var $zeit = $('<div class="dn-kal-chips dn-kal-zeit" role="group">').attr('aria-label', T.zeitraum).appendTo($leiste);
        ZEITEN.forEach(function (z) {
            knopf('data-zeit', z[0], z[1]).addClass('dn-kal-chip').appendTo($zeit);
        });
        var $kat = $('<div class="dn-kal-chips dn-kal-kat" role="group">').attr('aria-label', T.kategorie);
        // Kategorie-Knöpfe der Legende übernehmen (Klick und Markierung regelt kalenderLegende.php)
        var $legende = $('.kal-legende');
        $legende.find('.kal-legende-item').each(function () {
            this.style.setProperty('--c', $(this).data('color'));
            $(this).addClass('dn-kal-chip').appendTo($kat);
        });
        $legende.addClass('dn-kal-legende-leer');
        $box.prepend($leiste.add($kat.children().length ? $kat : $()));
        $('<div class="dn-kal-laufend" hidden><strong></strong><ul></ul></div>').find('strong').text(T.laeuft).end().insertBefore($kal);
        $('<div class="dn-kal-blaettern">')
            .append(knopf('data-blaettern', 'zurueck', '‹ ' + T.frueher), knopf('data-blaettern', 'vor', T.spaeter + ' ›'))
            .insertAfter($kal);
        obenMerken();
    }

    function heute0(tage) {
        var d = new Date();
        d.setHours(0, 0, 0, 0);
        d.setDate(d.getDate() + (tage || 0));
        return d;
    }

    function zeitraumZeigen() {
        if (!kalender) return;
        var tag = heute0().getDay(); // 0 = Sonntag
        var ziel = {
            heute: ['listTag', heute0()],
            morgen: ['listTag', heute0(1)],
            we: tag === 0 ? ['listTag', heute0()] : ['listZwei', heute0(tag === 6 ? 0 : 6 - tag)],
            woche: ['listWoche', heute0()]
        }[zeit] || [LISTE, heute0()];
        kalender.changeView(ziel[0], ziel[1]);
    }

    function leisteAktualisieren(v) {
        var $box = $('.dn-kal');
        if (!$box.length || !v) return;
        var liste = v.type.indexOf('list') === 0;
        $box.toggleClass('dn-kal-liste', liste).toggleClass('dn-kal-monat', !liste);
        $box.find('[data-ansicht]').each(function () {
            $(this).attr('aria-pressed', String(($(this).data('ansicht') === 'liste') === liste));
        });
        $box.find('[data-zeit]').each(function () {
            $(this).attr('aria-pressed', String($(this).data('zeit') === zeit));
        });
        $box.find('.dn-kal-titel').text(liste ? v.title : '');
        var amAnfang = v.activeStart <= heute0();
        $box.find('.dn-kal-blaettern').toggle(liste && !zeit);
        $box.find('[data-blaettern="zurueck"]').toggle(!amAnfang);
        if (!liste) $box.find('.dn-kal-laufend').prop('hidden', true);
    }

    $(document).on('click', '.dn-kal [data-ansicht]', function () {
        if (!kalender) return;
        if ($(this).data('ansicht') === 'monat') {
            kalender.changeView('dayGridMonth', heute0());
        } else {
            zeitraumZeigen();
        }
    });
    $(document).on('click', '.dn-kal [data-zeit]', function () {
        var z = $(this).data('zeit');
        zeit = zeit === z ? null : z;
        zeitraumZeigen();
    });
    $(document).on('click', '.dn-kal [data-blaettern]', function () {
        if (!kalender) return;
        $(this).data('blaettern') === 'vor' ? kalender.next() : kalender.prev();
        var leiste = document.querySelector('.dn-kal-leiste');
        if (leiste && leiste.getBoundingClientRect().top < 0) leiste.scrollIntoView({block: 'start'});
    });
    $(document).on('click', '.dn-kal-laufend li', function () {
        var zeile = document.querySelector('.fc-list-table tr[data-dn-termin="' + $(this).data('termin') + '"]');
        if (zeile) zeile.click();
    });

    // Tagesüberschriften kleben unter der festen Kopfleiste
    function obenMerken() {
        var unten = 0;
        document.querySelectorAll('#topbar, #topbar-first, .topbar').forEach(function (el) {
            var stil = getComputedStyle(el);
            if (stil.position !== 'fixed' && stil.position !== 'sticky') return;
            var r = el.getBoundingClientRect();
            if (r.top <= 0 && r.bottom > unten) unten = r.bottom;
        });
        document.documentElement.style.setProperty('--dn-kal-oben', Math.round(unten) + 'px');
    }
    $(window).on('resize', obenMerken);

    /* ---------- Liste aufbereiten ---------- */

    function tag(d) {
        return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
    }

    function mehrtaegig(ev) {
        if (!ev.end) return false;
        var ende = new Date(ev.end.getTime() - 60000); // Ende 00:00 gehört zum Vortag
        return tag(ende) !== tag(ev.start);
    }

    function bisText(ev) {
        var sprache = document.documentElement.lang || 'de';
        var ende = new Date(ev.end.getTime() - 60000);
        return T.bis.replace('{datum}', ende.toLocaleDateString(sprache, {day: 'numeric', month: 'numeric'}));
    }

    function zeitText(ev) {
        var sprache = document.documentElement.lang || 'de';
        return ev.allDay ? bisText(ev) : ev.start.toLocaleTimeString(sprache, {hour: '2-digit', minute: '2-digit'}) + ' · ' + bisText(ev);
    }

    function iso(d) {
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }

    // Folgetage ausblenden und leere Tagesüberschriften mit, Heute/Morgen markieren, „Läuft gerade“ füllen;
    // nach jedem Zeichnen der Liste
    var geplant = null;
    function aufraeumen() {
        geplant = null;
        var laufend = [];
        document.querySelectorAll('.fc-list-table').forEach(function (tabelle) {
            [[iso(heute0()), T.heute, 'dn-heute'], [iso(heute0(1)), T.morgen, 'dn-morgen']].forEach(function (m) {
                var kopf = tabelle.querySelector('tr.fc-list-heading[data-date="' + m[0] + '"]');
                var td = kopf && kopf.querySelector('td');
                if (td && !td.querySelector('.dn-kal-marke')) {
                    kopf.classList.add(m[2]);
                    $('<span class="dn-kal-marke">').text(m[1]).appendTo(td);
                }
            });
            var gesehen = {};
            var ersterTag = true;
            tabelle.querySelectorAll('tr').forEach(function (tr) {
                if (tr.classList.contains('fc-list-heading')) {
                    if (tr !== tabelle.querySelector('tr.fc-list-heading')) ersterTag = false;
                    return;
                }
                var id = tr.getAttribute('data-dn-termin');
                if (!id) return;
                var weg = !!gesehen[id];
                // Am ersten Tag der Liste ein Folgetag: hat schon vorher begonnen → nach oben zu „Läuft gerade“
                if (!weg && ersterTag && !tr.hasAttribute('data-dn-start')) {
                    weg = true;
                    laufend.push({id: id, titel: tr.getAttribute('data-dn-titel'), bis: tr.getAttribute('data-dn-bis')});
                }
                tr.classList.toggle('dn-folgetag', weg);
                gesehen[id] = true;
            });
            tabelle.querySelectorAll('tr.fc-list-heading').forEach(function (kopf) {
                var leer = true;
                for (var n = kopf.nextElementSibling; n && !n.classList.contains('fc-list-heading'); n = n.nextElementSibling) {
                    if (!n.classList.contains('dn-folgetag')) leer = false;
                }
                kopf.classList.toggle('dn-folgetag', leer);
            });
        });
        var $laufend = $('.dn-kal-laufend');
        $laufend.find('ul').empty().append(laufend.map(function (l) {
            return $('<li tabindex="0" role="button">').attr('data-termin', l.id).text(l.titel + ' ').append($('<small>').text(l.bis));
        }));
        $laufend.prop('hidden', !laufend.length || !$('.dn-kal').hasClass('dn-kal-liste'));
    }

    $(document).on('keydown', '.dn-kal-laufend li', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    var zeichnen = Calendar.prototype.renderEvent;
    Calendar.prototype.renderEvent = function (info) {
        var r = zeichnen.apply(this, arguments);
        if (!info.view || info.view.type.indexOf('list') !== 0) return r;
        if (!geplant) geplant = setTimeout(aufraeumen, 0);
        if (mehrtaegig(info.event)) {
            var $el = $(info.el);
            $el.attr({
                'data-dn-termin': info.event.id + '@' + info.event.start.getTime(),
                'data-dn-titel': info.event.title,
                'data-dn-bis': bisText(info.event)
            }).addClass('dn-mehrtaegig');
            if (info.isStart) $el.attr('data-dn-start', '1');
            $el.find('.fc-list-item-time').text(zeitText(info.event));
        }
        return r;
    };
})();
