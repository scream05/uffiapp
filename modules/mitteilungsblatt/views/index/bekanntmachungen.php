<?php

use humhub\helpers\Html;
use humhub\modules\mitteilungsblatt\models\Bekanntmachung;

/** @var Bekanntmachung[] $liste */
/** @var int[] $jahre */
/** @var int $laufend */
/** @var string $art */
/** @var int|string $jahr */
/** @var bool $frist */
/** @var string $q */
/** @var bool $hinweise */
/** @var bool $admin */
/** @var bool $frei */
/** @var int $geprueft */
/** @var int $ausgaben */

$this->title = Yii::t('MitteilungsblattModule.base', 'Amtliche Bekanntmachungen');
$url = fn(array $p = []) => $this->context->url('/mitteilungsblatt/index/bekanntmachungen',
    array_filter(array_merge(['art' => $art, 'jahr' => $jahr, 'frist' => $frist ? 1 : null, 'q' => $q, 'hinweise' => $hinweise ? 1 : null], $p), fn($v) => $v !== '' && $v !== null));
// Zurück aus dem Amtsblatt (…#bm-<id>): zum zuletzt geöffneten Eintrag springen, auch nach PJAX-Seitenwechsel
$this->registerJs(<<<'JS'
(function () {
    if (!/^#bm-\d+$/.test(location.hash)) return;
    var springen = function () {
        var e = document.getElementById(location.hash.slice(1));
        if (!e) return;
        var kopf = document.getElementById('topbar');
        var frei = kopf && /fixed|sticky/.test(getComputedStyle(kopf).position) ? kopf.getBoundingClientRect().bottom : 0;
        window.scrollTo(0, window.scrollY + e.getBoundingClientRect().top - frei - 12);
    };
    springen();
    setTimeout(springen, 300); // nach dem Sprung des Browsers zur Marke und nachgeladenen Schriften
})();
JS);
$gefiltert = $art !== '' || $jahr !== '' || $frist || $q !== '' || $hinweise;
?>
<style <?= Html::nonce() ?>>
    .bm-hinweis { border-left: 4px solid var(--hh-warning, #f0ad4e); background: var(--bs-tertiary-bg, #f7f7f7); padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; }
    .bm-hinweis p { margin: 0 0 6px; }
    .bm-hinweis p:last-child { margin-bottom: 0; }
    .bm-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .bm-filter .form-control, .bm-filter .form-select { width: auto; flex: 1 1 160px; }
    .bm-filter .bm-suche { flex: 3 1 220px; }
    .bm-eintrag { scroll-margin-top: 90px; padding: 14px 0; border-top: 1px solid var(--hh-border-color, #e5e5e5); }
    .bm-eintrag:first-child { border-top: 0; padding-top: 0; }
    .bm-eintrag.bm-aus { opacity: .5; }
    .bm-oben { display: flex; flex-wrap: wrap; gap: 4px 8px; align-items: center; font-size: .85em; margin-bottom: 4px; }
    .bm-art { display: inline-block; padding: 1px 8px; border-radius: 999px; background: var(--hh-primary, #1f5f8b); color: #fff; font-weight: 600; text-decoration: none; }
    .bm-art-zustellung, .bm-art-sonstiges, .bm-art-hinweis { background: #5c6670; }
    .bm-titel { display: block; font-size: 1.05em; line-height: 1.35; }
    .bm-meta { font-size: .9em; color: var(--bs-secondary-color, #666); margin-top: 2px; }
    .bm-frist { display: inline-flex; gap: 6px; align-items: baseline; margin-top: 6px; padding: 3px 10px; border-radius: 6px; font-size: .9em;
        background: var(--bs-tertiary-bg, #f2f2f2); }
    .bm-frist.bm-laeuft { background: #fff3cd; color: #664d03; font-weight: 600; }
    .bm-knoepfe { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .bm-knoepfe form { display: inline; }
    @media (prefers-color-scheme: dark) { .bm-frist.bm-laeuft { background: #4d3d05; color: #ffe08a; } }
</style>
<div class="<?= $this->context->contentContainer ? '' : 'container' ?>">
    <div class="panel panel-default">
        <div class="panel-heading"><?= Yii::t('MitteilungsblattModule.base', '<strong>Amtliche Bekanntmachungen</strong> der Stadt Uffenheim – nichtamtliche Übersicht') ?></div>
        <div class="panel-body">
            <?php if ($admin && !$frei): ?>
                <div class="alert alert-info"><i class="fa fa-lock"></i>
                    <?= Yii::t('MitteilungsblattModule.base', 'Nur für Admins sichtbar. Freigeben mit {befehl}.', ['befehl' => '<code>/app/yii mitteilungsblatt/bekanntmachungen-freigeben 1</code>']) ?>
                    <?= Yii::t('MitteilungsblattModule.base', '{n} von {m} Ausgaben durchgesehen.', ['n' => $geprueft, 'm' => $ausgaben]) ?>
                </div>
            <?php endif ?>
            <div class="bm-hinweis" role="note">
                <p><?= Yii::t('MitteilungsblattModule.base', 'Rechtsverbindlich ist allein die Bekanntmachung im Amtsblatt der Stadt Uffenheim (Mitteilungsblatt) bzw. an der Amtstafel im Rathaus. Maßgeblich für Fristen ist das Original.') ?></p>
                <p><?= Yii::t('MitteilungsblattModule.base', 'Die Liste wird automatisch aus den Ausgaben des Mitteilungsblatts erstellt und kann unvollständig sein. Jeder Eintrag führt zur Originalseite.') ?></p>
            </div>
            <form method="get" action="<?= $this->context->url('/mitteilungsblatt/index/bekanntmachungen') ?>" class="bm-filter" role="search">
                <input type="search" name="q" value="<?= Html::encode($q) ?>" class="form-control bm-suche"
                       placeholder="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Suchen, z. B. Bebauungsplan, Hundesteuer')) ?>"
                       aria-label="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Bekanntmachungen durchsuchen')) ?>">
                <select name="art" class="form-select" aria-label="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Art')) ?>">
                    <option value=""><?= Yii::t('MitteilungsblattModule.base', 'Alle Arten') ?></option>
                    <?php foreach (Bekanntmachung::ARTEN as $a): ?>
                        <option value="<?= $a ?>"<?= $a === $art ? ' selected' : '' ?>><?= Html::encode(Bekanntmachung::artName($a)) ?></option>
                    <?php endforeach ?>
                </select>
                <select name="jahr" class="form-select" aria-label="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Jahr')) ?>">
                    <option value=""><?= Yii::t('MitteilungsblattModule.base', 'Alle Jahre') ?></option>
                    <?php foreach ($jahre as $j): ?>
                        <option value="<?= (int)$j ?>"<?= (int)$j === $jahr ? ' selected' : '' ?>><?= (int)$j ?></option>
                    <?php endforeach ?>
                </select>
                <?php if ($hinweise): ?><input type="hidden" name="hinweise" value="1"><?php endif ?>
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> <?= Yii::t('MitteilungsblattModule.base', 'Anzeigen') ?></button>
            </form>
            <div class="bm-knoepfe">
                <?php if ($laufend): ?>
                    <?= Html::a('<i class="fa fa-clock-o"></i> ' . Yii::t('MitteilungsblattModule.base', '{n, plural, =1{# laufende Frist} other{# laufende Fristen}}', ['n' => $laufend]),
                        $url(['frist' => $frist ? null : 1]), ['class' => 'btn btn-sm ' . ($frist ? 'btn-primary' : 'btn-light')]) ?>
                <?php endif ?>
                <?php if ($art === ''): ?>
                    <?= Html::a('<i class="fa fa-info-circle"></i> ' . Yii::t('MitteilungsblattModule.base', 'Auch Hinweise der Verwaltung zeigen'),
                        $url(['hinweise' => $hinweise ? null : 1]), ['class' => 'btn btn-sm ' . ($hinweise ? 'btn-primary' : 'btn-light'),
                            'title' => Yii::t('MitteilungsblattModule.base', 'Fälligkeiten, Fundsachen, Schließtage, Vermietungen u. Ä. – stehen im Amtsblatt oft bei den Bekanntmachungen')]) ?>
                <?php endif ?>
                <?php if ($gefiltert): ?>
                    <?= Html::a(Yii::t('MitteilungsblattModule.base', 'Filter zurücksetzen'), $this->context->url('/mitteilungsblatt/index/bekanntmachungen'), ['class' => 'btn btn-sm btn-light']) ?>
                <?php endif ?>
                <?= Html::a('<i class="fa fa-newspaper-o"></i> ' . Yii::t('MitteilungsblattModule.base', 'Alle Ausgaben des Mitteilungsblatts'), $this->context->url('/mitteilungsblatt/index'), ['class' => 'btn btn-sm btn-light']) ?>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-body">
            <?php if (!$liste): ?>
                <?= $gefiltert ? Yii::t('MitteilungsblattModule.base', 'Keine Bekanntmachungen für diese Auswahl.') : Yii::t('MitteilungsblattModule.base', 'Noch keine Bekanntmachungen erfasst.') ?>
            <?php endif ?>
            <?php foreach ($liste as $b): ?>
                <div class="bm-eintrag<?= $b->ausgeblendet ? ' bm-aus' : '' ?>" id="bm-<?= $b->id ?>">
                    <div class="bm-oben">
                        <?= Html::a(Html::encode(Bekanntmachung::artName($b->art)), $url(['art' => $b->art]), ['class' => 'bm-art bm-art-' . $b->art]) ?>
                        <?php if ($b->stelle): ?><span><?= Html::encode($b->stelle) ?></span><?php endif ?>
                        <?php if ($b->ausgeblendet): ?><span class="badge text-bg-secondary"><?= Yii::t('MitteilungsblattModule.base', 'ausgeblendet') ?></span><?php endif ?>
                    </div>
                    <strong class="bm-titel"><?= Html::encode($b->titel) ?></strong>
                    <div class="bm-meta">
                        <?= Yii::t('MitteilungsblattModule.base', 'Amtsblatt {titel} vom {datum}, Seite {seite}', [
                            'titel' => Html::encode($b->ausgabe->titelAnzeige), 'datum' => Html::encode($b->ausgabe->datumAnzeige), 'seite' => $b->seite]) ?>
                    </div>
                    <?php if ($b->frist_bis): ?>
                        <div class="bm-frist<?= $b->fristLaeuft ? ' bm-laeuft' : '' ?>">
                            <i class="fa fa-clock-o" aria-hidden="true"></i>
                            <span><?= $b->fristLaeuft
                                ? Yii::t('MitteilungsblattModule.base', 'Frist bis {datum}', ['datum' => Yii::$app->formatter->asDate($b->frist_bis, 'php:d.m.Y')])
                                : Yii::t('MitteilungsblattModule.base', 'Frist abgelaufen ({datum})', ['datum' => Yii::$app->formatter->asDate($b->frist_bis, 'php:d.m.Y')]) ?><?=
                                $b->frist_text ? ': ' . Html::encode($b->frist_text) : '' ?></span>
                        </div>
                    <?php endif ?>
                    <?php if ($b->personenbezug): ?>
                        <div class="bm-meta"><i class="fa fa-user-secret" aria-hidden="true"></i>
                            <?= Yii::t('MitteilungsblattModule.base', 'Enthält Angaben zu Personen – Einzelheiten nur im Original.') ?></div>
                    <?php endif ?>
                    <div class="bm-knoepfe">
                        <?= Html::a('<i class="fa fa-file-text-o"></i> ' . Yii::t('MitteilungsblattModule.base', 'Original im Amtsblatt'),
                            $this->context->url('/mitteilungsblatt/index/lesen', ['id' => $b->ausgabe_id, 'seite' => $b->seite, 'zurueck' => $url() . '#bm-' . $b->id]),
                            ['class' => 'btn btn-sm btn-primary']) ?>
                        <?php if ($admin): ?>
                            <?= Html::beginForm($this->context->url('/mitteilungsblatt/index/bekanntmachung-umschalten', ['id' => $b->id]), 'post') ?>
                            <button type="submit" class="btn btn-sm btn-light" title="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Nur Admins: falsch erkannte Einträge ausblenden')) ?>">
                                <i class="fa fa-eye<?= $b->ausgeblendet ? '' : '-slash' ?>"></i>
                                <?= $b->ausgeblendet ? Yii::t('MitteilungsblattModule.base', 'Einblenden') : Yii::t('MitteilungsblattModule.base', 'Ausblenden') ?>
                            </button>
                            <?= Html::endForm() ?>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
