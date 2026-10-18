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

    /** Gutscheine: so lange zeigt die Bestätigung nach dem Einlösen die Live-Ansicht */
    public int $bestaetigungMinuten = 15;
    /** Gutscheine: so lange kann der Betrieb eine Einlösung zurücknehmen */
    public int $stornoMinuten = 10;
    /** Gutscheine: mehr als so viele Einlösungen je Betrieb in sperreMinuten pausieren alle seine Gutscheine */
    public int $sperreAnzahl = 30;
    public int $sperreMinuten = 10;
    /** Gutscheine: Einlösungen so viele Tage nach Ende des Gutscheins löschen (der Zähler bleibt) */
    public int $einloesungTage = 90;

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

    /** Branche zur Anzeige übersetzt (gespeichert und gefiltert wird immer der deutsche Wert) */
    public static function kategorieLabel(?string $kategorie): string
    {
        return $kategorie === null || $kategorie === '' ? '' : Yii::t('GewerbeModule.base', $kategorie);
    }

    /** Ort zur Anzeige: Ortsnamen bleiben, nur „Region“ wird übersetzt */
    public static function ortLabel(?string $ort): string
    {
        return $ort === 'Region' ? Yii::t('GewerbeModule.base', 'Region') : (string)$ort;
    }

    /** Liste Wert => übersetzte Anzeige, z. B. für Auswahllisten */
    public function kategorieOptionen(): array
    {
        $optionen = [];
        foreach ($this->categories as $k) {
            $optionen[$k] = self::kategorieLabel($k);
        }
        return $optionen;
    }

    public function ortOptionen(): array
    {
        $optionen = [];
        foreach ($this->ortsteile as $o) {
            $optionen[$o] = self::ortLabel($o);
        }
        return $optionen;
    }

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentContainerName($container)
    {
        return Yii::t('GewerbeModule.base', 'Gewerbeverzeichnis');
    }

    public function getContentContainerDescription($container)
    {
        return Yii::t('GewerbeModule.base', 'Betriebe mit Schaufenster-Seite; Inhaber pflegen ihre Seite selbst.');
    }

    /** Globales Recht „Gewerbe verwalten“ (Anfragen bestätigen, alle Betriebsseiten bearbeiten) */
    public function getPermissions($contentContainer = null)
    {
        return $contentContainer === null ? [new permissions\ManageGewerbe()] : [];
    }

    public function getNotifications()
    {
        return [notifications\VerwaltungAngefragt::class, notifications\VerwaltungBestaetigt::class,
            notifications\BeitragEingereicht::class, notifications\BeitragFreigegeben::class,
            notifications\GutscheinNeu::class, notifications\GutscheinPausiert::class, notifications\GutscheinFuerAlle::class];
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
