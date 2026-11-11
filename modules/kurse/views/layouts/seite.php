<?php
/**
 * Layout der eigenen Kursseite eines Vereins (/kurse/<slug>): eigenständiges HTML ohne Menüs und Skripte der Plattform.
 * Farben aus kurs_verein.farbe (SBU: #497c9f), Schrift Open Sans (lokal, OFL), Logo des Vereins aus dem Verzeichnis.
 *
 * @var \yii\web\View $this
 * @var string $content
 */

use humhub\helpers\Html;

/** @var \humhub\modules\kurse\controllers\SeiteController $c */
$c = $this->context;
$verein = $c->verein;
$seite = $c->seite;
$farbe = $seite->getFarbe();
[, $res] = Yii::$app->assetManager->publish(Yii::getAlias('@kurse/resources'));
$logo = $verein->getLogo();
$website = trim((string)$verein->website);
$flash = array_filter(['ok' => Yii::$app->session->getFlash('kurse-ok'), 'info' => Yii::$app->session->getFlash('kurse-info')]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= Html::encode($farbe) ?>">
    <title><?= Html::encode($this->title ?: $verein->name) ?></title>
    <?php if ($logo): ?><link rel="icon" href="<?= Html::encode($logo->getUrl()) ?>"><?php endif ?>
    <style <?= Html::nonce() ?>>
        @font-face { font-family: 'Open Sans'; font-weight: 400; font-display: swap; src: url('<?= $res ?>/fonts/open-sans-latin-400-normal.woff2') format('woff2'); }
        @font-face { font-family: 'Open Sans'; font-weight: 600; font-display: swap; src: url('<?= $res ?>/fonts/open-sans-latin-600-normal.woff2') format('woff2'); }
        @font-face { font-family: 'Open Sans'; font-weight: 700; font-display: swap; src: url('<?= $res ?>/fonts/open-sans-latin-700-normal.woff2') format('woff2'); }
        :root {
            --f: <?= Html::encode($farbe) ?>;
            --f-dunkel: color-mix(in srgb, var(--f) 30%, #0e1418);
            --f-hell: color-mix(in srgb, var(--f) 12%, #fff);
            --f-zart: color-mix(in srgb, var(--f) 5%, #fff);
            --text: #222; --grau: #5b646b; --linie: #dfe6ea; --ok: #1e7b45; --warn: #9a5b00; --fehler: #b3261e;
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: 'Open Sans', Helvetica, Arial, sans-serif; font-size: 17px; line-height: 1.6; color: var(--text); background: #fcfdfd; }
        a { color: var(--f); }
        a:hover { color: var(--f-dunkel); }
        :focus-visible { outline: 3px solid color-mix(in srgb, var(--f) 70%, #000); outline-offset: 2px; }
        img { max-width: 100%; height: auto; }
        h1, h2, h3 { line-height: 1.25; color: var(--f-dunkel); }
        .breite { max-width: 1040px; margin: 0 auto; padding: 0 20px; }
        .schmal { max-width: 760px; }

        /* Kopfleiste */
        .kopf { background: var(--f-dunkel); color: #fff; }
        .kopf .breite { display: flex; align-items: center; gap: 14px; min-height: 64px; padding-top: 8px; padding-bottom: 8px; }
        .kopf a { color: #fff; text-decoration: none; }
        .marke { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 1.05em; margin-right: auto; }
        .marke img { width: 44px; height: 44px; object-fit: contain; background: #fff; border-radius: 8px; padding: 3px; }
        .kopf nav { display: flex; gap: 6px; flex-wrap: wrap; }
        .kopf nav a { padding: 6px 12px; border-radius: 999px; font-size: .92em; }
        .kopf nav a:hover, .kopf nav a:focus-visible { background: rgba(255,255,255,.14); }
        @media (max-width: 640px) { .kopf nav .nur-breit { display: none; } .marke span { font-size: .95em; } }

        .vorschau { background: #fff4d6; color: #5c4300; font-size: .9em; padding: 8px 0; border-bottom: 1px solid #f0d68a; }
        .meldung { margin: 18px 0 0; padding: 12px 16px; border-radius: 10px; background: var(--f-hell); border-left: 4px solid var(--f); }
        .meldung.ok { background: #e7f5ec; border-color: var(--ok); }
        .meldung.fehler { background: #fdecea; border-color: var(--fehler); }
        .meldung.warn { background: #fff4d6; border-color: #e0a100; }

        /* Bausteine */
        .knopf { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 999px; border: 0; cursor: pointer;
            background: var(--f); color: #fff !important; font: inherit; font-weight: 600; text-decoration: none; }
        .knopf:hover, .knopf:focus-visible { background: var(--f-dunkel); }
        .knopf.hell { background: #fff; color: var(--f-dunkel) !important; }
        .knopf.hell:hover { background: var(--f-hell); }
        .knopf.rand { background: transparent; color: var(--f-dunkel) !important; box-shadow: inset 0 0 0 2px var(--f); }
        .knopf.rand:hover { background: var(--f-hell); }
        .karte { background: #fff; border: 1px solid var(--linie); border-radius: 16px; padding: 22px; box-shadow: 0 1px 2px rgba(24,32,37,.04); }
        .abschnitt { padding-top: 48px; padding-bottom: 48px; }
        .abschnitt.zart { background: var(--f-zart); }
        .abschnitt h2 { font-size: 1.7em; margin: 0 0 18px; }
        .klein, .small { font-size: .88em; }
        .text-muted { color: var(--grau); }
        .grau { color: var(--grau); }
        .marke-status { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: .82em; font-weight: 600; white-space: nowrap; }
        .s-success { background: #e1f3e8; color: var(--ok); } .s-warning { background: #fff0cc; color: var(--warn); }
        .s-danger { background: #fde4e1; color: var(--fehler); } .s-default, .s-secondary, .s-info { background: #eceff1; color: #4a5358; }
        .fakten { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 18px 0; }
        .fakten dt { font-weight: 600; color: var(--f-dunkel); } .fakten dd { margin: 0; }
        @media (max-width: 520px) { .fakten { grid-template-columns: 1fr; gap: 0; } .fakten dd { margin-bottom: 10px; } }
        details summary { cursor: pointer; font-weight: 600; color: var(--f-dunkel); }

        /* Formular */
        .feld { margin-bottom: 16px; }
        .feld label.titel { display: block; font-weight: 600; margin-bottom: 4px; }
        .feld input[type=text], .feld input[type=email], .feld input[type=tel], .feld input[type=date], .feld textarea {
            width: 100%; padding: 11px 13px; border: 1px solid #b9c4cb; border-radius: 10px; font: inherit; background: #fff; color: inherit; }
        .feld input:focus, .feld textarea:focus { border-color: var(--f); outline: 3px solid color-mix(in srgb, var(--f) 25%, transparent); }
        .feld .hinweis { font-size: .85em; color: var(--grau); margin-top: 4px; }
        .feld .fehlertext { font-size: .88em; color: var(--fehler); margin-top: 4px; font-weight: 600; }
        .feld.hat-fehler input, .feld.hat-fehler textarea { border-color: var(--fehler); }
        .feld .wahl label, .feld label.wahl { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; margin: 6px 0; cursor: pointer; }
        .feld input[type=checkbox], .feld input[type=radio] { width: 20px; height: 20px; margin-top: 3px; flex: 0 0 20px; accent-color: var(--f); }
        .reihe { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0 16px; }
        fieldset { border: 0; padding: 0; margin: 0 0 10px; }
        legend { font-size: 1.2em; font-weight: 700; color: var(--f-dunkel); margin: 18px 0 10px; padding: 0; }

        /* Fuß */
        /* Schlanke Fußzeile: nur Pflichtlinks (Impressum, Datenschutz, AGPL-Quellcode) */
        .fuss { border-top: 1px solid var(--linie); font-size: .82em; padding: 16px 0 22px; }
        .fuss ul { list-style: none; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 4px 0; }
        .fuss li + li::before { content: '·'; color: var(--grau); margin: 0 10px; }
        .fuss a { color: var(--grau); text-decoration: none; }
        .fuss a:hover { color: var(--f-dunkel); text-decoration: underline; }
    </style>
</head>
<body>
<header class="kopf">
    <div class="breite">
        <a class="marke" href="<?= Html::encode($seite->seiteUrl()) ?>">
            <?php if ($logo): ?><img src="<?= Html::encode($logo->getUrl()) ?>" alt=""><?php endif ?>
            <span><?= Html::encode($verein->name) ?></span>
        </a>
        <nav aria-label="Seitenmenü">
            <a href="<?= Html::encode($seite->seiteUrl()) ?>#kurse">Kurse</a>
            <a class="nur-breit" href="<?= Html::encode($seite->seiteUrl()) ?>#ueber-uns">Über uns</a>
            <a class="nur-breit" href="<?= Html::encode($seite->seiteUrl()) ?>#kontakt">Kontakt</a>
            <?php if ($website !== ''): ?><a class="nur-breit" href="<?= Html::encode($website) ?>" rel="noopener">Vereins-Website ↗</a><?php endif ?>
        </nav>
    </div>
</header>
<?php if ($c->vorschau): ?>
    <div class="vorschau"><div class="breite"><strong>Vorschau:</strong> Diese Seite ist noch nicht freigegeben – sichtbar nur für Kursverwalter, Admins und mit dem Vorschau-Link.
        Links aus Bestätigungsmails funktionieren trotzdem für alle.</div></div>
<?php endif ?>
<main id="inhalt">
    <?php if ($flash): ?>
        <div class="breite schmal">
            <?php foreach ($flash as $art => $text): ?><div class="meldung <?= $art ?>" role="status"><?= Html::encode($text) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>
    <?= $content ?>
</main>
<footer class="fuss">
    <ul class="breite">
        <li><?= Html::a('Impressum', ['/legal/page/view', 'pageKey' => 'imprint']) ?></li>
        <li><?= Html::a('Datenschutz', ['/legal/page/view', 'pageKey' => 'privacy']) ?></li>
        <li><a href="https://github.com/scream05/uffiapp" rel="noopener">Quellcode</a></li>
    </ul>
</footer>
</body>
</html>
