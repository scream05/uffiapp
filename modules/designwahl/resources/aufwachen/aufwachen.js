/**
 * Nach längerer Pause (App im Hintergrund, Handy gesperrt, Tab lange offen) die Seite einmal neu laden.
 * Grund: Nach Ablauf der Sitzung (window.dnSitzungSek, Admin-Einstellung „Idle Timeout“) vergibt HumHub einen neuen
 * Skript-Schlüssel (CSP-Nonce). Die alte Seite blockiert dann alle nachgeladenen Skripte – Stream bleibt leer,
 * Knöpfe reagieren nicht. HumHubs eigene Absicherung lädt höchstens alle 30 Minuten neu, in der App oft gar nicht.
 * Gemessen wird die Zeit seit der letzten Anfrage an den Server (die verlängert die Sitzung).
 * Nicht neu geladen wird, wenn gerade Text in einem Eingabefeld steht (sonst ginge er verloren).
 */
(function () {
    var grenze = Math.max(60, (window.dnSitzungSek || 1400) - 60) * 1000;
    var zuletzt = Date.now();
    var laedt = false;

    if (window.jQuery) {
        jQuery(document).on('ajaxComplete pjax:end', function () { zuletzt = Date.now(); });
    }

    function textInArbeit() {
        var felder = document.querySelectorAll('textarea, input[type="text"], input:not([type]), [contenteditable="true"]');
        for (var i = 0; i < felder.length; i++) {
            var f = felder[i];
            if (!f.offsetParent || f.readOnly || f.type === 'search' || f.closest('#search-menu')) continue;
            var text = f.isContentEditable ? f.textContent : (f.value !== f.defaultValue ? f.value : '');
            if (text && text.trim() !== '') return true;
        }
        return false;
    }

    function pruefen() {
        if (laedt || document.hidden || Date.now() - zuletzt < grenze || textInArbeit()) return;
        laedt = true;
        window.location.reload();
    }

    document.addEventListener('visibilitychange', pruefen);
    window.addEventListener('focus', pruefen);
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) pruefen();
    });
    // Erster Tipp nach der Pause (manche App-WebViews melden das Zurückkehren nicht)
    document.addEventListener('pointerdown', pruefen, true);
})();
