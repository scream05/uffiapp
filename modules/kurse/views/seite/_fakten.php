<?php
/**
 * Eckdaten eines Kurses als Liste
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
use humhub\modules\kurse\components\Inhalt;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\models\KursVerein;
?>
<dl class="fakten">
    <dt><?= Yii::t('KurseModule.base', 'Termine') ?></dt><dd><?= Html::encode($kurs->getTermineText()) ?><?= $kurs->termine_hinweis ? '<br><span class="grau">' . Html::encode(Inhalt::t($kurs->termine_hinweis)) . '</span>' : '' ?></dd>
    <dt><?= Yii::t('KurseModule.base', 'Ort') ?></dt><dd><?= Html::encode(Inhalt::t($kurs->ort)) ?></dd>
    <?php if ($alter = $kurs->getAlterText()): ?><dt><?= Yii::t('KurseModule.base', 'Alter') ?></dt><dd><?= Yii::t('KurseModule.base', '{alter} (am ersten Kurstag)', ['alter' => Html::encode($alter)]) ?></dd><?php endif ?>
    <dt><?= Yii::t('KurseModule.base', 'Kosten') ?></dt><dd><?= Html::encode($kurs->getPreisText()) ?><?php if ($kurs->hatPreis()): ?><br><span class="grau"><?= Yii::t('KurseModule.base', 'Zahlung: {zahlarten}', ['zahlarten' => Html::encode(implode(' ' . Yii::t('KurseModule.base', 'oder') . ' ', $kurs->zahlarten()))]) ?></span><?php endif ?></dd>
    <dt><?= Yii::t('KurseModule.base', 'Plätze') ?></dt><dd><?= $kurs->plaetze ?><?= $kurs->nur_mitglieder ? ' · ' . Yii::t('KurseModule.base', 'nur für Vereinsmitglieder') : '' ?></dd>
    <?php if ($mUrl = KursVerein::mitgliedUrl($kurs->verein_id)): ?>
        <dt><?= Yii::t('KurseModule.base', 'Mitglied werden') ?></dt><dd><?= Html::a(Html::encode(KursVerein::mitgliedLinkText($mUrl)), $mUrl, ['rel' => 'noopener', 'target' => '_blank']) ?></dd>
    <?php endif ?>
    <?php if ($kurs->anmeldung_ab || $kurs->anmeldung_bis): ?>
        <dt><?= Yii::t('KurseModule.base', 'Anmeldung') ?></dt><dd><?= $kurs->anmeldung_ab ? Html::encode(Yii::t('KurseModule.base', 'ab {zeitpunkt}', ['zeitpunkt' => Kurs::datumZeit($kurs->anmeldung_ab)])) : '' ?>
            <?= $kurs->anmeldung_bis ? Html::encode(Yii::t('KurseModule.base', 'bis {zeitpunkt}', ['zeitpunkt' => Kurs::datumZeit($kurs->anmeldung_bis)])) : '' ?></dd>
    <?php endif ?>
    <dt><?= Yii::t('KurseModule.base', 'Kontakt') ?></dt><dd><?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?></dd>
</dl>
