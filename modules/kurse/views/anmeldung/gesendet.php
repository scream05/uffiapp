<?php
/** @var \humhub\modules\kurse\models\Anmeldung $a */

use humhub\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-body">
        <h1 style="font-size:1.4em"><i class="fa fa-envelope-o"></i> Fast geschafft – bitte E-Mail bestätigen</h1>
        <p>Wir haben eine Mail an <strong><?= Html::encode($a->email) ?></strong> geschickt. Klicke dort auf <strong>„Anmeldung bestätigen“</strong>.
            Erst dann bekommt <?= Html::encode($a->getKindName()) ?> einen Platz – Plätze werden in der Reihenfolge der Bestätigungen vergeben.</p>
        <p class="text-muted">Keine Mail da? Schau bitte auch im Spam-Ordner nach. Der Link gilt <?= Yii::$app->getModule('kurse')->unbestaetigtStunden ?> Stunden;
            bei einer vertippten Adresse kannst du dich einfach noch einmal anmelden.</p>
        <p><?= Html::a('Zurück zum Kurs', $a->kurs->getUrl(), ['class' => 'btn btn-light']) ?></p>
    </div>
</div>
