<?php

use humhub\helpers\Html;
use humhub\modules\statistik\assets\StatistikAsset;
use humhub\modules\statistik\components\Bericht;
use yii\helpers\Url;

/**
 * @var Bericht $bericht
 * @var string $zeitraum
 * @var array $k
 * @var array $vorher
 * @var array $konten
 * @var int $jetzt
 * @var array $verlauf
 * @var array $seiten
 * @var array $einstieg
 * @var array $bereiche
 * @var array $herkunft
 * @var array $geraete
 * @var array $browser
 * @var array $systeme
 * @var array $zeitmuster
 * @var \humhub\modules\statistik\Module $modul
 */

StatistikAsset::register($this);
$t = fn($s, $p = []) => Yii::t('StatistikModule.base', $s, $p);
$zahl = fn($n) => Yii::$app->formatter->asInteger($n);
$datum = fn($d) => date('d.m.Y', strtotime($d));

// Veränderung zum gleich langen Zeitraum davor
$delta = function ($neu, $alt) use ($t) {
    if (!$alt) {
        return '';
    }
    $p = round(($neu - $alt) / $alt * 100);
    $kl = $p > 0 ? 'auf' : ($p < 0 ? 'ab' : 'gleich');
    $pf = $p > 0 ? '▲' : ($p < 0 ? '▼' : '■');
    return Html::tag('span', $pf . ' ' . ($p > 0 ? '+' : '') . $p . ' %', ['class' => "st-delta st-$kl",
        'title' => $t('im Vergleich zum Zeitraum davor')]);
};

// Tabelle mit waagerechten Balken: [[name, n, link?, n2?], …]
$balken = function (array $zeilen, string $spalte, ?string $spalte2 = null) use ($zahl) {
    $max = max(array_merge([1], array_column($zeilen, 1)));
    $html = '<table class="st-liste"><thead><tr><th></th><th>' . Html::encode($spalte) . '</th>'
        . ($spalte2 ? '<th>' . Html::encode($spalte2) . '</th>' : '') . '</tr></thead><tbody>';
    foreach ($zeilen as $z) {
        $name = $z[2] ? Html::a(Html::encode($z[0]), $z[2], ['target' => '_blank', 'title' => $z[2]]) : Html::encode($z[0]);
        $html .= '<tr><td class="st-name"><span class="st-balken" style="width:' . round($z[1] / $max * 100, 1) . '%"></span>'
            . '<span class="st-text">' . $name . '</span></td><td class="st-n">' . $zahl($z[1]) . '</td>'
            . ($spalte2 ? '<td class="st-n st-n2">' . $zahl($z[3]) . '</td>' : '') . '</tr>';
    }
    if (!$zeilen) {
        $html .= '<tr><td colspan="3" class="st-leer">' . Yii::t('StatistikModule.base', 'Keine Daten im Zeitraum') . '</td></tr>';
    }
    return $html . '</tbody></table>';
};

// Säulen (Uhrzeit, Wochentag)
$saeulen = function (array $werte) use ($zahl, $t) {
    $max = max(array_merge([1], array_column($werte, 'n')));
    $html = '<div class="st-saeulen" role="img">';
    foreach ($werte as $w) {
        $html .= '<div class="st-saeule" data-tip="' . Html::encode($w['titel'] . ': ' . $zahl($w['n']) . ' ' . $t('Aufrufe')) . '">'
            . '<span class="st-s" style="height:' . max(round($w['n'] / $max * 100, 1), $w['n'] ? 2 : 0) . '%"></span>'
            . '<span class="st-sl">' . Html::encode($w['label']) . '</span></div>';
    }
    return $html . '</div>';
};

// Linienverlauf: SVG nur für Linien (gestreckt, Striche bleiben 2 px), Beschriftung als HTML in Prozent
$n = count($verlauf);
$maxY = max(array_merge([4], array_column($verlauf, 'aufrufe')));
$stufe = 10 ** floor(log10($maxY / 4));
foreach ([1, 2, 2.5, 5, 10] as $f) {
    if ($maxY / ($stufe * $f) <= 4) { $stufe *= $f; break; }
}
$maxY = ceil($maxY / $stufe) * $stufe;
$x = fn($i) => $n > 1 ? $i * 1000 / ($n - 1) : 500;
$y = fn($v) => 200 * (1 - $v / $maxY);
$linie = fn($s) => implode(' ', array_map(fn($i) => round($x($i), 1) . ',' . round($y($verlauf[$i][$s]), 1), array_keys($verlauf)));
$beschriftungJede = max(1, (int)ceil($n / 12));

