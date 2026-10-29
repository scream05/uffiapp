<?php
/**
 * @var \humhub\modules\file\models\File[] $bilder
 * @var string $zurueck
 * @var int $id
 */

use humhub\helpers\Html;
use humhub\modules\designwahl\components\Bildtext;

$t = fn($s, $p = []) => Yii::t('DesignwahlModule.base', $s, $p);
?>
<style <?= Html::nonce() ?>>
    .bt-bild { display: grid; grid-template-columns: 160px 1fr; gap: 16px; align-items: start; padding: 16px 0; border-top: 1px solid var(--hh-background-color-secondary, #e6e9ec); }
    .bt-bild img { width: 160px; max-height: 200px; object-fit: contain; border-radius: 10px; background: var(--hh-background-color-secondary, #f1f3f5); }
    .bt-bild textarea { min-height: 84px; }
    .bt-tipps { margin: 0 0 8px; padding-left: 20px; }
    @media (max-width: 520px) { .bt-bild { grid-template-columns: 1fr; } .bt-bild img { width: 100%; } }
</style>
<div class="container">
<div class="panel panel-default" style="max-width:760px;margin:0 auto">
    <div class="panel-heading"><h1 class="h4" style="margin:0"><?= $t('Bildbeschreibungen') ?></h1></div>
    <div class="panel-body">
        <p><?= $t('Eine kurze Beschreibung hilft Menschen, die Bilder nicht sehen können: Screenreader lesen sie vor. Sie erscheint auch, wenn ein Bild nicht lädt.') ?></p>
        <ul class="bt-tipps help-block">
            <li><?= $t('Beschreib in einem Satz, was zu sehen ist und worauf es ankommt, z. B. „Kinder basteln Laternen im Pfarrsaal“.') ?></li>
            <li><?= $t('Steht auf einem Plakat wichtiger Text (Datum, Ort, Eintritt), schreib ihn mit hinein.') ?></li>
            <li><?= $t('Nicht nötig: „Bild von …“ oder „Foto von …“ – dass es ein Bild ist, sagt der Screenreader selbst.') ?></li>
        </ul>
        <?php if (!$bilder): ?>
            <p class="text-muted"><?= $t('Dieser Beitrag hat keine Bilder.') ?></p>
        <?php else: ?>
            <?= Html::beginForm(['/designwahl/bildtext/index', 'id' => $id], 'post') ?>
            <?php foreach ($bilder as $i => $file): $feld = 'bt-' . Html::encode($file->guid); ?>
                <div class="bt-bild">
                    <?= Html::img($file->getUrl(), ['alt' => '', 'loading' => 'lazy']) ?>
                    <div>
                        <label for="<?= $feld ?>" class="control-label"><?= $t('Beschreibung für Bild {nr} von {anzahl}', ['nr' => $i + 1, 'anzahl' => count($bilder)]) ?></label>
                        <?= Html::textarea('bildtext[' . $file->guid . ']', (string)$file->title, ['id' => $feld, 'class' => 'form-control',
                            'maxlength' => Bildtext::MAX, 'aria-describedby' => $feld . '-hilfe']) ?>
                        <p class="help-block" id="<?= $feld ?>-hilfe"><?= $t('Höchstens {max} Zeichen. Leer lassen, wenn das Bild nur Schmuck ist.', ['max' => Bildtext::MAX]) ?>
                            <?php if (trim((string)$file->title) === '' && ($vorschlag = Bildtext::ausDateiname((string)$file->file_name)) !== null): ?>
                                <?= $t('Bisher wird der Dateiname vorgelesen: „{name}“.', ['name' => Html::encode($vorschlag)]) ?>
                            <?php endif ?></p>
                    </div>
                </div>
            <?php endforeach ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:8px">
                <?= Html::submitButton($t('Speichern'), ['class' => 'btn btn-primary']) ?>
                <?= Html::a($t('Abbrechen'), $zurueck, ['class' => 'btn btn-light']) ?>
            </div>
            <?= Html::endForm() ?>
        <?php endif ?>
    </div>
</div>
</div>
