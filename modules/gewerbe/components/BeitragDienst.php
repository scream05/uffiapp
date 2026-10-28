<?php

namespace humhub\modules\gewerbe\components;

use humhub\modules\gewerbe\notifications\BeitragEingereicht;
use humhub\modules\gewerbe\notifications\BeitragFreigegeben;
use humhub\modules\gewerbe\permissions\ManageGewerbe;
use humhub\modules\verzeichnis\components\BeitragDienstBasis;

/** Einreichen, Freigeben, Ablehnen und Entfernen von Gewerbebeiträgen (Ablauf in modules/verzeichnis) */
class BeitragDienst extends BeitragDienstBasis
{
    protected static function modulId(): string
    {
        return 'gewerbe';
    }

    protected static function verwaltenRecht(): string
    {
        return ManageGewerbe::class;
    }

    protected static function eingereichtBenachrichtigung(): string
    {
        return BeitragEingereicht::class;
    }

    protected static function freigegebenBenachrichtigung(): string
    {
        return BeitragFreigegeben::class;
    }
}
