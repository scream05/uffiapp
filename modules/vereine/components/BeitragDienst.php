<?php

namespace humhub\modules\vereine\components;

use humhub\modules\vereine\notifications\BeitragEingereicht;
use humhub\modules\vereine\notifications\BeitragFreigegeben;
use humhub\modules\vereine\permissions\ManageVereine;
use humhub\modules\verzeichnis\components\BeitragDienstBasis;

/** Einreichen, Freigeben, Ablehnen und Entfernen von Vereinsbeiträgen (Ablauf in modules/verzeichnis) */
class BeitragDienst extends BeitragDienstBasis
{
    protected static function modulId(): string
    {
        return 'vereine';
    }

    protected static function verwaltenRecht(): string
    {
        return ManageVereine::class;
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
