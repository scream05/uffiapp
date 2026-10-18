<?php
/**
 * Kasten „Kalender abonnieren“ unter dem Kalender (Events::onCalendarTypeLegend), auch für Gäste.
 *
 * @var array $abos Liste [Bezeichnung, https-Adresse]
 * @var string $hinweis
 */

use humhub\helpers\Html;

?>
<div class="panel panel-default kal-abo">
    <div class="panel-heading">
        <strong><i class="fa fa-calendar-plus-o"></i> <?= Yii::t('DesignwahlModule.base', 'Kalender abonnieren') ?></strong>
    </div>
    <div class="panel-body">
        <p><?= Yii::t('DesignwahlModule.base', 'Die Termine erscheinen in deinem Kalender auf dem Handy oder am Computer und werden von selbst aktualisiert. Ein Konto ist dafür nicht nötig.') ?></p>
        <?php if ($hinweis !== '') : ?>
            <p class="text-muted"><?= Html::encode($hinweis) ?></p>
        <?php endif ?>
        <?php foreach ($abos as [$name, $url]) : ?>
            <?php $webcal = preg_replace('#^https?://#', 'webcal://', $url) ?>
            <div class="kal-abo-zeile">
                <?php if (count($abos) > 1) : ?>
                    <div class="kal-abo-name"><?= Html::encode($name) ?></div>
                <?php endif ?>
                <div class="kal-abo-knoepfe">
                    <?= Html::a('<i class="fa fa-mobile"></i> ' . Html::encode(Yii::t('DesignwahlModule.base', 'Abonnieren (iPhone, Outlook, Thunderbird)')), $webcal,
                        ['class' => 'btn btn-primary btn-sm']) ?>
                    <?= Html::a('<i class="fa fa-google"></i> ' . Html::encode(Yii::t('DesignwahlModule.base', 'Google Kalender (Android)')),
                        'https://calendar.google.com/calendar/r?cid=' . rawurlencode($webcal),
                        ['class' => 'btn btn-light btn-sm', 'target' => '_blank', 'rel' => 'noopener']) ?>
                </div>
                <div class="input-group input-group-sm kal-abo-adresse">
                    <input type="text" class="form-control" readonly value="<?= Html::encode($url) ?>"
                           aria-label="<?= Yii::t('DesignwahlModule.base', 'Adresse zum Abonnieren') ?>" onclick="this.select()">
                    <button type="button" class="btn btn-light kal-abo-kopieren" data-kopiert="<?= Yii::t('DesignwahlModule.base', 'Kopiert ✓') ?>"><?= Yii::t('DesignwahlModule.base', 'Adresse kopieren') ?></button>
                </div>
            </div>
        <?php endforeach ?>
        <p class="text-muted small kal-abo-tipp"><?= Yii::t('DesignwahlModule.base', 'Klappt der Knopf nicht? Adresse kopieren und in der Kalender-App unter „Kalender hinzufügen“ → „Abonnement“ bzw. „Per URL“ einfügen. Android: In Google Kalender abonnierte Kalender erscheinen auf dem Handy, wenn dort in den Einstellungen der Kalender eingeschaltet ist.') ?></p>
    </div>
</div>
<?php $this->registerCss(<<<'CSS'
.kal-abo .kal-abo-zeile { margin-bottom: 14px; }
.kal-abo .kal-abo-name { font-weight: 600; margin-bottom: 6px; }
.kal-abo .kal-abo-knoepfe { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
.kal-abo .kal-abo-adresse { max-width: 560px; }
.kal-abo .kal-abo-tipp { margin: 0; }
CSS, [], 'kal-abo') ?>
<?php $this->registerJs(<<<'JS'
if (!window.kalAbo) {
    window.kalAbo = true; // Seitenwechsel per PJAX: Handler nur einmal binden
    $(document).on('click', '.kal-abo-kopieren', function () {
        var $btn = $(this), feld = $btn.siblings('input')[0];
        feld.select();
        var alt = $btn.data('text') || $btn.text();
        $btn.data('text', alt);
        var fertig = function () { $btn.text($btn.attr('data-kopiert') || 'Kopiert ✓'); setTimeout(function () { $btn.text(alt); }, 2000); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(feld.value).then(fertig, function () { document.execCommand('copy'); fertig(); });
        } else {
            document.execCommand('copy');
            fertig();
        }
    });
}
JS) ?>
