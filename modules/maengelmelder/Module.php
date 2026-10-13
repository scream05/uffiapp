<?php

namespace humhub\modules\maengelmelder;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;

class Module extends ContentContainerModule
{
    public $resourcesPath = 'resources';

    /** Kartenmitte (Marktplatz Uffenheim) */
    public float $centerLat = 49.5455;
    public float $centerLng = 10.2293;

    /** Meldungen nur in diesem Bereich (Stadtgebiet mit Ortsteilen, großzügig) */
    public array $bounds = ['south' => 49.45, 'west' => 10.08, 'north' => 49.64, 'east' => 10.40];

    /** Umkreis in Metern, in dem beim Melden offene Meldungen angezeigt werden */
    public int $nearRadius = 75;

    public array $categories = [
        'Straße / Gehweg',
        'Straßenbeleuchtung',
        'Verkehrszeichen / Ampel',
        'Müll / Verschmutzung',
        'Grünanlage / Baum',
        'Spielplatz',
        'Wasser / Kanal',
        'Sonstiges',
    ];

    /** Bauhof: Space mit der Bauhof-Seite (URL), Gruppe der Bauhofleitung */
    public string $bauhofSpace = 'mangelmelder';
    public string $bauhofGruppe = 'Bauhof';

    /** WhatsApp-Vorlagen für den Erstkontakt außerhalb des 24-Stunden-Fensters (siehe commands/BauhofController) */
    public array $vorlagen = ['auftrag' => 'bauhof_auftrag', 'nachricht' => 'bauhof_nachricht'];
    public string $vorlagenSprache = 'de';

    /** Verlauf und Fotos des Bauhofs so viele Tage nach Abschluss einer Meldung löschen */
    public int $bauhofAufbewahrungTage = 365;

    public function getNotifications()
    {
        return [notifications\BauhofMeldung::class];
    }

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentContainerName($container)
    {
        return 'Mängelmelder-Karte';
    }

    public function getContentContainerDescription($container)
    {
        return 'Mängel mit Ort auf der Karte melden und alle Meldungen auf einer Karte anzeigen.';
    }

    /** Globales Recht „Mängelstatus bearbeiten“ (an Gruppen vergeben) */
    public function getPermissions($contentContainer = null)
    {
        if ($contentContainer === null) {
            return [new permissions\ManageStatus()];
        }
        return $contentContainer instanceof \humhub\modules\space\models\Space ? [new permissions\MangelMelden()] : [];
    }

    public function inBounds(float $lat, float $lng): bool
    {
        return $lat >= $this->bounds['south'] && $lat <= $this->bounds['north']
            && $lng >= $this->bounds['west'] && $lng <= $this->bounds['east'];
    }
}
