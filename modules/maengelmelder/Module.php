<?php

namespace humhub\modules\maengelmelder;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use Yii;

class Module extends ContentContainerModule
{
    public $resourcesPath = 'resources';

    /** Kartenmitte (Marktplatz Uffenheim) */
    public float $centerLat = 49.5455;
    public float $centerLng = 10.2293;

    /** Rechteck um das Stadtgebiet (Kartenausschnitt, Vorprüfung); maßgeblich ist die Gemeindegrenze in data/stadtgebiet.json */
    public array $bounds = ['south' => 49.47, 'west' => 10.13, 'north' => 49.565, 'east' => 10.30];

    private ?array $stadtgebiet = null;

    /** Umkreis in Metern, in dem beim Melden offene Meldungen angezeigt werden */
    public int $nearRadius = 75;

    /** Gespeichert (Beitragstext, URL, KI-Assistent) werden immer diese deutschen Werte; Anzeige über kategorieLabel() */
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
    public array $vorlagen = ['auftrag' => 'bauhof_auftrag', 'nachricht' => 'bauhof_nachricht',
        // Bürger-Vorlagen in der Du-Form (seit 14.10.2026; die alten Sie-Vorlagen maengel_eingang/maengel_status werden nicht mehr genutzt)
        'buergerEingang' => 'maengel_eingang_du', 'buergerStatus' => 'maengel_status_du'];
    public string $vorlagenSprache = 'de';
    /**
     * Vorlagen außerhalb des 24-Std.-Fensters kosten bei Meta Geld (Zahlungsmethode nötig). false = kostenlos (seit 17.10.2026):
     * keine Vorlagen; Mitarbeitern wird vorgemerkt (kommt, sobald sie wieder schreiben) und die Bauhofleitung
     * bekommt „bitte anrufen“, Meldende bekommen die Info per E-Mail.
     */
    public bool $vorlagenNutzen = false;

    /** Verlauf und Fotos des Bauhofs so viele Tage nach Abschluss einer Meldung löschen */
    public int $bauhofAufbewahrungTage = 365;

    /**
     * Meldungen ohne Konto (Gäste): erscheinen unter diesem Systemkonto als Entwurf und erst nach Freigabe
     * durch den Bauhof. Ein- und ausschalten: yii bauhof/gastmeldungen an|aus (Einstellung „gastMeldungen“).
     */
    public string $gastKonto = 'gastmeldung';
    public int $gastMeldungenProTag = 5; // je IP-Adresse
    public int $gastFotos = 3;
    public int $gastFotoMaxMb = 15;
    /** Öffentliche WhatsApp-Nummer (ohne +) für „START <Nr>“ – dieselbe wie die Termin-Nummer auf dem Flyer */
    public string $whatsappNummer = '4915563025017';

    /** Höchstens so viele Bestätigungsmails pro Tag (für alle zusammen, gegen Missbrauch) */
    public int $bestaetigungsMailsProTag = 50;

    /** So lange gilt der Bestätigungslink */
    public int $bestaetigungStunden = 48;

    /** Antwortadresse der Eingangsbestätigung und für Widerruf/Löschung */
    public string $kontaktMail = 'smartcity@buergerliste-uffenheim.de';

    /** Kontaktdaten der Meldenden so viele Tage nach Abschluss der Meldung löschen */
    public int $kontaktAufbewahrungTage = 183;

    /** Höchstfrist für Kontaktdaten und Nachrichten auch bei nie abgeschlossenen Meldungen (ab Meldung) */
    public int $kontaktHoechstTage = 730;

    /** Gastmeldungen, die der Bauhof so lange nicht geprüft hat, werden samt Kontaktdaten und Fotos gelöscht */
    public int $gastEntwurfTage = 60;

    public function getNotifications()
    {
        return [notifications\BauhofMeldung::class, notifications\GastmeldungNeu::class, notifications\BauhofNichtZugestellt::class];
    }

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentContainerName($container)
    {
        return Yii::t('MaengelmelderModule.base', 'Mängelmelder-Karte');
    }

    public function getContentContainerDescription($container)
    {
        return Yii::t('MaengelmelderModule.base', 'Mängel mit Ort auf der Karte melden und alle Meldungen auf einer Karte anzeigen.');
    }

    /** Angezeigte (übersetzte) Bezeichnung einer Kategorie; der gespeicherte Wert bleibt deutsch */
    public function kategorieLabel(string $kategorie): string
    {
        return Yii::t('MaengelmelderModule.base', $kategorie);
    }

