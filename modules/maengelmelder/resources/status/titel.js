/* Überschrift „Mängelmeldung: …“ im Beitragstext als Link zur Meldung (Bauhof: Bauhof-Ansicht, sonst der Beitrag), Ziel aus StatusAddon */
(function () {
    function verlinken() {
        document.querySelectorAll('.mm-statuszeile[data-mm-perma]:not([data-mm-verlinkt])').forEach(function (zeile) {
            zeile.setAttribute('data-mm-verlinkt', '');
            var eintrag = zeile.closest('.wall-entry') || zeile.closest('[data-content-key]');
            if (!eintrag) {
                return;
            }
            var titel = Array.prototype.find.call(eintrag.querySelectorAll('[data-ui-markdown] strong, .markdown-render strong'), function (s) {
                return /^Mängelmeldung:/.test(s.textContent.trim()) && !s.closest('a');
            });
            if (!titel || /\/(post\/post\/view|bauhof\/mangel)/.test(location.pathname)) { // nicht auf der Meldung selbst
                return;
            }
            var a = document.createElement('a');
            a.href = zeile.getAttribute('data-mm-perma');
            a.className = 'mm-titel-link';
            titel.parentNode.insertBefore(a, titel);
            a.appendChild(titel);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', verlinken);
    } else {
        verlinken();
    }
    var warte = null;
    new MutationObserver(function () {
        clearTimeout(warte);
        warte = setTimeout(verlinken, 50);
    }).observe(document.documentElement, {childList: true, subtree: true});
})();
