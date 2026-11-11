<?php
/** @var \humhub\modules\kurse\models\Kurs $kurs @var string $betreff @var string $nachricht @var string $an @var int[] $anzahl */

use humhub\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Rundmail: <?= Html::encode($kurs->titel) ?></strong></div>
    <div class="panel-body">
        <p class="help-block">Die Mail beginnt mit „Hallo &lt;Name&gt;,“ und endet mit dem persönlichen Link zur Anmeldung. Antworten gehen an <?= Html::encode($kurs->kontakt_email) ?>.</p>
        <?= Html::beginForm() ?>
        <div class="mb-3">
            <label class="form-label">An</label>
            <?= Html::radioList('an', $an, [
                'platz' => "alle Teilnehmer ({$anzahl['platz']})",
                'warteliste' => "nur Warteliste ({$anzahl['warteliste']})",
                'alle' => "Teilnehmer und Warteliste ({$anzahl['alle']})",
            ], ['class' => 'd-flex flex-wrap gap-3']) ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="betreff">Betreff</label>
            <?= Html::textInput('betreff', $betreff, ['class' => 'form-control', 'id' => 'betreff', 'maxlength' => 150]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="nachricht">Nachricht</label>
            <?= Html::textarea('nachricht', $nachricht, ['class' => 'form-control', 'rows' => 8, 'id' => 'nachricht']) ?>
        </div>
        <?= Html::submitButton('Senden', ['class' => 'btn btn-primary', 'data-confirm' => 'Rundmail jetzt verschicken?']) ?>
        <?= Html::a('Abbrechen', $kurs->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id]), ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