    /**
     * Mängel in der Übersicht (Dashboard): nur Bauhof (Gruppe $bauhofGruppe) und System-Admins dürfen sie dort sehen
     * und die Einstellung „In Übersicht anzeigen“ umschalten; für alle anderen ist sie fest aus (Events::onMitgliedschaftSpeichern).
     */
    public function maengelInUebersicht(?\humhub\modules\user\models\User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->isSystemAdmin()) {
            return true;
        }
        $gruppe = \humhub\modules\user\models\Group::findOne(['name' => $this->bauhofGruppe]);
        return $gruppe !== null && $gruppe->isMember($user);
    }

    /** Globales Recht „Mängelstatus bearbeiten“ (an Gruppen vergeben) */
    public function getPermissions($contentContainer = null)
    {
        if ($contentContainer === null) {
            return [new permissions\ManageStatus()];
        }
        return $contentContainer instanceof \humhub\modules\space\models\Space ? [new permissions\MangelMelden()] : [];
    }

    /** Meldende können sich über Statusänderungen per E-Mail informieren lassen: yii bauhof/buergerinfo an|aus */
    public function buergerInfoAktiv(): bool
    {
        return (bool)$this->settings->get('buergerInfo');
    }

    /** … zusätzlich per WhatsApp (braucht genehmigte Vorlagen): yii bauhof/buergerinfo-whatsapp an|aus|auto */
    public function buergerWhatsappAktiv(): bool
    {
        return $this->buergerInfoAktiv() && (bool)$this->settings->get('buergerInfoWhatsapp');
    }

    /**
     * Über welchen Kanal der Bauhof seine Mitarbeiter erreicht: 'whatsapp' (Standard) oder 'telegram'.
     * Beim Umschalten auf Telegram bekommen nur verbundene Mitarbeiter Telegram, die übrigen weiter WhatsApp
     * (sanfter Übergang). Siehe Mitarbeiter::bote().
     */
    public function bauhofKanal(): string
    {
        return $this->settings->get('bauhofKanal') === 'telegram' ? 'telegram' : 'whatsapp';
    }

    /**
     * Telegram-Zugang als Docker-Secret (secrets/telegram.json auf dem Host): token, webhook_secret.
     * Den Token liefert @BotFather beim Anlegen des Bots.
     */
    public function getTelegramZugang(): array
    {
        $json = json_decode((string)@file_get_contents('/run/secrets/telegram'), true);
        return is_array($json) ? array_map(fn($v) => trim((string)$v), $json) : [];
    }

    public function telegramZugang(string $key): ?string
    {
        $value = $this->getTelegramZugang()[$key] ?? '';
        return $value !== '' ? $value : null;
    }

    /** Telegram einsatzbereit (Token hinterlegt)? */
    public function telegramAktiv(): bool
    {
        return $this->telegramZugang('token') !== null;
    }

    /** Admin-Konfiguration (Administration → Module → Mängelmelder → Konfigurieren) */
    public function getConfigUrl()
    {
        return \yii\helpers\Url::to(['/maengelmelder/config/index']);
    }

    /** Gastmeldungen eingeschaltet und Systemkonto vorhanden? */
    public function gastMeldungenAktiv(): bool
    {
        return (bool)$this->settings->get('gastMeldungen') && $this->getGastKonto() !== null;
    }

    public function getGastKonto(): ?\humhub\modules\user\models\User
    {
        return \humhub\modules\user\models\User::findOne(['username' => $this->gastKonto, 'status' => \humhub\modules\user\models\User::STATUS_ENABLED]);
    }

    /** Liegt der Punkt im Stadtgebiet von Uffenheim (Gemeindegrenze mit allen Ortsteilen)? */
    public function inBounds(float $lat, float $lng): bool
    {
        if ($lat < $this->bounds['south'] || $lat > $this->bounds['north']
            || $lng < $this->bounds['west'] || $lng > $this->bounds['east']) {
            return false;
        }
        $ring = $this->getStadtgebiet();
        $innen = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$aLat, $aLng] = $ring[$i];
            [$bLat, $bLng] = $ring[$j];
            if (($aLat > $lat) !== ($bLat > $lat) && $lng < ($bLng - $aLng) * ($lat - $aLat) / ($bLat - $aLat) + $aLng) {
                $innen = !$innen;
            }
        }
        return $innen;
    }

    /** Gemeindegrenze als Liste [lat, lng] (OpenStreetMap, ODbL) */
    public function getStadtgebiet(): array
    {
        return $this->stadtgebiet ??= json_decode(file_get_contents(__DIR__ . '/data/stadtgebiet.json'), true)['ring'];
    }
}
