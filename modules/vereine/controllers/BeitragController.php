<?php

namespace humhub\modules\vereine\controllers;

use humhub\modules\vereine\components\BeitragDienst;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\Module;
use humhub\modules\verzeichnis\controllers\BeitragControllerBasis;
use Yii;

/**
 * Vereinsbeiträge: Verwalter schreiben, Admins (bzw. „Vereine verwalten“) prüfen, geben frei oder lehnen ab
 * (Ablauf in modules/verzeichnis).
 *
 * @property Module $module
 */
class BeitragController extends BeitragControllerBasis
{
    protected function beitragKlasse(): string
    {
        return VereinBeitrag::class;
    }

    protected function eintragKlasse(): string
    {
        return Verein::class;
    }

    protected function dienstKlasse(): string
    {
        return BeitragDienst::class;
    }

    protected function texte(): array
    {
        return [
            'nichtGefunden' => Yii::t('VereineModule.base', 'Verein nicht gefunden.'),
            'nurVerwalter' => Yii::t('VereineModule.base', 'Nur Verwalter dieses Vereins können Beiträge schreiben.'),
            'ortWaehlen' => Yii::t('VereineModule.base', 'Bitte wählen, wo der Beitrag erscheinen soll: Vereinsseite und/oder Übersicht.'),
            'beide' => Yii::t('VereineModule.base', 'Veröffentlicht auf der Vereinsseite und in der Übersicht.'),
            'seite' => Yii::t('VereineModule.base', 'Veröffentlicht auf der Vereinsseite.'),
            'uebersicht' => Yii::t('VereineModule.base', 'Veröffentlicht in der Übersicht.'),
            'ausUebersicht' => Yii::t('VereineModule.base', 'Der Beitrag steht nicht mehr in der Übersicht, nur noch auf der Vereinsseite.'),
        ];
    }
}
