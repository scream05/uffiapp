/*
 * Streams: erstes Bild eines Beitrags als Miniatur links an den Anfang des Textes, Text läuft herum.
 * HumHub zeigt Bilder unter dem Beitrag (.post-files im Fuß); hier wird der Link samt Bild in den Inhalt
 * verschoben. Weitere Bilder bleiben versteckt in derselben Galerie (Hinweis „+n“), Videos und andere
 * Dateien bleiben unverändert darunter. Einzelansicht (Beitragsseite, ?contentId=) behält das große Bild.
 * Längerer Text wird am letzten Zeilenende über der Unterkante des Bildes abgeschnitten, „Mehr …“ klappt ihn auf
 * (HumHubs eigenes „Mehr anzeigen“ ist in diesen Beiträgen per CSS abgeschaltet).
 */
humhub.module('uebersichtMiniatur', function (module, require, $) {

    var text = function (key, fallback) {
        return (module.config.text && module.config.text[key]) || fallback;
    };

    /** Text auf die Höhe des Bildes kürzen (an einem Zeilenende); bis etwa eine Zeile Überhang bleibt alles stehen */
    var kuerzen = function ($inhalt) {
        var inhalt = $inhalt[0];
        var bild = $inhalt.children('.miniatur')[0];
        if (!inhalt || !bild || $inhalt.hasClass('miniatur-offen') || !inhalt.offsetWidth) {
            return;
        }
        inhalt.style.maxHeight = '';
        var oben = inhalt.getBoundingClientRect().top;
        var bildUnten = bild.getBoundingClientRect().bottom - oben;
        var $link = $inhalt.next('.miniatur-mehr-link');
        if (inhalt.scrollHeight <= bildUnten + 28) {
            $inhalt.removeClass('miniatur-kurz');
            $link.remove();
            return;
        }
        // Unterkante der letzten Textzeile, die noch neben das Bild passt
        var schnitt = 0;
        var walker = document.createTreeWalker(inhalt, NodeFilter.SHOW_TEXT);
        var range = document.createRange();
        var n;
        while ((n = walker.nextNode())) {
            if (!n.nodeValue.trim() || bild.contains(n)) {
                continue;
            }
            range.selectNodeContents(n);
            var rects = range.getClientRects();
            for (var i = 0; i < rects.length; i++) {
                var unten = rects[i].bottom - oben;
                if (unten <= bildUnten + 2 && unten > schnitt) {
                    schnitt = unten;
                }
            }
            if (rects.length && rects[0].top - oben > bildUnten) {
                break;
            }
        }
        if (schnitt < bildUnten * 0.5) {
            schnitt = bildUnten;
        }
        // Kasten so hoch wie das Bild; neben dem Bild deckt ::after den Rest der angeschnittenen Zeile ab
        var rtl = getComputedStyle(inhalt).direction === 'rtl';
        var r = inhalt.getBoundingClientRect();
        var b = bild.getBoundingClientRect();
        var abstand = parseFloat(getComputedStyle(bild)[rtl ? 'marginLeft' : 'marginRight']) || 0;
        var spalte = Math.round((rtl ? r.right - b.left : b.right - r.left) + abstand) + 'px';
        var hg = 'transparent';
        for (var el = inhalt; el && hg === 'transparent'; el = el.parentElement) {
            var c = getComputedStyle(el).backgroundColor;
            hg = c && c !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(c) ? c : 'transparent';
        }
        inhalt.style.maxHeight = Math.ceil(Math.max(bildUnten, schnitt)) + 'px';
        inhalt.style.setProperty('--miniatur-schnitt', Math.ceil(schnitt) + 'px');
        inhalt.style.setProperty('--miniatur-spalte', spalte);
        inhalt.style.setProperty('--miniatur-hg', hg === 'transparent' ? '#fff' : hg);
        $inhalt.addClass('miniatur-kurz');
        if (!$link.length) {
            $link = $('<a href="#" class="miniatur-mehr-link" role="button" aria-expanded="false"></a>').text(text('mehr', 'Mehr …'));
            $inhalt.after($link);
        }
        $link.css('margin-inline-start', spalte);
    };

    $(document).on('click', '.miniatur-mehr-link', function (evt) {
        evt.preventDefault();
        var $link = $(this);
        var $inhalt = $link.prev('.wall-entry-content');
        if ($inhalt.hasClass('miniatur-offen')) {
            $inhalt.removeClass('miniatur-offen');
            kuerzen($inhalt);
            $link.text(text('mehr', 'Mehr …')).attr('aria-expanded', 'false');
            var oben = $inhalt.closest('.wall-entry')[0].getBoundingClientRect().top;
            if (oben < 0) {
                window.scrollBy(0, oben - 80);
            }
        } else {
            $inhalt.addClass('miniatur-offen').removeClass('miniatur-kurz').css('max-height', '');
            $link.text(text('weniger', 'Weniger')).attr('aria-expanded', 'true').css('margin-inline-start', '');
        }
    });

    var einzelansicht = function () {
        return /[?&]contentId=/.test(window.location.search);
    };

    var umbauen = function ($eintrag) {
        $eintrag.addClass('miniatur-geprueft');
        var $inhalt = $eintrag.find('.wall-entry-body > .wall-entry-content').first();
        var $dateien = $eintrag.find('.wall-entry-footer .post-files').filter(function () {
            return !$(this).closest('.comment-container').length;
        }).first();
        if (!$inhalt.length || !$dateien.length) {
            return;
        }
        var $bilder = $dateien.find('.col-media').filter(function () {
            return $(this).find('a[data-ui-gallery] > img').length > 0;
        });
        if (!$bilder.length) {
            return;
        }
        var $link = $bilder.first().find('a[data-ui-gallery]').first();
        var $miniatur = $('<div class="miniatur"></div>').append($link);
        if ($bilder.length > 1) {
            $link.append($('<span class="miniatur-mehr" aria-hidden="true"></span>').text('+' + ($bilder.length - 1)));
        }
        $bilder.first().remove();
        $bilder.slice(1).addClass('miniatur-versteckt');
        if (!$dateien.find('.col-media').not('.miniatur-versteckt').length) {
            $dateien.addClass('miniatur-versteckt');
        }
        $inhalt.addClass('miniatur-mit-bild').prepend($miniatur);
        var img = $link.find('img')[0];
        if (img.complete) {
            kuerzen($inhalt);
        } else {
            img.addEventListener('load', function () { kuerzen($inhalt); }, {once: true});
            img.addEventListener('error', function () { kuerzen($inhalt); }, {once: true});
        }
    };

    /** Nach Größenänderung und geladenen Schriften neu kürzen (Zeilen verschieben sich) */
    var allesKuerzen = function () {
        $('.wall-entry-content.miniatur-mit-bild').not('.miniatur-offen').each(function () {
            kuerzen($(this));
        });
    };

    var alle = function () {
        if (einzelansicht()) {
            return;
        }
        $('[data-stream] .wall-entry[data-stream-entry]').not('.miniatur-geprueft').each(function () {
            umbauen($(this));
        });
    };

    var init = function () {
        alle();
        if (!module.groesse) {
            module.groesse = true;
            var warten;
            $(window).on('resize', function () {
                clearTimeout(warten);
                warten = setTimeout(allesKuerzen, 200);
            });
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(allesKuerzen);
            }
        }
        if (window.MutationObserver && !module.beobachter) {
            module.beobachter = new MutationObserver(function () { alle(); });
            module.beobachter.observe(document.body, {childList: true, subtree: true});
        }
    };

    module.export({init: init, initOnPjaxLoad: true, kuerzen: allesKuerzen});
});
