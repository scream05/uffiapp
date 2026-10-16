<?php
/**
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var \humhub\modules\gewerbe\models\BetriebVerwalter|null $own
 * @var bool $canManage
 * @var \humhub\modules\gewerbe\models\BetriebBeitrag[] $beitraege veröffentlicht auf der Betriebsseite
 * @var \humhub\modules\gewerbe\models\BetriebBeitrag[] $eigene eingereichte/abgelehnte (nur für Verwalter)
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\gewerbe\components\Logo;
use humhub\modules\gewerbe\components\Oeffnungszeiten;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\modules\gewerbe\models\BetriebVerwalter;
use humhub\modules\gewerbe\Module;

$space = $this->context->contentContainer;
$pending = $canManage ? $betrieb->getVerwalter()->where(['status' => BetriebVerwalter::STATUS_ANGEFRAGT])->count() : 0;
$stichworte = array_filter(array_map('trim', preg_split('/[,;\n]+/u', (string)$betrieb->angebot)));
$karte = $betrieb->getKartenUrl();
$zeiten = $betrieb->getZeiten();
$stand = $betrieb->getStand();
?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left" aria-hidden="true"></i> Gewerbeverzeichnis', $space->createUrl('/gewerbe/betrieb/index')) ?></p>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <?= $this->render('_logo', ['betrieb' => $betrieb, 'size' => 80]) ?>
            <div style="flex:1;min-width:200px">
                <h1 style="margin:0 0 4px;font-size:1.5em"><?= Html::encode($betrieb->name) ?></h1>
                <span class="text-muted"><?= Html::encode($betrieb->kategorie) ?> · <?= Html::encode($betrieb->ortsteil) ?></span>
                <?php if ($stand): ?>
                    <div class="gw-stand <?= $stand['offen'] ? 'offen' : 'zu' ?>"><?= Html::encode($stand['text']) ?></div>
                <?php endif ?>
            </div>
        </div>

        <?php if ($canManage): ?>
            <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:6px">
                <?= Html::a('<i class="fa fa-pencil" aria-hidden="true"></i> Seite bearbeiten', $space->createUrl('/gewerbe/betrieb/edit', ['id' => $betrieb->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                <?php if ($betrieb->status !== Betrieb::STATUS_VORGESCHLAGEN): ?>
                    <?= Html::a('<i class="fa fa-pencil-square-o" aria-hidden="true"></i> Beitrag schreiben', $space->createUrl('/gewerbe/beitrag/create', ['betrieb' => $betrieb->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                <?php endif ?>
                <?php if ($betrieb->status !== Betrieb::STATUS_VORGESCHLAGEN): ?>
                    <?= Html::a('<i class="fa fa-ticket" aria-hidden="true"></i> Gutscheine', $space->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $betrieb->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                <?php endif ?>
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
        <?php elseif ($betrieb->isRecherchiert()): $osm = str_contains((string)$betrieb->quelle, 'openstreetmap.org');
            $webQuellen = [];
            foreach ((array)json_decode((string)$betrieb->web_quellen, true) as $u) {
                $webQuellen[Logo::host($u)] ??= $u; // je Domain nur der erste Link
            }
            $webQuellen = array_slice($webQuellen, 0, 4, true); ?>
            <div class="alert alert-info" style="margin-top:14px">Diese Angaben stammen aus <?= $osm ? 'OpenStreetMap' : 'einer öffentlichen Liste der Stadt Uffenheim' ?>
                (<?= Html::a('Quelle', $betrieb->quelle, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?><?= $betrieb->recherchiert_am ? ', Stand ' . Yii::$app->formatter->asDate($betrieb->recherchiert_am, 'medium') : '' ?>)<?php if ($webQuellen): ?>,
                Vorstellung und Leistungen aus öffentlichen Angaben im Netz (<?= implode(', ', array_map(fn($host, $u) => Html::a(Html::encode($host), $u, ['target' => '_blank', 'rel' => 'noopener nofollow']), array_keys($webQuellen), $webQuellen)) ?><?= $betrieb->web_recherche_am ? ', Stand ' . Yii::$app->formatter->asDate($betrieb->web_recherche_am, 'medium') : '' ?>)<?php endif ?>
                und werden wöchentlich abgeglichen; der Betrieb hat sie noch nicht bestätigt. Das ist dein Betrieb? Unten kannst du die Seite übernehmen.</div>
        <?php endif ?>
        <?php if ($canManage && $betrieb->abgleich_hinweis): ?>
            <div class="alert alert-warning" style="margin-top:14px"><strong>Hinweis aus dem wöchentlichen Abgleich</strong>
                (<?= Yii::$app->formatter->asDate($betrieb->abgleich_am, 'medium') ?>):
                <div style="white-space:pre-line;margin-top:4px"><?= Html::encode($betrieb->abgleich_hinweis) ?></div>
                <small>Bitte prüfen und die Seite bei Bedarf anpassen. Angaben auf gepflegten Seiten ändern wir nicht selbst.</small></div>
        <?php endif ?>

        <?php if ($stichworte): ?>
            <ul class="gw-stichworte" aria-label="Angebot">
                <?php foreach ($stichworte as $s): ?><li><?= Html::encode($s) ?></li><?php endforeach ?>
            </ul>
        <?php endif ?>

        <div class="gw-infos">
            <?php if (Oeffnungszeiten::hatZeiten($zeiten) || trim((string)$betrieb->oeffnungszeiten) !== ''): ?>
                <section>
                    <h2><i class="fa fa-clock-o" aria-hidden="true"></i> Öffnungszeiten</h2>
                    <?php if (Oeffnungszeiten::hatZeiten($zeiten)): ?>
                        <table class="gw-zeiten">
                            <caption class="visually-hidden">Öffnungszeiten nach Wochentagen</caption>
                            <?php foreach (Oeffnungszeiten::zeilen($zeiten) as [$tage, $text]): ?>
                                <tr><th scope="row"><?= Html::encode($tage) ?></th><td><?= Html::encode($text) ?></td></tr>
                            <?php endforeach ?>
                        </table>
                    <?php endif ?>
                    <?php if (trim((string)$betrieb->oeffnungszeiten) !== ''): ?>
                        <div style="white-space:pre-line;margin-top:6px"><?= Html::encode($betrieb->oeffnungszeiten) ?></div>
                    <?php endif ?>
                </section>
            <?php endif ?>
            <?php $social = $betrieb->getSocialList(); ?>
            <?php if ($betrieb->adresse || $betrieb->telefon || $betrieb->email || $betrieb->website || $social): ?>
                <section>
                    <h2><i class="fa fa-address-card-o" aria-hidden="true"></i> Kontakt</h2>
                    <ul class="list-unstyled" style="line-height:1.9;margin:0">
                        <?php if ($betrieb->adresse): ?>
                            <li><i class="fa fa-map-marker fa-fw" aria-hidden="true"></i> <?= Html::encode($betrieb->adresse) ?>
                                <?php if ($betrieb->lat !== null): ?> · <?= Html::a('Auf der Karte', $space->createUrl('/gewerbe/betrieb/karte', ['fokus' => $betrieb->id])) ?>
                                <?php elseif ($karte): ?> · <?= Html::a('Karte', $karte, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?><?php endif ?></li>
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
                    <a href="<?= Html::encode($file->getUrl([], false)) ?>" target="_blank" rel="noopener">
                        <?= Html::img($file->getUrl([], false), ['alt' => 'Foto: ' . $betrieb->name, 'loading' => 'lazy',
                            'style' => 'width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;display:block']) ?>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>

<?php $gutscheine = $betrieb->status === Betrieb::STATUS_VORGESCHLAGEN ? []
    : \humhub\modules\gewerbe\models\Gutschein::sichtbar()->andWhere(['betrieb_id' => $betrieb->id])->orderBy(['created_at' => SORT_DESC])->all(); ?>
<?php if ($gutscheine): ?>
<?= $this->render('@gewerbe/views/gutschein/_stil') ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><i class="fa fa-ticket" aria-hidden="true"></i> Gutscheine</strong></div>
    <div class="panel-body">
        <div class="gs-liste">
            <?php foreach ($gutscheine as $g): ?>
                <?= $this->render('@gewerbe/views/gutschein/_ticket', ['g' => $g, 'mitBetrieb' => false]) ?>
            <?php endforeach ?>
        </div>
    </div>
</div>
<?php endif ?>

<?php if ($beitraege || $eigene): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Beiträge</strong></div>
    <div class="panel-body">
        <?php foreach ($eigene as $b): ?>
            <div class="alert <?= $b->status === BetriebBeitrag::STATUS_ABGELEHNT ? 'alert-danger' : 'alert-warning' ?>" style="margin-bottom:12px">
                <strong><?= $b->status === BetriebBeitrag::STATUS_ABGELEHNT ? 'Nicht veröffentlicht' : 'Wartet auf Prüfung' ?></strong>
                (eingereicht <?= Yii::$app->formatter->asDatetime($b->created_at, 'short') ?> von <?= Html::encode($b->user->displayName ?? '') ?>):
                <?= Html::encode(mb_strimwidth($b->text, 0, 120, '…')) ?>
                <?php if ($b->status === BetriebBeitrag::STATUS_ABGELEHNT && $b->begruendung): ?>
                    <br><small>Begründung: <?= Html::encode($b->begruendung) ?></small>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <?php foreach ($beitraege as $i => $b): ?>
            <div style="padding:12px 0;<?= $i || $eigene ? 'border-top:1px solid rgba(0,0,0,.08)' : 'padding-top:0' ?>">
                <small class="text-muted"><?= Yii::$app->formatter->asDate($b->geprueft_am ?? $b->created_at, 'medium') ?></small>
                <?= $this->render('@gewerbe/views/beitrag/_beitrag', ['beitrag' => $b]) ?>
                <div style="margin-top:6px;font-size:0.9em">
                    <?php if ($b->content): ?>
                        <?= Html::a('<i class="fa fa-comment-o" aria-hidden="true"></i> Kommentieren', $b->content->getUrl()) ?>
                    <?php endif ?>
                    <?php if (Module::canManageAll()): ?>
                        <?= $b->content_id
                            ? Html::a('<i class="fa fa-eye-slash" aria-hidden="true"></i> Aus der Übersicht nehmen', $space->createUrl('/gewerbe/beitrag/aus-uebersicht', ['id' => $b->id]), [
                                'style' => 'margin-left:12px', 'data-method' => 'post', 'data-params' => ['zurueck' => 'betrieb'],
                                'data-confirm' => 'Aus der Übersicht nehmen? Kommentare dort werden gelöscht; auf der Betriebsseite bleibt der Beitrag.'])
                            : Html::a('<i class="fa fa-share" aria-hidden="true"></i> Auch in der Übersicht zeigen', $space->createUrl('/gewerbe/beitrag/uebersicht', ['id' => $b->id]), [
                                'style' => 'margin-left:12px', 'data-method' => 'post', 'data-params' => ['zurueck' => 'betrieb']]) ?>
                    <?php endif ?>
                    <?php if (Module::canManageAll()): ?>
                        <?= Html::a('<i class="fa fa-trash" aria-hidden="true"></i> Entfernen', $space->createUrl('/gewerbe/beitrag/entfernen', ['id' => $b->id]), [
                            'class' => 'text-danger', 'style' => 'margin-left:12px', 'data-method' => 'post', 'data-params' => ['zurueck' => 'betrieb'],
                            'data-confirm' => 'Diesen Beitrag ganz entfernen (auch aus der Übersicht)?']) ?>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<?php if ($betrieb->osm_ref || $betrieb->logo_quelle): ?>
<p class="text-muted small gw-nachweis">
    <?php if ($betrieb->osm_ref && $betrieb->status === Betrieb::STATUS_OFFEN): ?>
        Daten: <?= Html::a('© OpenStreetMap-Mitwirkende', 'https://www.openstreetmap.org/copyright', ['target' => '_blank', 'rel' => 'noopener']) ?>, ODbL.
    <?php endif ?>
    <?php if ($betrieb->logo_quelle): ?>
        Logo: <?= Html::a(Html::encode(parse_url($betrieb->logo_quelle, PHP_URL_HOST)), $betrieb->logo_quelle, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?>.
    <?php endif ?>
</p>
<?php endif ?>
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
    .gw-zeiten th { padding: 1px 14px 1px 0; font-weight: 600; font-size: inherit; color: inherit; vertical-align: top; white-space: nowrap; }
    .gw-zeiten td { padding: 1px 0; }
    .gw-stand { margin-top: 4px; font-weight: 600; }
    .gw-stand.offen { color: #3d5a06; }
    .gw-stand.zu { color: #8a2a20; }
    html[data-bs-theme="dark"] .gw-stand.offen { color: #b5d94a; }
    html[data-bs-theme="dark"] .gw-stand.zu { color: #f0a49a; }
</style>
