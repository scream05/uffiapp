<?php
/**
 * @var \humhub\modules\vorschlaege\Module $module
 * @var \humhub\modules\space\models\Space $space
 * @var \humhub\modules\vorschlaege\models\Vorschlag[] $vorschlaege
 * @var array $summen
 * @var array $meine
 * @var array $kommentare object_id => Anzahl
 * @var array $anzahl status => Anzahl
 * @var string $stand
 * @var string $sortierung
 */

use humhub\helpers\Html;
use yii\helpers\Url;
use humhub\modules\vorschlaege\assets\VorschlagAsset;
use humhub\modules\vorschlaege\models\Vorschlag;
use humhub\modules\vorschlaege\widgets\VorschlagAddon;

VorschlagAsset::register($this);
$url = fn(array $p) => $space->createUrl('/vorschlaege/vorschlag/index', $p + ['stand' => $stand, 'sortierung' => $sortierung]);
$offen = array_sum(array_diff_key($anzahl, array_flip(Vorschlag::CLOSED)));
$filter = ['offen' => [Yii::t('VorschlaegeModule.base', 'Offen'), $offen], 'alle' => [Yii::t('VorschlaegeModule.base', 'Alle'), array_sum($anzahl)]];
foreach (array_keys(Vorschlag::STATUSES) as $key) {
    if (!empty($anzahl[$key])) {
        $filter[$key] = [Vorschlag::anzeigeLabel($key), $anzahl[$key]];
    }
}
?>
<div class="panel panel-default">
    <div class="panel-body">
        <div class="vs-kopf">
            <h1><?= Yii::t('VorschlaegeModule.base', 'Ideen & Vorschläge') ?></h1>
            <?php if (!Yii::$app->user->isGuest): ?>
                <?= Html::a('<i class="fa fa-plus"></i> ' . Yii::t('VorschlaegeModule.base', 'Vorschlag einreichen'),
                    $space->createUrl('/vorschlaege/vorschlag/create'), ['class' => 'btn btn-primary btn-sm']) ?>
            <?php endif ?>
        </div>
        <p class="help-block"><?= Yii::t('VorschlaegeModule.base', 'Was fehlt dir auf der Plattform, was könnte besser laufen? Reich deine Idee ein, stimm bei anderen dafür oder dagegen und diskutier in den Kommentaren mit. Das Team setzt den Stand, damit alle sehen, was daraus wird.') ?></p>
        <?php if (Yii::$app->user->isGuest): ?>
            <p class="help-block"><?= Yii::t('VorschlaegeModule.base', 'Zum Einreichen, Abstimmen und Kommentieren bitte {anmelden}.',
                ['anmelden' => Html::a(Yii::t('VorschlaegeModule.base', 'anmelden'), ['/user/auth/login'], ['data-pjax-prevent' => 1, 'data-action-click' => 'ui.modal.load', 'data-action-url' => Url::to(['/user/auth/login'])])]) ?></p>
        <?php endif ?>

        <nav class="vs-filter" aria-label="<?= Yii::t('VorschlaegeModule.base', 'Nach Stand filtern') ?>">
            <?php foreach ($filter as $key => [$text, $n]): ?>
                <?= Html::a(Html::encode($text) . ' (' . $n . ')', $url(['stand' => $key]),
                    ['class' => $stand === $key ? 'aktiv' : null, 'aria-current' => $stand === $key ? 'page' : null]) ?>
            <?php endforeach ?>
        </nav>
        <nav class="vs-filter" aria-label="<?= Yii::t('VorschlaegeModule.base', 'Sortierung') ?>">
            <?= Html::a(Yii::t('VorschlaegeModule.base', 'Meiste Zustimmung'), $url(['sortierung' => 'stimmen']), ['class' => $sortierung === 'stimmen' ? 'aktiv' : null]) ?>
            <?= Html::a(Yii::t('VorschlaegeModule.base', 'Neueste'), $url(['sortierung' => 'neu']), ['class' => $sortierung === 'neu' ? 'aktiv' : null]) ?>
        </nav>

        <?php if (!$vorschlaege): ?>
            <p><?= $stand === 'offen' && !$anzahl ? Yii::t('VorschlaegeModule.base', 'Noch keine Vorschläge – mach den Anfang!')
                : Yii::t('VorschlaegeModule.base', 'Keine Vorschläge mit diesem Stand.') ?></p>
        <?php else: ?>
            <ul class="vs-liste">
                <?php foreach ($vorschlaege as $v): ?>
                    <?php
                    $autor = $v->content->createdBy;
                    $text = trim(preg_replace('/\s+/u', ' ', strip_tags(\humhub\libs\Helpers::truncateText(
                        Vorschlag::beschreibung($v->content->getPolymorphicRelation()->message ?? ''), 220))));
                    $n = $kommentare[$v->content->object_id] ?? 0;
                    ?>
                    <li class="vs-eintrag">
                        <h2><?= Html::a(Html::encode($v->titel), $v->content->getUrl()) ?></h2>
                        <div class="vs-meta">
                            <?= Html::encode($module->kategorieLabel($v->kategorie)) ?> ·
                            <?= Html::encode($autor->displayName ?? '') ?> ·
                            <?= Yii::$app->formatter->asDate($v->created_at, 'medium') ?> ·
                            <?= Html::a('<i class="fa fa-comment-o" aria-hidden="true"></i> ' . ($n === 1 ? Yii::t('VorschlaegeModule.base', '1 Kommentar')
                                : Yii::t('VorschlaegeModule.base', '{n} Kommentare', ['n' => $n])), $v->content->getUrl()) ?>
                        </div>
                        <?php if ($text !== ''): ?><p class="vs-auszug"><?= Html::encode($text) ?></p><?php endif ?>
                        <div class="vs-zeile">
                            <?= Vorschlag::etikett($v->status) ?>
                            <?= VorschlagAddon::abstimmung($v, $summen[$v->content_id] ?? null, $meine[$v->content_id] ?? 0) ?>
                        </div>
                        <?php if ($v->note): ?>
                            <div class="vs-meta"><?= Yii::t('VorschlaegeModule.base', 'Hinweis des Teams: {text}', ['text' => Html::encode($v->note)]) ?></div>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</div>
