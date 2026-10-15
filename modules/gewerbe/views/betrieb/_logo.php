<?php
/**
 * Logo oder farbiges Kürzel
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var int $size
 */

use humhub\helpers\Html;

$logo = $betrieb->getLogo();
$style = "width:{$size}px;height:{$size}px;border-radius:12px;flex:0 0 {$size}px;";
if ($logo) {
    echo Html::img($logo->getUrl(), ['alt' => '', 'style' => $style . 'object-fit:contain;background:#fff;border:1px solid rgba(0,0,0,.08)']);
} else {
    preg_match_all('/\p{L}[\p{L}.&]{1,}/u', $betrieb->name, $m);
    $initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice($m[0], 0, 2))));
    $hue = crc32($betrieb->kategorie) % 360;
    echo Html::tag('span', Html::encode($initials ?: '?'), ['style' => $style . "display:inline-flex;align-items:center;justify-content:center;"
        . "background:hsl($hue,45%,88%);color:hsl($hue,45%,26%);font-weight:600;font-size:" . round($size * 0.36) . 'px', 'aria-hidden' => 'true']);
}
