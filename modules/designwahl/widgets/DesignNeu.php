<?php

namespace humhub\modules\designwahl\widgets;

use humhub\components\View;
use humhub\components\Widget;
use humhub\modules\designwahl\assets\DesignAsset;
use humhub\modules\designwahl\Module;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\modules\space\models\Space;
use Yii;

/**
 * Lädt das neue Design, liefert die Vorlage der Begrüßungskarte für die Startseite (neu.js setzt sie ein)
 * und das Fach „Mehr“ des Hauptmenüs.
 */
class DesignNeu extends Widget
{
    /** Eigene Space-Symbole (resources/spaces/*.svg) je Space-URL */
    private const SPACE_SYMBOLE = [
        'rathaus-uffenheim' => 'rathaus',
        'veranstaltungen' => 'veranstaltungen',
        'mangelmelder' => 'maengelmelder',
        'vereine-and-ehrenamt' => 'vereine',
        'abfall-and-termine' => 'abfall',
    ];

    public function run()
    {
        try {
            $bundle = DesignAsset::register($this->view);
            $this->spaceSymbole($bundle->baseUrl);
            // Klasse vor dem ersten Zeichnen setzen, sonst blitzt kurz das alte Design auf
            $this->view->registerJs("document.documentElement.classList.add('design-neu');", View::POS_HEAD, 'design-neu');

            $user = Yii::$app->user->identity;
            $mehr = [];
            foreach (Module::MEHR as $id => [$text, $icon, $spaceUrl, $route, $farbe]) {
                // Mitteilungsblatt bis zur Freigabe nur für Admins
                if ($id === 'mitteilungsblatt' && !Yii::$app->getModule('mitteilungsblatt')?->darfLesen()) {
                    continue;
                }
                if (($url = Module::zielUrl($spaceUrl, $route)) !== null) {
                    $mehr[] = ['text' => $text, 'icon' => (string)Icon::get($icon), 'url' => $url, 'farbe' => $farbe, 'uffi' => false];
                }
            }
            // Was das neue Design aus dem Hauptmenü genommen hat (Kalender, Spaces, Uffi …)
            $farben = ['blau', 'gruen', 'gold', 'tuerkis', 'bordeaux'];
            foreach (Module::$mehrAusMenue as $i => $entry) {
                $icon = $entry->getIcon();
                $url = $entry->getUrl();
                $mehr[] = [
                    'text' => strip_tags($entry->getLabel()),
                    'icon' => is_string($icon) && str_starts_with(trim($icon), '<') ? $icon : (string)Icon::get($icon ?: 'circle'),
                    'url' => $url,
                    'farbe' => $farben[$i % count($farben)],
                    'uffi' => str_contains((string)$url, '/ki-assistent/chat'),
                ];
            }
            return $this->render('designNeu', [
                'vorname' => $user ? ($user->profile->firstname ?: $user->displayName) : null, // null = Gast
                'mehr' => $mehr,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e, 'designwahl');
            return '';
        }
    }

    /**
     * Space-Bild bzw. Kürzel durch das eigene Symbol ersetzen – nur per CSS im neuen Design, die hinterlegten
     * Space-Bilder bleiben unverändert (klassisches Design). object-position schiebt das Originalbild aus dem
     * sichtbaren Bereich, das Symbol liegt als Hintergrund darunter.
     */
    private function spaceSymbole(string $baseUrl): void
    {
        $css = '';
        $spaces = Space::find()->select(['id', 'url'])->where(['url' => array_keys(self::SPACE_SYMBOLE)])->all();
        foreach ($spaces as $space) {
            $svg = $baseUrl . '/spaces/' . self::SPACE_SYMBOLE[$space->url] . '.svg';
            $css .= "html.design-neu img.space-profile-image-{$space->id}{display:inline-block!important;object-position:-9999px 0;"
                . "background:url(\"{$svg}\") center/cover no-repeat;border-radius:50%!important}"
                . "html.design-neu .space-profile-acronym-{$space->id}{display:none!important}";
        }
        if ($css !== '') {
            $this->view->registerCss($css, [], 'designwahl-spaces');
        }
    }
}
