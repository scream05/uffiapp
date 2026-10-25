<?php
/**
 * Seite „Sport“ (Menüpunkt im Space): alle Vereine mit Spielplan bzw. Ergebnis-Link, nach Verein gruppiert,
 * je Sportart das nächste Spiel und der Weg zur Seite „Spiele & Ergebnisse“. An Spieltagen oben „Sport heute“.
 * @var array[] $gruppen siehe SpieleController::gruppen()
 */

use humhub\helpers\Html;
use humhub\modules\vereine\components\Bfv;
use humhub\modules\vereine\components\Spielquellen;
use humhub\modules\vereine\widgets\SpieltagKasten;

$heute = SpieltagKasten::widget(['zeilen' => 0]);
?>
<?= $this->render('_stil') ?>
<style <?= Html::nonce() ?>>
    .sport-zeile { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid var(--bs-border-color, #e5e5e5); color: inherit; text-decoration: none; }
    .sport-zeile:first-child { border-top: 0; padding-top: 0; }
    .sport-zeile:hover .sport-titel { text-decoration: underline; }
    .sport-symbol { flex: 0 0 auto; width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        background: var(--bs-secondary-bg, #eef2f5); font-size: 17px; }
    .sport-mitte { flex: 1 1 auto; min-width: 0; line-height: 1.35; }
    .sport-titel { font-weight: 600; display: block; }
    .sport-naechstes { display: block; font-size: .85em; color: var(--bs-secondary-color, #6b6b6b); overflow-wrap: break-word; }
    .sport-verein { display: flex; align-items: center; gap: 10px; }
    .sport-verein h2 { margin: 0; font-size: 1.1em; }
</style>

<div class="panel panel-default">
    <div class="panel-body">
        <h1 style="margin:0 0 4px;font-size:1.4em"><i class="fa fa-trophy"></i> <?= Yii::t('VereineModule.base', 'Sport') ?></h1>
        <p class="text-muted" style="margin:0"><?= Yii::t('VereineModule.base', 'Spiele, Ergebnisse und Tabellen der Uffenheimer Vereine – täglich von den Verbänden abgeglichen.') ?></p>
    </div>
</div>

<?= $heute ?>

<?php if (!$gruppen): ?>
<div class="panel panel-default"><div class="panel-body"><p class="text-muted" style="margin:0"><?= Yii::t('VereineModule.base', 'Noch keine Vereine mit Spielplan.') ?></p></div></div>
<?php endif ?>

<?php foreach ($gruppen as $g): ?>
<div class="panel panel-default">
    <div class="panel-heading sport-verein">
        <?= $this->render('@vereine/views/verein/_logo', ['verein' => $g['logo'], 'size' => 36]) ?>
        <h2><?= Html::encode($g['name']) ?></h2>
    </div>
    <div class="panel-body">
        <?php foreach ($g['vereine'] as $v): ?>
            <?php if ($v->bfv_id):
                $q = Spielquellen::quelle($v);
                $kurz = Bfv::kurz($v, 6);
                $naechstes = current(array_filter($kurz['kommend'], fn($s) => $s->getZustand() === null)) ?: null; // abgesetzte überspringen ?>
                <a class="sport-zeile" href="<?= Html::encode($v->getSpieleUrl()) ?>">
                    <span class="sport-symbol"><i class="fa <?= $q['icon'] ?>" aria-hidden="true"></i></span>
                    <span class="sport-mitte">
                        <span class="sport-titel"><?= Html::encode(Spielquellen::sport($v)) ?></span>
                        <span class="sport-naechstes">
                            <?php if ($naechstes): ?>
                                <?= Yii::t('VereineModule.base', 'Nächstes Spiel') ?>:
                                <?= Html::encode(Bfv::format($naechstes->getZeitpunkt(), $naechstes->uhrzeit ? 'EEEdMHm' : 'EEEdM')) ?>,
                                <?= Html::encode($kurz['label'][$naechstes->mannschaft_id] ?? '') ?> –
                                <?= Html::encode($naechstes->getGegner()) ?><?php if ($naechstes->getHeimspiel() !== null): ?> (<?= $naechstes->heim_eigen ? Yii::t('VereineModule.base', 'Heimspiel') : Yii::t('VereineModule.base', 'Auswärts') ?>)<?php endif ?>
                            <?php else: ?>
                                <?= Html::encode(implode(' · ', array_values($kurz['label']))) ?>
                            <?php endif ?>
                        </span>
                    </span>
                    <i class="fa fa-chevron-right text-muted" aria-hidden="true"></i>
                </a>
            <?php else: // nur Link (Quelle erlaubt keinen Abruf) ?>
                <a class="sport-zeile" href="<?= Html::encode($v->ergebnisse_url) ?>" target="_blank" rel="noopener nofollow">
                    <span class="sport-symbol"><i class="fa fa-bullseye" aria-hidden="true"></i></span>
                    <span class="sport-mitte">
                        <span class="sport-titel"><?= Html::encode(Yii::t('VereineModule.base', 'Ergebnisse & Tabellen {wo}', ['wo' => $v->ergebnisse_text ?: preg_replace('#^https?://(www\.)?([^/]+).*$#', '$2', $v->ergebnisse_url)])) ?></span>
                        <span class="sport-naechstes"><?= Yii::t('VereineModule.base', 'Ergebnisse und Tabellen der Mannschaften veröffentlicht der Verband auf seiner eigenen Seite.') ?></span>
                    </span>
                    <i class="fa fa-external-link text-muted" aria-hidden="true"></i>
                </a>
            <?php endif ?>
        <?php endforeach ?>
    </div>
</div>
<?php endforeach ?>
