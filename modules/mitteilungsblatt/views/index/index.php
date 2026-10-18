<?php

use humhub\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/** @var \humhub\modules\mitteilungsblatt\models\Ausgabe[] $ausgaben */
/** @var string $q */
/** @var array|null $treffer */
/** @var string|null $hinweis */
/** @var string|null $termineUrl */
/** @var string $quelle */

$this->title = Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt');
$markieren = function (string $text) use ($q): string {
    $html = Html::encode($text);
    foreach (preg_split('/\s+/u', $q) as $w) {
        if (mb_strlen($w) >= 2) {
            $html = preg_replace('/' . preg_quote(Html::encode($w), '/') . '/iu', '<mark>$0</mark>', $html);
        }
    }
    return $html;
};
?>
<style <?= Html::nonce() ?>>
    .mb-raster { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; }
    .mb-ausgabe { display: block; text-align: center; color: inherit; text-decoration: none; }
    .mb-ausgabe img { width: 100%; aspect-ratio: 586 / 842; object-fit: cover; border-radius: 6px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .15); background: #f3f3f3; transition: transform .15s; }
    .mb-ausgabe:hover img { transform: translateY(-3px); }
    .mb-ausgabe strong { display: block; margin-top: 8px; }
    .mb-ausgabe small { color: var(--hh-text-color-soft, #777); }
    .mb-neu { position: relative; }
    .mb-neu::after { content: attr(data-neu); position: absolute; top: 8px; right: 8px; background: var(--hh-primary, #1f5f8b);
        color: #fff; font-size: 12px; font-weight: bold; padding: 2px 8px; border-radius: 999px; }
    .mb-treffer { padding: 10px 0; border-top: 1px solid var(--hh-border-color, #e5e5e5); }
    .mb-treffer:first-child { border-top: 0; }
    .mb-treffer mark { padding: 0 2px; background: #ffe58a; }
    .mb-hinweis { color: var(--hh-text-color-soft, #777); font-size: .9em; margin-bottom: 12px; }
    @media (max-width: 575.98px) { .mb-raster { grid-template-columns: repeat(2, 1fr); gap: 12px; } }
</style>
<div class="<?= $this->context->contentContainer ? '' : 'container' ?>">
    <div class="panel panel-default">
        <div class="panel-heading"><?= Yii::t('MitteilungsblattModule.base', '<strong>Mitteilungsblatt</strong> der Stadt Uffenheim') ?></div>
        <div class="panel-body">
            <p class="mb-hinweis">
                <?= Yii::t('MitteilungsblattModule.base', 'Erscheint alle 14 Tage und wird an alle Haushalte verteilt.') ?>
                <?php if ($hinweis): ?><br><?= Html::encode($hinweis) ?><?php endif ?>
                <?php if ($termineUrl): ?> <?= Html::a(Yii::t('MitteilungsblattModule.base', 'Alle Termine'), $termineUrl, ['target' => '_blank', 'rel' => 'noopener']) ?><?php endif ?>
                <br>Quelle: <?= Html::a('uffenheim.de', $quelle, ['target' => '_blank', 'rel' => 'noopener']) ?>
            </p>
            <form method="get" action="<?= $this->context->url('/mitteilungsblatt/index') ?>" class="d-flex gap-2" role="search">
                <input type="search" name="q" value="<?= Html::encode($q) ?>" class="form-control"
                       placeholder="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'In allen Ausgaben suchen, z. B. Kirchweih, Sperrung, Bauplatz')) ?>" aria-label="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'In allen Ausgaben suchen')) ?>">
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i><span class="visually-hidden"><?= Yii::t('MitteilungsblattModule.base', 'Suchen') ?></span></button>
            </form>
        </div>
    </div>

    <?php if ($treffer !== null): ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <?= count($treffer) >= 60 ? Yii::t('MitteilungsblattModule.base', '<strong>Mehr als 60 Fundstellen</strong> für „{q}“', ['q' => Html::encode($q)]) : Yii::t('MitteilungsblattModule.base', '<strong>{n} Fundstellen</strong> für „{q}“', ['n' => count($treffer), 'q' => Html::encode($q)]) ?>
                <?= Html::a(Yii::t('MitteilungsblattModule.base', 'Suche beenden'), $this->context->url('/mitteilungsblatt/index'), ['class' => 'float-end']) ?>
            </div>
            <div class="panel-body">
                <?php if (!$treffer): ?>
                    <?= Yii::t('MitteilungsblattModule.base', 'Nichts gefunden. Tipp: ein kürzeres Stichwort versuchen (z. B. „Kerwa“ statt „Kerwaumzug“).') ?>
                <?php endif ?>
                <?php foreach ($treffer as $t): ?>
                    <div class="mb-treffer">
                        <?= Html::a('<strong>' . Html::encode($t['ausgabe']->titelAnzeige) . '</strong>, ' . Yii::t('MitteilungsblattModule.base', 'Seite {n}', ['n' => $t['seite']]),
                            $this->context->url('/mitteilungsblatt/index/lesen', ['id' => $t['ausgabe']->id, 'seite' => $t['seite'], 'q' => $q])) ?>
                        <small class="text-muted">· <?= Html::encode($t['ausgabe']->datumAnzeige) ?></small><br>
                        <span><?= $markieren($t['auszug']) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>

    <?php if (!$ausgaben): ?>
        <div class="panel panel-default"><div class="panel-body"><?= Yii::t('MitteilungsblattModule.base', 'Noch keine Ausgaben geladen.') ?></div></div>
    <?php endif ?>
    <?php foreach (ArrayHelper::index($ausgaben, null, 'jahr') as $jahr => $liste): ?>
        <div class="panel panel-default">
            <div class="panel-heading"><strong><?= Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt {jahr}', ['jahr' => (string)$jahr]) ?></strong>
                <small class="text-muted">· <?= Yii::t('MitteilungsblattModule.base', '{n, plural, =1{# Ausgabe} other{# Ausgaben}}', ['n' => count($liste)]) ?></small></div>
            <div class="panel-body">
                <div class="mb-raster">
                    <?php foreach ($liste as $a): ?>
                        <a class="mb-ausgabe<?= $a === $ausgaben[0] && strtotime($a->datum) > time() - 14 * 86400 ? ' mb-neu" data-neu="' . Html::encode(Yii::t('MitteilungsblattModule.base', 'Neu')) : '' ?>"
                           href="<?= $this->context->url('/mitteilungsblatt/index/lesen', ['id' => $a->id]) ?>">
                            <img src="<?= $this->context->url('/mitteilungsblatt/index/bild', ['id' => $a->id]) ?>" alt="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Titelseite {titel}', ['titel' => $a->titelAnzeige])) ?>" loading="lazy">
                            <strong><?= Html::encode($a->titelAnzeige) ?></strong>
                            <small><?= Html::encode($a->datumAnzeige) ?></small>
                        </a>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>
