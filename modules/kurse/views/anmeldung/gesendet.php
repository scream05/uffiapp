<?php
/** @var \humhub\modules\kurse\models\Anmeldung $a */

use humhub\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-body">
        <h1 style="font-size:1.4em"><i class="fa fa-envelope-o"></i> <?= Yii::t('KurseModule.base', 'Fast geschafft – bitte E-Mail bestätigen') ?></h1>
        <p><?= Yii::t('KurseModule.base', 'Wir haben eine Mail an {email} geschickt. Klicke dort auf {knopf}. Erst dann bekommt {kind} einen Platz – Plätze werden in der Reihenfolge der Bestätigungen vergeben.', ['email' => '<strong>' . Html::encode($a->email) . '</strong>', 'knopf' => '<strong>' . Html::encode(Yii::t('KurseModule.base', 'Anmeldung bestätigen')) . '</strong>', 'kind' => Html::encode($a->getKindName())]) ?></p>
        <p class="text-muted"><?= Yii::t('KurseModule.base', 'Keine Mail da? Schau bitte auch im Spam-Ordner nach. Der Link gilt {stunden} Stunden; bei einer vertippten Adresse kannst du dich einfach noch einmal anmelden. Kommt gar nichts an – etwa weil das Kind schon über eine andere E-Mail-Adresse angemeldet wurde –, melde dich bitte beim Verein: {email}.', ['stunden' => Yii::$app->getModule('kurse')->unbestaetigtStunden, 'email' => Html::mailto(Html::encode($a->kurs->kontakt_email), $a->kurs->kontakt_email)]) ?></p>
        <p><?= Html::a(Html::encode(Yii::t('KurseModule.base', 'Zurück zum Kurs')), $a->kurs->getUrl(), ['class' => 'btn btn-light']) ?></p>
    </div>
</div>
