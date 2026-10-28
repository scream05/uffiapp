/**
 * Web-App am iPhone (vom Startbildschirm): iOS meldet nach dem Schließen der Tastatur manchmal weiter einen zu
 * kurzen Bildschirm (window.innerHeight um die Tastaturhöhe zu klein). Alles mit position: fixed; bottom: … –
 * Tab-Leiste, Uffi-Knopf – schwebt dann mitten im Bild, darunter scrollt der Inhalt weiter.
 * Erst stupsen wir WebKit an (scrollTo), bleibt die Lücke, schieben wir die Elemente per CSS-Variable
 * --dn-luecke um genau diese Lücke nach unten. Im Browser-Tab und in der HumHub-App passiert nichts.
 */
(function () {
    var standalone = window.navigator.standalone === true;
    if (!standalone || !/iPhone|iPad|iPod/.test(navigator.userAgent)) return;

    var root = document.documentElement;

    function tastaturOffen() {
        var el = document.activeElement;
        return el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
    }

    function messen() {
        // screen.height ist am iPhone immer die Hochkant-Höhe; mit viewport-fit=cover füllt die Web-App den ganzen Bildschirm
        var hochkant = window.matchMedia('(orientation: portrait)').matches;
        var soll = hochkant ? screen.height : screen.width;
        var luecke = tastaturOffen() ? 0 : Math.round(soll - window.innerHeight);
        // nur plausible Lücken (Tastaturhöhe), keine Fehlmessungen beim Drehen
        if (luecke < 20 || luecke > soll / 2) luecke = 0;
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
