<?php
/**
 * HTML-Rahmen aller Mails im Stil „Uffi frisch“ (statt @humhub/views/mail/layouts/html.php, eingesetzt über
 * Events::mailLayout): Sandgrund, stahlblaue Kopfleiste mit runder Unterkante und App-Symbol, Inhalt als weiße Karte
 * mit großen Rundungen, Fußzeile leise darunter. Die Inhalte (Benachrichtigungen, Zusammenfassung, eigene Module)
 * bleiben die von HumHub – sie liefern Tabellenzeilen in 600 px Breite, die hier in die Karte gesetzt werden.
 * Bei HumHub-Updates mit dem Original abgleichen (Abbestellen-Link, FooterMenu, PoweredBy).
 */

use humhub\components\View;
use humhub\helpers\Html;
use humhub\helpers\MailStyleHelper;
use humhub\widgets\FooterMenu;
use humhub\widgets\PoweredBy;
use yii\helpers\Url;

/* @var View $this */
/* @var string $content */

$blau = '#446e88';
$blauTief = '#2b4a5f';
$sand = '#f5f1ea';
$text = '#1f2a33';
$leise = '#5f6b75';
$schrift = MailStyleHelper::getFontFamily();
try {
    $symbol = Yii::$app->img->icon->exists() ? Yii::$app->img->icon->getUrl(['width' => 96, 'height' => 96], true) : null;
} catch (\Throwable $e) {
    $symbol = null;
}
$name = Yii::$app->name;
?>
<?php $this->beginPage() ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="format-detection" content="telephone=no"/>
    <meta name="color-scheme" content="light"/>
    <meta name="supported-color-schemes" content="light"/>
    <title><?= Html::encode($name) ?></title>
    <style type="text/css">
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: <?= $sand ?>; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: separate; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; -ms-interpolation-mode: bicubic; }
        /* Links im Inhalt (Beiträge, Hinweise) in Stadtblau statt Standard-Blau */
        .dn-karte a { color: <?= $blau ?>; text-decoration: none; }
        .dn-karte p { margin: 0 0 12px; }
        .dn-karte hr { border: 0; border-top: 1px solid rgba(43, 74, 95, .12); margin: 16px 0; }
        @media only screen and (max-width: 640px) {
            .dn-rahmen { width: 100% !important; }
            .dn-aussen { padding-left: 10px !important; padding-right: 10px !important; }
            table[class~="container"], table[class~="full-width"], td[class~="full-width"] { width: 100% !important; }
            table[class="container"] { padding-left: 16px !important; padding-right: 16px !important; }
            table[class="full-width"] { padding: 0 !important; }
            img[class="image-100-percent"] { width: 100% !important; height: auto !important; max-width: 100% !important; }
        }
    </style>
    <?php $this->head() ?>
</head>
<body style="margin:0; padding:0; background-color:<?= $sand ?>; font-family:<?= $schrift ?>; color:<?= $text ?>;">
<?php $this->beginBody() ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation" style="background-color:<?= $sand ?>;">
    <!-- Kopfleiste wie in der App: Stahlblau, runde Unterkante -->
    <tr>
        <td align="center" valign="top" style="background-color:<?= $blau ?>; background-image:linear-gradient(160deg, <?= $blau ?> 0%, <?= $blauTief ?> 100%); border-bottom-left-radius:28px; border-bottom-right-radius:28px;">
            <table width="600" class="dn-rahmen" border="0" cellspacing="0" cellpadding="0" role="presentation">
                <tr>
                    <td class="dn-aussen" style="padding:18px 20px 22px;" align="left">
                        <a href="<?= Url::to(['/'], true) ?>" style="text-decoration:none; color:#ffffff;">
                            <table border="0" cellspacing="0" cellpadding="0" role="presentation">
                                <tr>
                                    <?php if ($symbol): ?>
                                        <td valign="middle" style="padding-right:12px;">
                                            <img src="<?= Html::encode($symbol) ?>" width="40" height="40" alt="" style="display:block; width:40px; height:40px; border-radius:12px; background-color:#ffffff;"/>
                                        </td>
                                    <?php endif ?>
                                    <td valign="middle" style="font-family:<?= $schrift ?>; font-size:20px; line-height:24px; font-weight:700; color:#ffffff;">
                                        <?= Html::encode($name) ?>
                                    </td>
                                </tr>
                            </table>
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr><td height="24" style="font-size:0; line-height:0;">&nbsp;</td></tr>

    <!-- Inhalt als weiße Karte -->
    <tr>
        <td align="center" valign="top" class="dn-aussen" style="padding:0 10px;">
            <table width="600" class="dn-rahmen dn-karte" border="0" cellspacing="0" cellpadding="0" role="presentation"
                   style="background-color:#ffffff; border-radius:22px; box-shadow:0 1px 2px rgba(43,74,95,.05), 0 6px 20px rgba(43,74,95,.07); overflow:hidden;">
                <tr><td height="20" style="font-size:0; line-height:0;">&nbsp;</td></tr>
                <?= $content ?>
                <tr><td height="16" style="font-size:0; line-height:0;">&nbsp;</td></tr>
            </table>
        </td>
    </tr>

    <!-- Fußzeile -->
    <tr>
        <td align="center" valign="top" class="dn-aussen" style="padding:22px 20px 32px;">
            <table width="560" class="dn-rahmen" border="0" cellspacing="0" cellpadding="0" role="presentation">
                <tr>
                    <td align="center" style="font-family:<?= $schrift ?>; font-size:12px; line-height:20px; color:<?= $leise ?>;">
                        <?php if (!empty(Yii::$app->view->params['showUnsubscribe'])): ?>
                            <a href="<?= Yii::$app->view->params['unsubscribeUrl'] ?? Url::to(['/notification/user'], true) ?>"
                               style="display:inline-block; margin-bottom:10px; padding:6px 16px; border-radius:999px; background-color:#ffffff; color:<?= $blau ?>; text-decoration:none; font-weight:600;"><?= Yii::t('base', 'Unsubscribe') ?></a><br/>
                        <?php endif ?>
                        <span style="color:<?= $leise ?>;">
                            <?= FooterMenu::widget(['location' => FooterMenu::LOCATION_EMAIL]) ?>
                            <?= PoweredBy::widget(['linkOptions' => ['style' => 'text-decoration:none;color:' . $leise]]) ?>
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
