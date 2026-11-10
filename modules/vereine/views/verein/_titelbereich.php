<?php
/**
 * Titelbereich der Vereinsseite (Slogan, Unterzeile, Stichwörter, Knopf); das Titelbild steht in view.php über dem Namen.
 * Der Knopf springt zu den Kursen, wenn der Kurse-Kasten etwas zeigt (beim Klick geprüft), sonst zum Kontakt.
 *
 * @var \humhub\modules\vereine\models\Verein $verein
 */

use humhub\helpers\Html;

$stichwoerter = $verein->getStichwortList();
$knopf = trim((string)$verein->knopf_text);
?>
<style <?= Html::nonce() ?>>
    .verein-titelbild { width: 100%; aspect-ratio: 16 / 7; object-fit: cover; border-radius: 12px; display: block; margin-bottom: 14px; }
    .verein-slogan { font-size: 1.35em; font-weight: 700; margin: 14px 0 4px; line-height: 1.3; }
    .verein-stichwoerter { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0 0; padding: 0; list-style: none; }
    .verein-stichwoerter li { font-size: .82em; padding: 2px 10px; border-radius: 999px;
        background: var(--hh-background-color-secondary, #eef2f5); border: 1px solid var(--bs-border-color, #dde3e8); }
    .verein-knopf { display: flex; align-items: center; gap: 12px; margin-top: 14px; padding: 12px 16px; border-radius: 12px;
        background: var(--bs-primary, #0b6e8a); color: #fff !important; text-decoration: none !important; }
    .verein-knopf:hover, .verein-knopf:focus { filter: brightness(1.08); }
    .verein-knopf strong { display: block; font-size: 1.05em; }
    .verein-knopf .fa { margin-left: auto; font-size: 1.3em; }
</style>
<?php if (trim((string)$verein->slogan) !== ''): ?>
    <p class="verein-slogan"><?= Html::encode($verein->slogan) ?></p>
<?php endif ?>
<?php if (trim((string)$verein->untertitel) !== ''): ?>
    <p class="text-muted" style="margin:0"><?= Html::encode($verein->untertitel) ?></p>
<?php endif ?>
<?php if ($stichwoerter): ?>
    <ul class="verein-stichwoerter">
        <?php foreach ($stichwoerter as $wort): ?><li><?= Html::encode($wort) ?></li><?php endforeach ?>
    </ul>
<?php endif ?>
<?php if ($knopf !== ''): ?>
    <a class="verein-knopf" href="#verein-kontakt" data-pjax-prevent>
        <span><strong><?= Html::encode($knopf) ?></strong>
            <?php if (trim((string)$verein->knopf_untertext) !== ''): ?><span><?= Html::encode($verein->knopf_untertext) ?></span><?php endif ?></span>
        <i class="fa fa-arrow-down" aria-hidden="true"></i>
    </a>
    <script <?= Html::nonce() ?>>
    (function () {
        // Ziel erst beim Klick bestimmen: der Kurse-Kasten steht weiter unten (und ist leer, wenn keine Kurse sichtbar sind)
        document.querySelectorAll('.verein-knopf').forEach(function (knopf) {
            knopf.addEventListener('click', function (e) {
                var kurse = document.getElementById('verein-kurse');
                var ziel = kurse && kurse.querySelector('.panel') ? kurse : document.getElementById('verein-kontakt');
                if (ziel) {
                    e.preventDefault();
                    ziel.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            });
        });
    })();
    </script>
<?php endif ?>
