<?php
/**
 * @var \humhub\modules\space\models\Space $space
 * @var \humhub\modules\maengelmelder\models\Mitarbeiter $mitarbeiter
 * @var array $offen [content_id => Beschreibung] offene Aufträge
 * @var \humhub\modules\maengelmelder\models\BauhofNachricht[] $verlauf
 */

use humhub\helpers\Html;

$m = $mitarbeiter;
$fmt = Yii::$app->formatter;
?>
<div class="panel panel-default">
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'aktiv' => 'chat']) ?>
        <h2 class="h5"><i class="fa fa-comments"></i> <?= Html::encode($m->name) ?>
            <small class="text-muted"><?= Html::encode($m->getTelefonAnzeige()) ?></small></h2>

        <?php if ($offen): ?>
            <p class="small mb-2"><strong>Offene Aufträge:</strong>
                <?php foreach ($offen as $id => $text): ?>
                    <?= Html::a(Html::encode($text), $space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $id]), ['class' => 'd-block']) ?>
                <?php endforeach ?></p>
        <?php endif ?>

        <div class="bauhof-verlauf" id="bauhof-verlauf"
             data-url="<?= Html::encode($space->createUrl('/maengelmelder/bauhof/verlauf', ['mitarbeiter' => $m->id])) ?>">
            <?= $this->render('_verlauf', ['verlauf' => $verlauf, 'space' => $space, 'mitAuftrag' => true]) ?>
        </div>

        <?php if ($m->aktiv): ?>
            <?= Html::beginForm('', 'post', ['class' => 'mt-2', 'id' => 'bauhof-chat']) ?>
            <?php if ($offen): ?>
                <?= Html::dropDownList('content_id', '', $offen, ['class' => 'form-control form-control-sm mb-1', 'prompt' => 'Ohne Auftragsbezug']) ?>
            <?php endif ?>
            <div class="input-group">
                <?= Html::textarea('text', '', ['class' => 'form-control', 'rows' => 2, 'maxlength' => 2000, 'required' => true,
                    'placeholder' => 'Nachricht an ' . $m->name . ' per WhatsApp …']) ?>
                <button class="btn btn-primary" title="Senden"><i class="fa fa-paper-plane"></i></button>
            </div>
            <small class="text-muted">
                <?= $m->isFensterOffen()
                    ? 'Kommt direkt an (WhatsApp-Fenster offen bis ' . $fmt->asTime($m->getFensterBis(), 'short') . ').'
                    : 'Hat seit über 24 Std. nicht geschrieben: zuerst kommt der Hinweis „Neue Nachricht vom Bauhof“, die Nachricht folgt beim Antippen.' ?>
            </small>
            <?= Html::endForm() ?>
        <?php else: ?>
            <p class="text-muted">Mitarbeiter ist inaktiv – keine Nachrichten möglich.</p>
        <?php endif ?>
    </div>
</div>
<?= $this->render('_verlaufJs') ?>
