<?php
/**
 * @var string $avatar
 * @var bool $enabled
 * @var bool $ageConfirmed
 */

use humhub\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

?>
<button type="button" class="ki-fab" id="ki-fab" title="<?= Yii::t('KiAssistentModule.base', 'Uffi fragen – zum Verschieben ziehen') ?>" aria-label="<?= Yii::t('KiAssistentModule.base', 'Uffi, den KI-Assistenten, öffnen') ?>"
        aria-controls="ki-panel" aria-expanded="false">
    <img src="<?= Html::encode($avatar) ?>" alt="">
</button>
<div class="ki-panel" id="ki-panel" role="dialog" aria-label="<?= Yii::t('KiAssistentModule.base', 'Uffi – KI-Assistent') ?>">
    <div class="ki-panel-head">
        <img src="<?= Html::encode($avatar) ?>" alt="">
        <div class="ki-title" tabindex="-1"><strong>Uffi</strong><small><?= Yii::t('KiAssistentModule.base', 'KI-Assistent') ?></small></div>
        <a href="<?= Url::to(['/ki-assistent/chat']) ?>" title="<?= Yii::t('KiAssistentModule.base', 'Als Seite öffnen') ?>" aria-label="<?= Yii::t('KiAssistentModule.base', 'Als Seite öffnen') ?>"><i class="fa fa-expand" aria-hidden="true"></i></a>
        <button type="button" class="ki-close" title="<?= Yii::t('KiAssistentModule.base', 'Schließen') ?>" aria-label="<?= Yii::t('KiAssistentModule.base', 'Schließen') ?>"><i class="fa fa-times"></i></button>
    </div>
    <div class="ki-messages" role="log" aria-live="polite" aria-relevant="additions" aria-label="<?= Yii::t('KiAssistentModule.base', 'Gespräch mit Uffi') ?>">
        <p class="ki-hello" dir="auto"><?= Yii::t('KiAssistentModule.base', 'Hallo, ich bin Uffi! Ich beantworte Fragen zu Uffenheim: Abfuhrtermine, Veranstaltungen, Vereine, Rathaus und Formulare. Ich kann auch eine Mängelmeldung vorbereiten. Meine Antworten kommen von Claude (Anthropic) und sind unverbindlich.') ?></p>
    </div>
    <?php if (!$ageConfirmed): ?>
        <?= Html::beginForm(['/ki-assistent/chat/confirm-age'], 'post', ['class' => 'ki-age']) ?>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="adult" value="1" id="ki-adult" required>
            <label class="form-check-label" for="ki-adult"><?= Yii::t('KiAssistentModule.base', 'Ich bin mindestens 18 Jahre alt.') ?></label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm mt-1"><?= Yii::t('KiAssistentModule.base', 'Bestätigen') ?></button>
        <?= Html::endForm() ?>
    <?php endif ?>
    <div class="ki-panel-foot">
        <form class="ki-form">
            <div class="input-group">
                <input type="text" class="form-control ki-input" maxlength="2000" autocomplete="off"
                       dir="auto" placeholder="<?= Yii::t('KiAssistentModule.base', 'Deine Frage …') ?>" aria-label="<?= Yii::t('KiAssistentModule.base', 'Deine Frage') ?>" <?= $enabled ? '' : 'disabled' ?>>
                <button class="btn btn-primary" type="submit" title="<?= Yii::t('KiAssistentModule.base', 'Senden') ?>" aria-label="<?= Yii::t('KiAssistentModule.base', 'Senden') ?>" <?= $enabled ? '' : 'disabled' ?>>
                    <i class="fa fa-paper-plane"></i></button>
            </div>
        </form>
        <div class="ki-links"><a href="#" class="ki-reset"><?= Yii::t('KiAssistentModule.base', 'Neues Gespräch') ?></a> ·
            <?= Html::a(Html::encode(Yii::t('KiAssistentModule.base', 'Datenschutz')), ['/legal/page/view', 'pageKey' => 'privacy']) ?></div>
    </div>
