/**
 * Web-App am iPhone (vom Startbildschirm): iOS meldet nach dem Schließen der Tastatur manchmal weiter einen zu
 * kurzen Bildschirm (window.innerHeight um die Tastaturhöhe zu klein). Alles mit position: fixed; bottom: … –
 * Tab-Leiste, Uffi-Knopf – schwebt dann mitten im Bild, darunter scrollt der Inhalt weiter.
 * Erst stupsen wir WebKit an (scrollTo), bleibt die Lücke zur größten bisher gemessenen Höhe, schieben wir die
 * Elemente per CSS-Variable --dn-luecke um genau diese Lücke nach unten. Im Browser-Tab und in der HumHub-App passiert nichts.
 */
(function () {
    var standalone = window.navigator.standalone === true;
    if (!standalone || !/iPhone|iPad|iPod/.test(navigator.userAgent)) return;

    var root = document.documentElement;

    function tastaturOffen() {
        var el = document.activeElement;
        return el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
    }

    // Vergleichsmaß ist die größte Höhe, die wir auf diesem Gerät je Ausrichtung schon gemessen haben – nicht
    // screen.height, denn die Web-App beginnt unter der Statusleiste und ist deshalb immer etwas kürzer
    function bestHoehe(hochkant, jetzt) {
        var key = 'dn-hoehe-' + (hochkant ? 'h' : 'q') + '-' + screen.width + 'x' + screen.height;
        var best = 0;
        try { best = parseInt(localStorage.getItem(key), 10) || 0; } catch (e) {}
        if (jetzt > best && jetzt <= Math.max(screen.width, screen.height)) {
            best = jetzt;
            try { localStorage.setItem(key, String(best)); } catch (e) {}
        }
        return best;
    }

    function messen() {
        var hochkant = window.matchMedia('(orientation: portrait)').matches;
        var luecke = 0;
        if (!tastaturOffen()) {
            var best = bestHoehe(hochkant, window.innerHeight);
            luecke = Math.round(best - window.innerHeight);
            // nur plausible Lücken (Tastaturhöhe), keine Fehlmessungen beim Drehen
            if (luecke < 100 || luecke > best / 2) luecke = 0;
        }
        root.style.setProperty('--dn-luecke', luecke + 'px');
    }

    function nachTastatur() {
        setTimeout(function () {
            if (tastaturOffen()) return;
            window.scrollTo(window.scrollX, window.scrollY);
            setTimeout(messen, 150);
        }, 300);
    }

    messen();
    window.addEventListener('resize', messen);
    window.addEventListener('pageshow', messen);
    window.addEventListener('orientationchange', function () { setTimeout(messen, 400); });
    document.addEventListener('focusout', nachTastatur);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) setTimeout(messen, 300); });
    if (window.visualViewport) window.visualViewport.addEventListener('resize', messen);
    if (window.jQuery) jQuery(document).on('pjax:end', messen);
})();
