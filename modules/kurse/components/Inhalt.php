<?php

namespace humhub\modules\kurse\components;

use humhub\modules\kurse\jobs\InhaltUebersetzenJob;
use Yii;

/**
 * Inhalte, die der Verein selbst einträgt (Slogan, Vorstellung, Kursnamen, Rollen im Team …), auf der Kursseite in der
 * gewählten Sprache: aus dem Zwischenspeicher der KI-Übersetzung (ki-assistent, Tabelle ki_uebersetzung, 30 Tage).
 * Fehlt eine Übersetzung, bleibt der deutsche Text stehen und ein Hintergrundauftrag übersetzt alle fehlenden Texte der
 * Seite in einem KI-Aufruf – beim nächsten Laden ist die Seite übersetzt. Namen bleiben unverändert (Regel im Prompt).
 */
class Inhalt
{
    /** @var array<string, string> fehlende Texte dieser Anfrage (hash => Text) */
    private static array $fehlt = [];

    /** Zielsprache ('en', 'uk' …) oder null (Deutsch bzw. Übersetzung nicht verfügbar) */
    public static function ziel(): ?string
    {
        if (!class_exists(\humhub\modules\kiAssistent\components\Uebersetzer::class) || !Yii::$app->hasModule('ki-assistent')) {
            return null;
        }
        return \humhub\modules\kiAssistent\components\Uebersetzer::ziel();
    }

    /** Text in der gewählten Sprache, sonst unverändert (und für den Hintergrundauftrag vormerken) */
    public static function t(?string $text): string
    {
        $text = (string)$text;
        $ziel = self::ziel();
        $norm = mb_substr(trim($text), 0, 12000);
        if ($ziel === null || $norm === '' || !preg_match('/\p{L}/u', $norm)) {
            return $text;
        }
        try {
            $uebersetzt = \humhub\modules\kiAssistent\components\Uebersetzer::cached($norm, $ziel);
        } catch (\Throwable $e) {
            Yii::warning('Kursseite: Übersetzung nicht lesbar: ' . $e->getMessage(), 'kurse');
            return $text;
        }
        if ($uebersetzt !== null) {
            return $uebersetzt;
        }
        self::$fehlt[hash('sha256', $norm)] = $norm;
        return $text;
    }

    /** Fehlen auf dieser Seite noch Übersetzungen? */
    public static function fehlt(): bool
    {
        return self::$fehlt !== [];
    }

    /**
     * Hintergrundauftrag für die fehlenden Texte starten (höchstens einmal je Textmenge und Sprache in 10 Minuten,
     * damit mehrfaches Neuladen keine doppelten KI-Aufrufe auslöst).
     */
    public static function auftragStarten(): void
    {
        $ziel = self::ziel();
        if ($ziel === null || self::$fehlt === []) {
            return;
        }
        $schluessel = 'kurse-uebersetzen-' . $ziel . '-' . md5(implode(',', array_keys(self::$fehlt)));
        if (Yii::$app->cache->get($schluessel)) {
            return;
        }
        Yii::$app->cache->set($schluessel, 1, 600);
        Yii::$app->queue->push(new InhaltUebersetzenJob(['texte' => array_values(self::$fehlt), 'sprache' => $ziel]));
    }
}
