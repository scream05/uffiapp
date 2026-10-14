<?php
/**
 * @var \humhub\modules\space\models\Space[] $bereiche
 * @var string[] $aus GUIDs der ausgeschalteten Spaces
 */

use humhub\helpers\Html;
use humhub\modules\content\models\Content;
use humhub\modules\space\widgets\Image;

$this->title = 'Meine Übersicht';
?>
<div class="container">
<div class="panel panel-default" style="max-width:640px;margin:0 auto">
    <div class="panel-body">
        <h1 style="font-size:1.4em;margin-top:0">Meine Übersicht</h1>
        <p class="help-block">Wählen Sie, aus welchen Bereichen Beiträge in Ihrer Übersicht erscheinen. Das gilt nur für Sie.
            Beiträge, die wir oben anpinnen, sehen Sie immer. Ihre Benachrichtigungen ändern sich dadurch nicht.</p>
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
                                <small style="display:block;color:#7c1922">Beiträge hier sehen nur Mitglieder –
                                    <?= Html::a('Bereich öffnen und beitreten', $space->getUrl()) ?></small>
                            <?php endif ?>
                        </span>
                        <span class="form-check form-switch" style="margin:0">
                            <?= Html::checkbox('an[]', !in_array($space->guid, $aus, true), [
                                'value' => $space->guid, 'class' => 'form-check-input', 'role' => 'switch',
                                'aria-label' => 'Beiträge aus ' . $space->name . ' in meiner Übersicht']) ?>
                        </span>
                    </label>
                <?php endforeach ?>
            </div>
            <button type="submit" class="btn btn-primary">Speichern</button>
            <?= Html::a('Abbrechen', ['/dashboard/dashboard'], ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
</div>
<style <?= Html::nonce() ?>>
    /* HumHub macht .list-group unter 992 px waagerecht scrollbar (Space-Menü) – hier senkrecht lassen */
    .uebersicht-bereiche.list-group { flex-direction: column; overflow-x: visible; margin-left: 0; gap: 0; }
    .uebersicht-bereiche .form-check-input { width: 2.6em; height: 1.4em; cursor: pointer; }
</style>
