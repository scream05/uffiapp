<?php
/**
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var \humhub\modules\space\models\Space $space
 * @var \humhub\modules\kurse\models\Kurs[] $kurse
 * @var bool $freigeschaltet
 * @var bool $verwalten
 * @var bool $admin
 * @var int $entwuerfe
 */

use humhub\helpers\Html;

echo $this->render('@kurse/views/kurs/_stil');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><i class="fa fa-graduation-cap"></i> Kurse</strong></div>
    <div class="panel-body">
        <?php foreach ($kurse as $kurs): ?>
            <?= $this->render('@kurse/views/kurs/_karte', ['kurs' => $kurs, 'mitVerein' => false]) ?>
        <?php endforeach ?>
        <?php if (!$kurse && $freigeschaltet): ?>
            <p class="text-muted">Gerade sind keine Kurse ausgeschrieben.</p>
        <?php endif ?>
        <div class="d-flex flex-wrap gap-1">
            <?php if ($verwalten): ?>
                <?= Html::a('<i class="fa fa-plus"></i> Neuer Kurs', $space->createUrl('/kurse/kurs/create', ['verein' => $verein->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                <?= Html::a('<i class="fa fa-list"></i> Kurse verwalten' . ($entwuerfe ? " ($entwuerfe Entwurf" . ($entwuerfe > 1 ? 'e' : '') . ')' : ''), $space->createUrl('/kurse/kurs/index'), ['class' => 'btn btn-light btn-sm']) ?>
            <?php endif ?>
            <?php if ($admin): ?>
                <?= Html::a($freigeschaltet ? '<i class="fa fa-lock"></i> Kursmodul sperren' : '<i class="fa fa-unlock"></i> Kursmodul für diesen Verein freischalten',
                    $space->createUrl('/kurse/kurs/freigabe', ['verein' => $verein->id]), ['class' => 'btn btn-light btn-sm', 'data-method' => 'post',
                        'data-confirm' => $freigeschaltet ? 'Kursmodul für diesen Verein sperren? Kurse bleiben gespeichert, sind aber nicht mehr sichtbar.'
                            : 'Kursmodul freischalten? Alle bestätigten Verwalter dieses Vereins können dann Kurse anlegen und Anmeldungen (mit Kinderdaten) sehen.']) ?>
            <?php endif ?>
        </div>
        <?php if ($admin && !$freigeschaltet): ?>
            <p class="help-block mt-2 mb-0">Nur für Admins sichtbar. Vor der Freischaltung: Auftragsverarbeitungsvertrag mit dem Verein.</p>
        <?php endif ?>
    </div>
</div>
