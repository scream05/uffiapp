<?php

use humhub\helpers\Html;
use humhub\modules\stadtrat\components\Ris;

/** @var \humhub\modules\stadtrat\Module $module */
/** @var \humhub\modules\stadtrat\models\Sitzung[] $kommende */
/** @var \humhub\modules\stadtrat\models\Sitzung[] $vergangene */
/** @var int $mehr */
/** @var string[] $gremien */
/** @var string $gremium */
/** @var string $q */
/** @var bool|null $abo null = Gast */
/** @var string|null $stand */
/** @var \humhub\modules\stadtrat\models\Kurzfassung[] $erklaert sitzung_id => veröffentlichte Kurzfassung */
/** @var bool $darfBearbeiten */

$t = fn($s, $p = []) => Yii::t('StadtratModule.base', $s, $p);
$this->title = $t('Stadtrat');
$filterLink = fn($label, $g) => Html::a(Html::encode($label), Ris::url() . ($g !== '' || $q !== '' ? '?' . http_build_query(array_filter(['gremium' => $g, 'q' => $q])) : ''),
    ['class' => 'btn btn-sm ' . ($g === $gremium ? 'btn-primary' : 'btn-default btn-light')]);
$sitzung = function ($s, bool $offen) use ($t, $module, $erklaert) {
    $punkte = $s->getPunkte();
    $k = $erklaert[$s->id] ?? null;
    ob_start(); ?>
    <details class="sr-sitzung" id="sitzung-<?= $s->id ?>"<?= $offen ? ' open' : '' ?>>
        <summary>
            <span class="sr-datum"><?= Html::encode($s->tag()) ?><?= $s->beginn ? ', ' . Html::encode($t('{uhr} Uhr', ['uhr' => $s->beginn])) : '' ?></span>
            <strong><?= Html::encode($s->gremium) ?></strong>
            <span class="sr-klein"><?= $punkte ? $t('{n, plural, =1{1 Punkt} other{# Punkte}}', ['n' => count($punkte)]) : $t('Tagesordnung folgt') ?></span>
            <?php if ($k): ?><span class="sr-marke"><i class="fa fa-commenting-o" aria-hidden="true"></i> <?= $t('kurz erklärt') ?></span><?php endif ?>
        </summary>
        <div class="sr-inhalt">
            <?php if ($k): ?>
                <div class="sr-erklaert">
                    <?= \humhub\modules\stadtrat\components\Erklaert::html($k->kurz) ?>
                    <?php if (\humhub\modules\stadtrat\components\Erklaert::audioDatei($s->id) !== null): ?>
                        <div class="sr-klein" style="margin:4px 0"><i class="fa fa-headphones" aria-hidden="true"></i> <?= $t('Zum Anhören:') ?></div>
                        <audio controls preload="none" src="<?= Html::encode(\yii\helpers\Url::to(['/stadtrat/index/audio', 'id' => $s->id, 'v' => strtotime($k->geaendert_am)])) ?>"></audio>
                    <?php endif ?>
                    <details class="sr-lang"><summary><?= $t('Ausführlich erklärt') ?></summary><?= \humhub\modules\stadtrat\components\Erklaert::html($k->lang) ?></details>
                    <div class="sr-klein"><?= $t('Kurzfassung in einfacher Sprache, ohne Gewähr. Verbindlich ist die Niederschrift der Stadt Uffenheim.') ?></div>
                </div>
                <div class="sr-klein" style="margin:10px 0 2px"><strong><?= $t('Tagesordnung') ?></strong></div>
            <?php endif ?>
            <?php if ($s->ort): ?><div class="sr-klein"><i class="fa fa-map-marker" aria-hidden="true"></i> <?= Html::encode($s->ort) ?></div><?php endif ?>
            <?php foreach ($s->getHinweise() as [$k, $text]): ?>
                <div class="sr-hinweis"><i class="fa fa-info-circle" aria-hidden="true"></i> <?= Html::encode($text) ?></div>
            <?php endforeach ?>
            <?php if ($punkte): ?>
                <ol class="sr-to">
                    <?php foreach ($punkte as [$nr, $titel]): ?>
                        <li><span class="sr-nr"><?= Html::encode($nr) ?></span><span><?= nl2br(Html::encode($titel)) ?></span></li>
                    <?php endforeach ?>
                </ol>
                <?php if ($s->nichtoeffentlich): ?><div class="sr-klein"><?= $t('Danach folgt ein nichtöffentlicher Teil.') ?></div><?php endif ?>
            <?php else: ?>
                <p class="sr-klein"><?= $t('Die Tagesordnung ist noch nicht veröffentlicht. Sie erscheint meist etwa eine Woche vor der Sitzung.') ?></p>
            <?php endif ?>
            <div class="sr-klein" style="margin-top:6px"><?= Html::a($t('Im Ratsinformationssystem ansehen'), $module->sitzungUrl($s->id), ['target' => '_blank', 'rel' => 'noopener']) ?></div>
        </div>
    </details>
    <?php return ob_get_clean();
};
?>
<style <?= Html::nonce() ?>>
    #stadtrat .sr-filter { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 10px; }
    #stadtrat .sr-suche { display: flex; gap: 6px; margin-bottom: 14px; max-width: 420px; }
    #stadtrat .sr-sitzung { border-top: 1px solid var(--hh-border-color, #e5e5e5); padding: 8px 0; }
    #stadtrat .sr-sitzung summary { cursor: pointer; display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: baseline; }
    #stadtrat .sr-datum { min-width: 10.5em; font-variant-numeric: tabular-nums; }
    #stadtrat .sr-inhalt { padding: 6px 0 4px 18px; }
    #stadtrat .sr-to { list-style: none; padding: 0; margin: 8px 0; }
    #stadtrat .sr-to li { display: flex; gap: 8px; padding: 4px 0; border-top: 1px dashed var(--hh-border-color, #eee); }
    #stadtrat .sr-to li:first-child { border-top: 0; }
    #stadtrat .sr-nr { min-width: 1.8em; color: var(--hh-text-color-soft, #777); font-variant-numeric: tabular-nums; }
    #stadtrat .sr-klein { font-size: 0.88em; color: var(--hh-text-color-soft, #6b6b6b); }
    #stadtrat .sr-hinweis { margin: 6px 0; padding: 6px 10px; border-left: 3px solid var(--hh-primary, #21a1b3); background: var(--hh-background-color-secondary, #f5f7f8); }
    #stadtrat h2 { font-size: 1.05em; margin: 18px 0 4px; }
    #stadtrat .sr-marke { font-size: 0.8em; padding: 1px 8px; border-radius: 10px; background: var(--hh-primary, #21a1b3); color: #fff; }
    #stadtrat .sr-erklaert { margin: 8px 0; padding: 10px 14px; border-radius: 6px; background: var(--hh-background-color-secondary, #f5f7f8); }
    #stadtrat .sr-erklaert h3 { font-size: 1.05em; margin: 2px 0 8px; }
    #stadtrat .sr-erklaert audio { width: 100%; max-width: 420px; margin-bottom: 6px; }
    #stadtrat .sr-lang { margin: 6px 0; }
    #stadtrat .sr-lang summary { cursor: pointer; font-weight: 600; }
