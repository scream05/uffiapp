<?php

namespace humhub\modules\gewerbe\controllers;

use humhub\modules\gewerbe\components\BeitragDienst;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\modules\gewerbe\Module;
use humhub\modules\verzeichnis\controllers\BeitragControllerBasis;
use Yii;

/**
 * Gewerbebeiträge: Verwalter schreiben, Admins (bzw. „Gewerbe verwalten“) prüfen, geben frei oder lehnen ab
 * (Ablauf in modules/verzeichnis).
 *
 * @property Module $module
 */
class BeitragController extends BeitragControllerBasis
{
    protected function beitragKlasse(): string
    {
        return BetriebBeitrag::class;
    }

    protected function eintragKlasse(): string
    {
        return Betrieb::class;
    }

    protected function dienstKlasse(): string
    {
        return BeitragDienst::class;
    }

    protected function texte(): array
    {
        return [
            'nichtGefunden' => Yii::t('GewerbeModule.base', 'Betrieb nicht gefunden.'),
            'nurVerwalter' => Yii::t('GewerbeModule.base', 'Nur Verwalter dieses Betriebs können Beiträge schreiben.'),
            'ortWaehlen' => Yii::t('GewerbeModule.base', 'Bitte wählen, wo der Beitrag erscheinen soll: Betriebsseite und/oder Übersicht.'),
            'beide' => Yii::t('GewerbeModule.base', 'Veröffentlicht auf der Betriebsseite und in der Übersicht.'),
            'seite' => Yii::t('GewerbeModule.base', 'Veröffentlicht auf der Betriebsseite.'),
            'uebersicht' => Yii::t('GewerbeModule.base', 'Veröffentlicht in der Übersicht.'),
            'ausUebersicht' => Yii::t('GewerbeModule.base', 'Der Beitrag steht nicht mehr in der Übersicht, nur noch auf der Betriebsseite.'),
        ];
    }
}
