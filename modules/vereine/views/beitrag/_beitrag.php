<?php
/**
 * Ein Vereinsbeitrag (Text + Bilder), z. B. auf der Vereinsseite und in der Prüfliste.
 * @var \humhub\modules\vereine\models\VereinBeitrag $beitrag
 */

use humhub\helpers\Html;

$bilder = $beitrag->getBilder();
?>
<div style="white-space:pre-line;overflow-wrap:anywhere"><?= Html::encode($beitrag->text) ?></div>
<?php if ($bilder): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-top:10px">
        <?php foreach ($bilder as $file): ?>
            <a href="<?= Html::encode($file->getUrl()) ?>" target="_blank" rel="noopener">
                <?= Html::img($file->getUrl(), ['alt' => '', 'loading' => 'lazy',
                    'style' => 'width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;display:block']) ?>
            </a>
        <?php endforeach ?>
    </div>
<?php endif ?>
