<?php
/**
 * @var string $vorname
 * @var array[] $schritte [symbol, titel, text, knopf-text|null, url|null, uffi]
 * @var string|null $avatar
 */

use humhub\helpers\Html;
use yii\helpers\Url;

$anzahl = count($schritte);
?>
<div class="nk-popup nk-rundgang" id="nk-rundgang" role="dialog" aria-modal="true" aria-labelledby="nk-rg-titel-0" hidden
     data-gesehen-url="<?= Html::encode(Url::to(['/neuigkeiten/index/rundgang'])) ?>">
    <div class="nk-popup-hinter"></div>
    <div class="nk-popup-karte">
        <button type="button" class="nk-popup-x" data-nk-rg-zu aria-label="<?= Yii::t('NeuigkeitenModule.base', 'Rundgang überspringen') ?>"
                title="<?= Yii::t('NeuigkeitenModule.base', 'Rundgang überspringen') ?>"><i class="fa fa-times" aria-hidden="true"></i></button>
        <p class="nk-rg-hallo"><?= Yii::t('NeuigkeitenModule.base', 'Willkommen, {name}', ['name' => Html::encode($vorname)]) ?></p>
        <?php foreach ($schritte as $i => [$symbol, $titel, $text, $knopf, $url, $uffi]): ?>
            <section class="nk-rg-schritt" data-nk-rg-schritt="<?= $i ?>"<?= $i ? ' hidden' : '' ?> aria-labelledby="nk-rg-titel-<?= $i ?>">
                <div class="nk-rg-bild nk-rg-bild-<?= $i ?>" aria-hidden="true">
                    <?php if ($uffi && $avatar): ?>
                        <img src="<?= Html::encode($avatar) ?>" alt="">
                    <?php else: ?>
                        <i class="fa fa-<?= Html::encode($symbol) ?>"></i>
                    <?php endif ?>
                </div>
                <h2 id="nk-rg-titel-<?= $i ?>"><?= Html::encode($titel) ?></h2>
                <p><?= Html::encode($text) ?></p>
                <?php if ($knopf !== null): ?>
                    <a class="btn btn-light nk-rg-ziel" href="<?= Html::encode($url) ?>"<?= $uffi ? ' data-nk-rg-uffi' : '' ?> data-pjax-prevent>
                        <?= Html::encode($knopf) ?> <i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                <?php endif ?>
            </section>
        <?php endforeach ?>
        <div class="nk-popup-fuss nk-rg-fuss">
            <div class="nk-rg-punkte" aria-hidden="true">
                <?php for ($i = 0; $i < $anzahl; $i++): ?><span<?= $i ? '' : ' class="aktiv"' ?>></span><?php endfor ?>
            </div>
            <span class="visually-hidden" aria-live="polite" data-nk-rg-stand
                  data-vorlage="<?= Html::encode(Yii::t('NeuigkeitenModule.base', 'Schritt {nr} von {anzahl}', ['nr' => '{nr}', 'anzahl' => $anzahl])) ?>"></span>
            <button type="button" class="btn btn-light" data-nk-rg-zurueck hidden><?= Yii::t('NeuigkeitenModule.base', 'Zurück') ?></button>
            <button type="button" class="btn btn-primary" data-nk-rg-weiter
                    data-weiter="<?= Yii::t('NeuigkeitenModule.base', 'Weiter') ?>"
                    data-fertig="<?= Yii::t('NeuigkeitenModule.base', 'Los geht’s') ?>"><?= Yii::t('NeuigkeitenModule.base', 'Weiter') ?></button>
        </div>
    </div>
</div>
<?php $this->registerJs('window.nkRundgang && window.nkRundgang();') ?>
