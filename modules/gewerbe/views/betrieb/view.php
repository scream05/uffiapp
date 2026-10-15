<?php
/**
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var \humhub\modules\gewerbe\models\BetriebVerwalter|null $own
 * @var bool $canManage
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\BetriebVerwalter;
use humhub\modules\gewerbe\Module;

$space = $this->context->contentContainer;
$pending = $canManage ? $betrieb->getVerwalter()->where(['status' => BetriebVerwalter::STATUS_ANGEFRAGT])->count() : 0;
$stichworte = array_filter(array_map('trim', preg_split('/[,;\n]+/u', (string)$betrieb->angebot)));
$karte = $betrieb->getKartenUrl();
?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left" aria-hidden="true"></i> Gewerbeverzeichnis', $space->createUrl('/gewerbe/betrieb/index')) ?></p>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <?= $this->render('_logo', ['betrieb' => $betrieb, 'size' => 80]) ?>
            <div style="flex:1;min-width:200px">
                <h1 style="margin:0 0 4px;font-size:1.5em"><?= Html::encode($betrieb->name) ?></h1>
                <span class="text-muted"><?= Html::encode($betrieb->kategorie) ?> · <?= Html::encode($betrieb->ortsteil) ?></span>
            </div>
        </div>

        <?php if ($canManage): ?>
            <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:6px">
                <?= Html::a('<i class="fa fa-pencil" aria-hidden="true"></i> Seite bearbeiten', $space->createUrl('/gewerbe/betrieb/edit', ['id' => $betrieb->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                <?= Html::a('<i class="fa fa-user-plus" aria-hidden="true"></i> Verwalter' . ($pending ? " ($pending neu)" : ''), $space->createUrl('/gewerbe/betrieb/verwalter', ['id' => $betrieb->id]), ['class' => 'btn btn-light btn-sm']) ?>
                <?php if (Module::canManageAll()): ?>
                    <?= Html::a('<i class="fa fa-trash" aria-hidden="true"></i> Aus dem Verzeichnis löschen', $space->createUrl('/gewerbe/betrieb/delete', ['id' => $betrieb->id]), [
                        'class' => 'btn btn-danger btn-sm', 'data-method' => 'post',
                        'data-confirm' => 'Diesen Betrieb wirklich aus dem Verzeichnis löschen?']) ?>
                <?php endif ?>
            </div>
        <?php endif ?>

        <?php if ($betrieb->status === Betrieb::STATUS_VORGESCHLAGEN): ?>
            <div class="alert alert-warning" style="margin-top:14px">Dieser Eintrag wartet auf Freigabe. Erst danach ist er im Verzeichnis zu sehen.</div>
        <?php endif ?>

        <?php if ($stichworte): ?>
            <ul class="gw-stichworte" aria-label="Angebot">
                <?php foreach ($stichworte as $s): ?><li><?= Html::encode($s) ?></li><?php endforeach ?>
            </ul>
        <?php endif ?>

        <div class="gw-infos">
            <?php if (trim((string)$betrieb->oeffnungszeiten) !== ''): ?>
                <section>
                    <h2><i class="fa fa-clock-o" aria-hidden="true"></i> Öffnungszeiten</h2>
                    <div style="white-space:pre-line"><?= Html::encode($betrieb->oeffnungszeiten) ?></div>
                </section>
            <?php endif ?>
            <?php $social = $betrieb->getSocialList(); ?>
            <?php if ($betrieb->adresse || $betrieb->telefon || $betrieb->email || $betrieb->website || $social): ?>
                <section>
                    <h2><i class="fa fa-address-card-o" aria-hidden="true"></i> Kontakt</h2>
                    <ul class="list-unstyled" style="line-height:1.9;margin:0">
                        <?php if ($betrieb->adresse): ?>
                            <li><i class="fa fa-map-marker fa-fw" aria-hidden="true"></i> <?= Html::encode($betrieb->adresse) ?>
                                <?php if ($karte): ?> · <?= Html::a('Karte', $karte, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?><?php endif ?></li>
                        <?php endif ?>
                        <?php if ($betrieb->telefon): ?><li><i class="fa fa-phone fa-fw" aria-hidden="true"></i> <?= Html::a(Html::encode($betrieb->telefon), 'tel:' . preg_replace('/[^0-9+]/', '', $betrieb->telefon)) ?></li><?php endif ?>
                        <?php if ($betrieb->email): ?><li><i class="fa fa-envelope fa-fw" aria-hidden="true"></i> <?= Html::mailto(Html::encode($betrieb->email), $betrieb->email) ?></li><?php endif ?>
                        <?php if ($betrieb->website): ?><li><i class="fa fa-globe fa-fw" aria-hidden="true"></i> <?= Html::a(Html::encode(preg_replace('#^https?://(www\.)?|/$#', '', $betrieb->website)), $betrieb->website, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?></li><?php endif ?>
                        <?php foreach ($social as $link): ?>
                            <li><i class="fa fa-<?= ['Facebook' => 'facebook-square', 'Instagram' => 'instagram', 'YouTube' => 'youtube-play', 'Google' => 'google'][$link['typ']] ?? 'link' ?> fa-fw" aria-hidden="true"></i>
                                <?= Html::a(Html::encode($link['typ']), $link['url'], ['target' => '_blank', 'rel' => 'noopener nofollow']) ?></li>
                        <?php endforeach ?>
                    </ul>
                </section>
            <?php endif ?>
        </div>

        <?php if (trim((string)$betrieb->beschreibung) !== ''): ?>
            <div style="margin-top:16px"><?= RichText::output($betrieb->beschreibung) ?></div>
        <?php endif ?>

        <?php $gallery = $betrieb->getGallery(); ?>
        <?php if ($gallery): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-top:16px">
                <?php foreach ($gallery as $file): ?>
                    <a href="<?= Html::encode($file->getUrl()) ?>" target="_blank" rel="noopener">
                        <?= Html::img($file->getUrl(), ['alt' => 'Foto: ' . $betrieb->name, 'loading' => 'lazy',
                            'style' => 'width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;display:block']) ?>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>

<?php if (!$canManage && $betrieb->status !== Betrieb::STATUS_VORGESCHLAGEN): ?>
<div class="panel panel-default">
    <div class="panel-body">
        <?php if (Yii::$app->user->isGuest): ?>
            <p class="text-muted">Das ist dein Betrieb? <?= Html::a('Melde dich an', ['/user/auth/login']) ?>, um die Seite selbst zu pflegen.</p>
        <?php elseif ($own && $own->status === BetriebVerwalter::STATUS_ANGEFRAGT): ?>
            <p class="text-muted"><i class="fa fa-hourglass-half" aria-hidden="true"></i> Deine Anfrage, diesen Betrieb zu verwalten, wartet auf Bestätigung.</p>
        <?php else: ?>
            <details>
                <summary style="cursor:pointer"><strong>Das ist dein Betrieb?</strong> Seite selbst pflegen</summary>
                <?= Html::beginForm($space->createUrl('/gewerbe/betrieb/anfrage', ['id' => $betrieb->id]), 'post', ['style' => 'margin-top:10px']) ?>
                    <p class="help-block">Deine Anfrage geht an die Verwalter des Betriebs bzw. an die Betreiber der Plattform. Wir prüfen kurz, ob du zum Betrieb gehörst.</p>
                    <div class="form-group">
                        <label for="gw-nachricht">Deine Funktion im Betrieb</label>
                        <?= Html::textInput('nachricht', '', ['id' => 'gw-nachricht', 'class' => 'form-control', 'maxlength' => 1000,
                            'placeholder' => 'z. B. Inhaberin, Geschäftsführer, Mitarbeiterin mit Auftrag', 'required' => true]) ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Als Verwalter anfragen</button>
                <?= Html::endForm() ?>
            </details>
        <?php endif ?>
    </div>
</div>
<?php endif ?>
<style <?= Html::nonce() ?>>
    .gw-stichworte { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; padding: 0; margin: 16px 0 0; }
    .gw-stichworte li { padding: 3px 10px; border-radius: 999px; background: rgba(68, 110, 136, .1); font-size: .9em; }
    .gw-infos { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 16px; }
    .gw-infos h2 { font-size: 1.05em; margin: 0 0 6px; }
    .gw-infos a { text-decoration: underline; text-underline-offset: 2px; }
</style>
