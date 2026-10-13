<?php

namespace humhub\modules\designwahl;

use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\helpers\Url;

class Events
{
    /** Profilmenü (oben rechts): Umschalter auf das jeweils andere Design */
    public static function onAccountTopMenuInit($event)
    {
        try {
            if (Yii::$app->user->isGuest) {
                return;
            }
            /** @var Module $module */
            $module = Yii::$app->getModule('designwahl');
            $other = $module->getDesign() === 'neu' ? 'klassisch' : 'neu';
            $event->sender->addEntry(new MenuLink([
                'label' => 'Design: ' . Module::DESIGNS[$other],
                'url' => Url::to(['/designwahl/wechsel', 'design' => $other]),
                'icon' => $other === 'neu' ? 'magic' : 'undo',
                'sortOrder' => 650,
                'htmlOptions' => ['data-method' => 'post', 'data-pjax-prevent' => 1],
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Ersatz-Widgets: Space-Kopf ohne Beitragszähler und ohne „Einladen“, Mitgliederseite ohne „Neue Leute einladen“ */
    public static function onBeforeRequest($event)
    {
        Yii::$container->set(\humhub\modules\space\widgets\HeaderCounterSet::class, widgets\SpaceZaehler::class);
        Yii::$container->set(\humhub\modules\space\widgets\InviteButton::class, widgets\OhneEinladen::class);
        Yii::$container->set(\humhub\modules\user\widgets\PeopleHeadingButtons::class, widgets\PersonenKnoepfe::class);
    }

    /** Fußzeile (beide Designs, auch Anmeldeseite): Link zum öffentlichen Quellcode (AGPL-3.0 § 13), nach den Rechtstexten */
    public static function onFooterMenuInit($event)
    {
        try {
            $event->sender->addEntry(new MenuLink([
                'label' => 'Quellcode',
                'url' => 'https://github.com/scream05/uffiapp',
                'sortOrder' => 900,
                'htmlOptions' => ['target' => '_blank', 'rel' => 'noopener'],
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /**
     * Neues Design: Hauptmenü (Handy: Leiste unten) = Übersicht, Termine, Abfuhr, Mangel melden, Mehr.
     * Alle übrigen Punkte (Kalender, Spaces, Uffi …) wandern ins Fach „Mehr“ (widgets/DesignNeu).
     */
    public static function onTopMenuRun($event)
    {
        try {
            /** @var Module $module */
            $module = Yii::$app->getModule('designwahl');
            if ($module->getDesign() !== 'neu') {
                return;
            }
            /** @var \humhub\widgets\TopMenu $menu */
            $menu = $event->sender;
            $mehrAktiv = false;
            Module::$mehrAusMenue = [];
            foreach ($menu->getEntries() as $entry) {
                if ($entry->getId() === 'dashboard') {
                    $entry->setLabel('Übersicht');
                    $entry->setIcon('home');
                    $entry->setSortOrder(100);
                    continue;
                }
                if ($entry instanceof MenuLink) {
                    Module::$mehrAusMenue[] = $entry;
                    $mehrAktiv = $mehrAktiv || $entry->getIsActive();
                }
                $menu->removeEntry($entry);
            }
            $sort = 110;
            foreach (Module::LEISTE as $id => [$text, $icon, $spaceUrl, $route]) {
                if (($url = Module::zielUrl($spaceUrl, $route)) === null) {
                    continue;
                }
                $menu->addEntry(new MenuLink([
                    'id' => 'dn-' . $id,
                    'label' => $text,
                    'url' => $url,
                    'icon' => $icon,
                    'sortOrder' => $sort += 10,
                    'isActive' => $id === 'mangel' ? Module::aktuellIn($spaceUrl) : Module::aktuellIn($spaceUrl, 'calendar'),
                ]));
            }
            foreach (Module::MEHR as [, , $spaceUrl, $route]) {
                $mehrAktiv = $mehrAktiv || Module::aktuellIn($spaceUrl, $spaceUrl === null ? explode('/', $route)[1] : null);
            }
            $menu->addEntry(new MenuLink([
                'id' => 'dn-mehr',
                'label' => 'Mehr',
                'url' => '#',
                'icon' => 'th-large',
                'sortOrder' => 900,
                'isActive' => $mehrAktiv,
                'htmlOptions' => ['data-dn-mehr' => 1, 'data-pjax-prevent' => 1, 'aria-haspopup' => 'dialog', 'aria-controls' => 'dn-mehr'],
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Neues Design: Stylesheet laden und html.design-neu setzen (nur beim vollen Seitenaufbau, bleibt bei PJAX) */
    public static function onLayoutAddons($event)
    {
        try {
            if (Yii::$app->request->isPjax) {
                return;
            }
            /** @var Module $module */
            $module = Yii::$app->getModule('designwahl');
            if ($module->getDesign() === 'neu') {
                $event->sender->addWidget(widgets\DesignNeu::class);
            }
            if (!Yii::$app->user->isGuest) {
                assets\TextfeldAsset::register(Yii::$app->view);
            } else {
                self::abbrechenKnopf();
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /**
     * Anmelde-/Registrierungsseite (eigenes Layout ohne Menü): Knopf „Abbrechen“ oben,
     * führt zur Seite zurück, von der man kam (sonst Startseite). Wichtig auf dem Handy ohne Zurück-Taste.
     */
    private static function abbrechenKnopf()
    {
        // Nur auf der ganzen Anmelde-/Registrierungsseite, nicht in nachgeladenen Teilen (Stream, Modals)
        $controller = Yii::$app->controller;
        if (Yii::$app->request->isAjax || $controller === null
            || !in_array($controller->uniqueId, ['user/auth', 'user/registration'], true)) {
            return;
        }
        $view = Yii::$app->view;
        $view->registerCss(<<<CSS
.dn-abbrechen { display: inline-flex; align-items: center; gap: 6px; margin: 0 0 14px; padding: 9px 16px;
    border-radius: 999px; background: rgba(255,255,255,.92); color: #1f2a37; font-weight: 600; text-decoration: none; }
.dn-abbrechen:hover, .dn-abbrechen:focus { background: #fff; color: #1f2a37; text-decoration: none; }
.dn-abbrechen-zeile { max-width: 380px; margin: 0 auto; text-align: left; }
CSS);
        $view->registerJs(<<<'JS'
(function () {
    if (!document.body.classList.contains('login-container') || document.querySelector('.dn-abbrechen')) return;
    var schluessel = 'dnAbbrechenZiel', ziel = null;
    try {
        var r = document.referrer ? new URL(document.referrer) : null;
        if (r && r.origin === location.origin && r.pathname.indexOf('/user/') !== 0) {
            ziel = r.pathname + r.search + r.hash;
            sessionStorage.setItem(schluessel, ziel);
        } else {
            ziel = sessionStorage.getItem(schluessel);
        }
    } catch (e) {}
    var form = document.getElementById('login-form') || document.getElementById('create-account-form')
        || document.querySelector('.login-container .panel');
    if (!form) return;
    var zeile = document.createElement('div');
    zeile.className = 'dn-abbrechen-zeile';
    zeile.innerHTML = '<a class="dn-abbrechen" href="#" data-pjax-prevent><i class="fa fa-chevron-left"></i> Abbrechen</a>';
    zeile.firstChild.href = ziel || '/';
    form.parentNode.insertBefore(zeile, form);
})();
JS, \yii\web\View::POS_END);
    }

    /** Kommentare: einfaches Textfeld statt Richtext-Editor (beide Designs) */
    public static function onCommentFormCreate($event)
    {
        if (($event->config['class'] ?? null) === \humhub\modules\comment\widgets\Form::class) {
            $event->config['class'] = widgets\KommentarForm::class;
        }
    }

    /** Eigene Views (einfache Textfelder, keine Anhänge): Original-Ordner => Ordner in designwahl */
    const ANSICHTEN = [
        '@humhub/modules/comment/views/comment' => '@designwahl/views/comment',
        '@humhub/modules/post/views/post' => '@designwahl/views/post/post',
        '@humhub/modules/post/widgets/views' => '@designwahl/views/post/widgets',
        '@humhub/modules/content/widgets/views' => '@designwahl/views/content/widgets',
        '@mail/views/mail' => '@designwahl/views/mail',
        '@questions/views/question' => '@designwahl/views/questions/question',
        '@questions/widgets/views' => '@designwahl/views/questions/widgets',
    ];

    /** Aktionen, deren Formulardaten nur Text sein dürfen (Controller-ID => Aktionen) */
    const NUR_TEXT = [
        'comment/comment' => ['post', 'edit'],
        'mail/mail' => ['reply', 'create', 'edit-entry'],
        'post/post' => ['post', 'edit'],
        'questions/question' => ['create', 'edit'],
        'questions/answer' => ['edit'],
    ];

    /**
     * Kommentare, Nachrichten, Beiträge und Fragen nur als Text (beide Designs): eigene Views mit
     * einfachem Textfeld ohne Anhänge einbinden und beim Speichern nur Text annehmen
     */
    public static function onControllerBeforeAction($event)
    {
        try {
            self::gastSprache();
            $theme = Yii::$app->view->theme;
            if ($theme !== null) {
                $theme->applyTo(''); // füllt die Standard-pathMap des Themes, bevor wir ergänzen
                foreach (self::ANSICHTEN as $original => $eigene) {
                    if (Yii::getAlias($original, false) !== false) {
                        $theme->pathMap[$original] = [$eigene];
                    }
                }
            }
            $aktionen = self::NUR_TEXT[$event->action->controller->uniqueId] ?? [];
            if (in_array($event->action->id, $aktionen, true)) {
                self::nurText();
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /**
     * Gäste: Standardsprache (Deutsch) statt Browsersprache – HumHub nimmt sonst die Browsersprache und
     * ohne Angabe die erste erlaubte (Englisch). Eine selbst gewählte Sprache (Auswahl auf der Anmeldeseite,
     * Cookie „language“) bleibt.
     */
    private static function gastSprache()
    {
        $request = Yii::$app->request;
        if (!Yii::$app->user->isGuest || !$request instanceof \yii\web\Request
            || isset($request->cookies['language']) || $request->post('ChooseLanguage') !== null) {
            return;
        }
        Yii::$app->i18n->setDefaultLocale();
    }

    /**
     * Formulardaten auf reinen Text beschränken: Dateilisten (fileList, files) entfernen und
     * Markdown-Bilder ![…](…) zu normalen Links machen – außer Verweisen auf vorhandene HumHub-Dateien
     * (file-guid:), damit z. B. importierte Beiträge beim Bearbeiten ihr Bild behalten
     */
    private static function nurText()
    {
        $request = Yii::$app->request;
        if (!$request->isPost) {
            return;
        }
        $bereinigen = function ($daten) use (&$bereinigen) {
            foreach ($daten as $name => $wert) {
                if ($name === 'fileList' || $name === 'files') {
                    unset($daten[$name]);
                } elseif (is_array($wert)) {
                    $daten[$name] = $bereinigen($wert);
                } elseif (is_string($wert)) {
                    $daten[$name] = preg_replace('/!(\[[^\]]*\]\()(?!file-guid:)/', '$1', $wert);
                }
            }
            return $daten;
        };
        $request->setBodyParams($bereinigen($request->getBodyParams()));
    }

    /**
     * Kalender: Kategorien in der Legende unter dem Kalender anklickbar – setzt den Kategorie-Filter
     * der Filterleiste (nur eingeloggt, Gäste haben keine Filterleiste). Erneuter Klick hebt ihn auf.
     * Gilt in beiden Designs.
     */
    public static function onCalendarTypeLegend($event)
    {
        try {
            /** @var \humhub\modules\calendar\widgets\CalendarTypeLegend $legend */
            $legend = $event->sender;
            if (Yii::$app->user->isGuest || $event->result === '') {
                return;
            }
            $event->result = $legend->view->render('@designwahl/widgets/views/kalenderLegende', [
                'types' => $legend->getCalendarTypes(),
            ]);
        } catch (\Throwable $e) {
            Yii::error($e, 'designwahl');
        }
    }

    /** Abfallkalender: Filter „Nur anzeigen“ (Teilnahme/Eigene …) ausblenden – für Abfuhrtermine sinnlos */
    public static function onCalendarFilterBar($event)
    {
        try {
            $container = \humhub\modules\content\helpers\ContentContainerHelper::getCurrent();
            if ($container instanceof \humhub\modules\space\models\Space && $container->url === 'abfall-and-termine') {
                $event->sender->showFilter = false;
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'designwahl');
        }
    }
}
