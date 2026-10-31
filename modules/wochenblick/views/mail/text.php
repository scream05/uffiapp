<?php
/**
 * Wochenüberblick als reiner Text (E-Mail ohne HTML, Konsole wochenblick/zeigen)
 * @var array $d Ueberblick::daten()
 */

use humhub\modules\wochenblick\components\Ueberblick;
use yii\helpers\Url;

$t = fn($s, $p = []) => Yii::t('WochenblickModule.base', $s, $p);
$z = [$t('Das steht in Uffenheim an ({von} bis {bis}):', ['von' => Ueberblick::tag($d['von']), 'bis' => Ueberblick::tag($d['bis'])])];
if ($d['wetter']) {
    $z[] = "\n" . $t('Wetter am Wochenende');
    foreach ($d['wetter'] as [$tag, $symbol, $text, $min, $max, $regen]) {
        $z[] = "- $tag: $text, {$min}–{$max} °C" . ($regen >= 0.5 ? ', ' . $t('{mm} mm Regen', ['mm' => Yii::$app->formatter->asDecimal($regen, 1)]) : '');
    }
}
if ($d['termine']) {
    $z[] = "\n" . $t('Veranstaltungen');
    foreach ($d['termine'] as [$datum, $uhr, $titel, $ort, $url]) {
        $z[] = "- $datum" . ($uhr !== '' ? ", $uhr" : '') . ": $titel" . ($ort !== '' ? " ($ort)" : '') . "\n  $url";
    }
    if ($d['mehrTermine']) {
        $z[] = $t('{n} weitere Termine im Kalender', ['n' => $d['mehrTermine']]);
    }
}
if ($d['abfuhr']) {
    $z[] = "\n" . $t('Müllabfuhr') . ' (' . ($d['gebiet'] === 'ortsteil' ? $t('Ortsteile') : $t('Kernstadt')) . ')';
    foreach ($d['abfuhr'] as [$tag, $text]) {
        $z[] = "- $tag: $text";
    }
}
if ($d['spiele']) {
    $z[] = "\n" . $t('Spiele am Wochenende');
    foreach ($d['spiele'] as [$tag, $uhr, $text, $zustand]) {
        $z[] = "- $tag" . ($uhr !== '' ? ", $uhr" : '') . ": $text" . ($zustand !== '' ? " ($zustand)" : '');
    }
}
if ($d['sitzungen'] ?? []) {
    $z[] = "\n" . $t('Stadtrat und Ausschüsse');
    foreach ($d['sitzungen'] as [$tag, $uhr, $gremium, $top, $url]) {
        $z[] = "- $tag" . ($uhr !== '' ? ", $uhr" : '') . ": $gremium (" . ($top ? $t('{n, plural, =1{1 Punkt} other{# Punkte}} auf der Tagesordnung', ['n' => $top]) : $t('Tagesordnung folgt')) . ")\n  $url";
    }
}
if ($d['mb']) {
    $z[] = "\n" . $t('Mitteilungsblatt') . "\n- " . $t('Neue Ausgabe {titel} vom {datum}', ['titel' => $d['mb'][0], 'datum' => $d['mb'][1]]) . "\n  " . $d['mb'][2];
}
if ($d['gutscheine']) {
    $z[] = "\n" . $t('Neue Gutscheine');
    foreach ($d['gutscheine'] as [$titel, $betrieb, $bis, $url]) {
        $z[] = "- $titel · $betrieb (" . $t('bis {datum}', ['datum' => $bis]) . ")\n  $url";
    }
}
$z[] = "\n" . $t('Abbestellen: Konto → Wochenüberblick') . ' ' . Url::to(['/wochenblick/einstellungen'], true);
echo implode("\n", $z);
