<?php

namespace humhub\modules\whatsapp;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use Yii;

/**
 * Veranstaltungen aus der WhatsApp-Community: Wer einen Beitrag an unsere Termin-Nummer (WhatsApp Cloud API von Meta)
 * weiterleitet, löst einen Webhook aus (/whatsapp/webhook). Ein Hintergrundjob lädt Bilder/PDFs, Claude liest daraus
 * Termine aus, die als Entwürfe im Space „Veranstaltungen“ (Menü „WhatsApp-Eingang“) auf Freigabe durch Admins warten.
 */
class Module extends ContentContainerModule
{
    /** Space mit dem gemeinsamen Kalender (dort Menü „WhatsApp-Eingang“) */
    public string $calendarSpace = 'Veranstaltungen';

    /** Version der Graph-API von Meta */
    public string $graphVersion = 'v23.0';

    /** Wartezeit, bis zusammen weitergeleitete Nachrichten (Bild + Text) als eine Einreichung gelten */
    public int $sammelSekunden = 60;

    /** Höchstens so viele Auswertungen durch Claude pro Tag (Schutz des Guthabens bei Spam) */
    public int $tagesLimit = 40;

    /** Rohnachrichten, Dateien und Telefonnummern werden so viele Tage nach der Bearbeitung gelöscht */
    public int $aufbewahrungTage = 30;

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentContainerName($container)
    {
        return 'WhatsApp-Eingang';
    }

    public function getContentContainerDescription($container)
    {
        return 'Von der WhatsApp-Community eingereichte Veranstaltungen prüfen und in den Kalender übernehmen.';
    }

    public function getNotifications()
    {
        return [notifications\NeueEntwuerfe::class];
    }

    public function getCalendarSpace(): ?Space
    {
        return Space::findOne(['name' => $this->calendarSpace]);
    }

    /**
     * Zugangsdaten als Docker-Secret (secrets/whatsapp.json auf dem Host):
     * token, app_secret, phone_number_id, waba_id, verify_token
     */
    public function getZugang(): array
    {
        $json = json_decode((string)@file_get_contents('/run/secrets/whatsapp'), true);
        return is_array($json) ? array_map(fn($v) => trim((string)$v), $json) : [];
    }

    public function zugang(string $key): ?string
    {
        $value = $this->getZugang()[$key] ?? '';
        return $value !== '' ? $value : null;
    }

    /** Darf der aktuelle Nutzer den Eingang sehen und Termine freigeben? */
    public static function canReview(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        if (Yii::$app->user->isAdmin()) {
            return true;
        }
        $space = Yii::$app->getModule('whatsapp')->getCalendarSpace();
        return $space !== null && $space->isAdmin(Yii::$app->user->id);
    }

    /** Zähler der Auswertungen je Tag: [Anzahl, Eingabe-Tokens, Ausgabe-Tokens] */
    public function getUsage(?string $day = null): array
    {
        $usage = json_decode((string)$this->settings->get('usage-' . ($day ?? date('Y-m-d'))), true);
        return is_array($usage) ? $usage + [0, 0, 0] : [0, 0, 0];
    }

    public function addUsage(int $in, int $out): void
    {
        [$n, $i, $o] = $this->getUsage();
        $this->settings->set('usage-' . date('Y-m-d'), json_encode([$n + 1, $i + $in, $o + $out]));
    }
}
