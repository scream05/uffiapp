<?php

namespace humhub\modules\kiAssistent\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\kiAssistent\assets\UebersetzenAsset;
use Yii;
use yii\helpers\Url;

/** Beschriftungen und Adressen für resources/uebersetzen.js; nur eingebunden, wenn die Sprache nicht Deutsch ist */
class UebersetzenConfig extends Widget
{
    public function run()
    {
        UebersetzenAsset::register($this->view);
        $t = fn($s) => Yii::t('KiAssistentModule.base', $s);
        return Html::tag('div', '', [
            'id' => 'ki-uebersetzen-cfg',
            'style' => 'display:none',
            'data-url' => Url::to(['/ki-assistent/uebersetzung/text']),
            'data-age-url' => Url::to(['/ki-assistent/chat/confirm-age']),
            'data-labels' => json_encode([
                'uebersetzen' => $t('Übersetzen'),
                'original' => $t('Original anzeigen'),
                'laedt' => $t('Wird übersetzt …'),
                'hinweis' => $t('Maschinell übersetzt (KI), Fehler möglich'),
                'fehler' => $t('Die Übersetzung hat nicht geklappt. Bitte versuch es später noch einmal.'),
                'alter' => $t('Die Übersetzung nutzt eine KI (Claude von Anthropic). Sie ist ab 18 Jahren freigegeben. Bist du mindestens 18 Jahre alt?'),
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
