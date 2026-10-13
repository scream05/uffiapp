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
}
