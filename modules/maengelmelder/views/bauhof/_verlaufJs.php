<?php
/** Verlauf alle 20 s neu laden, Chat ohne Seitenwechsel senden, ans Ende scrollen */

use humhub\helpers\Html;

?>
<script <?= Html::nonce() ?>>
(function () {
    var box = document.getElementById('bauhof-verlauf');
    if (!box) return;
    var unten = function () { box.scrollTop = box.scrollHeight; };
    unten();
    var laden = function () {
        if (!document.body.contains(box) || document.hidden) return;
        fetch(box.dataset.url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (html) {
                if (html === null || html === box.innerHTML) return;
                var amEnde = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
                box.innerHTML = html;
                if (amEnde) unten();
            });
    };
    var timer = setInterval(function () {
        if (!document.body.contains(box)) { clearInterval(timer); return; } // Seitenwechsel per PJAX
        laden();
    }, 20000);
    $('#bauhof-chat').on('submit', function (e) {
        e.preventDefault();
        var form = this, btn = form.querySelector('button');
        btn.disabled = true;
        fetch(form.action || location.href, {method: 'POST', body: new FormData(form), credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.text(); })
            .then(function (html) { box.innerHTML = html; form.querySelector('textarea').value = ''; unten(); })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
