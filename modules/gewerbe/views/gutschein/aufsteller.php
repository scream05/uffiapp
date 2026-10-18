<?php
/**
 * Druckseite: Theken-Aufsteller (A6 hoch, auf A4 mit Schnittlinie viermal) für die Kasse
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var string $url Ziel des Theken-QR
 * @var string $sprache Sprache der Karten (Kundschaft); Hinweise oben in der Sprache des Betriebs
 * @var array $sprachen Sprachkürzel => Name
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\components\GutscheinDienst;

$svg = preg_replace('/^<\?xml[^>]*>\s*/', '', GutscheinDienst::qrSvg($url, 600));
$k = fn($s, $p = []) => Yii::t('GewerbeModule.base', $s, $p, $sprache);
$t = fn($s, $p = []) => Yii::t('GewerbeModule.base', $s, $p);
$rtl = str_starts_with($sprache, 'ar');
$karte = function () use ($betrieb, $svg, $k, $rtl, $sprache) { ?>
    <section class="karte" lang="<?= Html::encode($sprache) ?>"<?= $rtl ? ' dir="rtl"' : '' ?>>
        <div class="kopf"><?= $k('Gutschein einlösen') ?></div>
        <div class="name"><?= Html::encode($betrieb->name) ?></div>
        <div class="qr"><?= $svg ?></div>
        <ol>
            <li><?= $k('QR-Code mit der Handykamera scannen') ?></li>
            <li><?= $k('Bei <strong>Rund um Uffni</strong> anmelden (kostenlos)') ?></li>
            <li><?= $k('„Jetzt einlösen“ tippen und vorzeigen') ?></li>
        </ol>
        <div class="nummer"><?= $k('Ohne Kamera: Nummer {nummer}', ['nummer' => '<strong dir="ltr">' . Html::encode($betrieb->theke_nummer) . '</strong>']) ?></div>
        <div class="fuss">community.rundumuffni.de</div>
    </section>
<?php };
?>
<!DOCTYPE html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $t('Theken-Aufsteller – {name}', ['name' => Html::encode($betrieb->name)]) ?></title>
    <style <?= Html::nonce() ?>>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Nunito Sans", "Segoe UI", Arial, sans-serif; color: #1d2b33; background: #ebebeb; }
        .hinweis { max-width: 210mm; margin: 12px auto; padding: 10px 14px; background: #fff; border-radius: 10px; font-size: 14px; }
        .hinweis button { font: inherit; padding: 6px 14px; border-radius: 8px; border: 0; background: #446e88; color: #fff; cursor: pointer; }
        .blatt { width: 210mm; height: 297mm; margin: 0 auto; background: #fff; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
        .karte { border: 0.3mm dashed #b5b9bd; padding: 9mm 8mm 7mm; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .kopf { background: #446e88; color: #fff; font-weight: 800; font-size: 15pt; padding: 2mm 6mm; border-radius: 4mm; letter-spacing: .02em; }
        .name { margin-top: 4mm; font-size: 13pt; font-weight: 700; line-height: 1.2; }
        .qr { width: 60mm; height: 60mm; margin: 4mm 0 3mm; }
        .qr svg { width: 100%; height: 100%; display: block; }
        ol { text-align: start; margin: 0; padding-inline-start: 5mm; padding-inline-end: 0; font-size: 10pt; line-height: 1.45; }
        .hinweis select { font: inherit; padding: 4px 6px; border-radius: 8px; }
        .nummer { margin-top: 3mm; font-size: 10pt; }
        .nummer strong { font-size: 14pt; letter-spacing: .15em; }
        .fuss { margin-top: auto; padding-top: 2mm; font-size: 8.5pt; color: #5e7f0a; font-weight: 700; }
        @media print { body { background: #fff; } .hinweis { display: none; } .blatt { margin: 0; } }
    </style>
</head>
<body>
<div class="hinweis">
    <?= $t('Vier Aufsteller auf einem A4-Blatt (A6). Ausschneiden, ggf. laminieren oder in einen Tischaufsteller stecken.') ?>
    <?= $t('Drucken ohne Skalierung („Tatsächliche Größe“).') ?>
    <form method="get" style="display:inline">
        <input type="hidden" name="betrieb" value="<?= (int)$betrieb->id ?>">
        <label><?= $t('Sprache der Karten:') ?>
            <select name="sprache" id="sprache">
                <?php foreach ($sprachen as $code => $name): ?>
                    <option value="<?= Html::encode($code) ?>"<?= $code === $sprache ? ' selected' : '' ?>><?= Html::encode($name) ?></option>
                <?php endforeach ?>
            </select>
        </label>
    </form>
    <button type="button" id="drucken"><?= $t('Drucken') ?></button>
</div>
<div class="blatt">
    <?php for ($i = 0; $i < 4; $i++) {
        $karte();
    } ?>
</div>
<script <?= Html::nonce() ?>>
    document.getElementById('drucken').addEventListener('click', function () { window.print(); });
    document.getElementById('sprache').addEventListener('change', function () { this.form.submit(); });
</script>
</body>
</html>