</div>
<?php ob_start() // nach ki-chat.js ausführen, das erst am Seitenende geladen wird ?>
(function () {
    var body = document.body;
    var fab = document.getElementById('ki-fab');
    var panel = document.getElementById('ki-panel');
    var mobile = function () { return window.matchMedia('(max-width: 575.98px)').matches; };
    // Tablet (iPad): Fenster wie am Computer, aber Bildschirmtastatur – nicht von selbst ins Feld springen
    var touch = function () { return window.matchMedia('(hover: none) and (pointer: coarse)').matches; };
    var chat = kiChat(panel, {
        askUrl: <?= json_encode(Url::to(['/ki-assistent/chat/ask'])) ?>,
        feedbackUrl: <?= json_encode(Url::to(['/ki-assistent/chat/feedback'])) ?>,
        storageKey: 'ki-assistent-chat',
        newestFirst: mobile
    });

    function toggle(open) {
        body.classList.toggle('ki-fab-open', open);
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        try { sessionStorage.setItem('ki-assistent-open', open ? '1' : ''); } catch (e) {}
        fit();
        if (open) {
            var box = panel.querySelector('.ki-messages');
            box.scrollTop = mobile() ? 0 : box.scrollHeight;
            // Fokus ins Fenster (Screenreader); am Handy auf den Titel, damit keine Tastatur aufspringt
            if (!mobile() && !touch()) chat.focus(); else panel.querySelector('.ki-title').focus();
            // Eingabefeld noch gesperrt (Altersbestätigung fehlt): Fokus trotzdem ins Fenster, sonst landet er im Nichts
            if (!panel.contains(document.activeElement)) panel.querySelector('.ki-title').focus();
        } else {
            fab.focus();
            ueberHinweis();
        }
    }

    // Der Cookie-Hinweis (unten, über allem) verdeckt am Handy den Knopf, bis er bestätigt ist – Antippen und Ziehen
    // kommen dann nicht an. iOS löscht die Bestätigung (per Skript gesetztes Cookie) nach 7 Tagen, der Hinweis kommt
    // also immer wieder. So lange hebt --ki-heben den Knopf über den Hinweis (ki-chat.css, neu.css).
    var hinweisBeobachter = null;
    function ueberHinweis() {
        var cc = document.querySelector('.cc-window'), heben = 0;
        if (cc && !hinweisBeobachter) {
            hinweisBeobachter = new MutationObserver(ueberHinweis);
            hinweisBeobachter.observe(cc, {attributes: true, attributeFilter: ['class', 'style']});
        }
        var root = document.documentElement.style, alt = root.getPropertyValue('--ki-heben');
        if (cc && !cc.classList.contains('cc-invisible') && getComputedStyle(cc).display !== 'none') {
            root.setProperty('--ki-heben', '0px'); // Grundlage des Knopfs messen
            var c = cc.getBoundingClientRect(), f = fab.getBoundingClientRect();
            if (c.width && f.width && c.left < f.right && c.right > f.left && c.top > window.innerHeight / 2) {
                heben = Math.max(0, Math.ceil(f.bottom - c.top + 12));
            }
        }
        if (heben + 'px' !== alt) root.setProperty('--ki-heben', heben + 'px');
    }
    new MutationObserver(ueberHinweis).observe(body, {childList: true});
    window.addEventListener('resize', ueberHinweis);
    ueberHinweis();

    // Handy: Bei offener Tastatur schrumpft nur der sichtbare Bereich (visualViewport), nicht 100dvh –
    // sonst schiebt sich das Eingabefeld über die Nachrichten. Fenster daher genau auf den sichtbaren Bereich legen.
    var vv = window.visualViewport;
    function fit() {
        if (!vv || !(mobile() || touch()) || !body.classList.contains('ki-fab-open')) {
            panel.style.height = panel.style.top = '';
            return;
        }
        var box = panel.querySelector('.ki-messages');
        if (mobile()) {
            panel.style.top = vv.offsetTop + 'px';
            panel.style.height = vv.height + 'px';
            box.scrollTop = 0;
            return;
        }
        // Tablet: Fenster bleibt schwebend, rutscht aber mit der Tastatur nach oben, damit der Kopf mit Uffi sichtbar bleibt
        var h = Math.min(600, vv.height - 32);
        panel.style.height = h + 'px';
        panel.style.top = (vv.offsetTop + vv.height - h - 16) + 'px';
        box.scrollTop = box.scrollHeight;
    }
    if (vv) {
        vv.addEventListener('resize', fit);
        vv.addEventListener('scroll', fit);
    }
    panel.querySelector('.ki-input').addEventListener('focus', function () { setTimeout(fit, 300); });

    // Knopf frei verschiebbar (Maus/Finger), damit er z. B. den Senden-Knopf bei Nachrichten nicht verdeckt.
    // Beim Öffnen von Uffi kehrt er an seine ursprüngliche Stelle zurück.
    var drag = null, dragged = false;
    function place(x, y) {
        var w = fab.offsetWidth, h = fab.offsetHeight;
        fab.style.left = Math.max(4, Math.min(window.innerWidth - w - 4, x)) + 'px';
        fab.style.top = Math.max(4, Math.min(window.innerHeight - h - 4, y)) + 'px';
        fab.style.right = fab.style.bottom = 'auto';
    }
    fab.addEventListener('pointerdown', function (e) {
        if (e.button !== 0) return;
        var r = fab.getBoundingClientRect();
        drag = {id: e.pointerId, sx: e.clientX, sy: e.clientY, dx: e.clientX - r.left, dy: e.clientY - r.top, moving: false};
        dragged = false;
    });
    fab.addEventListener('pointermove', function (e) {
        if (!drag || e.pointerId !== drag.id) return;
        if (!drag.moving) {
            if (Math.abs(e.clientX - drag.sx) + Math.abs(e.clientY - drag.sy) < 8) return;
            drag.moving = true;
            fab.setPointerCapture(e.pointerId);
            fab.classList.add('ki-fab-dragging');
        }
        place(e.clientX - drag.dx, e.clientY - drag.dy);
    });
    function dragEnd() {
        if (!drag) return;
        dragged = drag.moving;
        drag = null;
        fab.classList.remove('ki-fab-dragging');
    }
    fab.addEventListener('pointerup', dragEnd);
    fab.addEventListener('pointercancel', dragEnd);
    // Nach Drehen/Verkleinern des Fensters im sichtbaren Bereich halten
    window.addEventListener('resize', function () {
        if (fab.style.left) place(parseFloat(fab.style.left), parseFloat(fab.style.top));
    });

    fab.addEventListener('click', function (e) {
        if (dragged) { dragged = false; e.preventDefault(); return; }
        fab.style.left = fab.style.top = fab.style.right = fab.style.bottom = '';
        toggle(true);
    });
    panel.querySelector('.ki-close').addEventListener('click', function () { toggle(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && body.classList.contains('ki-fab-open')) toggle(false);
    });
    // Handy: Link in einer Antwort angetippt → Fenster schließen, damit man die Seite sieht
    panel.querySelector('.ki-messages').addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (a && mobile() && !a.target && !a.hasAttribute('download')) toggle(false);
    });
    // Am Computer bleibt das Fenster auch nach einem vollen Seitenaufbau offen
    try { if (!mobile() && sessionStorage.getItem('ki-assistent-open')) toggle(true); } catch (e) {}

    var age = panel.querySelector('.ki-age');
    if (age) {
        age.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch(age.action, {method: 'POST', body: new FormData(age), credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) return;
                    age.remove();
                    chat.enable();
                    if (!touch()) chat.focus();
                });
        });
    }
})();
<?php $this->registerJs(ob_get_clean(), View::POS_END) ?>
