<?php

namespace humhub\modules\kurse\models;

use humhub\components\ActiveRecord;
use yii\helpers\Url;

/**
 * Freischaltung eines Vereins für das Kursmodul, dazu optional eine eigene Kursseite (/kurse/<slug>).
 *
 * @property int $verein_id
 * @property string $created_at
 * @property int|null $created_by
 * @property string|null $slug Adresse der eigenen Kursseite
 * @property bool $seite_aktiv false = nur Vorschau für Admins/Kursverwalter
 * @property string|null $farbe Hauptfarbe der Seite (#rrggbb)
 */
class KursVerein extends ActiveRecord
{
    /** Wörter, die als Adresse nicht gehen (Routen des Moduls) */
    public const RESERVIERT = ['kurs', 'anmeldung', 'seite', 'index'];

    public static function tableName()
    {
        return 'kurs_verein';
    }

    /** Cache je Anfrage: Mails und Listen fragen oft nach demselben Verein */
    private static array $cache = [];

    public static function fuer(int $vereinId): ?self
    {
        if (!array_key_exists($vereinId, self::$cache)) {
            self::$cache[$vereinId] = self::findOne($vereinId);
        }
        return self::$cache[$vereinId];
    }

    public static function cacheLeeren(): void
    {
        self::$cache = [];
    }

    public static function gueltigerSlug(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$/', $slug) && !in_array($slug, self::RESERVIERT, true);
    }

    /** Geheimer Schlüssel für den Vorschau-Link (solange die Seite nicht freigegeben ist) */
    public function vorschauSchluessel(): string
    {
        // eigenes Geheimnis in den Moduleinstellungen (in Web und Konsole gleich)
        $settings = \Yii::$app->getModule('kurse')->settings;
        $geheim = (string)$settings->get('vorschauGeheim');
        if ($geheim === '') {
            $geheim = bin2hex(random_bytes(24));
            $settings->set('vorschauGeheim', $geheim);
        }
        return substr(hash_hmac('sha256', 'kurse-vorschau|' . $this->verein_id . '|' . $this->slug, $geheim), 0, 24);
    }

    public function hatSeite(): bool
    {
        return $this->slug !== null && $this->slug !== '';
    }

    /** Hauptfarbe als #rrggbb (Vorgabe: Blau der Plattform) */
    public function getFarbe(): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string)$this->farbe) ? strtolower($this->farbe) : '#446e88';
    }

    /**
     * Adresse auf der eigenen Seite.
     * @param string $was '' (Startseite), kurs, anmelden, datenschutz (je mit id), bestaetigen, status (je mit t)
     */
    public function seiteUrl(string $was = '', array $params = [], bool $scheme = false): string
    {
        return Url::to(array_merge(['/kurse/seite/' . ($was === '' ? 'index' : $was), 'slug' => $this->slug], $params), $scheme);
    }
}
