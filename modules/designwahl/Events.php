<?php

namespace humhub\modules\designwahl;

use humhub\modules\content\widgets\PermaLink;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\ui\menu\WidgetMenuEntry;
use Yii;
use yii\helpers\Url;

class Events
{
    /** Profilmenü (oben rechts): Umschalter auf das jeweils andere Design */
    public static function onAccountTopMenuInit($event)
    {
        try {
            /** @var Module $module */
            $module = Yii::$app->getModule('designwahl');
            if (Yii::$app->user->isGuest || !$module->umschalterAnzeigen) {
                return;
            }
            $other = $module->getDesign() === 'neu' ? 'klassisch' : 'neu';
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('DesignwahlModule.base', 'Design: {name}', ['name' => Module::designText($other)]),
                'url' => Url::to(['/designwahl/wechsel', 'design' => $other]),
                'icon' => $other === 'neu' ? 'magic' : 'undo',
                'sortOrder' => 650,
                'htmlOptions' => ['data-method' => 'post', 'data-pjax-prevent' => 1],
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /**
     * Ersatz-Widgets: Space-Kopf ohne Zähler und ohne „Einladen“, Mitgliederkasten und Space-Verzeichnis ohne
     * Mitgliederzahl, Profilkopf ohne Follower/Folgt, Mitgliederseite ohne „Neue Leute einladen“
     */
    public static function onBeforeRequest($event)
    {
        self::uebersetzungenRegistrieren();
        Yii::$container->set(\humhub\modules\space\widgets\HeaderCounterSet::class, widgets\SpaceZaehler::class);
        Yii::$container->set(\humhub\modules\space\widgets\Members::class, widgets\SpaceMitglieder::class);
        Yii::$container->set(\humhub\modules\space\widgets\SpaceDirectoryIcons::class, widgets\SpaceVerzeichnisSymbole::class);
        Yii::$container->set(\humhub\modules\user\widgets\ProfileHeaderCounterSet::class, widgets\ProfilZaehler::class);
        // Kalender-Erinnerungen systemweit aus: kein Link am Termin, und keine neuen Erinnerungen speichern
        // (weder am Termin noch als Standard für Space/Konto/System; Abfuhr-Erinnerung ist ein eigenes Modul)
        if (class_exists(\humhub\modules\calendar\widgets\ReminderLink::class)) {
            Yii::$container->set(\humhub\modules\calendar\widgets\ReminderLink::class, widgets\OhneErinnerung::class);
            \yii\base\Event::on(\humhub\modules\calendar\models\reminder\CalendarReminder::class,
                \yii\db\BaseActiveRecord::EVENT_BEFORE_INSERT, fn($e) => $e->isValid = false);
        }
        Yii::$container->set(\humhub\modules\space\widgets\InviteButton::class, widgets\OhneEinladen::class);
        Yii::$container->set(\humhub\modules\user\widgets\PeopleHeadingButtons::class, widgets\PersonenKnoepfe::class);
    }

    /**
     * HumHub erwartet englische Ausgangstexte; unsere Module sind deutsch geschrieben. Mit Ausgangssprache „de“
     * bleiben deutsche Nutzer beim Original, alle anderen bekommen messages/<sprache>/base.php (en-US → en).
     * Auch für die Konsole (Warteschlange: Benachrichtigungen und Mails in der Sprache des Empfängers).
     */
    public static function uebersetzungenRegistrieren(): void
    {
        foreach (Module::DEUTSCHE_MODULE as $id) {
            if (Yii::$app->hasModule($id)) {
                Yii::$app->i18n->translations[\humhub\libs\I18NHelper::getModuleTranslationCategory($id) . 'base'] = [
                    'class' => \humhub\components\i18n\ModuleMessageSource::class,
                    'moduleId' => $id,
                    'sourceLanguage' => 'de',
                ];
            }
        }
    }

    /** Fußzeile (beide Designs, auch Anmeldeseite): Link zum öffentlichen Quellcode (AGPL-3.0 § 13), nach den Rechtstexten */
    public static function onFooterMenuInit($event)
    {
        try {
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('DesignwahlModule.base', 'Quellcode'),
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
            /** @var \humhub\widgets\TopMenu $menu */
            $menu = $event->sender;
            // Menüpunkt „Spaces“ (Space-Verzeichnis) nur für Admins – in beiden Designs und im Fach „Mehr“
            if (Yii::$app->user->isGuest || !Yii::$app->user->isAdmin()) {
                if (($spaces = $menu->getEntryById('spaces')) !== null) {
                    $menu->removeEntry($spaces);
                }
            }
            /** @var Module $module */
            $module = Yii::$app->getModule('designwahl');
            if ($module->getDesign() !== 'neu') {
                return;
            }
            // „Kalender“ (alle Kalender) doppelt sich mit „Termine“ in der Leiste – im neuen Design nur für Admins
            if (!Yii::$app->user->isAdmin()) {
                foreach ($menu->getEntries() as $entry) {
                    if ($entry instanceof MenuLink && str_contains((string)$entry->getUrl(), '/calendar/global')) {
                        $menu->removeEntry($entry);
                    }
                }
            }
            $mehrAktiv = false;
            Module::$mehrAusMenue = [];
            foreach ($menu->getEntries() as $entry) {
                if ($entry->getId() === 'dashboard') {
                    $entry->setLabel(Yii::t('DesignwahlModule.base', 'Übersicht'));
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
            foreach (Module::LEISTE as $id => [, $icon, $spaceUrl, $route]) {
                if (($url = Module::zielUrl($spaceUrl, $route)) === null) {
                    continue;
                }
                $menu->addEntry(new MenuLink([
                    'id' => 'dn-' . $id,
                    'label' => Module::zielText($id),
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
                'label' => Yii::t('DesignwahlModule.base', 'Mehr'),
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
    /**
     * Gäste: im Menü neben Beiträgen (Drei-Punkte-Knopf) nur „Permalink“ – z. B. keine „Teilnehmer“-Liste
     * bei Terminen und keine Trennlinien.
     */
    public static function onWallEntryControlsRun($event)
    {
        try {
            /** @var \humhub\modules\content\widgets\WallEntryControls $menu */
            $menu = $event->sender;
            if (!Yii::$app->user->isGuest) {
                // Eintrag „Teilnehmer“ bei Terminen nur für alle, die die Teilnehmerliste sehen dürfen
                foreach ($menu->getEntries() as $entry) {
                    if ($entry instanceof WidgetMenuEntry
                        && $entry->widgetClass === 'humhub\\modules\\calendar\\widgets\\ParticipantsLink'
                        && !self::darfTeilnehmerSehen($entry->widgetOptions['entry']->id ?? null)) {
                        $menu->removeEntry($entry);
                    }
                }
                return;
            }
            foreach ($menu->getEntries() as $entry) {
                if (!($entry instanceof WidgetMenuEntry && $entry->widgetClass === PermaLink::class)) {
                    $menu->removeEntry($entry);
                }
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Seitentitel, die HumHub englisch oder ohne Aussage lässt (2.4.2), je Controller/Aktion (übersetzt) */
    private static function titel(string $controller): ?string
    {
        return match ($controller) {
            'calendar/global' => Yii::t('DesignwahlModule.base', 'Kalender'),
            'user/auth' => Yii::t('DesignwahlModule.base', 'Anmelden'),
            'user/registration' => Yii::t('DesignwahlModule.base', 'Registrieren'),
            'user/password-recovery' => Yii::t('DesignwahlModule.base', 'Passwort vergessen'),
            'ki-assistent/chat' => Yii::t('DesignwahlModule.base', 'Uffi – KI-Assistent'),
            default => null,
        };
    }

    private static function rechtstext(?string $pageKey): ?string
    {
        return match ($pageKey) {
            'privacy' => Yii::t('DesignwahlModule.base', 'Datenschutzerklärung'),
            'imprint' => Yii::t('DesignwahlModule.base', 'Impressum'),
            'terms' => Yii::t('DesignwahlModule.base', 'Nutzungsbedingungen'),
            'cookies' => Yii::t('DesignwahlModule.base', 'Cookies'),
            default => null,
        };
    }

    /**
     * Fertige HTML-Seite kurz vor dem Senden: Zoom erlauben (das Layout der Anmeldeseite im Kern und das des
     * 2FA-Moduls sperren ihn mit maximum-scale=1, WCAG 1.4.4) und fehlende deutsche Seitentitel setzen.
     */
    public static function onResponseBeforeSend($event)
    {
        try {
            $response = $event->sender;
            // Vor dem Senden steht die Seite noch in data (content füllt Yii erst danach)
            if (!is_string($response->data) || $response->format !== \yii\web\Response::FORMAT_HTML
                || !str_contains($response->data, '<title>')) {
                return;
            }
            $html = preg_replace('/(<meta name="viewport" content="[^"]*?),\s*maximum-scale=1(\.0)?/', '$1', $response->data, 1);
            $controller = Yii::$app->controller;
            $titel = $controller ? self::titel($controller->uniqueId) : null;
            if ($controller && $controller->uniqueId === 'legal/page') {
                $pageKey = Yii::$app->request->get('pageKey');
                $titel = self::rechtstext(is_string($pageKey) ? $pageKey : null);
            }
            if ($titel !== null) {
                $name = Yii::$app->name;
                $html = preg_replace('#<title>[^<]*</title>#', '<title>' . \yii\helpers\Html::encode($titel . ' - ' . $name) . '</title>', $html, 1);
            }
            $response->data = $html;
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onLayoutAddons($event)
    {
        try {
            if (Yii::$app->request->isPjax) {
                return;
            }
            /** @var Module $module */
            $module = Yii::$app->getModule('designwahl');
            assets\BarrierefreiAsset::register(Yii::$app->view);
            if (!str_starts_with(Yii::$app->language, 'de')) { // barrierefrei.js ist deutsch geschrieben
                Yii::$app->view->registerJs('window.bfText = ' . json_encode(self::barrierefreiTexte(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';',
                    \yii\web\View::POS_HEAD, 'barrierefrei-texte');
            }
            if ($module->getDesign() === 'neu') {
                $event->sender->addWidget(widgets\DesignNeu::class);
            }
            assets\KalenderAsset::register(Yii::$app->view);
            assets\AufwachenAsset::register(Yii::$app->view);
            Yii::$app->view->registerJs('window.dnSitzungSek = ' . (int) Yii::$app->user->authTimeout . ';', \yii\web\View::POS_HEAD, 'dn-sitzung');
            if (Yii::$app->user->isGuest) {
                self::abbrechenKnopf();
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Übersetzte Texte für barrierefrei.js (Schlüssel = deutscher Text) */
    private static function barrierefreiTexte(): array
    {
        return [
            'Zum Inhalt springen' => Yii::t('DesignwahlModule.base', 'Zum Inhalt springen'),
            'Suche' => Yii::t('DesignwahlModule.base', 'Suche'),
            'Suchen' => Yii::t('DesignwahlModule.base', 'Suchen'),
            'Filter' => Yii::t('DesignwahlModule.base', 'Filter'),
            'Schließen' => Yii::t('DesignwahlModule.base', 'Schließen'),
            'Menü' => Yii::t('DesignwahlModule.base', 'Menü'),
            'Weitere Aktionen' => Yii::t('DesignwahlModule.base', 'Weitere Aktionen'),
            'Hinzufügen' => Yii::t('DesignwahlModule.base', 'Hinzufügen'),
            'Bearbeiten' => Yii::t('DesignwahlModule.base', 'Bearbeiten'),
            'Löschen' => Yii::t('DesignwahlModule.base', 'Löschen'),
            'Einstellungen' => Yii::t('DesignwahlModule.base', 'Einstellungen'),
            'Benachrichtigungen' => Yii::t('DesignwahlModule.base', 'Benachrichtigungen'),
            'Nachrichten' => Yii::t('DesignwahlModule.base', 'Nachrichten'),
            'Sortierung' => Yii::t('DesignwahlModule.base', 'Sortierung'),
            'Sprache' => Yii::t('DesignwahlModule.base', 'Sprache'),
            'Auswahl' => Yii::t('DesignwahlModule.base', 'Auswahl'),
            'Tabelle, seitlich scrollbar' => Yii::t('DesignwahlModule.base', 'Tabelle, seitlich scrollbar'),
            'ALTCHA-Website öffnen' => Yii::t('DesignwahlModule.base', 'ALTCHA-Website öffnen'),
        ];
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
        $view->registerJs(strtr(<<<'JS'
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
    zeile.innerHTML = '<a class="dn-abbrechen" href="#" data-pjax-prevent><i class="fa fa-chevron-left"></i> </a>';
    zeile.firstChild.appendChild(document.createTextNode(DN_ABBRECHEN));
    zeile.firstChild.href = ziel || '/';
    form.parentNode.insertBefore(zeile, form);
})();
JS, ['DN_ABBRECHEN' => json_encode(Yii::t('DesignwahlModule.base', 'Abbrechen'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)]), \yii\web\View::POS_END);
    }

    /** Eigene Views: Original-Ordner => Ordner in designwahl */
    const ANSICHTEN = [
        '@calendar/widgets/views' => '@designwahl/views/calendar/widgets', // nur wallEntry.php (Teilnahme-Knöpfe) + participants.php (Liste nur für Bearbeiter), Rest Original
        '@calendar/views/common' => '@designwahl/views/calendar/common', // nur defaultConfig.php (ohne Reiter „Erinnerung“)
        '@humhub/modules/space/widgets/views' => '@designwahl/views/space/widgets', // nur profileHeaderControls.php (Leiste nur für Admins)
    ];

    /** Teilnehmerliste eines Termins: nur wer ihn bearbeiten darf (Ersteller, Space-Admins, Admins) */
    public static function darfTeilnehmerSehen($eintragId): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        $eintrag = \humhub\modules\calendar\models\CalendarEntry::findOne(['id' => (int) $eintragId]);
        return $eintrag !== null && $eintrag->content->canEdit();
    }

    /** Vor jeder Aktion: Termin-Teilnehmerlisten schützen, Gastsprache, eigene Views (ANSICHTEN) einbinden */
    public static function onControllerBeforeAction($event)
    {
        // Teilnehmerlisten von Terminen (Namen der Teilnehmer) nur für alle, die den Termin bearbeiten dürfen
        // (Ersteller, Space-Admins, Admins) – Gäste und Mitglieder sehen nur die Anzahl, auch nicht über die Adresse
        if ($event->action->controller->uniqueId === 'calendar/entry'
            && in_array($event->action->id, ['modal-participants', 'participants-list'], true)
            && !self::darfTeilnehmerSehen(Yii::$app->request->get('id'))) {
            throw new \yii\web\ForbiddenHttpException();
        }
        try {
            self::gastSprache();
            // Seitentitel der Übersicht: HumHub setzt ihn schon in init(), bei Gästen also noch vor der Umstellung auf Deutsch
            if ($event->sender instanceof \humhub\modules\dashboard\controllers\DashboardController) {
                $event->sender->setPageTitle(Yii::t('DesignwahlModule.base', 'Übersicht'));
            }
            $theme = Yii::$app->view->theme;
            if ($theme !== null) {
                $theme->applyTo(''); // füllt die Standard-pathMap des Themes, bevor wir ergänzen
                foreach (self::ANSICHTEN as $original => $eigene) {
                    if (Yii::getAlias($original, false) !== false) {
                        $theme->pathMap[$original] = [$eigene];
                    }
                }
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /**
     * Gäste: „Herunterladen als ICS-Datei“ bei Terminen, die sie sehen dürfen – das Kalendermodul verlangt
     * dafür sonst eine Anmeldung. Gleiche Prüfung und Ausgabe wie ExportController::actionEvent.
     */
    public static function onAppBeforeAction($event)
    {
        // Läuft vor der Zugriffsprüfung des Controllers (die schickt Gäste sonst zur Anmeldung)
        if ($event->action->controller->uniqueId !== 'calendar/export' || $event->action->id !== 'event'
            || !Yii::$app->user->isGuest) {
            return;
        }
        $content = \humhub\modules\content\models\Content::findOne(['id' => Yii::$app->request->get('id')]);
        if (!$content || !$content->getModel() || !$content->canView()
            || !method_exists($content->model, 'generateIcs')) {
            return; // weiter wie bisher (Anmeldeseite)
        }
        $ics = $content->model->generateIcs(true);
        if (empty($ics)) {
            return;
        }
        $uid = method_exists($content->model, 'getUid') && $content->model->getUid() ? $content->model->getUid() : 'termin';
        $event->isValid = false;
        Yii::$app->response->sendContentAsFile(controllers\AboController::ohnePersonen($ics), $uid . '.ics', ['mimeType' => 'text/calendar']);
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
     * Kalender: Kategorien in der Legende unter dem Kalender anklickbar – setzt den Kategorie-Filter
     * der Filterleiste (nur eingeloggt, Gäste haben keine Filterleiste). Erneuter Klick hebt ihn auf.
     * Gilt in beiden Designs.
     */
    public static function onCalendarTypeLegend($event)
    {
        try {
            /** @var \humhub\modules\calendar\widgets\CalendarTypeLegend $legend */
            $legend = $event->sender;
            if (!Yii::$app->user->isGuest && $event->result !== '') {
                $event->result = $legend->view->render('@designwahl/widgets/views/kalenderLegende', [
                    'types' => $legend->getCalendarTypes(),
                ]);
            }
            $event->result .= self::oeffnungszeiten($legend) . self::kalenderAbo($legend);
        } catch (\Throwable $e) {
            Yii::error($e, 'designwahl');
        }
    }

    /** Abfallkalender: Kasten mit Öffnungszeiten von Wertstoffhof und Kompostplatz */
    private static function oeffnungszeiten($legend): string
    {
        $space = $legend->contentContainer;
        if (!$space instanceof \humhub\modules\space\models\Space || $space->url !== 'abfall-and-termine' || !Yii::$app->hasModule('oeffnungszeiten')) {
            return '';
        }
        return \humhub\modules\oeffnungszeiten\components\Zeiten::ortszeit(fn() => $legend->view->render('@designwahl/widgets/views/oeffnungszeiten'));
    }

    /** Kasten „Kalender abonnieren“ (öffentliche Abos ohne Konto) für Veranstaltungen und Abfuhrtermine */
    private static function kalenderAbo($legend): string
    {
        $space = $legend->contentContainer;
        if (!$space instanceof \humhub\modules\space\models\Space) {
            return '';
        }
        if (in_array($space->url, controllers\AboController::KALENDER, true)) {
            $abos = [[Yii::t('DesignwahlModule.base', 'Veranstaltungen'), Url::to(['/designwahl/abo', 'kalender' => array_search($space->url, controllers\AboController::KALENDER, true)], true)]];
            $hinweis = '';
        } elseif ($space->url === 'abfall-and-termine') {
            $abos = [
                [Yii::t('DesignwahlModule.base', 'Kernstadt Uffenheim'), Url::to('/abfall/abfall-kernstadt.ics', true)],
                [Yii::t('DesignwahlModule.base', 'Ortsteile'), Url::to('/abfall/abfall-ortsteile.ics', true)],
            ];
            $hinweis = Yii::t('DesignwahlModule.base', 'Restmüll, Bio- und Papiertonne sind überall gleich, die Gelbe Tonne kommt in den Ortsteilen einen Tag später. Bitte den passenden Kalender wählen.');
        } else {
            return '';
        }
        return $legend->view->render('@designwahl/widgets/views/kalenderAbo', ['abos' => $abos, 'hinweis' => $hinweis]);
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

    /**
     * Zahl am App-Symbol: Der sichtbare Push der HumHub-App setzt die Zahl am iPhone nicht, nur die stille Nachricht von
     * fcm-push (kommt sonst nur beim Lesen). Deshalb lösen auch neue Glockenmeldungen und neue private Nachrichten das
     * Ereignis aus; fcm-push schickt dann nach silentUnreadNotificationCountPushDelay die aktuelle Zahl (je Konto ein Auftrag).
     */
    public static function onNotificationInsert($event)
    {
        try {
            if ($event->sender->send_web_notifications && $event->sender->user) {
                \humhub\modules\notification\events\UnreadCountChangedEvent::triggerChanged($event->sender->user);
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'designwahl');
        }
    }

    public static function onMessageEntryInsert($event)
    {
        try {
            $message = $event->sender->message;
            foreach ($message ? $message->users : [] as $user) {
                if ($user->id != $event->sender->user_id) {
                    \humhub\modules\notification\events\UnreadCountChangedEvent::triggerChanged($user);
                }
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'designwahl');
        }
    }
}
