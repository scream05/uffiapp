/* Übersicht: angepinnte Beiträge markieren, Anpinnen/Lösen im Beitragsmenü (nur Admins) */
humhub.module('uebersicht', function (module, require, $) {
    var client = require('client');

    var text = function (key, fallback) {
        return (module.config.text && module.config.text[key]) || fallback;
    };

    var markieren = function () {
        var ids = module.config.ids || [];
        $('[data-content-key]').each(function () {
            var $e = $(this);
            var an = ids.indexOf(parseInt($e.attr('data-content-key'), 10)) !== -1;
            $e.toggleClass('uebersicht-angepinnt', an);
            if (an && !$e.children('.uebersicht-pin').length) {
                $e.prepend($('<div class="uebersicht-pin"><i class="fa fa-thumb-tack"></i> </div>').append(document.createTextNode(text('angepinnt', 'Angepinnt'))));
            } else if (!an) {
                $e.children('.uebersicht-pin').remove();
            }
        });
    };

    /* Link „Übersicht anpassen“ über dem Stream (nur angemeldet) */
    var anpassenLink = function () {
        if (!module.config.einstellungen || $('.uebersicht-anpassen').length) {
            return;
        }
        var $stream = $('#wallStream.dashboard-wall-stream');
        if ($stream.length) {
            var $a = $('<a><i class="fa fa-sliders"></i> </a>').attr('href', module.config.einstellungen)
                .append(document.createTextNode(text('anpassen', 'Übersicht anpassen')));
            $stream.before($('<div class="uebersicht-anpassen"></div>').append($a));
        }
    };

    var init = function () {
        anpassenLink();
        markieren();
        var stream = document.getElementById('wallStream') || document.querySelector('[data-stream]') || document.body;
        if (window.MutationObserver && !module.beobachter) {
            module.beobachter = new MutationObserver(function () { markieren(); });
            module.beobachter.observe(stream, {childList: true, subtree: true});
        }
    };

    var umschalten = function (evt) {
        client.post(evt.url).then(function () {
            window.location.reload();
        }).catch(function (e) {
            module.log.error(e, true);
        });
    };

    module.export({init: init, initOnPjaxLoad: true, umschalten: umschalten});
});
