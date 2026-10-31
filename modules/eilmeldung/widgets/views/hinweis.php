<?php
/**
 * @var \humhub\modules\eilmeldung\models\Eilmeldung[] $liste
 * @var \humhub\modules\eilmeldung\Module $module
 */

use yii\helpers\Html;

?>
<div class="panel eilmeldung-hinweis" role="alert">
    <?php foreach ($liste as $e): ?>
        <a class="eilmeldung-zeile" href="<?= Html::encode($e->getUrl()) ?>">
            <i class="fa fa-<?= Html::encode($e->getSymbol()) ?>" aria-hidden="true"></i>
            <span><strong><?= Html::encode($module->artName($e->art)) ?>:</strong> <?= Html::encode($e->titel) ?>
                <small><?= Html::encode(Yii::t('EilmeldungModule.base', 'bis {zeit}', ['zeit' => date('j.n., G:i', strtotime($e->gueltig_bis))])) ?></small></span>
        </a>
    <?php endforeach ?>
</div>
<?php $this->registerCss(<<<CSS
.eilmeldung-hinweis { background: #fff4e5; border: 2px solid #c25e00; border-radius: 12px; padding: 4px 0; margin-bottom: 14px; }
.eilmeldung-hinweis .eilmeldung-zeile { display: flex; gap: 10px; align-items: flex-start; padding: 10px 14px; color: #5a2d00; text-decoration: none; }
.eilmeldung-hinweis .eilmeldung-zeile + .eilmeldung-zeile { border-top: 1px solid #f0c48a; }
.eilmeldung-hinweis .eilmeldung-zeile:hover span { text-decoration: underline; }
.eilmeldung-hinweis .fa { color: #c25e00; font-size: 1.3em; margin-top: 2px; }
.eilmeldung-hinweis small { display: block; color: #7a4a1a; margin-top: 2px; }
CSS, [], 'eilmeldung-hinweis') ?>
