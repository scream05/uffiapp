<?php
/**
 * Knopf in Mails wie in der App: Stadtblau, ganz rund (statt @humhub/widgets/mails/views/mailButton.php,
 * eingesetzt über Events::mailLayout). Die Farbe aus dem Aufruf gilt weiter, nur Form und Abstände sind neu.
 */

use humhub\helpers\MailStyleHelper;
use humhub\helpers\ScssHelper;

/* @var $color string */
/* @var $text string */
/* @var $url string */

$farbe = $color ?: '#446e88';
$schrift = ScssHelper::getColorContrast($farbe);
?>
<td align="center" valign="middle"
    style="background-color:<?= $farbe ?>; border-radius:999px; background-clip:padding-box; font-family:<?= MailStyleHelper::getFontFamily() ?>; font-size:15px; text-align:center; padding:0; box-shadow:0 6px 18px rgba(68,110,136,.30);">
    <a href="<?= $url ?>" style="display:inline-block; padding:12px 30px; text-decoration:none; color:<?= $schrift ?>; font-weight:600; border-radius:999px;"><?= $text ?></a>
</td>
