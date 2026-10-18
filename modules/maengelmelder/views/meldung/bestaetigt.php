<?php
/**
 * Ergebnis des Bestätigungslinks aus der E-Mail
 *
 * @var bool $ok
 * @var bool $abgelaufen
 * @var \humhub\modules\maengelmelder\models\MaengelKontakt|null $kontakt
 * @var \humhub\modules\space\models\Space $space
 * @var bool $infos
 */

use humhub\helpers\Html;

?>
<div class="panel panel-default">
    <div class="panel-body">
        <?php if ($ok): ?>
            <div class="alert alert-success" role="status">
                <strong><?= Yii::t('MaengelmelderModule.base', 'Danke, deine E-Mail-Adresse ist bestätigt.') ?></strong><br>
                <?= $kontakt && $kontakt->info_mail && $infos
                    ? Yii::t('MaengelmelderModule.base', 'Wir informieren dich per E-Mail über den Bearbeitungsstand deiner Meldung Nr. {nr}. Auf unsere E-Mails kannst du einfach antworten.', ['nr' => (int)$kontakt->content_id])
                    : Yii::t('MaengelmelderModule.base', 'Bei Rückfragen kann sich der Bauhof jetzt per E-Mail bei dir melden.') ?>
            </div>
        <?php elseif ($abgelaufen): ?>
            <div class="alert alert-warning" role="status"><?= Yii::t('MaengelmelderModule.base', 'Dieser Link ist abgelaufen. Deine Meldung ist trotzdem beim Bauhof – nur Infos per E-Mail bekommst du dazu nicht. Bei Fragen: {mail}', ['mail' => Html::mailto('smartcity@buergerliste-uffenheim.de')]) ?></div>
        <?php else: ?>
            <div class="alert alert-warning" role="status"><?= Yii::t('MaengelmelderModule.base', 'Dieser Bestätigungslink ist ungültig.') ?></div>
        <?php endif ?>
        <p><?= Html::a('<i class="fa fa-map-o"></i> ' . Yii::t('MaengelmelderModule.base', 'Zur Mängelkarte'), $space->createUrl('/maengelmelder/meldung/karte')) ?></p>
    </div>
</div>