</style>
<script <?= Html::nonce() ?>>
    // Link mit #sitzung-… (Push, Wochenüberblick, Kasten): diese Sitzung aufklappen
    $(function () { var d = location.hash && document.getElementById(location.hash.slice(1)); if (d && d.tagName === 'DETAILS') { d.open = true; d.scrollIntoView(); } });
</script>
<div class="panel panel-default" id="stadtrat">
    <div class="panel-heading"><strong><?= $t('Stadtrat und Ausschüsse') ?></strong>
        <?php if ($darfBearbeiten): ?><?= Html::a($t('Kurz erklärt schreiben'), ['/stadtrat/kurzfassung/index'], ['class' => 'btn btn-sm btn-primary float-end pull-right']) ?><?php endif ?></div>
    <div class="panel-body">
        <p><?= $t('Wann tagen Stadtrat, Finanz- und Werkausschuss und Verwaltungs- und Bauausschuss – und worüber wird beraten? Die Sitzungen sind öffentlich, zuhören darf jede und jeder ohne Anmeldung.') ?></p>

        <?php if ($abo !== null): ?>
            <?= Html::beginForm(['abo'], 'post', ['class' => 'checkbox', 'style' => 'margin:0 0 12px']) ?>
            <label><?= Html::checkbox('abo', $abo, ['onchange' => 'this.form.submit()']) ?> <?= $t('Benachrichtige mich, sobald eine neue Tagesordnung oder eine Kurzfassung erscheint') ?></label>
            <noscript><button type="submit" class="btn btn-sm btn-primary"><?= $t('Speichern') ?></button></noscript>
            <?= Html::endForm() ?>
        <?php endif ?>

        <div class="sr-filter">
            <?= $filterLink($t('Alle Gremien'), '') ?>
            <?php foreach ($gremien as $g): ?><?= $filterLink($g, $g) ?><?php endforeach ?>
        </div>
        <?= Html::beginForm(Ris::url(), 'get', ['class' => 'sr-suche', 'role' => 'search']) ?>
        <?php if ($gremium !== ''): ?><?= Html::hiddenInput('gremium', $gremium) ?><?php endif ?>
        <?= Html::textInput('q', $q, ['class' => 'form-control', 'placeholder' => $t('In Tagesordnungen suchen, z. B. Bebauungsplan'), 'aria-label' => $t('In Tagesordnungen suchen')]) ?>
        <button type="submit" class="btn btn-primary"><i class="fa fa-search" aria-hidden="true"></i><span class="visually-hidden"><?= $t('Suchen') ?></span></button>
        <?= Html::endForm() ?>

        <h2><?= $t('Kommende Sitzungen') ?></h2>
        <?php if (!$kommende): ?><p class="sr-klein"><?= $q !== '' ? $t('Keine Treffer.') : $t('Derzeit sind keine Sitzungen angesetzt.') ?></p><?php endif ?>
        <?php foreach ($kommende as $i => $s): ?><?= $sitzung($s, $i === 0 || $q !== '') ?><?php endforeach ?>

        <h2><?= $t('Vergangene Sitzungen') ?></h2>
        <?php if (!$vergangene): ?><p class="sr-klein"><?= $t('Keine Treffer.') ?></p><?php endif ?>
        <?php foreach ($vergangene as $s): ?><?= $sitzung($s, $q !== '') ?><?php endforeach ?>
        <?php if ($mehr): ?>
            <p style="margin-top:8px"><?= Html::a($t('{n} ältere Sitzungen zeigen', ['n' => $mehr]), Ris::url() . '?' . http_build_query(array_filter(['gremium' => $gremium, 'alle' => 1]))) ?></p>
        <?php endif ?>

        <p class="sr-klein" style="margin-top:18px">
            <?= $t('Quelle: {link} der Verwaltungsgemeinschaft Uffenheim, unverändert übernommen und ohne Bewertung (nur bei Bauanträgen ohne genaue Adresse). Verbindlich sind die Bekanntmachungen der Stadt. Beschlüsse und Unterlagen stehen dort nicht öffentlich; die Niederschriften öffentlicher Sitzungen können Gemeindebürgerinnen und -bürger im Rathaus einsehen (Art. 54 Abs. 3 Gemeindeordnung).', [
                'link' => Html::a($t('Ratsinformationssystem'), $module->risUrl, ['target' => '_blank', 'rel' => 'noopener'])]) ?>
            <?php if ($stand): ?><?= $t('Stand: {zeit}', ['zeit' => date('j.n.Y, G:i', strtotime($stand))]) ?><?php endif ?>
        </p>
    </div>
</div>
