<?php

namespace humhub\modules\kiAssistent\widgets;

use humhub\components\Widget;
use humhub\modules\kiAssistent\assets\ChatAsset;
use humhub\modules\kiAssistent\Module;
use Yii;

/**
 * Avatar-Knopf unten rechts mit Chatfenster auf allen Seiten. Wird über LayoutAddons nur beim vollen
 * Seitenaufbau ausgegeben und liegt außerhalb des PJAX-Bereichs – bleibt also beim Seitenwechsel offen.
 */
class FloatingChat extends Widget
{
    public function run()
    {
        try {
            /** @var Module $module */
            $module = Yii::$app->getModule('ki-assistent');
            $bundle = ChatAsset::register($this->view);
            $this->view->registerJs('window.kiChatDomains = ' . json_encode(\Yii::$app->getModule('ki-assistent')->getLinkDomains()) . ';', \yii\web\View::POS_HEAD);
            self::texteRegistrieren($this->view);

            return $this->render('floatingChat', [
                'avatar' => $bundle->baseUrl . '/avatar.svg',
                'enabled' => $module->getApiKey() !== null && $module->isAgeConfirmed(),
                'ageConfirmed' => $module->isAgeConfirmed(),
            ]);
        } catch (\Throwable $e) {
            // Ein Fehler im Chatfenster darf nicht jede Seite kaputt machen
            Yii::error($e, 'ki-assistent');
            return '';
        }
    }

    /** Übersetzte Texte für ki-chat.js (Schlüssel = deutscher Text), bei Deutsch nicht nötig */
    public static function texteRegistrieren(\yii\web\View $view): void
    {
        if (str_starts_with(Yii::$app->language, 'de')) {
            return;
        }
        $texte = [
            'Hilfreich' => Yii::t('KiAssistentModule.base', 'Hilfreich'),
            'Nicht hilfreich' => Yii::t('KiAssistentModule.base', 'Nicht hilfreich'),
            'Danke für deine Rückmeldung!' => Yii::t('KiAssistentModule.base', 'Danke für deine Rückmeldung!'),
            'Was war falsch oder hat gefehlt? (optional)' => Yii::t('KiAssistentModule.base', 'Was war falsch oder hat gefehlt? (optional)'),
            'Senden' => Yii::t('KiAssistentModule.base', 'Senden'),
            'Verbindungsfehler' => Yii::t('KiAssistentModule.base', 'Verbindungsfehler'),
        ];
        $view->registerJs('window.kiChatText = ' . json_encode($texte, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';',
            \yii\web\View::POS_HEAD, 'ki-chat-texte');
    }
}
