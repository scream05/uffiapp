<?php

namespace humhub\modules\designwahl;

use Yii;

/**
 * Zwei Designs nebeneinander: „klassisch“ (Clean-Theme + design/ios.scss, im Theme-CSS) und „neu“
 * (resources/neu.css, wird nur zusätzlich geladen und greift nur unter html.design-neu).
 * Wahl je Konto (Benutzereinstellung), Gäste und Konten ohne Wahl bekommen den Standard (Moduleinstellung).
 */
class Module extends \humhub\components\Module
{
    public const DESIGNS = ['klassisch' => 'Klassisch', 'neu' => 'Uffi frisch'];

    /**
     * Eigene Module mit deutschen Ausgangstexten: Yii::t('<Modul>Module.base', 'Deutscher Text'),
     * Übersetzungen in <modul>/messages/<sprache>/base.php (en, uk, ar, tr, ru). Registrierung in Events::onBeforeRequest.
     */
    public const DEUTSCHE_MODULE = ['abfuhr', 'designwahl', 'gewerbe', 'ki-assistent', 'maengelmelder', 'mitteilungsblatt',
        'notdienst', 'statistik', 'tanken', 'uebersicht', 'vereine', 'vorschlaege'];

    /**
     * Umschalter „Design: …“ im Profilmenü anzeigen? Seit 15.10.2026 aus (alle nutzen „Uffi frisch“); die Technik
     * (Benutzereinstellung, /designwahl/wechsel, klassisches Design) bleibt – zum Wiedereinschalten auf true setzen.
     */
    public bool $umschalterAnzeigen = false;

    /**
     * Ziele im neuen Design: [Text, Symbol, Space-URL oder null, Route, Farbe].
     * „leiste“ steht in der Leiste (Handy unten, Computer oben), „mehr“ im Fach hinter „Mehr“.
     */
    public const LEISTE = [
        'termine' => ['Termine', 'calendar', 'veranstaltungen', '/calendar/view/index', 'blau'],
        'abfuhr' => ['Abfuhr', 'trash', 'abfall-and-termine', '/calendar/view/index', 'gruen'],
        'mangel' => ['Mangel melden', 'exclamation-triangle', 'mangelmelder', '/maengelmelder/meldung/create', 'gold'],
    ];
    // Spritpreise (nur Startseiten-Kasten + Space Gewerbe) und Ideen & Vorschläge (Link in der Fußzeile) seit 21.10.2026 bewusst nicht hier
    public const MEHR = [
        'vereine' => ['Vereine', 'users', 'vereine-and-ehrenamt', '/vereine/verein/index', 'tuerkis'],
        'gewerbe' => ['Gewerbe', 'shopping-bag', 'gewerbe', '/gewerbe/betrieb/index', 'gruen'],
        'rathaus' => ['Rathaus', 'university', 'rathaus-uffenheim', '/space/space/home', 'bordeaux'],
        'notdienste' => ['Notdienste', 'medkit', null, '/notdienst/index', 'rot'],
        'mitteilungsblatt' => ['Mitteilungsblatt', 'newspaper-o', null, '/mitteilungsblatt/index', 'blau'],
    ];

    /** Angezeigter (übersetzter) Text eines Ziels aus LEISTE/MEHR – die Konstanten bleiben deutsch */
    public static function zielText(string $id): string
    {
        return match ($id) {
            'termine' => Yii::t('DesignwahlModule.base', 'Termine'),
            'abfuhr' => Yii::t('DesignwahlModule.base', 'Abfuhr'),
            'mangel' => Yii::t('DesignwahlModule.base', 'Mangel melden'),
            'vereine' => Yii::t('DesignwahlModule.base', 'Vereine'),
            'gewerbe' => Yii::t('DesignwahlModule.base', 'Gewerbe'),
            'rathaus' => Yii::t('DesignwahlModule.base', 'Rathaus'),
            'notdienste' => Yii::t('DesignwahlModule.base', 'Notdienste'),
            'mitteilungsblatt' => Yii::t('DesignwahlModule.base', 'Mitteilungsblatt'),
            default => self::LEISTE[$id][0] ?? self::MEHR[$id][0] ?? $id,
        };
    }

    /** Angezeigter Name eines Designs („Uffi frisch“ bleibt) */
    public static function designText(string $design): string
    {
        return $design === 'klassisch' ? Yii::t('DesignwahlModule.base', 'Klassisch') : (self::DESIGNS[$design] ?? $design);
    }

    /** Menüpunkte, die das neue Design aus dem Hauptmenü ins Fach „Mehr“ verschiebt (gefüllt von Events::onTopMenuRun) */
    public static array $mehrAusMenue = [];

    /** Standard für Gäste und Konten ohne eigene Wahl: /app/yii designwahl/standard <klassisch|neu> */
    public function getStandard(): string
    {
        $value = $this->settings->get('standard');
        return isset(self::DESIGNS[$value]) ? $value : 'klassisch';
    }

    public function getDesign(): string
    {
        $value = Yii::$app->user->isGuest ? null : $this->settings->user()->get('design');
        return isset(self::DESIGNS[$value]) ? $value : $this->getStandard();
    }

    /** URL eines Ziels oder null, wenn der Space fehlt */
    public static function zielUrl(?string $spaceUrl, string $route): ?string
    {
        if ($spaceUrl === null) {
            return \yii\helpers\Url::to([$route]);
        }
        $space = \humhub\modules\space\models\Space::findOne(['url' => $spaceUrl]);
        return $space ? $space->createUrl($route) : null;
    }

    /** Liegt die aktuelle Seite im Space $spaceUrl (optional nur in Modul $moduleId)? */
    public static function aktuellIn(?string $spaceUrl, ?string $moduleId = null): bool
    {
        $c = Yii::$app->controller;
        if ($moduleId !== null && ($c->module->id ?? null) !== $moduleId) {
            return false;
        }
        if ($spaceUrl === null) {
            return $moduleId !== null;
        }
        $container = $c instanceof \humhub\modules\content\components\ContentContainerController ? $c->contentContainer : null;
        return $container instanceof \humhub\modules\space\models\Space && $container->url === $spaceUrl;
    }

    public function setDesign(string $design): void
    {
        if (isset(self::DESIGNS[$design]) && !Yii::$app->user->isGuest) {
            $this->settings->user()->set('design', $design);
        }
    }
}
