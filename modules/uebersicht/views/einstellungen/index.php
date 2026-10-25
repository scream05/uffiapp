<?php
/**
 * @var \humhub\modules\space\models\Space[] $bereiche
 * @var string[] $aus GUIDs der ausgeschalteten Spaces
 * @var string[] $kaesten Schlüssel der Kästen in der Reihenfolge der Person (Module::KAESTEN)
 * @var string[] $kaestenAus Schlüssel der ausgeblendeten Kästen
 */

use humhub\helpers\Html;
use humhub\modules\content\models\Content;
use humhub\modules\space\widgets\Image;
use humhub\modules\uebersicht\Module;

$this->title = Yii::t('UebersichtModule.base', 'Meine Übersicht');
?>
<div class="container">
<div class="panel panel-default" style="max-width:640px;margin:0 auto">
    <div class="panel-body">
        <h1 style="font-size:1.4em;margin-top:0"><?= Yii::t('UebersichtModule.base', 'Meine Übersicht') ?></h1>
        <p class="help-block"><?= Yii::t('UebersichtModule.base', 'Wähle, aus welchen Bereichen Beiträge in deiner Übersicht erscheinen. Das gilt nur für dich. Beiträge, die wir oben anpinnen, siehst du immer. Deine Benachrichtigungen ändern sich dadurch nicht.') ?></p>
        <?= Html::beginForm(['/uebersicht/einstellungen/index'], 'post') ?>
            <div class="list-group uebersicht-bereiche" style="margin:16px 0">
                <?php foreach ($bereiche as $space): ?>
                    <label class="list-group-item" style="display:flex;align-items:center;gap:12px;cursor:pointer;margin:0;white-space:normal">
                        <?= Image::widget(['space' => $space, 'width' => 36, 'link' => false]) ?>
                        <span style="flex:1;min-width:0">
                            <strong style="display:block"><?= Html::encode($space->name) ?></strong>
                            <?php if ($space->description): ?>
                                <small class="text-muted"><?= Html::encode($space->description) ?></small>
                            <?php endif ?>
                            <?php if ($space->getDefaultContentVisibility() == Content::VISIBILITY_PRIVATE && !$space->isMember()): ?>
                                <small style="display:block;color:#7c1922"><?= Yii::t('UebersichtModule.base', 'Beiträge hier sehen nur Mitglieder –') ?>
                                    <?= Html::a(Yii::t('UebersichtModule.base', 'Bereich öffnen und beitreten'), $space->getUrl()) ?></small>
                            <?php endif ?>
                        </span>
                        <span class="form-check form-switch" style="margin:0">
                            <?= Html::checkbox('an[]', !in_array($space->guid, $aus, true), [
                                'value' => $space->guid, 'class' => 'form-check-input', 'role' => 'switch',
                                'aria-label' => Yii::t('UebersichtModule.base', 'Beiträge aus {bereich} in meiner Übersicht', ['bereich' => $space->name])]) ?>
                        </span>
                    </label>
                <?php endforeach ?>
            </div>
            <h2 style="font-size:1.15em;margin:24px 0 4px"><?= Yii::t('UebersichtModule.base', 'Kästen rechts') ?></h2>
            <p class="help-block"><?= Yii::t('UebersichtModule.base', 'Lege fest, welche Kästen neben deiner Übersicht stehen und in welcher Reihenfolge (am Handy im Fach am rechten Rand). Manche erscheinen nur, wenn es etwas zu zeigen gibt.') ?></p>
            <ol class="list-group uebersicht-kaesten" style="margin:12px 0 16px;padding:0">
                <?php foreach ($kaesten as $i => $k): ?>
                    <li class="list-group-item" data-kasten="<?= Html::encode($k) ?>">
                        <?= Html::hiddenInput('kaesten[]', $k) ?>
                        <span class="uebersicht-kasten-nr" aria-hidden="true"><?= $i + 1 ?></span>
                        <span style="flex:1;min-width:0"><?= Html::encode(Yii::t('UebersichtModule.base', Module::KAESTEN[$k][1])) ?></span>
                        <span class="form-check form-switch" style="margin:0">
                            <?= Html::checkbox('kaesten_an[]', !in_array($k, $kaestenAus, true), ['value' => $k, 'class' => 'form-check-input', 'role' => 'switch',
                                'aria-label' => Yii::t('UebersichtModule.base', '{kasten} anzeigen', ['kasten' => Yii::t('UebersichtModule.base', Module::KAESTEN[$k][1])])]) ?>
                        </span>
                        <button type="button" class="btn btn-light btn-sm" data-schieben="-1" aria-label="<?= Html::encode(Yii::t('UebersichtModule.base', '{kasten} nach oben', ['kasten' => Yii::t('UebersichtModule.base', Module::KAESTEN[$k][1])])) ?>"><i class="fa fa-arrow-up"></i></button>
                        <button type="button" class="btn btn-light btn-sm" data-schieben="1" aria-label="<?= Html::encode(Yii::t('UebersichtModule.base', '{kasten} nach unten', ['kasten' => Yii::t('UebersichtModule.base', Module::KAESTEN[$k][1])])) ?>"><i class="fa fa-arrow-down"></i></button>
                    </li>
                <?php endforeach ?>
            </ol>
            <button type="submit" class="btn btn-primary"><?= Yii::t('UebersichtModule.base', 'Speichern') ?></button>
            <?= Html::a(Yii::t('UebersichtModule.base', 'Abbrechen'), ['/dashboard/dashboard'], ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
</div>
<style <?= Html::nonce() ?>>
    /* HumHub macht .list-group unter 992 px waagerecht scrollbar (Space-Menü) – hier senkrecht lassen */
    .uebersicht-bereiche.list-group { flex-direction: column; overflow-x: visible; margin-left: 0; gap: 0; }
    .uebersicht-bereiche .form-check-input { width: 2.6em; height: 1.4em; cursor: pointer; }
    .uebersicht-kaesten.list-group { flex-direction: column; overflow-x: visible; margin-left: 0; gap: 0; list-style: none; }
    .uebersicht-kaesten .list-group-item { display: flex; align-items: center; gap: 10px; margin: 0; white-space: normal; }
    .uebersicht-kaesten .form-check-input { width: 2.6em; height: 1.4em; cursor: pointer; }
    .uebersicht-kaesten .uebersicht-kasten-nr { flex: 0 0 auto; width: 1.6em; text-align: center; font-weight: 700; color: var(--bs-secondary-color, #6b6b6b); }
    .uebersicht-kaesten .list-group-item:first-child [data-schieben="-1"], .uebersicht-kaesten .list-group-item:last-child [data-schieben="1"] { visibility: hidden; }
</style>
<?php $this->registerJs(<<<JS
document.querySelectorAll('.uebersicht-kaesten').forEach(function (liste) {
    liste.addEventListener('click', function (e) {
        var knopf = e.target.closest('[data-schieben]');
        if (!knopf) { return; }
        var li = knopf.closest('li');
        var nach = knopf.dataset.schieben === '-1' ? li.previousElementSibling : li.nextElementSibling;
        if (!nach) { return; }
        knopf.dataset.schieben === '-1' ? liste.insertBefore(li, nach) : liste.insertBefore(nach, li);
        liste.querySelectorAll('.uebersicht-kasten-nr').forEach(function (n, i) { n.textContent = i + 1; });
        // Fokus bleibt beim Pfeil; am Listenende ist er ausgeblendet, dann der andere
        var ziel = li.querySelector('[data-schieben="' + knopf.dataset.schieben + '"]');
        if (getComputedStyle(ziel).visibility === 'hidden') {
            ziel = li.querySelector('[data-schieben="' + (knopf.dataset.schieben === '-1' ? '1' : '-1') + '"]');
        }
        ziel.focus();
    });
});
JS); ?>
