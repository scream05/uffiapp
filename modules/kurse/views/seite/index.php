<?php
/**
 * Startseite der eigenen Kursseite: Titelbereich, Kurse, Vorstellung, Team, Kontakt
 * (Inhalte aus der Vereinsseite: Slogan, Unterzeile, Stichwörter, Knopf, Vorstellungstext, Team, Kontakt)
 *
 * @var \yii\web\View $this
 * @var \humhub\modules\kurse\models\Kurs[] $kurse
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\Module;

/** @var \humhub\modules\kurse\controllers\SeiteController $c */
$c = $this->context;
$verein = $c->verein;
if (Module::kannVerwalten($verein->id)) {
    // Vorschau für Kursverwalter: Entwürfe mit anzeigen
    $entwuerfe = Kurs::find()->where(['verein_id' => $verein->id, 'status' => Kurs::STATUS_ENTWURF])
        ->andWhere(['>=', 'COALESCE(ende, beginn)', date('Y-m-d')])->orderBy(['beginn' => SORT_ASC])->all();
    $kurse = array_merge($kurse, $entwuerfe);
    usort($kurse, fn($a, $b) => strcmp($a->beginn . $a->zeit_von, $b->beginn . $b->zeit_von));
}
// Kurse nach Jahr des ersten Kurstags gruppieren; laufende Kurse aus dem Vorjahr zählen zum aktuellen Jahr
$jahre = [];
foreach ($kurse as $kurs) {
    $jahre[max((int)date('Y'), (int)substr($kurs->beginn, 0, 4))][] = $kurs;
}
ksort($jahre);
$titelbild = $verein->getTitelbild();
$stichwoerter = $verein->getStichwortList();
$knopf = trim((string)$verein->knopf_text) ?: 'Kurse ansehen';
$teams = $verein->getTeamList();
$teambild = $verein->getTeambild();
// Kontakt: E-Mail des Vereins, sonst die Kontaktadresse des nächsten ausgeschriebenen Kurses
$email = trim((string)$verein->email) ?: (Module::sichtbareKurse($verein->id)[0]->kontakt_email ?? '');
$website = trim((string)$verein->website);
$ap = $verein->getAnsprechpartnerList();
$social = $verein->getSocialList();
?>
<style <?= Html::nonce() ?>>
    .held { position: relative; color: #fff; background: linear-gradient(135deg, var(--f) 0%, var(--f-dunkel) 100%); overflow: hidden; }
    .held.mit-bild { background: linear-gradient(90deg, rgba(14,20,24,.82) 0%, rgba(14,20,24,.45) 70%, rgba(14,20,24,.25) 100%), var(--bild) center / cover no-repeat; }
    .held .breite { position: relative; z-index: 1; padding-top: 64px; padding-bottom: 110px; display: grid; grid-template-columns: 1fr auto; gap: 30px; align-items: center; }
    .held h1 { color: #fff; font-size: clamp(1.9em, 4.6vw, 3em); margin: 0 0 12px; letter-spacing: -.01em; max-width: 18em; }
    .held .unterzeile { font-size: 1.15em; opacity: .95; margin: 0 0 18px; max-width: 34em; }
    .held .vereinsname { text-transform: uppercase; letter-spacing: .08em; font-size: .82em; font-weight: 600; opacity: .85; margin: 0 0 10px; }
    .held .wappen { width: 150px; height: 150px; object-fit: contain; background: #fff; border-radius: 24px; padding: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.25); }
    .stichwoerter { display: flex; flex-wrap: wrap; gap: 8px; list-style: none; margin: 0 0 26px; padding: 0; }
    .stichwoerter li { padding: 4px 14px; border-radius: 999px; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.35); font-size: .9em; }
    .held .knopf.hell { flex-direction: column; align-items: flex-start; gap: 0; padding: 12px 26px; border-radius: 16px; }
    .held .knopf small { font-weight: 400; font-size: .82em; color: var(--grau); }
    .welle { position: absolute; left: 0; right: 0; bottom: -1px; width: 100%; height: 70px; display: block; }
    @media (max-width: 720px) { .held .breite { grid-template-columns: 1fr; padding-top: 40px; padding-bottom: 90px; } .held .wappen { display: none; } }

    .kurs-liste { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
    .kurs-karte { display: flex; flex-direction: column; }
    .kurs-karte-kopf { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; flex-wrap: wrap; }
    .kurs-karte h3 { margin: 0; font-size: 1.2em; }
    .kurs-karte h3 a { text-decoration: none; color: var(--f-dunkel); }
    .kurs-niveau { margin: 4px 0 0; }
    .kurs-eckdaten { list-style: none; padding: 0; margin: 14px 0 18px; font-size: .95em; flex: 1; }
    .kurs-eckdaten li { display: flex; gap: 8px; margin: 4px 0; }
    .kurs-karte .knopf { align-self: flex-start; }
    .leer { text-align: center; padding: 34px 22px; }
    .kurs-jahr { margin: 0 0 14px; }
    .kurs-jahr > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 12px; padding: 12px 18px;
        border-radius: 14px; background: var(--f-hell); color: var(--f-dunkel); font-weight: 700; user-select: none; }
    .kurs-jahr > summary::-webkit-details-marker { display: none; }
    .kurs-jahr > summary::after { content: ''; margin-left: auto; width: 10px; height: 10px; border-right: 2.5px solid currentColor;
        border-bottom: 2.5px solid currentColor; transform: rotate(45deg); transition: transform .2s; margin-top: -4px; }
    .kurs-jahr[open] > summary::after { transform: rotate(-135deg); margin-top: 4px; }
    .kurs-jahr > summary:hover { background: color-mix(in srgb, var(--f) 20%, #fff); }
    .kurs-jahr-zahl { font-size: 1.25em; }
    .kurs-jahr-anzahl { font-weight: 400; color: var(--grau); font-size: .95em; }
    .kurs-jahr > .kurs-liste { margin-top: 16px; }

    .vorstellung { font-size: 1.02em; }
    .vorstellung::after { content: ''; display: block; clear: both; }
    .vorstellung img { border-radius: 14px; margin: 6px 0; }
    .vorstellung img.float-start { float: left; width: 45%; margin: 4px 22px 12px 0; }
    .vorstellung img.float-end { float: right; width: 45%; margin: 4px 0 12px 22px; }
    @media (max-width: 600px) { .vorstellung img.float-start, .vorstellung img.float-end { float: none; width: 100%; margin: 8px 0; } }
    .vorstellung h1, .vorstellung h2, .vorstellung h3 { font-size: 1.3em; margin: 1.4em 0 .5em; }

    .teams { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-top: 18px; }
    .teams h3 { margin: 0 0 8px; font-size: 1.05em; }
    .teams ul { list-style: none; margin: 0; padding: 0; line-height: 1.8; }
    .teambild { width: 100%; max-height: 460px; object-fit: cover; border-radius: 16px; display: block; margin-bottom: 14px; }

    .kontakt { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
    .kontakt ul { list-style: none; margin: 0; padding: 0; line-height: 1.9; }
    .kontakt h3 { margin: 0 0 10px; font-size: 1.1em; }
</style>

<section class="held<?= $titelbild ? ' mit-bild' : '' ?>"<?= $titelbild ? ' style="--bild:url(\'' . Html::encode($titelbild->getUrl()) . '\')"' : '' ?>>
    <div class="breite">
        <div>
            <p class="vereinsname"><?= Html::encode($verein->name) ?></p>
            <h1><?= Html::encode(trim((string)$verein->slogan) ?: $verein->name) ?></h1>
            <?php if (trim((string)$verein->untertitel) !== ''): ?><p class="unterzeile"><?= Html::encode($verein->untertitel) ?></p><?php endif ?>
            <?php if ($stichwoerter): ?>
                <ul class="stichwoerter"><?php foreach ($stichwoerter as $w): ?><li><?= Html::encode($w) ?></li><?php endforeach ?></ul>
            <?php endif ?>
            <a class="knopf hell" href="#kurse"><span><?= Html::encode($knopf) ?> <span aria-hidden="true">↓</span></span>
                <?php if (trim((string)$verein->knopf_untertext) !== ''): ?><small><?= Html::encode($verein->knopf_untertext) ?></small><?php endif ?></a>
        </div>
        <?php if (($logo = $verein->getLogo()) && !$titelbild): ?>
            <img class="wappen" src="<?= Html::encode($logo->getUrl()) ?>" alt="Logo <?= Html::encode($verein->name) ?>">
        <?php endif ?>
    </div>
    <svg class="welle" viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,38 C180,8 360,8 540,30 C720,52 900,62 1080,44 C1260,26 1350,20 1440,26 L1440,70 L0,70 Z" fill="#fcfdfd" opacity=".55"/>
        <path d="M0,50 C200,30 380,28 600,46 C800,62 1000,66 1200,50 C1320,42 1400,40 1440,42 L1440,70 L0,70 Z" fill="#fcfdfd"/>
    </svg>
</section>

<section class="abschnitt" id="kurse">
    <div class="breite">
        <h2>Aktuelle Kurse</h2>
        <?php if ($kurse): ?>
            <p class="grau">Die Anmeldung geht ohne Konto: Du bekommst eine Mail zum Bestätigen. Plätze werden in der Reihenfolge der Bestätigungen vergeben.</p>
            <?php $erstes = true; foreach ($jahre as $jahr => $liste): ?>
                <?php // Aktuelles Jahr offen; gibt es dort keine Kurse, ist das nächste Jahr mit Kursen offen ?>
                <details class="kurs-jahr"<?= $erstes ? ' open' : '' ?>>
                    <summary><span class="kurs-jahr-zahl"><?= $jahr ?></span>
                        <span class="kurs-jahr-anzahl"><?= count($liste) === 1 ? '1 Kurs' : count($liste) . ' Kurse' ?></span></summary>
                    <div class="kurs-liste">
                        <?php foreach ($liste as $kurs): ?><?= $this->render('_karte', ['kurs' => $kurs]) ?><?php endforeach ?>
                    </div>
                </details>
            <?php $erstes = false; endforeach ?>
        <?php else: ?>
            <div class="karte leer">
                <p><strong>Gerade sind keine Kurse ausgeschrieben.</strong></p>
                <p class="grau">Schau bald wieder vorbei<?= $email !== '' ? ' oder schreib uns: ' . Html::mailto(Html::encode($email), $email) : '' ?>.</p>
            </div>
        <?php endif ?>
    </div>
</section>

<?php if (trim((string)$verein->beschreibung) !== ''): ?>
    <section class="abschnitt zart" id="ueber-uns">
        <div class="breite">
            <h2>Über uns</h2>
            <div class="vorstellung"><?= RichText::convert($verein->beschreibung, RichText::FORMAT_HTML) ?></div>
        </div>
    </section>
<?php endif ?>

<?php if ($teams || $teambild || trim((string)$verein->team_text) !== ''): ?>
    <section class="abschnitt" id="team">
        <div class="breite">
            <h2>Unser Team</h2>
            <?php if ($teambild): ?><img class="teambild" src="<?= Html::encode($teambild->getUrl()) ?>" alt="Teamfoto" loading="lazy"><?php endif ?>
            <?php if (trim((string)$verein->team_text) !== ''): ?><p style="white-space:pre-line"><?= Html::encode($verein->team_text) ?></p><?php endif ?>
            <?php if ($teams): ?>
                <div class="teams">
                    <?php foreach ($teams as $team): ?>
                        <div class="karte">
                            <?php if (($team['titel'] ?? '') !== ''): ?><h3><?= Html::encode($team['titel']) ?></h3><?php endif ?>
                            <ul>
                                <?php foreach ($team['mitglieder'] ?? [] as $p): ?>
                                    <li><strong><?= Html::encode($p['name']) ?></strong><?= ($p['funktion'] ?? '') !== '' ? ' <span class="grau">· ' . Html::encode($p['funktion']) . '</span>' : '' ?></li>
                                <?php endforeach ?>
                            </ul>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>

<section class="abschnitt zart" id="kontakt">
    <div class="breite">
        <h2>Kontakt</h2>
        <div class="kontakt">
            <div class="karte">
                <h3>So erreichst du uns</h3>
                <ul>
                    <?php if ($email !== ''): ?><li>✉️ <?= Html::mailto(Html::encode($email), $email) ?></li><?php endif ?>
                    <?php if ($verein->telefon): ?><li>📞 <?= Html::a(Html::encode($verein->telefon), 'tel:' . preg_replace('/[^0-9+]/', '', $verein->telefon)) ?></li><?php endif ?>
                    <?php if ($verein->treffpunkt): ?><li>📍 <?= Html::encode($verein->treffpunkt) ?></li><?php endif ?>
                    <?php if ($website !== ''): ?><li>🌐 <?= Html::a(Html::encode(preg_replace('#^https?://(www\.)?|/$#', '', $website)), $website, ['rel' => 'noopener']) ?></li><?php endif ?>
                    <?php foreach ($social as $link): ?><li>🔗 <?= Html::a(Html::encode($link['typ']), $link['url'], ['rel' => 'noopener nofollow']) ?></li><?php endforeach ?>
                </ul>
            </div>
            <?php if ($ap): ?>
                <div class="karte">
                    <h3>Ansprechpartner</h3>
                    <ul>
                        <?php foreach ($ap as $p): ?>
                            <li><?= ($p['funktion'] ?? '') !== '' ? Html::encode($p['funktion']) . ': ' : '' ?><strong><?= Html::encode($p['name']) ?></strong>
                                <?php if (($p['email'] ?? '') !== ''): ?><br><?= Html::mailto(Html::encode($p['email']), $p['email']) ?><?php endif ?>
                                <?php if (($p['telefon'] ?? '') !== ''): ?><br><?= Html::a(Html::encode($p['telefon']), 'tel:' . preg_replace('/[^0-9+]/', '', $p['telefon'])) ?><?php endif ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </div>
            <?php endif ?>
        </div>
    </div>
</section>
