/*
 * Streams: erstes Bild eines Beitrags als Miniatur links an den Anfang des Textes, Text läuft herum.
 * HumHub zeigt Bilder unter dem Beitrag (.post-files im Fuß); hier wird der Link samt Bild in den Inhalt
 * verschoben. Weitere Bilder bleiben versteckt in derselben Galerie (Hinweis „+n“), Videos und andere
 * Dateien bleiben unverändert darunter. Einzelansicht (Beitragsseite, ?contentId=) behält das große Bild.
 */
humhub.module('uebersichtMiniatur', function (module, require, $) {

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
        if (window.MutationObserver && !module.beobachter) {
            module.beobachter = new MutationObserver(function () { alle(); });
            module.beobachter.observe(document.body, {childList: true, subtree: true});
        }
    };

    module.export({init: init, initOnPjaxLoad: true});
});
