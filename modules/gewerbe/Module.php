<?php

namespace humhub\modules\gewerbe;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use Yii;

/**
 * Gewerbeverzeichnis (Space „Gewerbe & Einkaufen“): Schaufenster für Einzelhandel, Handwerk, Gastronomie und
 * Dienstleister. Betriebe pflegen ihre Seite selbst, sobald ihre Verwaltung bestätigt ist (wie im Vereinsverzeichnis).
 */
class Module extends ContentContainerModule
{
    public $resourcesPath = 'resources';

    /** Antwortadresse für Mails an Verfasser (Ablehnung eines Beitrags) */
    public string $kontaktMail = 'smartcity@buergerliste-uffenheim.de';

    public array $categories = [
        'Einzelhandel',
        'Lebensmittel & Hofladen',
        'Gastronomie & Café',
        'Übernachtung',
        'Handwerk & Bau',
        'Kfz & Mobilität',
        'Gesundheit & Pflege',
        'Schönheit & Wellness',
        'Dienstleistung & Beratung',
        'Landwirtschaft',
        'Industrie & Produktion',
        'Sonstiges',
    ];

    public array $ortsteile = [
        'Uffenheim', 'Brackenlohr', 'Custenlohr', 'Langensteinach', 'Rudolzhofen', 'Uttenhofen', 'Wallmersbach',
        'Welbhausen', 'Region',
    ];

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentContainerName($container)
    {
        return 'Gewerbeverzeichnis';
    }

    public function getContentContainerDescription($container)
    {
        return 'Betriebe mit Schaufenster-Seite; Inhaber pflegen ihre Seite selbst.';
    }

    /** Globales Recht „Gewerbe verwalten“ (Anfragen bestätigen, alle Betriebsseiten bearbeiten) */
    public function getPermissions($contentContainer = null)
    {
        return $contentContainer === null ? [new permissions\ManageGewerbe()] : [];
    }

    public function getNotifications()
    {
        return [notifications\VerwaltungAngefragt::class, notifications\VerwaltungBestaetigt::class,
            notifications\BeitragEingereicht::class, notifications\BeitragFreigegeben::class];
    }

    /** Space, in dem das Verzeichnis läuft */
    public function getDirectorySpace(): ?Space
    {
        if ($this->directorySpace === false) {
            $this->directorySpace = null;
            foreach (Space::find()->all() as $space) {
                if ($space->moduleManager->isEnabled('gewerbe')) {
                    $this->directorySpace = $space;
                    break;
                }
            }
        }
        return $this->directorySpace;
    }

    private Space|null|false $directorySpace = false;

    /** Admin oder Recht „Gewerbe verwalten“ (aktueller Nutzer) */
    public static function canManageAll(): bool
    {
        return !Yii::$app->user->isGuest
            && (Yii::$app->user->isAdmin() || Yii::$app->user->can(permissions\ManageGewerbe::class));
    }
}
