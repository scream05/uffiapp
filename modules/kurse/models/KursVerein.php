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
 * @property string|null $impressum_url Impressum des Vereins für die eigene Seite (leer = Impressum der Plattform)
 * @property string|null $mitglied_url Aufnahmeantrag / „Mitglied werden“-Seite des Vereins (leer = kein Hinweis)
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

    public function rules()
    {
        return [
            [['mitglied_url', 'impressum_url'], 'trim'],
            [['mitglied_url', 'impressum_url'], 'default', 'value' => null],
            ['mitglied_url', 'string', 'max' => 500],
            ['impressum_url', 'string', 'max' => 255],
            [['mitglied_url', 'impressum_url'], 'url', 'validSchemes' => ['https'],
                'message' => \Yii::t('KurseModule.base', 'Bitte eine vollständige Adresse mit https:// angeben.')],
        ];
    }

    public function attributeLabels()
    {
        return [
            'mitglied_url' => \Yii::t('KurseModule.base', 'Link zum Aufnahmeantrag (Mitglied werden)'),
            'impressum_url' => \Yii::t('KurseModule.base', 'Link zum Impressum des Vereins'),
        ];
    }

    /** Link zum Aufnahmeantrag eines Vereins oder null */
    public static function mitgliedUrl(int $vereinId): ?string
    {
        $url = self::fuer($vereinId)?->mitglied_url;
        return $url !== null && $url !== '' ? $url : null;
    }

    /** Linktext „Aufnahmeantrag“ bzw. „Aufnahmeantrag (PDF)“ */
    public static function mitgliedLinkText(string $url): string
    {
        $pdf = str_ends_with(strtolower((string)parse_url($url, PHP_URL_PATH)), '.pdf');
        return $pdf ? \Yii::t('KurseModule.base', 'Aufnahmeantrag (PDF)') : \Yii::t('KurseModule.base', 'Aufnahmeantrag');
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
