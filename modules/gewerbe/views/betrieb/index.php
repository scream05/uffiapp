<?php
/**
 * @var \humhub\modules\gewerbe\models\Betrieb[] $betriebe
 * @var \humhub\modules\gewerbe\models\Betrieb[] $mine
 * @var array $anzahl Branche => Anzahl aktiver Betriebe
 * @var string $q
 * @var string $kategorie
 * @var string $ortsteil
 * @var string $offen nicht leer = nur jetzt geöffnete
 * @var \humhub\modules\gewerbe\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Betrieb;

$space = $this->context->contentContainer;
$filtered = trim($q) !== '' || $kategorie !== '' || $ortsteil !== '' || $offen !== '';
$url = fn(array $p = []) => $space->createUrl('/gewerbe/betrieb/index', array_filter($p, fn($v) => $v !== ''));
?>
<?php if ($mine): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Meine Betriebe</strong></div>
    <div class="panel-body">
        <?php foreach ($mine as $b): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <?= $this->render('_logo', ['betrieb' => $b, 'size' => 32]) ?>
                <span style="flex:1"><?= Html::a(Html::encode($b->name), $b->getUrl()) ?></span>
                <?= Html::a('<i class="fa fa-pencil" aria-hidden="true"></i> Seite bearbeiten', $space->createUrl('/gewerbe/betrieb/edit', ['id' => $b->id]), ['class' => 'btn btn-sm btn-light']) ?>
            </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Gewerbe &amp; Einkaufen in Uffenheim</strong></div>
    <div class="panel-body">
        <p class="help-block">Geschäfte, Handwerk, Gastronomie und Dienstleister aus Uffenheim und den Ortsteilen.
            Du hast einen Betrieb? Trag ihn kostenlos ein und pflege deine Seite selbst.</p>

        <?= $this->render('_ansicht', ['aktiv' => 'liste', 'q' => $q, 'kategorie' => $kategorie, 'ortsteil' => $ortsteil]) ?>

        <div class="gw-branchen" role="list" aria-label="Branchen">
            <?php foreach ($module->categories as $k): ?>
                <?= Html::a(Html::encode($k) . (isset($anzahl[$k]) ? ' <span class="gw-zahl">' . (int)$anzahl[$k] . '</span>' : ''),
                    $url(['kategorie' => $kategorie === $k ? '' : $k, 'ortsteil' => $ortsteil, 'q' => $q]),
                    ['class' => 'gw-branche' . ($kategorie === $k ? ' aktiv' : ''), 'role' => 'listitem', 'aria-current' => $kategorie === $k ? 'true' : null]) ?>
            <?php endforeach ?>
        </div>

        <?= Html::beginForm($url(), 'get', ['style' => 'display:flex;flex-wrap:wrap;gap:8px;margin:12px 0']) ?>
            <?= Html::hiddenInput('cguid', $space->guid) ?>
            <?= Html::textInput('q', $q, ['class' => 'form-control', 'placeholder' => 'Suchen, z. B. Bäcker, Fahrrad, Friseur …', 'style' => 'flex:2 1 200px', 'aria-label' => 'Suchen']) ?>
            <?= Html::dropDownList('kategorie', $kategorie, array_combine($module->categories, $module->categories),
                ['class' => 'form-control', 'prompt' => 'Alle Branchen', 'style' => 'flex:1 1 150px', 'aria-label' => 'Branche']) ?>
            <?= Html::dropDownList('ortsteil', $ortsteil, array_combine($module->ortsteile, $module->ortsteile),
                ['class' => 'form-control', 'prompt' => 'Alle Orte', 'style' => 'flex:1 1 130px', 'aria-label' => 'Ort']) ?>
            <label class="gw-offen-filter"><?= Html::checkbox('offen', $offen !== '', ['value' => 1]) ?> Jetzt geöffnet</label>
            <button type="submit" class="btn btn-light"><i class="fa fa-search" aria-hidden="true"></i> Filtern</button>
            <?php if ($filtered): ?>
                <?= Html::a('Zurücksetzen', $url(), ['class' => 'btn btn-link']) ?>
            <?php endif ?>
        <?= Html::endForm() ?>

        <p class="text-muted" role="status"><?= count($betriebe) ?> <?= count($betriebe) === 1 ? 'Betrieb' : 'Betriebe' ?></p>
        <div class="gw-liste">
            <?php foreach ($betriebe as $b): ?>
                <a href="<?= Html::encode($b->getUrl()) ?>" class="gw-karte">
                    <?= $this->render('_logo', ['betrieb' => $b, 'size' => 52]) ?>
                    <span style="flex:1;min-width:0">
                        <strong><?= Html::encode($b->name) ?></strong>
                        <small class="text-muted" style="display:block"><?= Html::encode($b->kategorie) ?> · <?= Html::encode($b->ortsteil) ?></small>
                        <?php if ($b->angebot): ?>
                            <small style="display:block;margin-top:2px"><?= Html::encode(mb_strimwidth($b->angebot, 0, 90, '…')) ?></small>
                        <?php endif ?>
                        <?php if (($stand = $b->getStand()) !== null): ?>
                            <small class="gw-stand <?= $stand['offen'] ? 'offen' : 'zu' ?>"><?= Html::encode($stand['text']) ?></small>
                        <?php endif ?>
                        <?php if ($b->status === Betrieb::STATUS_VORGESCHLAGEN): ?>
                            <small class="text-muted" style="display:block"><i class="fa fa-hourglass-half" aria-hidden="true"></i> wartet auf Freigabe</small>
                        <?php endif ?>
                    </span>
                </a>
            <?php endforeach ?>
        </div>
        <?php if (!$betriebe): ?>
            <p><?= $filtered ? 'Nichts gefunden.' : 'Noch keine Betriebe eingetragen. Mach den Anfang!' ?></p>
        <?php endif ?>
        <p style="margin-top:14px">
            <?= Html::a('<i class="fa fa-plus" aria-hidden="true"></i> Betrieb eintragen', $space->createUrl('/gewerbe/betrieb/create'), ['class' => 'btn btn-primary']) ?>
        </p>
    </div>
</div>
<style <?= Html::nonce() ?>>
    .gw-branchen { display: flex; flex-wrap: wrap; gap: 6px; }
    /* Handy: Branchen als eine waagerecht wischbare Zeile statt sieben Zeilen Kacheln */
    @media (max-width: 575.98px) {
        /* Kein negativer Rand, kein scroll-snap/-webkit-overflow-scrolling: das ließ in iOS-Safari die fixierte Tab-Leiste mitwandern */
        .gw-branchen { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; max-width: 100%; padding: 2px 0 8px;
            overscroll-behavior-x: contain; scrollbar-width: none; }
        .gw-branchen::-webkit-scrollbar { display: none; }
        .gw-branche { flex: 0 0 auto; }
    }
    .gw-offen-filter { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-weight: normal; min-height: 38px; }
    .gw-offen-filter input { width: 20px; height: 20px; }
    .gw-stand { display: block; margin-top: 3px; font-weight: 600; }
    .gw-stand.offen { color: #3d5a06; }
    .gw-stand.zu { color: #8a2a20; }
    html[data-bs-theme="dark"] .gw-stand.offen { color: #b5d94a; }
    html[data-bs-theme="dark"] .gw-stand.zu { color: #f0a49a; }
    .gw-branche { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; padding: 4px 12px; border-radius: 999px;
        border: 1px solid #8a8f94; color: inherit; text-decoration: none !important; font-size: .92em; }
    .gw-branche:hover, .gw-branche:focus { background: rgba(68, 110, 136, .1); }
    .gw-branche.aktiv { background: #446e88; border-color: #446e88; color: #fff; }
    .gw-zahl { font-size: .8em; font-weight: 600; opacity: .85; }
    .gw-liste { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
    .gw-karte { display: flex; align-items: flex-start; gap: 12px; padding: 12px; border-radius: 14px;
        border: 1px solid rgba(0, 0, 0, .1); color: inherit; text-decoration: none !important; }
    .gw-karte:hover, .gw-karte:focus { background: rgba(68, 110, 136, .06); }
    .gw-karte strong { overflow-wrap: anywhere; }
</style>
