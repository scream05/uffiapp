<?php
/** @var \humhub\modules\kurse\models\Kurs $kurs @var int $betroffen */

use humhub\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Kurs absagen: <?= Html::encode($kurs->titel) ?></strong></div>
    <div class="panel-body">
        <p>Alle <?= $betroffen ?> angemeldeten Kinder (mit Platz oder auf der Warteliste) werden abgemeldet, die Eltern bekommen eine Mail. Das lässt sich nicht rückgängig machen.</p>
        <?= Html::beginForm() ?>
        <div class="mb-3">
            <label class="form-label" for="grund">Begründung für die Eltern (optional)</label>
            <?= Html::textarea('grund', '', ['class' => 'form-control', 'rows' => 3, 'id' => 'grund', 'placeholder' => 'z. B. Das Hallenbad bleibt wegen Reparaturen geschlossen.']) ?>
        </div>
        <?= Html::submitButton('Kurs absagen und Eltern informieren', ['class' => 'btn btn-danger', 'data-confirm' => 'Kurs wirklich absagen?']) ?>
        <?= Html::a('Abbrechen', $kurs->getUrl(), ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
