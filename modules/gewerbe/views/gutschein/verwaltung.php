<?php
/**
 * Gutscheine eines Betriebs für seine Verwalter
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var \humhub\modules\gewerbe\models\Gutschein[] $gutscheine
 * @var array $heute gutschein_id => Einlösungen heute
 * @var \humhub\modules\gewerbe\models\Einloesung[] $einloesungen letzte 30 Tage
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\components\GutscheinDienst;
use humhub\modules\gewerbe\models\Gutschein;

$space = $this->context->contentContainer;
$f = Yii::$app->formatter;
$module = Yii::$app->getModule('gewerbe');
$knopf = fn(Gutschein $g, string $aktion, string $label, string $klasse = 'btn-light', ?string $frage = null) =>
    Html::a($label, $space->createUrl('/gewerbe/gutschein/status', ['id' => $g->id]), ['class' => "btn btn-sm $klasse",
        'data-method' => 'post', 'data-params' => ['aktion' => $aktion], 'data-confirm' => $frage]);
?>
<?= $this->render('_stil') ?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left" aria-hidden="true"></i> ' . Html::encode($betrieb->name), $betrieb->getUrl()) ?></p>
        <h1 style="font-size:1.4em;margin-top:0"><?= Yii::t('GewerbeModule.base', 'Gutscheine') ?></h1>
        <p><?= Html::a('<i class="fa fa-plus" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Neuer Gutschein'), $space->createUrl('/gewerbe/gutschein/create', ['betrieb' => $betrieb->id]), ['class' => 'btn btn-primary']) ?></p>
        <?php if (!$gutscheine): ?>
            <p class="text-muted"><?= Yii::t('GewerbeModule.base', 'Noch keine Gutscheine. Leg den ersten an – er ist sofort für alle sichtbar.') ?></p>
        <?php endif ?>
        <?php foreach ($gutscheine as $g): $abgelaufen = $g->istAbgelaufen(); ?>
            <div class="gs-ticket<?= GutscheinDienst::grundAllgemein($g) === null ? '' : ' zu' ?>" style="margin-bottom:12px;flex-direction:column;align-items:stretch">
                <span class="gs-titel"><?= Html::a(Html::encode($g->titel), $g->getUrl()) ?></span>
                <span class="gs-stand"><?= Html::encode($g->getStandText()) ?></span>
                <?php if ($g->pause_grund): ?>
                    <div class="alert alert-warning" style="margin:8px 0 0"><?= Html::encode($g->pause_grund) ?></div>
                <?php endif ?>
                <small class="text-muted" style="margin-top:4px">
                    <?= $g->limit_gesamt
                        ? Yii::t('GewerbeModule.base', 'Eingelöst: {anzahl} von {gesamt}', ['anzahl' => (int)$g->eingeloest, 'gesamt' => (int)$g->limit_gesamt])
                        : Yii::t('GewerbeModule.base', 'Eingelöst: {anzahl}', ['anzahl' => (int)$g->eingeloest]) ?> · <?= Yii::t('GewerbeModule.base', 'heute {anzahl}', ['anzahl' => (int)($heute[$g->id] ?? 0)]) ?>
                    · <?= Yii::t('GewerbeModule.base', '{wie_oft} pro Person', ['wie_oft' => Html::encode(Gutschein::proPersonLabels()[$g->pro_person] ?? '')]) ?>
                </small>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px">
                    <?= Html::a('<i class="fa fa-pencil" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Bearbeiten'), $space->createUrl('/gewerbe/gutschein/edit', ['id' => $g->id]), ['class' => 'btn btn-sm btn-light']) ?>
                    <?php if ($g->status === Gutschein::STATUS_AKTIV && !$abgelaufen): ?>
                        <?= $knopf($g, 'pausieren', '<i class="fa fa-pause" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Pausieren')) ?>
                    <?php elseif ($g->status !== Gutschein::STATUS_AKTIV && !$abgelaufen): ?>
                        <?= $knopf($g, 'starten', '<i class="fa fa-play" aria-hidden="true"></i> ' . ($g->status === Gutschein::STATUS_BEENDET ? Yii::t('GewerbeModule.base', 'Wieder anbieten') : Yii::t('GewerbeModule.base', 'Weiter einlösen'))) ?>
                    <?php endif ?>
                    <?php if ($g->status !== Gutschein::STATUS_BEENDET && !$abgelaufen): ?>
                        <?= $knopf($g, 'beenden', '<i class="fa fa-stop" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Beenden'), 'btn-light', Yii::t('GewerbeModule.base', 'Gutschein beenden? Er ist dann nicht mehr zu sehen.')) ?>
                    <?php endif ?>
                    <?= Html::a('<i class="fa fa-qrcode" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'QR für Werbung'), $space->createUrl('/gewerbe/gutschein/qr', ['id' => $g->id, 'download' => 1]),
                        ['class' => 'btn btn-sm btn-light', 'data-pjax-prevent' => true, 'title' => Yii::t('GewerbeModule.base', 'QR-Code (SVG) mit Link zu diesem Gutschein, z. B. für Schaufenster, Flyer oder Instagram')]) ?>
                    <?= $knopf($g, 'loeschen', '<i class="fa fa-trash" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Löschen'), 'btn-link text-danger', Yii::t('GewerbeModule.base', 'Gutschein ganz löschen, samt der Liste der Einlösungen?')) ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('GewerbeModule.base', 'Theken-QR für deine Kasse') ?></strong></div>
    <div class="panel-body">
        <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:center">
            <img src="<?= Html::encode($space->createUrl('/gewerbe/gutschein/qr', ['id' => $betrieb->id, 'art' => 'theke'])) ?>" alt="<?= Html::encode(Yii::t('GewerbeModule.base', 'Theken-QR von {name}', ['name' => $betrieb->name])) ?>"
                 width="150" height="150" style="background:#fff;border-radius:8px">
            <div style="flex:1;min-width:220px">
                <p><?= Yii::t('GewerbeModule.base', 'Stell den Aufsteller gut sichtbar an die Kasse. Damit lösen deine Kunden alle deine Gutscheine ein; er gilt, bis du ihn erneuerst.') ?>
                    <?= Yii::t('GewerbeModule.base', 'Ohne QR-Scanner geben sie die Nummer {nummer} ein.', ['nummer' => '<strong style="font-size:1.2em;letter-spacing:.15em">' . Html::encode($betrieb->theke_nummer) . '</strong>']) ?></p>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    <?= Html::a('<i class="fa fa-print" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Aufsteller drucken'), $space->createUrl('/gewerbe/gutschein/aufsteller', ['betrieb' => $betrieb->id]),
                        ['class' => 'btn btn-primary btn-sm', 'target' => '_blank', 'data-pjax-prevent' => true]) ?>
                    <?= Html::a('<i class="fa fa-refresh" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Neuen QR erzeugen'), $space->createUrl('/gewerbe/gutschein/theke-erneuern', ['betrieb' => $betrieb->id]),
                        ['class' => 'btn btn-light btn-sm', 'data-method' => 'post',
                            'data-confirm' => Yii::t('GewerbeModule.base', 'Neuen Theken-QR und neue Nummer erzeugen? Der bisherige Aufsteller funktioniert dann nicht mehr. Sinnvoll, wenn der QR-Code weitergegeben wurde.')]) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('GewerbeModule.base', 'Einlösungen der letzten 30 Tage') ?></strong></div>
    <div class="panel-body">
        <p class="help-block"><?= Yii::t('GewerbeModule.base', 'Du siehst Uhrzeit und Code – der Code steht auch auf dem Bildschirm der Kundin oder des Kunden. Wer eingelöst hat, sehen wir dir nicht.') ?>
            <?= Yii::t('GewerbeModule.base', 'Aus Versehen eingelöst? In den ersten {minuten} Minuten kannst du die Einlösung zurücknehmen.', ['minuten' => (int)$module->stornoMinuten]) ?></p>
        <?php if (!$einloesungen): ?>
            <p class="text-muted" style="margin:0"><?= Yii::t('GewerbeModule.base', 'Noch keine Einlösungen.') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-condensed" style="margin:0">
                    <thead><tr><th scope="col"><?= Yii::t('GewerbeModule.base', 'Zeit') ?></th><th scope="col"><?= Yii::t('GewerbeModule.base', 'Code') ?></th><th scope="col"><?= Yii::t('GewerbeModule.base', 'Gutschein') ?></th><th scope="col"><span class="visually-hidden"><?= Yii::t('GewerbeModule.base', 'Aktion') ?></span></th></tr></thead>
                    <tbody>
                    <?php foreach ($einloesungen as $e): ?>
                        <tr<?= $e->storniert_am ? ' class="text-muted" style="text-decoration:line-through"' : '' ?>>
                            <td style="white-space:nowrap"><?= $f->asDatetime($e->created_at, 'php:j.n. H:i') ?></td>
                            <td><code style="font-size:1.05em;letter-spacing:.1em"><?= Html::encode($e->code) ?></code></td>
                            <td><?= Html::encode($e->gutschein->titel ?? '') ?></td>
                            <td style="text-align:right">
                                <?php if ($e->storniert_am === null && $e->alter() <= 60 * $module->stornoMinuten): ?>
                                    <?= Html::a(Yii::t('GewerbeModule.base', 'Zurücknehmen'), $space->createUrl('/gewerbe/gutschein/stornieren', ['id' => $e->id]), ['class' => 'btn btn-sm btn-light',
                                        'data-method' => 'post', 'data-confirm' => Yii::t('GewerbeModule.base', 'Einlösung {code} zurücknehmen? Die Person kann den Gutschein dann erneut nutzen.', ['code' => $e->code])]) ?>
                                <?php elseif ($e->storniert_am): ?>
                                    <small><?= Yii::t('GewerbeModule.base', 'zurückgenommen') ?></small>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
