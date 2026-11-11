<?php

use humhub\helpers\Html;
use humhub\modules\stadtrat\components\Ris;

/** @var \humhub\modules\stadtrat\models\Sitzung[] $sitzungen */
/** @var \humhub\modules\stadtrat\models\Kurzfassung[] $fassungen */
$t = fn($s, $p = []) => Yii::t('StadtratModule.base', $s, $p);
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= $t('Stadtrat kurz erklärt') ?></strong> · <?= Html::a($t('zur Seite „Stadtrat“'), Ris::url()) ?></div>
    <div class="panel-body">
        <p><?= $t('Niederschrift einer öffentlichen Sitzung als PDF hochladen. {ki} schreibt daraus eine Kurzfassung, eine ausführliche Fassung und einen Vorlesetext. Du prüfst und änderst die Texte, lässt das Audio erzeugen, hörst es an und gibst frei. Erst dann ist es öffentlich.', ['ki' => Yii::$app->getModule('ki-assistent')?->getAnbieterName() ?? 'KI']) ?></p>
        <table class="table">
            <thead><tr><th><?= $t('Sitzung') ?></th><th><?= $t('Stand') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($sitzungen as $s): $k = $fassungen[$s->id] ?? null; ?>
                <tr>
                    <td><?= Html::encode($s->tag()) ?> · <?= Html::encode($s->gremium) ?></td>
                    <td><?= $k === null ? '–' : ($k->arbeit ? Html::encode($t('in Arbeit')) : ($k->istFreigegeben() ? '<span class="text-success">' . $t('veröffentlicht') . '</span>' : $t('Entwurf'))) ?></td>
                    <td><?= Html::a($k ? $t('Bearbeiten') : $t('Niederschrift hochladen'), ['bearbeiten', 'id' => $s->id], ['class' => 'btn btn-sm btn-light btn-default']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
