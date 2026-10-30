/**
 * Kalender am Handy: Liste „ab heute, 30 Tage“ statt Monatsraster (nicht im Abfallkalender, dort bleibt der Monat).
 * Der Knopf „Liste“ zeigt überall diese Liste statt der Kalenderwoche; die Knöpfe „Woche“ und „Tag“ entfallen überall.
 * Mehrtägige Termine (Kerwa …) stehen in der Liste nur einmal – am ersten Tag bzw. am ersten sichtbaren Tag – mit „bis …“.
 *
 * Läuft nach dem Kalendermodul (KalenderListeAsset hängt an CalendarAsset) und vor dem Start des Kalenders (humhub:ready).
 */
(function () {
    var Calendar = humhub.require('calendar.Calendar');
    if (!Calendar || Calendar.prototype.dnListe) return; // Seitenwechsel per PJAX: nur einmal anpassen
    Calendar.prototype.dnListe = true;

    var view = humhub.require('ui.view');
    var LISTE = 'listDemnaechst';
    var bis = (window.dnKalText && window.dnKalText.bis) || 'bis {datum}';

    function mitListe() {
        return location.pathname.indexOf('/s/abfall-and-termine/') === -1;
    }

    var optionen = Calendar.prototype.getDefaultOptions;
    Calendar.prototype.getDefaultOptions = function () {
        var o = optionen.apply(this, arguments);
        // Ansichten Woche und Tag braucht hier niemand – nur Monat und Liste (alle Kalender)
        ['header', 'footer'].forEach(function (k) {
            if (!o[k]) return;
            Object.keys(o[k]).forEach(function (pos) {
                if (typeof o[k][pos] === 'string') o[k][pos] = o[k][pos].replace(/,?timeGrid(Week|Day)\b/g, '');
            });
        });
        if (!mitListe()) return o;
        o.views = $.extend({}, o.views);
        o.views[LISTE] = {type: 'list', duration: {days: 30}, buttonText: (o.buttonText && o.buttonText.list) || 'Liste',
            listDayFormat: {weekday: 'long', day: 'numeric', month: 'long'}, listDayAltFormat: false};
        ['header', 'footer'].forEach(function (k) {
            if (!o[k]) return;
            Object.keys(o[k]).forEach(function (pos) {
                if (typeof o[k][pos] === 'string') o[k][pos] = o[k][pos].replace(/\blist(Week|Month|Year)\b/, LISTE);
            });
        });
        return o;
    };

    // Standardansicht kommt aus den Space-Einstellungen (data-default-view) und überschreibt getDefaultOptions
    var starten = Calendar.prototype.initFullCalendar;
    Calendar.prototype.initFullCalendar = function (reload) {
        if (!this.fullCalendar && mitListe()) {
            if (view.isSmall() || /^list/.test(this.options.defaultView || '')) this.options.defaultView = LISTE;
        }
        return starten.apply(this, arguments);
    };

    function tag(d) {
        return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
    }

    function mehrtaegig(ev) {
        if (!ev.end) return false;
        var ende = new Date(ev.end.getTime() - 60000); // Ende 00:00 gehört zum Vortag
        return tag(ende) !== tag(ev.start);
    }

    function zeitText(ev) {
        var sprache = document.documentElement.lang || 'de';
        var ende = new Date(ev.end.getTime() - 60000);
        var text = bis.replace('{datum}', ende.toLocaleDateString(sprache, {day: 'numeric', month: 'numeric'}));
        return ev.allDay ? text : ev.start.toLocaleTimeString(sprache, {hour: '2-digit', minute: '2-digit'}) + ' · ' + text;
    }

    function iso(d) {
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }

    // Folgetage ausblenden und leere Tagesüberschriften mit, heutigen Tag markieren; nach jedem Zeichnen der Liste
    var geplant = null;
    function aufraeumen() {
        geplant = null;
        document.querySelectorAll('.fc-list-table').forEach(function (tabelle) {
            var heute = tabelle.querySelector('tr.fc-list-heading[data-date="' + iso(new Date()) + '"]');
            if (heute) heute.classList.add('dn-heute');
            var gesehen = {};
            tabelle.querySelectorAll('tr[data-dn-termin]').forEach(function (tr) {
                var id = tr.getAttribute('data-dn-termin');
                tr.classList.toggle('dn-folgetag', !!gesehen[id]);
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
    }

    var zeichnen = Calendar.prototype.renderEvent;
    Calendar.prototype.renderEvent = function (info) {
        var r = zeichnen.apply(this, arguments);
        if (info.view && info.view.type.indexOf('list') === 0 && !geplant) geplant = setTimeout(aufraeumen, 0);
        if (info.view && info.view.type.indexOf('list') === 0 && mehrtaegig(info.event)) {
            var $el = $(info.el);
            $el.attr('data-dn-termin', info.event.id + '@' + info.event.start.getTime()).addClass('dn-mehrtaegig');
            $el.find('.fc-list-item-time').text(zeitText(info.event));
        }
        return r;
    };
})();
