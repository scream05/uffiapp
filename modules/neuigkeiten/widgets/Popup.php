<?php

namespace humhub\modules\neuigkeiten\widgets;

use humhub\components\Widget;
use humhub\modules\neuigkeiten\assets\NeuigkeitenAsset;
use Yii;

/** Einmaliges Popup auf der Startseite mit neuen Funktionen (Seitenleiste; das Skript hängt es an body) */
class Popup extends Widget
{
    public function run()
    {
        try {
            /** @var \humhub\modules\neuigkeiten\Module $module */
            $module = Yii::$app->getModule('neuigkeiten');
            $eintraege = $module->fuerPopup(Yii::$app->user->getIdentity());
            if (!$eintraege) {
                return '';
            }
            NeuigkeitenAsset::register($this->view);
            return $this->render('popup', ['module' => $module, 'eintraege' => $eintraege]);
        } catch (\Throwable $e) {
            Yii::error($e, 'neuigkeiten');
            return '';
        }
    }
}
