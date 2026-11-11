<?php
/** @var \humhub\modules\kurse\models\Anmeldung $a */

use humhub\helpers\Html;
?>
<div class="breite schmal abschnitt">
    <div class="karte">
        <h1 style="margin-top:0;font-size:1.6em">✉️ Fast geschafft – bitte schau in dein Postfach</h1>
        <?php // Bewusst immer derselbe Text – auch wenn das Kind schon angemeldet ist (siehe Dienst::formularAnmelden) ?>
        <p>Wir haben eine Mail an <strong><?= Html::encode($a->email) ?></strong> geschickt. Klicke dort auf <strong>„Anmeldung bestätigen“</strong>.
            Erst dann bekommt <?= Html::encode($a->getKindName()) ?> einen Platz – Plätze werden in der Reihenfolge der Bestätigungen vergeben.</p>
        <p class="grau">Keine Mail da? Schau bitte auch im Spam-Ordner nach. Der Link gilt <?= Yii::$app->getModule('kurse')->unbestaetigtStunden ?> Stunden;
            bei einer vertippten Adresse kannst du dich einfach noch einmal anmelden. Kommt gar nichts an – etwa weil das Kind schon über eine andere
            E-Mail-Adresse angemeldet wurde –, melde dich bitte beim Verein: <?= Html::mailto(Html::encode($a->kurs->kontakt_email), $a->kurs->kontakt_email) ?>.</p>
        <p><a class="knopf rand" href="<?= Html::encode($a->kurs->elternUrl('kurs')) ?>">Zurück zum Kurs</a></p>
    </div>
</div>
