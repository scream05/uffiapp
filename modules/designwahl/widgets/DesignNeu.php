<?php

namespace humhub\modules\designwahl\widgets;

use humhub\components\View;
use humhub\components\Widget;
use humhub\modules\designwahl\assets\DesignAsset;
use humhub\modules\designwahl\Module;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

/**
 * Lädt das neue Design, liefert die Vorlage der Begrüßungskarte für die Startseite (neu.js setzt sie ein)
 * und das Fach „Mehr“ des Hauptmenüs.
 */
class DesignNeu extends Widget
{
    /** Eigene Space-Symbole (resources/spaces/*.svg) je Space-URL */
    public const SPACE_SYMBOLE = [
        'rathaus-uffenheim' => 'rathaus',
        'veranstaltungen' => 'veranstaltungen',
        'mangelmelder' => 'maengelmelder',
        'vereine-and-ehrenamt' => 'vereine',
        'abfall-and-termine' => 'abfall',
        'gewerbe' => 'gewerbe',
        'vorschlaege' => 'vorschlaege',
    ];

    /** Import-Konten, deren Profilbild im neuen Design das Symbol ihres Spaces zeigt (Benutzername => Symbol) */
    private const KONTO_SYMBOLE = [
        'mitteilungsblatt' => 'vereine',
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
            foreach (Module::MEHR as $id => [, $icon, $spaceUrl, $route, $farbe]) {
                // Mitteilungsblatt bis zur Freigabe nur für Admins
                if ($id === 'mitteilungsblatt' && !Yii::$app->getModule('mitteilungsblatt')?->darfLesen()) {
                    continue;
                }
                if (($url = Module::zielUrl($spaceUrl, $route)) !== null) {
                    $mehr[] = ['text' => Module::zielText($id), 'icon' => (string)Icon::get($icon), 'url' => $url, 'farbe' => $farbe, 'uffi' => false];
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
            $this->view->registerJs('window.dnText = ' . json_encode(self::texte(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';',
                View::POS_HEAD, 'design-neu-texte');
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
     * Texte für neu.js (deutsch geschrieben: Schlüssel = deutscher Text). Bei Deutsch leer – neu.js nimmt dann
     * seine eigenen Texte und deutschen Tages-/Monatsnamen. Sonst übersetzte Texte und die Datumsangaben für heute
     * und morgen (ICU über den Formatter, in der Sprache der Seite).
     */
    public static function texte(): array
    {
        if (str_starts_with(Yii::$app->language, 'de')) {
            return [];
        }
        $zone = new \DateTimeZone(Yii::$app->formatter->timeZone ?: 'Europe/Berlin');
        $heute = new \DateTimeImmutable('today', $zone);
        $muster = 'EEEE, d MMMM';
        if (class_exists(\IntlDatePatternGenerator::class)) {
            $muster = (new \IntlDatePatternGenerator(Yii::$app->language))->getBestPattern('EEEEdMMMM') ?: $muster;
        }
        $datum = fn(\DateTimeImmutable $d) => Yii::$app->formatter->asDate($d->getTimestamp(), $muster);
        return [
            'sprache' => Yii::$app->language,
            'tag' => $heute->format('Y-m-d'),
            'heute' => $datum($heute),
            'morgen' => $datum($heute->modify('+1 day')),
            'dezimal' => Yii::$app->formatter->decimalSeparator ?: '.',
            't' => [
                'Gute Nacht,' => Yii::t('DesignwahlModule.base', 'Gute Nacht,'),
                'Guten Morgen,' => Yii::t('DesignwahlModule.base', 'Guten Morgen,'),
                'Hallo,' => Yii::t('DesignwahlModule.base', 'Hallo,'),
                'Guten Abend,' => Yii::t('DesignwahlModule.base', 'Guten Abend,'),
                'Vorhersage für heute und morgen' => Yii::t('DesignwahlModule.base', 'Vorhersage für heute und morgen'),
                'Wetter in Uffenheim: {text}, {grad} Grad. Vorhersage öffnen' => Yii::t('DesignwahlModule.base', 'Wetter in Uffenheim: {text}, {grad} Grad. Vorhersage öffnen'),
                'Regen bis {prozent} %' => Yii::t('DesignwahlModule.base', 'Regen bis {prozent} %'),
                'Regen kaum' => Yii::t('DesignwahlModule.base', 'Regen kaum'),
                '{menge} l/m²' => Yii::t('DesignwahlModule.base', '{menge} l/m²'),
                'Wind bis {wind} km/h' => Yii::t('DesignwahlModule.base', 'Wind bis {wind} km/h'),
                'Böen {boeen} km/h' => Yii::t('DesignwahlModule.base', 'Böen {boeen} km/h'),
                '{von} – {bis} Uhr' => Yii::t('DesignwahlModule.base', '{von} – {bis} Uhr'),
                'Gemessen in {station}' => Yii::t('DesignwahlModule.base', 'Gemessen in {station}'),
                'Gemessen in {station} um {zeit} Uhr' => Yii::t('DesignwahlModule.base', 'Gemessen in {station} um {zeit} Uhr'),
                '{von} bis {bis} Uhr' => Yii::t('DesignwahlModule.base', '{von} bis {bis} Uhr'),
                '{von} Uhr' => Yii::t('DesignwahlModule.base', '{von} Uhr'),
                'Keine Wetterwarnungen für Uffenheim' => Yii::t('DesignwahlModule.base', 'Keine Wetterwarnungen für Uffenheim'),
                'Heute' => Yii::t('DesignwahlModule.base', 'Heute'),
                'Stündliche Vorhersage' => Yii::t('DesignwahlModule.base', 'Stündliche Vorhersage'),
                'Morgen, {datum}' => Yii::t('DesignwahlModule.base', 'Morgen, {datum}'),
                'Vorhersage und Warnungen: Deutscher Wetterdienst (über Bright Sky), Stand {zeit} Uhr. Prozentwerte: Regenwahrscheinlichkeit.' => Yii::t('DesignwahlModule.base', 'Vorhersage und Warnungen: Deutscher Wetterdienst (über Bright Sky), Stand {zeit} Uhr. Prozentwerte: Regenwahrscheinlichkeit.'),
                'Vorhersage wird geladen …' => Yii::t('DesignwahlModule.base', 'Vorhersage wird geladen …'),
                'Die Vorhersage ist gerade nicht erreichbar. Bitte später noch einmal versuchen.' => Yii::t('DesignwahlModule.base', 'Die Vorhersage ist gerade nicht erreichbar. Bitte später noch einmal versuchen.'),
                'Hinweis aufklappen' => Yii::t('DesignwahlModule.base', 'Hinweis aufklappen'),
                'Hinweis einklappen' => Yii::t('DesignwahlModule.base', 'Hinweis einklappen'),
                'Notdienste, Öffnungszeiten, Spritpreise und Termine' => Yii::t('DesignwahlModule.base', 'Notdienste, Öffnungszeiten, Spritpreise und Termine'),
            ],
        ];
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
        $konten = User::find()->select(['contentcontainer_id', 'username'])->where(['username' => array_keys(self::KONTO_SYMBOLE)])->all();
        foreach ($konten as $konto) {
            $svg = $baseUrl . '/spaces/' . self::KONTO_SYMBOLE[$konto->username] . '.svg';
            $css .= "html.design-neu img[data-contentcontainer-id=\"{$konto->contentcontainer_id}\"]:not([class*=space-profile-image]){object-position:-9999px 0;"
                . "background:url(\"{$svg}\") center/cover no-repeat;border-radius:50%!important}";
        }
        if ($css !== '') {
            $this->view->registerCss($css, [], 'designwahl-spaces');
        }
    }
}
