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
     * Ziele im neuen Design: [Text, Symbol, Space-URL oder null, Route, Farbe].
     * „leiste“ steht in der Leiste (Handy unten, Computer oben), „mehr“ im Fach hinter „Mehr“.
     */
    public const LEISTE = [
        'termine' => ['Termine', 'calendar', 'veranstaltungen', '/calendar/view/index', 'blau'],
        'abfuhr' => ['Abfuhr', 'trash', 'abfall-and-termine', '/calendar/view/index', 'gruen'],
        'mangel' => ['Mangel melden', 'exclamation-triangle', 'mangelmelder', '/maengelmelder/meldung/create', 'gold'],
    ];
    public const MEHR = [
        'vereine' => ['Vereine', 'users', 'vereine-and-ehrenamt', '/vereine/verein/index', 'tuerkis'],
        'rathaus' => ['Rathaus', 'university', 'rathaus-uffenheim', '/space/space/home', 'bordeaux'],
        'notdienste' => ['Notdienste', 'medkit', null, '/notdienst/index', 'rot'],
        'mitteilungsblatt' => ['Mitteilungsblatt', 'newspaper-o', null, '/mitteilungsblatt/index', 'blau'],
    ];

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
