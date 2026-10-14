<?php
/**
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var \humhub\modules\vereine\models\VereinVerwalter|null $own
 * @var bool $canManage
 * @var \humhub\modules\calendar\models\CalendarEntry[] $entries
 * @var \humhub\modules\space\models\Space|null $calendarSpace
 * @var VereinBeitrag[] $beitraege veröffentlicht auf der Vereinsseite
 * @var VereinBeitrag[] $eigene eingereichte/abgelehnte (nur für Verwalter)
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\models\VereinVerwalter;
use humhub\modules\vereine\Module;

$space = $this->context->contentContainer;
$pending = $canManage ? $verein->getVerwalter()->where(['status' => VereinVerwalter::STATUS_ANGEFRAGT])->count() : 0;
$website = $verein->website;
?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> Vereinsverzeichnis', $space->createUrl('/vereine/verein/index')) ?></p>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <?= $this->render('_logo', ['verein' => $verein, 'size' => 72]) ?>
            <div style="flex:1;min-width:200px">
                <h1 style="margin:0 0 4px;font-size:1.5em"><?= Html::encode($verein->name) ?></h1>
                <span class="text-muted"><?= Html::encode($verein->kategorie) ?> · <?= Html::encode($verein->ortsteil) ?></span>
            </div>
        </div>

        <?php if ($canManage): ?>
            <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:6px">
                <?php if ($verein->status !== Verein::STATUS_VORGESCHLAGEN): ?>
                    <?= Html::a('<i class="fa fa-calendar-plus-o"></i> Termin eintragen', $space->createUrl('/vereine/termin/create', ['verein' => $verein->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                    <?= Html::a('<i class="fa fa-pencil-square-o"></i> Beitrag schreiben', $space->createUrl('/vereine/beitrag/create', ['verein' => $verein->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                <?php endif ?>
                <?= Html::a('<i class="fa fa-pencil"></i> Seite bearbeiten', $space->createUrl('/vereine/verein/edit', ['id' => $verein->id]), ['class' => 'btn btn-light btn-sm']) ?>
                <?= Html::a('<i class="fa fa-user-plus"></i> Verwalter' . ($pending ? " ($pending neu)" : ''), $space->createUrl('/vereine/verein/verwalter', ['id' => $verein->id]), ['class' => 'btn btn-light btn-sm']) ?>
                <?php if (Module::canManageAll()): ?>
                    <?= Html::a('<i class="fa fa-trash"></i> Aus dem Verzeichnis löschen', $space->createUrl('/vereine/verein/delete', ['id' => $verein->id]), [
                        'class' => 'btn btn-danger btn-sm', 'data-method' => 'post',
                        'data-confirm' => 'Diesen Verein wirklich aus dem Verzeichnis löschen? Eingetragene Termine bleiben im Kalender.']) ?>
                <?php endif ?>
            </div>
        <?php endif ?>

        <?php if ($verein->status === Verein::STATUS_VORGESCHLAGEN): ?>
            <div class="alert alert-warning" style="margin-top:14px">Dieser Verein wurde vorgeschlagen und wartet auf Freigabe. Erst danach ist er im Verzeichnis zu sehen.</div>
        <?php elseif ($verein->isRecherchiert()): ?>
            <div class="alert alert-info" style="margin-top:14px">Diese Angaben haben wir aus öffentlichen Quellen zusammengestellt
                (Stand: <?= Yii::$app->formatter->asDate($verein->recherchiert_am, 'long') ?>); der Verein hat sie noch nicht bestätigt.
                Etwas stimmt nicht? Vorstandsmitglieder können die Seite unten übernehmen und selbst pflegen.</div>
        <?php elseif ($verein->status === Verein::STATUS_OFFEN): ?>
            <div class="alert alert-info" style="margin-top:14px">Dieser Eintrag stammt aus der Vereinsliste der Stadt und wurde vom Verein noch nicht gepflegt.</div>
        <?php endif ?>

        <?php if (trim((string)$verein->beschreibung) !== ''): ?>
            <div style="margin-top:16px"><?= RichText::output($verein->beschreibung) ?></div>
        <?php endif ?>

        <?php $gallery = $verein->getGallery(); $credits = $verein->getBildnachweise(); ?>
        <?php if ($gallery): ?>
            <div class="verein-galerie" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-top:16px">
                <?php foreach ($gallery as $file): $c = $credits[$file->guid] ?? null; ?>
                    <figure style="margin:0">
                        <a href="<?= Html::encode($file->getUrl()) ?>" target="_blank" rel="noopener">
                            <?= Html::img($file->getUrl(), ['alt' => Html::encode($c['beschreibung'] ?? $verein->name), 'loading' => 'lazy',
                                'style' => 'width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;display:block']) ?>
                        </a>
                        <?php if ($c): ?>
                            <figcaption style="font-size:0.75em;line-height:1.3;margin-top:4px" class="text-muted">
                                <?= Html::encode($c['beschreibung'] ?? '') ?><br>
                                Foto: <?= Html::encode($c['urheber'] ?? 'unbekannt') ?>,
                                <?= !empty($c['seite']) ? Html::a(Html::encode($c['lizenz'] ?? 'Lizenz'), $c['seite'], ['target' => '_blank', 'rel' => 'noopener nofollow']) : Html::encode($c['lizenz'] ?? '') ?>
                            </figcaption>
                        <?php endif ?>
                    </figure>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <?php $ap = $verein->getAnsprechpartnerList(); $social = $verein->getSocialList(); ?>
        <?php if ($verein->treffpunkt || $website || $verein->email || $verein->telefon || $ap || $social): ?>
            <h2 style="font-size:1.1em;margin-top:20px">Kontakt</h2>
            <ul class="list-unstyled" style="line-height:1.9">
                <?php if ($verein->treffpunkt): ?><li><i class="fa fa-map-marker fa-fw"></i> <?= Html::encode($verein->treffpunkt) ?></li><?php endif ?>
                <?php if ($website): ?><li><i class="fa fa-globe fa-fw"></i> <?= Html::a(Html::encode(preg_replace('#^https?://(www\.)?|/$#', '', $website)), $website, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?></li><?php endif ?>
                <?php if ($verein->email): ?><li><i class="fa fa-envelope fa-fw"></i> <?= Html::mailto(Html::encode($verein->email), $verein->email) ?></li><?php endif ?>
                <?php if ($verein->telefon): ?><li><i class="fa fa-phone fa-fw"></i> <?= Html::a(Html::encode($verein->telefon), 'tel:' . preg_replace('/[^0-9+]/', '', $verein->telefon)) ?></li><?php endif ?>
                <?php foreach ($social as $link): ?>
                    <li><i class="fa fa-<?= ['Facebook' => 'facebook-square', 'Instagram' => 'instagram', 'YouTube' => 'youtube-play'][$link['typ']] ?? 'link' ?> fa-fw"></i>
                        <?= Html::a(Html::encode($link['typ']), $link['url'], ['target' => '_blank', 'rel' => 'noopener nofollow']) ?></li>
                <?php endforeach ?>
            </ul>
            <?php if ($ap): ?>
                <h3 style="font-size:1em;margin-top:12px">Ansprechpartner</h3>
                <ul class="list-unstyled" style="line-height:1.7">
                    <?php foreach ($ap as $p): ?>
                        <li><?= $p['funktion'] !== '' ? Html::encode($p['funktion']) . ': ' : '' ?><strong><?= Html::encode($p['name']) ?></strong>
                            <?php if (($p['email'] ?? '') !== ''): ?> · <?= Html::mailto(Html::encode($p['email']), $p['email']) ?><?php endif ?>
                            <?php if (($p['telefon'] ?? '') !== ''): ?> · <?= Html::a(Html::encode($p['telefon']), 'tel:' . preg_replace('/[^0-9+]/', '', $p['telefon'])) ?><?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        <?php endif ?>
    </div>
</div>

<?php $quellen = $verein->getQuellenList(); ?>
<?php if ($quellen || $verein->logo_quelle): ?>
<div class="panel panel-default">
    <div class="panel-body">
        <details>
            <summary style="cursor:pointer" class="text-muted">Quellen<?= $verein->recherchiert_am ? ' (Stand: ' . Yii::$app->formatter->asDate($verein->recherchiert_am, 'medium') . ')' : '' ?></summary>
            <ul style="margin-top:8px;font-size:0.9em">
                <?php foreach ($quellen as $url): ?>
                    <li><?= Html::a(Html::encode(mb_strimwidth(preg_replace('#^https?://(www\.)?#', '', $url), 0, 80, '…')), $url, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?></li>
                <?php endforeach ?>
                <?php if ($verein->logo_quelle): ?>
                    <li>Logo: <?= Html::a(Html::encode(mb_strimwidth(preg_replace('#^https?://(www\.)?#', '', $verein->logo_quelle), 0, 80, '…')), $verein->logo_quelle, ['target' => '_blank', 'rel' => 'noopener nofollow']) ?></li>
                <?php endif ?>
            </ul>
        </details>
    </div>
</div>
<?php endif ?>

<?php if ($beitraege || $eigene): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Beiträge</strong></div>
    <div class="panel-body">
        <?php foreach ($eigene as $b): ?>
            <div class="alert <?= $b->status === VereinBeitrag::STATUS_ABGELEHNT ? 'alert-danger' : 'alert-warning' ?>" style="margin-bottom:12px">
                <strong><?= $b->status === VereinBeitrag::STATUS_ABGELEHNT ? 'Nicht veröffentlicht' : 'Wartet auf Prüfung' ?></strong>
                (eingereicht <?= Yii::$app->formatter->asDatetime($b->created_at, 'short') ?> von <?= Html::encode($b->user->displayName ?? '') ?>):
                <?= Html::encode(mb_strimwidth($b->text, 0, 120, '…')) ?>
                <?php if ($b->status === VereinBeitrag::STATUS_ABGELEHNT && $b->begruendung): ?>
                    <br><small>Begründung: <?= Html::encode($b->begruendung) ?></small>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <?php foreach ($beitraege as $b): ?>
            <div style="padding:12px 0;border-top:1px solid #eee">
                <small class="text-muted"><?= Yii::$app->formatter->asDate($b->geprueft_am ?? $b->created_at, 'medium') ?></small>
                <?= $this->render('@vereine/views/beitrag/_beitrag', ['beitrag' => $b]) ?>
                <div style="margin-top:6px;font-size:0.9em">
                    <?php if ($b->content): ?>
                        <?= Html::a('<i class="fa fa-comment-o"></i> Kommentieren', $b->content->getUrl()) ?>
                    <?php endif ?>
                    <?php if (Module::canManageAll()): ?>
                        <?= Html::a('<i class="fa fa-trash"></i> Entfernen', $space->createUrl('/vereine/beitrag/entfernen', ['id' => $b->id]), [
                            'class' => 'text-danger', 'style' => 'margin-left:12px', 'data-method' => 'post', 'data-params' => ['zurueck' => 'verein'],
                            'data-confirm' => 'Diesen Beitrag ganz entfernen (auch aus der Übersicht)?']) ?>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Nächste Termine</strong></div>
    <div class="panel-body">
        <?php if ($entries): ?>
            <ul class="list-unstyled">
                <?php foreach ($entries as $entry): ?>
                    <li style="margin-bottom:10px">
                        <strong><?= Html::a(Html::encode($entry->title), $entry->content->getUrl()) ?></strong><br>
                        <small class="text-muted"><i class="fa fa-clock-o"></i> <?= Html::encode($entry->getFormattedTime()) ?>
                            <?= $entry->location ? ' · <i class="fa fa-map-marker"></i> ' . Html::encode($entry->location) : '' ?></small>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">Zurzeit keine Termine eingetragen.</p>
        <?php endif ?>
        <?php if ($calendarSpace): ?>
            <?= Html::a('Zum gemeinsamen Kalender', $calendarSpace->createUrl('/calendar/view/index')) ?>
        <?php endif ?>
    </div>
</div>

<?php if (!$canManage && $verein->status !== Verein::STATUS_VORGESCHLAGEN): ?>
<div class="panel panel-default">
    <div class="panel-body">
        <?php if ($own && $own->status === VereinVerwalter::STATUS_ANGEFRAGT): ?>
            <p class="text-muted"><i class="fa fa-hourglass-half"></i> Deine Anfrage, diesen Verein zu verwalten, wartet auf Bestätigung.</p>
        <?php else: ?>
            <details>
                <summary style="cursor:pointer"><strong>Du bist im Vorstand dieses Vereins?</strong> Seite und Termine selbst pflegen</summary>
                <?= Html::beginForm($space->createUrl('/vereine/verein/anfrage', ['id' => $verein->id]), 'post', ['style' => 'margin-top:10px']) ?>
                    <p class="help-block">Deine Anfrage geht an die Verwalter des Vereins bzw. an die Betreiber der Plattform. Wir prüfen kurz, ob du zum Verein gehörst.</p>
                    <div class="form-group">
                        <label for="verein-nachricht">Deine Funktion im Verein</label>
                        <?= Html::textInput('nachricht', '', ['id' => 'verein-nachricht', 'class' => 'form-control', 'maxlength' => 1000,
                            'placeholder' => 'z. B. 2. Vorsitzende, Schriftführer, Jugendwart', 'required' => true]) ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Als Verwalter anfragen</button>
                <?= Html::endForm() ?>
            </details>
        <?php endif ?>
    </div>
</div>
<?php endif ?>