$knopf = function ($wert, $label) use ($zeitraum) {
    return Html::a($label, ['/statistik/admin/index', 'zeitraum' => $wert],
        ['class' => 'btn btn-sm ' . ($zeitraum === (string)$wert ? 'btn-primary' : 'btn-default'), 'data-pjax-prevent' => 1]);
};
$csv = Url::to(['/statistik/admin/csv'] + Yii::$app->request->get());
?>
<div class="panel panel-default st">
    <div class="panel-heading">
        <strong><?= $t('Statistik') ?></strong>
        <span class="st-zeitraum"><?= $bericht->von === $bericht->bis ? $datum($bericht->von) : $datum($bericht->von) . ' – ' . $datum($bericht->bis) ?></span>
        <span class="st-live" title="<?= $t('Besucher mit einem Aufruf in den letzten 5 Minuten') ?>"><span class="st-punkt"></span><?= $t('{n} gerade aktiv', ['n' => $zahl($jetzt)]) ?></span>
    </div>
    <div class="panel-body">
        <div class="st-filter">
            <div class="btn-group st-knoepfe">
                <?= $knopf('heute', $t('Heute')) ?><?= $knopf('gestern', $t('Gestern')) ?><?= $knopf('7', $t('7 Tage')) ?>
                <?= $knopf('30', $t('30 Tage')) ?><?= $knopf('90', $t('90 Tage')) ?><?= $knopf('365', $t('12 Monate')) ?>
            </div>
            <form method="get" action="<?= Url::to(['/statistik/admin/index']) ?>" class="st-eigen">
                <input type="date" name="von" value="<?= Html::encode($bericht->von) ?>" max="<?= date('Y-m-d') ?>" aria-label="<?= $t('Von') ?>" class="form-control input-sm">
                <span>–</span>
                <input type="date" name="bis" value="<?= Html::encode($bericht->bis) ?>" max="<?= date('Y-m-d') ?>" aria-label="<?= $t('Bis') ?>" class="form-control input-sm">
                <button class="btn btn-sm <?= $zeitraum === 'eigen' ? 'btn-primary' : 'btn-default' ?>"><?= $t('Anzeigen') ?></button>
            </form>
            <a href="<?= Html::encode($csv) ?>" class="btn btn-sm btn-default st-csv" data-pjax-prevent><i class="fa fa-download"></i> CSV</a>
        </div>

        <div class="st-kacheln">
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Seitenaufrufe') ?></div>
                <div class="st-k-wert"><?= $zahl($k['aufrufe']) ?></div><?= $delta($k['aufrufe'], $vorher['aufrufe']) ?></div>
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Eindeutige Besucher') ?> <sup>1</sup></div>
                <div class="st-k-wert"><?= $zahl($k['besucher']) ?></div><?= $delta($k['besucher'], $vorher['besucher']) ?></div>
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Besuche') ?> <sup>2</sup></div>
                <div class="st-k-wert"><?= $zahl($k['besuche']) ?></div><?= $delta($k['besuche'], $vorher['besuche']) ?></div>
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Seiten je Besuch') ?></div>
                <div class="st-k-wert"><?= Yii::$app->formatter->asDecimal($k['seitenJeBesuch'], 1) ?></div></div>
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Absprungrate') ?> <sup>3</sup></div>
                <div class="st-k-wert"><?= $k['absprung'] ?> %</div></div>
            <div class="st-kachel"><div class="st-k-titel"><?= $t('davon angemeldet') ?></div>
                <div class="st-k-wert"><?= $zahl($k['angemeldet']) ?></div>
                <span class="st-k-zusatz"><?= $k['besucher'] ? round($k['angemeldet'] / $k['besucher'] * 100) : 0 ?> % <?= $t('der Besucher') ?></span></div>
        </div>
        <div class="st-kacheln st-kacheln-konten">
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Konten mit Anmeldung im Zeitraum') ?> <sup>4</sup></div>
                <div class="st-k-wert"><?= $zahl($konten['aktiv']) ?></div></div>
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Neue Registrierungen') ?></div>
                <div class="st-k-wert"><?= $zahl($konten['neu']) ?></div></div>
            <div class="st-kachel"><div class="st-k-titel"><?= $t('Aktive Konten insgesamt') ?></div>
                <div class="st-k-wert"><?= $zahl($konten['gesamt']) ?></div></div>
        </div>

        <h4 class="st-h"><?= $t('Verlauf') ?>
            <span class="st-legende"><span class="st-l st-l-a"></span><?= $t('Seitenaufrufe') ?><span class="st-l st-l-b"></span><?= $t('Besucher') ?></span></h4>
        <div class="st-diagramm" data-verlauf="<?= Html::encode(json_encode(array_map(fn($v) => [$v['label'], $zahl($v['aufrufe']), $zahl($v['besucher']), $v['aufrufe'], $v['besucher']], $verlauf))) ?>"
             data-text="<?= Html::encode(json_encode([$t('Seitenaufrufe'), $t('Besucher')])) ?>" data-max="<?= $maxY ?>">
            <div class="st-plot">
                <?php for ($v = 0; $v <= $maxY; $v += $stufe): ?>
                    <span class="st-y" style="top:<?= $y($v) / 2 ?>%"><?= $zahl($v) ?></span>
                <?php endfor ?>
                <?php foreach ($verlauf as $i => $v): ?>
                    <?php if ($i % $beschriftungJede === 0): ?>
                        <span class="st-x" style="left:<?= $x($i) / 10 ?>%"><?= Html::encode($v['kurz']) ?></span>
                    <?php endif ?>
                <?php endforeach ?>
                <svg viewBox="0 0 1000 200" preserveAspectRatio="none" role="img" aria-label="<?= $t('Seitenaufrufe und Besucher im Verlauf') ?>">
                    <?php for ($v = 0; $v <= $maxY; $v += $stufe): ?>
                        <line class="st-gitter<?= $v ? '' : ' st-null' ?>" x1="0" x2="1000" y1="<?= $y($v) ?>" y2="<?= $y($v) ?>"></line>
                    <?php endfor ?>
                    <polygon class="st-flaeche" points="<?= $x(0) ?>,200 <?= $linie('aufrufe') ?> <?= $x($n - 1) ?>,200"></polygon>
                    <polyline class="st-linie st-linie-a" points="<?= $linie('aufrufe') ?>"></polyline>
                    <polyline class="st-linie st-linie-b" points="<?= $linie('besucher') ?>"></polyline>
                </svg>
                <span class="st-fadenkreuz" hidden></span>
                <span class="st-marke st-marke-a" hidden></span>
                <span class="st-marke st-marke-b" hidden></span>
                <div class="st-tip" hidden></div>
            </div>
        </div>
        <details class="st-tabelle">
            <summary><?= $t('Als Tabelle anzeigen') ?></summary>
            <table class="table table-condensed">
                <thead><tr><th><?= $t('Zeit') ?></th><th class="text-right"><?= $t('Seitenaufrufe') ?></th><th class="text-right"><?= $t('Besucher') ?></th></tr></thead>
                <tbody><?php foreach ($verlauf as $v): ?>
                    <tr><td><?= Html::encode($v['label']) ?></td><td class="text-right"><?= $zahl($v['aufrufe']) ?></td><td class="text-right"><?= $zahl($v['besucher']) ?></td></tr>
                <?php endforeach ?></tbody>
            </table>
        </details>

        <div class="st-raster">
            <section class="st-breit">
                <h4 class="st-h"><?= $t('Meistbesuchte Seiten') ?></h4>
                <?= $balken(array_map(fn($s) => [Bericht::bezeichnung($s['pfad']), (int)$s['aufrufe'], $s['pfad'], (int)$s['besucher']], $seiten),
                    $t('Aufrufe'), $t('Besucher')) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Spaces und Bereiche') ?></h4>
                <?= $balken(array_map(fn($b) => [$b['name'], (int)$b['aufrufe'], $b['url'], (int)$b['besucher']], $bereiche),
                    $t('Aufrufe'), $t('Besucher')) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Einstiegsseiten') ?></h4>
                <?= $balken(array_map(fn($s) => [Bericht::bezeichnung($s['pfad']), (int)$s['aufrufe'], $s['pfad']], $einstieg), $t('Besuche')) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Herkunft') ?></h4>
                <?= $balken(array_map(fn($h) => [$h['name'] === '' ? $t('Direkt / unbekannt') : $h['name'], (int)$h['anzahl'], null], $herkunft), $t('Besuche')) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Geräte') ?></h4>
                <?= $balken(array_map(fn($g) => [$t($g['name']), (int)$g['anzahl'], null], $geraete), $t('Besucher')) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Browser') ?></h4>
                <?= $balken(array_map(fn($g) => [$g['name'] === 'Andere' ? $t('Andere') : $g['name'], (int)$g['anzahl'], null], $browser), $t('Besucher')) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Betriebssysteme') ?></h4>
                <?= $balken(array_map(fn($g) => [$g['name'] === 'Andere' ? $t('Andere') : $g['name'], (int)$g['anzahl'], null], $systeme), $t('Besucher')) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Aufrufe nach Uhrzeit') ?></h4>
                <?= $saeulen($zeitmuster[0]) ?>
            </section>
            <section>
                <h4 class="st-h"><?= $t('Aufrufe nach Wochentag') ?></h4>
                <?= $saeulen($zeitmuster[1]) ?>
            </section>
        </div>

        <div class="st-hinweis">
            <p><sup>1</sup> <?= $t('Besucher werden je Tag gezählt: Wer an drei Tagen kommt, zählt im Zeitraum dreimal. Ohne Cookies ist mehr nicht möglich – und auch nicht gewollt.') ?>
               <sup>2</sup> <?= $t('Ein Besuch endet nach {n} Minuten ohne Aufruf.', ['n' => $modul->besuchPauseMinuten]) ?>
               <sup>3</sup> <?= $t('Anteil der Besuche mit nur einer Seite.') ?>
               <sup>4</sup> <?= $t('Letzte Anmeldung laut HumHub; wer dauerhaft angemeldet bleibt, erscheint hier nicht erneut.') ?></p>
            <p><i class="fa fa-shield"></i> <?= $t('Datenschutz: Gezählt wird auf unserem Server, ohne Cookies und ohne externe Dienste. IP-Adressen werden nicht gespeichert, sondern nur mit einem täglich wechselnden Zufallswert zu einer nicht umkehrbaren Prüfsumme verrechnet. Nicht gezählt werden Bots, Admins und Browser mit „Do Not Track“. Rohdaten werden nach {n} Monaten gelöscht.', ['n' => $modul->aufbewahrungMonate]) ?></p>
        </div>
    </div>
</div>
