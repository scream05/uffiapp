<?php

namespace humhub\modules\vereine;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use Yii;

/**
 * Vereinsverzeichnis (im Space „Vereine & Ehrenamt“): je Verein eine Vorstellungsseite, gepflegt von bestätigten
 * Vereinsverwaltern. Termine der Vereine landen als normale Kalendereinträge im gemeinsamen Kalender (Space
 * „Veranstaltungen“) und sind mit dem Verein verknüpft, damit alle Verwalter des Vereins sie bearbeiten können.
 */
class Module extends ContentContainerModule
{
    public $resourcesPath = 'resources';

    /** Space mit dem gemeinsamen Kalender */
    public string $calendarSpaceUrl = 'veranstaltungen'; // URL des Space, bleibt beim Umbenennen gleich
    /** Kalender-Kategorie für Vereinstermine (dieselbe wie beim Import von uffenheim.de) */
    public string $calendarType = 'Vereinsveranstaltungen, JHV, Sommerfeste, Jubiläen usw.';

    /** Antwortadresse der Ablehnungs-Mails */
    public string $kontaktMail = 'smartcity@buergerliste-uffenheim.de';

    public array $categories = [
        'Sport',
        'Musik & Gesang',
        'Feuerwehr & Rettung',
        'Kultur & Heimat',
        'Natur, Garten & Tiere',
        'Jugend',
        'Kirche & Soziales',
        'Schützen & Tradition',
        'Partnerschaft & Begegnung',
        'Politik',
        'Förderverein',
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
        return 'Vereinsverzeichnis';
    }

    public function getContentContainerDescription($container)
    {
        return 'Vereine mit Vorstellungsseite; Vereinsverwalter tragen Termine in den gemeinsamen Kalender ein.';
    }

    /** Globales Recht „Vereine verwalten“ (Anfragen bestätigen, alle Vereinsseiten bearbeiten) */
    public function getPermissions($contentContainer = null)
    {
        return $contentContainer === null ? [new permissions\ManageVereine()] : [];
    }

    public function getNotifications()
    {
        return [notifications\VerwaltungAngefragt::class, notifications\VerwaltungBestaetigt::class,
            notifications\BeitragEingereicht::class, notifications\BeitragFreigegeben::class];
    }

    public function getCalendarSpace(): ?Space
    {
        return Space::findOne(['url' => $this->calendarSpaceUrl]);
    }

    /** Space, in dem das Verzeichnis läuft (für Links aus dem Kalender) */
    public function getDirectorySpace(): ?Space
    {
        if ($this->directorySpace === false) {
            $this->directorySpace = null;
            foreach (Space::find()->all() as $space) {
                if ($space->moduleManager->isEnabled('vereine')) {
                    $this->directorySpace = $space;
                    break;
                }
            }
        }
        return $this->directorySpace;
    }

    private Space|null|false $directorySpace = false;

    /** Admin oder Recht „Vereine verwalten“ (aktueller Nutzer) */
    public static function canManageAll(): bool
    {
        return !Yii::$app->user->isGuest
            && (Yii::$app->user->isAdmin() || Yii::$app->user->can(permissions\ManageVereine::class));
    }
}
