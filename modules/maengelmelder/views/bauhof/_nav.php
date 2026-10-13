<?php
/**
 * Kopf der Bauhof-Seiten
 *
 * @var \humhub\modules\space\models\Space $space
 * @var string $aktiv index|mitarbeiter|…
 */

use humhub\helpers\Html;
use humhub\modules\maengelmelder\models\BauhofNachricht;

$ungelesen = BauhofNachricht::ungelesen();
?>
<div class="bauhof-nav d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 m-0 me-auto"><i class="fa fa-truck"></i> Bauhof</h1>
    <?= Html::a('<i class="fa fa-list"></i> Meldungen' . ($ungelesen ? ' <span class="badge bg-danger">' . $ungelesen . '</span>' : ''),
        $space->createUrl('/maengelmelder/bauhof/index'), ['class' => 'btn btn-sm ' . ($aktiv === 'index' ? 'btn-primary' : 'btn-light')]) ?>
    <?= Html::a('<i class="fa fa-users"></i> Mitarbeiter', $space->createUrl('/maengelmelder/bauhof/mitarbeiter'),
        ['class' => 'btn btn-sm ' . ($aktiv === 'mitarbeiter' ? 'btn-primary' : 'btn-light')]) ?>
    <?= Html::a('<i class="fa fa-plus"></i> Neuer Mangel', $space->createUrl('/maengelmelder/meldung/create', ['bauhof' => 1]),
        ['class' => 'btn btn-sm btn-light']) ?>
</div>
<?php $this->registerCss(<<<'CSS'
.bauhof-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; color: #fff; font-size: 12px; font-weight: 600; white-space: nowrap; }
.bauhof-zeile { cursor: pointer; }
.bauhof-zeile:hover { background: var(--hh-background-3, #f5f5f5); }
.bauhof-zeile.ueberfaellig td:first-child { box-shadow: inset 4px 0 0 #c0392b; }
.bauhof-neu { display: inline-block; min-width: 18px; padding: 0 5px; border-radius: 9px; background: #c0392b; color: #fff; font-size: 11px; text-align: center; }
.bauhof-karten { display: none; }
@media (max-width: 767.98px) {
    .bauhof-tabelle { display: none; }
    .bauhof-karten { display: block; }
    .bauhof-karte { display: block; padding: 12px; border-radius: 10px; margin-bottom: 10px; background: var(--hh-background-color-main, #fff);
        border: 1px solid rgba(0,0,0,.08); color: inherit; text-decoration: none; }
    .bauhof-karte.ueberfaellig { border-left: 4px solid #c0392b; }
}
.bauhof-verlauf { max-height: 60vh; overflow-y: auto; padding: 4px 2px; }
.bauhof-msg { max-width: 85%; margin: 6px 0; padding: 8px 12px; border-radius: 12px; overflow-wrap: anywhere; }
.bauhof-msg small { display: block; opacity: .75; font-size: 11px; margin-bottom: 2px; }
.bauhof-msg-ein { background: var(--hh-background-3, #f1f1f1); }
.bauhof-msg-aus { margin-left: auto; background: #dcf2e3; }
.bauhof-msg-intern { max-width: none; text-align: center; color: var(--hh-text-color-soft, #777); font-size: 12px; padding: 2px; }
.bauhof-msg img { display: block; max-width: 100%; max-height: 260px; border-radius: 8px; margin-top: 4px; }
.bauhof-fotos img { width: 110px; height: 110px; object-fit: cover; border-radius: 8px; margin: 0 6px 6px 0; }
CSS) ?>
