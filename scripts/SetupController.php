<?php
/**
 * Einmalige Grundstruktur für community.rundumuffni.de anlegen.
 * Aufruf: scripts/run-setup.sh (registriert diesen Controller temporär als "setup").
 * Idempotent: vorhandene Spaces (gleicher Name) werden übersprungen.
 */

use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use yii\console\Controller;
use yii\console\ExitCode;

class SetupController extends Controller
{
    private const SPACES = [
        [
            'name' => 'Rathaus Uffenheim',
            'url' => 'rathaus-uffenheim',
            'description' => 'Offizielle Informationen, Bekanntmachungen und Neuigkeiten der Stadt.',
            'color' => '#1f5f8b',
            'auto_add' => true,
            'modules' => ['calendar', 'polls'],
        ],
        [
            'name' => 'Schwarzes Brett',
            'description' => 'Biete, Suche, Verschenke, Fundsachen – alles, was Uffenheim bewegt.',
            'color' => '#b5651d',
            'auto_add' => true,
            'modules' => ['gallery'],
        ],
        [
            'name' => 'Veranstaltungen',
            'url' => 'veranstaltungen',
            'description' => 'Feste, Märkte, Vereinsabende und Termine in und um Uffenheim.',
            'color' => '#7b3fa0',
            'auto_add' => true,
            'modules' => ['calendar'],
        ],
        [
            'name' => 'Nachbarschaftshilfe',
            'description' => 'Einkaufen, Fahrdienste, Gartenhilfe: Hilfe anbieten und Hilfe finden.',
            'color' => '#2e8b57',
            'auto_add' => false,
            'modules' => ['questions'],
        ],
        [
            'name' => 'Mängelmelder',
            'url' => 'mangelmelder',
            'description' => 'Schlagloch, defekte Laterne, wilder Müll? Hier mit Foto und Ort melden.',
            'color' => '#c0392b',
            'auto_add' => true, // alle Angemeldeten sehen die Meldungen (setup/maengelmelder-alle)
            'modules' => [],
        ],
        [
            'name' => 'Vereine & Ehrenamt',
            'url' => 'vereine-and-ehrenamt',
            'description' => 'Vereinsverzeichnis mit Vorstellungsseiten; Vereinstermine stehen im gemeinsamen Kalender unter „Veranstaltungen“.',
            'color' => '#d4a017',
            'auto_add' => false,
            'modules' => ['calendar', 'polls'],
        ],
        [
            'name' => 'Gewerbe & Einkaufen',
            'url' => 'gewerbe',
            'description' => 'Geschäfte, Handwerk, Gastronomie und Dienstleister aus Uffenheim',
            'color' => '#5e7f0a',
            'auto_add' => false,
            'modules' => ['gewerbe'],
        ],
        [
            'name' => 'Bürgerbeteiligung',
            'description' => 'Ideen, Umfragen und Diskussionen zur Zukunft Uffenheims.',
            'color' => '#16a085',
            'auto_add' => false,
            'modules' => ['polls', 'questions'],
        ],
        [
            'name' => 'Abfall & Termine', // heute „Abfalltermine“, gefunden über url
            'url' => 'abfall-and-termine',
            'description' => 'Abfuhrtermine für Uffenheim und Ortsteile: Restmüll, Bio, Papier, Gelbe Tonne.',
            'color' => '#2e7d32',
            'auto_add' => true,
            'modules' => ['calendar', 'external_calendar'],
        ],
    ];

    /** Einstellungen des Clean-Themes für den iOS-Stil (Rest in design/ios.scss) */
    private const CLEAN_THEME = [
        'linkColor' => '#446e88',
        'textColorHeading' => '#1c1c1e',
        'textColorMain' => '#1c1c1e',
        'textColorDefault' => '#3a3a3c',
        'textColorSecondary' => '#6e6e73',
        'textColorHighlight' => '#000000',
        'textColorSoft' => '#6e6e73',
        'textColorSoft2' => '#8e8e93',
        'textColorSoft3' => '#aeaeb2',
        'backgroundColorMain' => '#ffffff',
        'backgroundColorSecondary' => '#f7f7fa',
        'backgroundColorPage' => '#f2f2f7',
        'backgroundColorHighlight' => '#e4ecf1',
        'backgroundColorHighlightSoft' => '#f1f5f8',
        'fontSize' => '15',
        'fontBoldWeight' => '600',
        'phFontSize' => '17',
        'phFontWeight' => '600',
        'panelBorderStyle' => 'none',
        'panelBorderRadius' => '16',
        'panelBoxShadow' => '0 1px 2px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.05)',
        'menuStyle' => 'bordered',
        'menuTextColor' => '#1c1c1e',
        'menuBorderColor' => '#e5e5ea',
        'topBarHeight' => '54',
        'topMenuBackgroundColor' => '#ffffff',
        'topMenuTextColor' => '#6e6e73',
        'topMenuButtonHoverBackgroundColor' => '#f2f2f7',
        'topMenuButtonHoverTextColor' => '#000000',
        'bottomMenuBackgroundColor' => '#ffffff',
        'bottomMenuTextColor' => '#8e8e93',
        // Dunkelmodus (iOS-Systemfarben), falls HumHub ihn später anbietet
        'linkColorDark' => '#8fb4cc',
        'textColorHeadingDark' => '#ffffff',
        'textColorMainDark' => '#f2f2f7',
        'textColorDefaultDark' => '#e5e5ea',
        'textColorSecondaryDark' => '#98989f',
        'textColorHighlightDark' => '#ffffff',
        'textColorSoftDark' => '#98989f',
        'textColorSoft2Dark' => '#8e8e93',
        'textColorSoft3Dark' => '#636366',
        'backgroundColorMainDark' => '#1c1c1e',
        'backgroundColorSecondaryDark' => '#2c2c2e',
        'backgroundColorPageDark' => '#000000',
        'panelBorderColorDark' => '#2c2c2e',
        'menuTextColorDark' => '#f2f2f7',
        'menuBorderColorDark' => '#38383a',
        'topMenuBackgroundColorDark' => '#1c1c1e',
        'topMenuTextColorDark' => '#98989f',
        'topMenuButtonHoverBackgroundColorDark' => '#2c2c2e',
        'topMenuButtonHoverTextColorDark' => '#ffffff',
        'bottomMenuBackgroundColorDark' => '#1c1c1e',
        'bottomMenuTextColorDark' => '#8e8e93',
    ];

    public function actionSpaces($ownerUsername = 'admin')
    {
        $owner = User::findOne(['username' => $ownerUsername]);
        if ($owner === null) {
            $this->stderr("User $ownerUsername not found\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->user->switchIdentity($owner);

        foreach (self::SPACES as $def) {
            if (Space::findOne(['name' => $def['name']]) || (isset($def['url']) && Space::findOne(['url' => $def['url']]))) {
                $this->stdout("= {$def['name']} (existiert)\n");
                continue;
            }
            $space = new Space();
            $space->scenario = Space::SCENARIO_CREATE;
            $space->name = $def['name'];
            $space->description = $def['description'];
            $space->color = $def['color'];
            $space->visibility = Space::VISIBILITY_ALL;
            $space->join_policy = Space::JOIN_POLICY_FREE;
            $space->auto_add_new_members = $def['auto_add'] ? 1 : 0;
            if (!$space->save()) {
                $this->stderr("! {$def['name']}: " . json_encode($space->getErrors()) . "\n");
                continue;
            }
            foreach ($def['modules'] as $moduleId) {
                $space->moduleManager->enable($moduleId);
            }
            $this->stdout("+ {$def['name']} (" . implode(', ', $def['modules']) . ")\n");
        }

        return ExitCode::OK;
    }

    /** Testmail über den konfigurierten Transport: setup/testmail empfaenger@example.org */
    public function actionTestmail($to)
    {
        $ok = Yii::$app->mailer->compose()
            ->setFrom([Yii::$app->settings->get('mailerSystemEmailAddress') => Yii::$app->settings->get('mailerSystemEmailName')])
            ->setTo($to)
            ->setSubject('Testmail von ' . Yii::$app->name)
            ->setTextBody('SMTP-Versand von ' . Yii::$app->settings->get('baseUrl') . ' funktioniert.')
            ->send();
        $this->stdout(($ok ? 'OK' : 'FEHLER') . "\n");
        return $ok ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /** Kurze Hinweis-Mail an den ersten Admin (Benutzer-ID 1): setup/admin-hinweis "Betreff" "Text" */
    public function actionAdminHinweis($betreff, $text)
    {
        $admin = \humhub\modules\user\models\User::findOne(1);
        $ok = $admin && Yii::$app->mailer->compose()
            ->setFrom([Yii::$app->settings->get('mailerSystemEmailAddress') => Yii::$app->settings->get('mailerSystemEmailName')])
            ->setTo($admin->email)
            ->setSubject($betreff)
            ->setTextBody($text)
            ->send();
        $this->stdout(($ok ? 'OK' : 'FEHLER') . "\n");
        return $ok ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /** Rechtstexte aus legal/*.md übernehmen: setup/legal (Dateien liegen unter /tmp/legal) */
    public function actionLegal()
    {
        $pages = [
            'imprint' => ['Impressum', 'impressum.md'],
            'privacy' => ['Datenschutzerklärung', 'datenschutz.md'],
            'terms' => ['Nutzungsbedingungen', 'nutzungsbedingungen.md'],
            'cookies' => ['Verstanden', 'cookies.md'],
        ];
        foreach ($pages as $key => [$title, $file]) {
            $page = \humhub\modules\legal\models\Page::findOne(['page_key' => $key, 'language' => 'de'])
                ?? new \humhub\modules\legal\models\Page(['page_key' => $key, 'language' => 'de']);
            $page->title = $title;
            $page->content = trim(file_get_contents('/tmp/legal/' . $file));
            if (!$page->save()) {
                $this->stderr("$key: " . json_encode($page->getErrors()) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("$key gespeichert\n");
        }
        Yii::$app->settings->set('defaultLanguage', 'de'); // Systemsprache (Gäste: designwahl)
        $settings = Yii::$app->getModule('legal')->settings;
        $settings->set('defaultLanguage', 'de');
        $settings->set('enabledPages', implode(',', array_keys($pages)));
        $settings->set('showAgeCheck', false);
        return ExitCode::OK;
    }

    /** Farben (BLU-Blau/-Gelb), iOS-Stil (design/ios.scss) und optional Logo setzen: setup/design [/tmp/logo.png] */
    public function actionDesign($logo = null)
    {
        $s = Yii::$app->settings;
        // Farben angelehnt an uffenheim.de: Stahlblau (Primär), Bordeaux (Akzent, z. B. Zähler)
        foreach (['Primary' => '#446e88', 'Accent' => '#7c1922'] as $name => $color) {
            $s->set("theme{$name}Color", $color);
            $s->set("useDefaultTheme{$name}Color", false);
        }
        // Clean-Theme im iOS-Stil (Systemfarben, runde Karten ohne Rahmen); Schriftart bleibt Open Sans,
        // sonst lädt das Theme Google Fonts – die Systemschrift setzt design/ios.scss
        $clean = Yii::$app->getModule('clean-theme')->configuration;
        foreach (self::CLEAN_THEME as $attr => $value) {
            $clean->$attr = $value;
        }
        if (!$clean->save()) {
            $this->stderr('Clean-Theme: ' . json_encode($clean->getErrors()) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $scss = file_get_contents('/tmp/design/ios.scss');
        try {
            \humhub\helpers\ScssHelper::extractVariablesAndMaps($scss);
        } catch (\Throwable $e) {
            $this->stderr("SCSS-Fehler: {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $s->set('themeCustomScss', $scss);
        if ($logo !== null) {
            \Yii::$app->img->logo->setByFile($logo);
            $this->stdout("Logo gesetzt\n");
        }
        $result = \humhub\helpers\ThemeHelper::buildCss();
        $this->stdout($result === true ? "CSS neu gebaut\n" : "CSS-Fehler: $result\n");
        return $result === true ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /** Firebase-Zugang prüfen (validate_only an Dummy-Token): setup/pushcheck */
    public function actionPushcheck()
    {
        $module = Yii::$app->getModule('fcm-push');
        \humhub\modules\fcmPush\Module::registerAutoloader();
        $config = $module->getConfigureForm();
        $messaging = (new \Kreait\Firebase\Factory())->withServiceAccount($config->getJsonAsArray())->createMessaging();
        $message = \Kreait\Firebase\Messaging\CloudMessage::new()
            ->withNotification(\Kreait\Firebase\Messaging\Notification::create('Test', 'Test'))
            ->withChangedTarget('token', 'dummy-token-zum-pruefen');
        try {
            $messaging->validate($message);
            $this->stdout("Unerwartet: Dummy-Token akzeptiert\n");
        } catch (\Throwable $e) {
            $this->stdout(get_class($e) . ': ' . $e->getMessage() . "\n");
        }
        $this->stdout('Projekt: ' . $config->getJsonParam('project_id') . ', Sender-ID: ' . $config->senderId . "\n");
        return ExitCode::OK;
    }

    /**
     * Test-Push an alle Geräte eines Kontos (App und Browser), mit der Zahl ungelesener Glockenmeldungen als Kennzeichen:
     * setup/pushtest <benutzername> ["Text"]. Fehler stehen im HumHub-Log (Kategorie fcm-push).
     */
    public function actionPushtest($username, $text = 'Test-Push von Rund um Uffni')
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("Konto $username nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        \humhub\modules\fcmPush\Module::registerAutoloader();
        $config = Yii::$app->getModule('fcm-push')->getConfigureForm();
        $service = new \humhub\modules\fcmPush\services\MessagingService($config);
        $count = $service->getNotificationCount($user);
        $url = \yii\helpers\Url::to(['/notification/overview'], true);
        $this->stdout("Kennzeichen-Zahl $count\n");
        // Je Weg einzeln senden und die Antwort zeigen (processMessage schluckt Fehler)
        foreach ((new \humhub\modules\fcmPush\services\DriverService($config))->getConfiguredDrivers() as $driver) {
            $tokens = (new \humhub\modules\fcmPush\services\TokenService())->getTokensForUser($user, $driver);
            $name = (new \ReflectionClass($driver))->getShortName();
            $this->stdout("$name (Sender {$driver->getSenderId()}): " . count($tokens) . " Gerät(e)\n");
            if (!$tokens) {
                continue;
            }
            if ($driver instanceof \humhub\modules\fcmPush\drivers\Proxy) {
                $response = $driver->post('/push', ['tokens' => $tokens, 'title' => Yii::$app->name, 'body' => $text,
                    'iconUrl' => null, 'url' => $url, 'notificationCount' => $count])->send();
                $this->stdout("  HTTP {$response->statusCode}: " . json_encode($response->data, JSON_UNESCAPED_UNICODE) . "\n");
            } else {
                $report = $driver->processCloudMessage($tokens, Yii::$app->name, $text, $url, null, $count);
                $this->stdout('  ' . json_encode($report, JSON_UNESCAPED_UNICODE) . "\n");
            }
        }
        return ExitCode::OK;
    }

    /**
     * Alte Push-Geräte aufräumen (Crontab täglich): Die HumHub-App legt bei fast jedem Start einen neuen Token an, die alten
     * bleiben in fcmpush_user liegen. Je Konto und Push-Weg bleibt der neueste Eintrag immer stehen; ältere Einträge fallen
     * weg, wenn sie seit <tage> Tagen nicht erneuert wurden. Ein Gerät, das so lange nicht geöffnet wurde, meldet sich beim
     * nächsten App-Start neu an. setup/push-geraete-aufraeumen [tage=30] [probe=0]
     */
    public function actionPushGeraeteAufraeumen($tage = 30, $probe = 0)
    {
        $bedingung = 'FROM fcmpush_user f JOIN (SELECT user_id, sender_id, MAX(id) AS neueste FROM fcmpush_user GROUP BY user_id, sender_id) k'
            . ' ON k.user_id = f.user_id AND k.sender_id = f.sender_id'
            . ' WHERE f.id < k.neueste AND COALESCE(f.updated_at, f.created_at) < NOW() - INTERVAL :tage DAY';
        $db = Yii::$app->db;
        $anzahl = (int)$db->createCommand("SELECT COUNT(*) $bedingung", [':tage' => (int)$tage])->queryScalar();
        if (!$probe && $anzahl) {
            $anzahl = $db->createCommand("DELETE f $bedingung", [':tage' => (int)$tage])->execute();
        }
        $rest = (int)$db->createCommand('SELECT COUNT(*) FROM fcmpush_user')->queryScalar();
        $this->stdout(date('Y-m-d H:i:s') . ($probe ? ' Probe: würde ' : ' ') . "$anzahl alte Push-Geräte "
            . ($probe ? 'löschen' : 'gelöscht') . " (älter als $tage Tage), $rest Einträge übrig\n");
        return ExitCode::OK;
    }

    /**
     * Vorschau der Mails an Bürger in einer Sprache, ohne Versand: setup/mailvorschau <sprache> (de, en-US, uk, ar, tr, ru).
     * Nimmt die neueste Mängelmeldung und den neuesten Vereins-/Gewerbebeitrag als Beispiel.
     */
    public function actionMailvorschau($sprache = 'en-US')
    {
        Yii::$app->language = Yii::$app->formatter->locale = $sprache;
        $zeige = fn($titel, $text) => $this->stdout("===== $titel\n$text\n");
        $mangel = null;
        foreach (\humhub\modules\post\models\Post::find()->where(['like', 'message', 'maengelmelder/meldung/karte?lat='])
                     ->orderBy(['id' => SORT_DESC])->limit(5)->all() as $post) {
            if (($mangel = \humhub\modules\maengelmelder\models\Mangel::fromContent($post->content)) !== null) {
                break;
            }
        }
        if ($mangel !== null) {
            $m = Yii::$app->getModule('maengelmelder');
            $zeige('Mängelmelder: Bestätigung', Yii::$app->view->renderFile('@maengelmelder/views/mail/bestaetigung_text.php',
                ['name' => 'Max Muster', 'nr' => $mangel->getContentId(), 'link' => 'https://…', 'stunden' => 48, 'kontaktMail' => $m->kontaktMail]));
            $zeige('Mängelmelder: Status', Yii::$app->view->renderFile('@maengelmelder/views/mail/status_text.php', [
                'name' => 'Max Muster', 'mangel' => $mangel, 'stand' => Yii::t('MaengelmelderModule.base', 'In Bearbeitung'),
                'hinweis' => Yii::t('MaengelmelderModule.base', 'Deine Meldung ist jetzt auf der Karte zu sehen.'),
                'link' => 'https://…', 'kontaktMail' => $m->kontaktMail, 'gast' => true]));
        }
        foreach (['vereine' => \humhub\modules\vereine\models\VereinBeitrag::class, 'gewerbe' => \humhub\modules\gewerbe\models\BetriebBeitrag::class] as $id => $klasse) {
            if (class_exists($klasse) && ($b = $klasse::find()->orderBy(['id' => SORT_DESC])->one()) !== null) {
                $b->begruendung = $b->begruendung ?: '(Begründung)';
                $zeige("$id: Ablehnung", Yii::$app->view->renderFile("@$id/views/mail/abgelehnt_text.php",
                    ['beitrag' => $b, 'name' => 'Max Muster', 'link' => 'https://…', 'kontaktMail' => Yii::$app->getModule($id)->kontaktMail]));
            }
        }
        // Gutschein-Aufsteller: Karten in der Sprache $sprache, Hinweise oben auf Deutsch (Sprache des Betriebs)
        if (class_exists(\humhub\modules\gewerbe\models\Betrieb::class)
            && ($b = \humhub\modules\gewerbe\models\Betrieb::find()->where(['not', ['theke_nummer' => null]])->one()) !== null) {
            if (!Yii::$app->has('session')) { // Html::nonce() braucht eine Sitzung
                Yii::$app->set('session', ['class' => \yii\web\Session::class]);
            }
            $html = Yii::$app->view->renderFile('@gewerbe/views/gutschein/aufsteller.php', ['betrieb' => $b, 'url' => 'https://example.org',
                'sprache' => $sprache, 'sprachen' => Yii::$app->i18n->getAllowedLanguages()]);
            Yii::$app->language = 'de';
            preg_match('#<section.*?</section>#s', $html, $m);
            $zeige('Gutschein-Aufsteller (eine Karte)', trim(preg_replace(['#<svg.*?</svg>#s', '#<[^>]+>#', "#\n\s*\n+#"], ['[QR]', '', "\n"], $m[0] ?? '')));
        }
        return ExitCode::OK;
    }

    /**
     * Abfallkalender im Space „Abfall & Termine“ anlegen/abgleichen und alle Nutzer aufnehmen:
     * setup/abfall  (die ICS-Datei erzeugt scripts/abfall-kalender.py)
     */
    public function actionAbfall($ownerUsername = 'admin')
    {
        $owner = User::findOne(['username' => $ownerUsername]);
        Yii::$app->user->switchIdentity($owner);
        $space = Space::findOne(['url' => 'abfall-and-termine']);
        if ($space === null) {
            $this->stderr("Space fehlt, zuerst setup/spaces ausführen\n");
            return ExitCode::DATAERR;
        }

        // Alle Bürger als Mitglieder (für die Abfuhr-Erinnerung), nicht die Import-Konten
        foreach (User::find()->active()->all() as $user) {
            $import = in_array($user->username, [self::RSS_USER, self::FB_USER, self::MB_USER], true);
            if (!$import && !$space->isMember($user->id)) {
                $space->addMember($user->id, 1, false);
                $this->stdout("+ Mitglied {$user->username}\n");
            } elseif ($import && $space->isMember($user->id)) {
                $space->removeMember($user->id);
                $this->stdout("- Mitglied {$user->username}\n");
            }
        }

        \humhub\modules\external_calendar\Events::registerAutoloader();
        // Abfuhrtermine nur im Kalender, nicht jeder einzeln im Stream (gilt modulweit)
        Yii::$app->getModule('external_calendar')->settings->set('autopost_entries', 0);
        // … und die Kalender selbst nicht (sonst kommt „Ein neuer Kalender …“ bei jedem Speichern wieder in den Stream)
        Yii::$app->getModule('external_calendar')->settings->set('autopost_calendar', 0);
        // Je Tonnenart ein eigener Kalender, weil External Calendar die Farbe nur je Kalender kennt
        // (Dateien von scripts/abfall-kalender.py; lokal abrufen, damit der Abgleich nicht über den Proxy läuft)
        $calClass = \humhub\modules\external_calendar\models\ExternalCalendar::class;
        $arten = [
            'restmuell' => ['Restmüll', '#1c1c1e'],
            'bio' => ['Biotonne', '#8b5a2b'],
            'papier' => ['Papiertonne', '#1f5fa8'],
            'gelb' => ['Gelbe Tonne', '#f2c200'],
            'gebuehren' => ['Müllgebühren', '#2e7d32'],
        ];
        $urls = [];
        $anzahl = 0;
        foreach ($arten as $name => [$titel, $farbe]) {
            $url = "http://127.0.0.1/abfall/abfall-$name.ics";
            $urls[] = $url;
            $calendar = $calClass::find()->contentContainer($space)->andWhere(['external_calendar.url' => $url])->one()
                ?? new $calClass($space);
            $calendar->title = "Abfuhrtermine – $titel";
            $calendar->description = 'Quelle: Abfallportal des Landkreises Neustadt a.d.Aisch-Bad Windsheim, nächtlich aktualisiert.';
            $calendar->url = $url;
            $calendar->color = $farbe;
            $calendar->sync_mode = $calClass::SYNC_MODE_DAILY;
            $calendar->event_mode = $calClass::EVENT_MODE_ALL;
            $calendar->public = 1;
            if (!$calendar->save()) {
                $this->stderr("Kalender $titel: " . json_encode($calendar->getErrors()) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $calendar->sync();
            $anzahl += count($calendar->entries);
            // Gleicher Aufruf wie bei der Kalenderanzeige, bricht bei fehlerhaften Einträgen ab
            foreach ($calendar->entries as $entry) {
                $entry->getFullCalendarArray();
            }
        }
        $this->stdout("Kalender abgeglichen: $anzahl Termine in " . count($arten) . " Kalendern, Anzeige-Test ok\n");
        // Früheren Gesamtkalender (abfall.ics) samt Terminen entfernen
        foreach ($calClass::find()->contentContainer($space)->andWhere(['not in', 'external_calendar.url', $urls])->all() as $alt) {
            $alt->hardDelete();
            $this->stdout("Alten Kalender „{$alt->title}“ entfernt\n");
        }
        // Früher (autopost an) angelegte Termine aus Stream und Aktivitäten nehmen
        $entryClass = \humhub\modules\external_calendar\models\ExternalCalendarEntry::class;
        // (auch die Beiträge „Ein neuer Kalender wurde hinzugefügt“ und normale Beiträge: der Space zeigt nur den Kalender)
        $postClass = \humhub\modules\post\models\Post::class;
        $n = \humhub\modules\content\models\Content::updateAll(['stream_channel' => null],
            ['object_model' => [$entryClass, $calClass, $postClass], 'stream_channel' => 'default', 'contentcontainer_id' => $space->contentcontainer_id]);
        $a = 0;
        foreach (\humhub\modules\activity\models\Activity::find()->where(['content_id' => \humhub\modules\content\models\Content::find()->select('id')
                ->where(['object_model' => [$entryClass, $calClass, $postClass], 'contentcontainer_id' => $space->contentcontainer_id])])->all() as $activity) {
            $activity->delete();
            $a++;
        }
        if ($n || $a) {
            $this->stdout("Aus dem Stream genommen: $n Termine/Kalender/Beiträge, $a Aktivitäten\n");
        }

        // Info-Beitrag anlegen bzw. aktualisieren; erkannt an der Abo-Adresse im Text
        $message = trim(file_get_contents('/tmp/legal/abfall-info.md'));
        $post = \humhub\modules\post\models\Post::find()->contentContainer($space)
            ->andWhere(['like', 'post.message', 'rundumuffni.de/abfall/abfall.ics'])->one();
        if ($post !== null && $post->message !== $message) {
            $post->message = $message;
            $post->save();
            $this->stdout("Info-Beitrag aktualisiert\n");
        } elseif ($post === null) {
            $post = new \humhub\modules\post\models\Post($space);
            $post->message = $message;
            $post->content->visibility = \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
            $post->save();
            $post->content->pin();
            $this->stdout("Info-Beitrag angelegt und angepinnt\n");
        }
        return ExitCode::OK;
    }

    private const RSS_URL = 'https://www.uffenheim.de/nachrichten/rss';
    private const RSS_USER = 'stadt-uffenheim';
    /** Konto der Facebook-Übernahme (modules/facebook, Module::$konto) */
    private const FB_USER = 'stadt-uffenheim-fb';
    /** Verfasser der aus dem Mitteilungsblatt übernommenen Vereinsbeiträge (setup/mb-vereinsbeitraege) */
    private const MB_USER = 'mitteilungsblatt';

    /**
     * Vereinsbeiträge aus dem Mitteilungsblatt übernehmen (mit Genehmigung der Vereine und der LINUS WITTICH Medien KG,
     * Stand 25.10.2026): setup/mb-vereinsbeitraege /tmp/mb/beitraege.json [1 = Probelauf]
     * JSON: [{schluessel, verein_id, ausgabe, seite, datum, text, bilder: [{datei, name}]}], Bilder im selben Ordner.
     * Verfasser ist das Konto „Mitteilungsblatt (Vereinsbeiträge)“ (ohne Anmeldung, wird bei Bedarf angelegt).
     * Jeder Beitrag erscheint auf der Vereinsseite und still (ohne Benachrichtigungen) in der Übersicht, datiert auf die
     * Ausgabe. Schon übernommene (gleicher Verein, gleiche erste Zeile, gleiche Ausgabe) werden nicht neu angelegt, aber Text
     * und Datum angeglichen. Einfache Zeilenumbrüche im JSON werden harte Umbrüche.
     */
    public function actionMbVereinsbeitraege($json, $probelauf = 0)
    {
        $liste = json_decode((string)@file_get_contents($json), true);
        if (!is_array($liste)) {
            $this->stderr("JSON nicht lesbar: $json\n");
            return ExitCode::DATAERR;
        }
        $ordner = dirname($json);
        $konto = User::findOne(['username' => self::MB_USER]);
        if ($konto === null && !$probelauf) {
            $konto = new User(['scenario' => 'editAdmin']);
            $konto->username = self::MB_USER;
            $konto->email = 'mitteilungsblatt@vereinsbeitraege.invalid';
            $konto->status = User::STATUS_ENABLED;
            if (!$konto->save()) {
                $this->stderr('Konto: ' . json_encode($konto->getErrors()) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $konto->profile->scenario = 'editAdmin';
            $konto->profile->firstname = 'Mitteilungsblatt';
            $konto->profile->lastname = '(Vereinsbeiträge)';
            $konto->profile->about = 'Beiträge der Vereine aus dem Mitteilungsblatt der Stadt Uffenheim, übernommen mit Genehmigung '
                . 'der Vereine und der LINUS WITTICH Medien KG. Dieses Konto wird nicht betreut – Fragen bitte an den jeweiligen Verein.';
            $konto->profile->save();
            $this->stdout("+ Konto " . self::MB_USER . "\n");
        }
        $admin = User::find()->where(['username' => 'admin'])->one();
        $V = \humhub\modules\vereine\models\VereinBeitrag::class;
        $fehler = 0;
        foreach ($liste as $e) {
            $verein = \humhub\modules\vereine\models\Verein::findOne((int)$e['verein_id']);
            $ersteZeile = strtok($e['text'], "\n");
            if ($verein === null) {
                $this->stderr("! {$e['schluessel']}: Verein {$e['verein_id']} fehlt\n");
                $fehler++;
                continue;
            }
            // Einfacher Zeilenumbruch innerhalb eines Absatzes = harter Umbruch (HumHub-Markdown: „\“ am Zeilenende)
            $e['text'] = preg_replace('/(?<![\n\\\\])\n(?!\n)/', "\\\\\n", $e['text']);
            $schon = $konto ? $V::find()->where(['verein_id' => $verein->id, 'user_id' => $konto->id])
                ->andWhere(['like', 'text', $ersteZeile])->andWhere(['like', 'text', 'Ausgabe ' . $e['ausgabe'] . '/'])->one() : null;
            if ($schon && !$probelauf) {
                // Schon übernommen: Text und Datum angleichen (z. B. nach Korrekturen im JSON)
                $schon->updateAttributes(['text' => $e['text'], 'created_at' => $e['datum'], 'geprueft_am' => $e['datum']]);
                if (($post = $schon->content?->getModel()) !== null) {
                    $post->updateAttributes(['message' => $e['text']]);
                    $schon->content->updateAttributes(['created_at' => $e['datum'], 'updated_at' => $e['datum'], 'stream_sort_date' => $e['datum']]);
                }
            }
            $bilder = array_map(fn($b) => $ordner . '/' . basename($b['datei']), $e['bilder']);
            $fehlend = array_filter($bilder, fn($f) => !is_file($f));
            $this->stdout(sprintf("%s %s → %s (%d Zeichen, %d Bilder)%s\n", $schon ? '=' : '+', $e['schluessel'], $verein->name,
                mb_strlen($e['text']), count($bilder), $fehlend ? ' ! fehlt: ' . implode(', ', $fehlend) : ''));
            if ($fehlend) {
                $fehler++;
            }
            if ($schon || $probelauf || $fehlend) {
                continue;
            }
            $tx = Yii::$app->db->beginTransaction();
            try {
                Yii::$app->user->switchIdentity($konto); // Verfasser = geprüft von → keine Benachrichtigung an das Konto
                $b = new $V(['verein_id' => $verein->id, 'user_id' => $konto->id, 'text' => $e['text'], 'status' => $V::STATUS_EINGEREICHT]);
                $b->save(false);
                foreach ($e['bilder'] as $i => $bild) {
                    $pfad = $bilder[$i];
                    $file = new \humhub\modules\file\models\File(['file_name' => $bild['name'], 'mime_type' => 'image/jpeg', 'size' => filesize($pfad)]);
                    $file->save();
                    $file->setStoredFileContent(file_get_contents($pfad));
                    $b->fileManager->attach($file);
                }
                \humhub\modules\vereine\components\BeitragDienst::freigeben($b, true, true, true);
                $b->refresh();
                $b->updateAttributes(['created_at' => $e['datum'], 'geprueft_am' => $e['datum'], 'geprueft_von' => $admin?->id ?? $konto->id]);
                if ($b->content) {
                    $b->content->updateAttributes(['created_at' => $e['datum'], 'updated_at' => $e['datum'], 'stream_sort_date' => $e['datum']]);
                }
                $tx->commit();
            } catch (\Throwable $ex) {
                $tx->rollBack();
                $this->stderr("! {$e['schluessel']}: " . $ex->getMessage() . "\n");
                $fehler++;
            }
        }
        Yii::$app->cache->delete('ki-assistent-context');
        return $fehler ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Konto „Stadt Uffenheim (Website)“ als Verfasser der Stadt-Nachrichten anlegen: setup/rssuser
     * Ohne Passwort und mit nicht zustellbarer Adresse (.invalid), also keine Anmeldung und keine Mails.
     * Bisher importierte Beiträge werden auf das Konto umgeschrieben.
     * Optional Profilbild (SVG/PNG, vorher in den Container kopieren): setup/rssuser /tmp/wappen.svg
     */
    public function actionRssuser($avatar = null)
    {
        $user = User::findOne(['username' => self::RSS_USER]);
        if ($user === null) {
            $user = new User(['scenario' => 'editAdmin']);
            $user->username = self::RSS_USER;
            $user->email = 'stadt-uffenheim@website.invalid';
            $user->status = User::STATUS_ENABLED;
            if (!$user->save()) {
                $this->stderr('Konto: ' . json_encode($user->getErrors()) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("+ Konto " . self::RSS_USER . "\n");
        }
        $user->profile->scenario = 'editAdmin';
        $user->profile->firstname = 'Stadt Uffenheim';
        $user->profile->lastname = '(Website)';
        $user->profile->about = 'Nachrichten von www.uffenheim.de, automatisch übernommen. '
            . 'Dieses Konto wird nicht betreut – Fragen bitte direkt an die Stadtverwaltung.';
        if (!$user->profile->save()) {
            $this->stderr('Profil: ' . json_encode($user->profile->getErrors()) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($avatar !== null) {
            // Quadratisch auf Weiß, mit Rand, damit auch runde Profilbilder nichts abschneiden
            $im = new \Imagick();
            $im->setBackgroundColor(new \ImagickPixel('transparent'));
            $im->setResolution(300, 300);
            $im->readImage($avatar);
            $im->thumbnailImage(340, 340, true);
            $canvas = new \Imagick();
            $canvas->newImage(512, 512, new \ImagickPixel('white'), 'png');
            $canvas->compositeImage($im, \Imagick::COMPOSITE_OVER,
                intdiv(512 - $im->getImageWidth(), 2), intdiv(512 - $im->getImageHeight(), 2));
            $canvas->writeImage('/tmp/rssuser-avatar.png');
            $user->getProfileImage()->setNew('/tmp/rssuser-avatar.png');
            $this->stdout("Profilbild gesetzt\n");
        }

        // Nur Web-Benachrichtigungen, keine Mails/Push und keine Zusammenfassung
        $settings = Yii::$app->getModule('notification')->settings->user($user);
        foreach (Yii::$app->notification->getNotificationCategories($user) as $category) {
            foreach (Yii::$app->notification->getTargets($user) as $target) {
                if ($target->id !== 'web') {
                    $settings->set($target->getSettingKey($category), 0);
                }
            }
        }
        Yii::$app->getModule('activity')->settings->user($user)->set('mailSummaryInterval', 0);

        $spaceIds = [];
        foreach (['rathaus-uffenheim', 'veranstaltungen'] as $url) {
            $s = Space::findOne(['url' => $url]);
            $spaceIds[] = $s->id;
            if (!$s->isMember($user->id)) {
                $s->addMember($user->id, 1, false);
                $this->stdout("+ Mitglied in {$s->name}\n");
            }
        }
        $space = Space::findOne(['url' => 'rathaus-uffenheim']);
        // Neue Konten landen automatisch in mehreren Spaces; das Konto gehört nur ins Rathaus und
        // zu den Veranstaltungen. Mitgliedschaft direkt löschen und die „ist beigetreten“-Aktivitäten entfernen.
        foreach (\humhub\modules\space\models\Membership::find()->where(['user_id' => $user->id])
                     ->andWhere(['not in', 'space_id', $spaceIds])->all() as $membership) {
            $membership->delete();
            $this->stdout("- Mitglied in {$membership->space->name}\n");
        }
        foreach (\humhub\modules\activity\models\Activity::find()->where(['created_by' => $user->id,
                     'class' => \humhub\modules\space\activities\MemberAddedActivity::class])->all() as $activity) {
            $activity->delete();
        }

        $posts = \humhub\modules\post\models\Post::find()->contentContainer($space)
            ->andWhere(['like', 'post.message', 'uffenheim.de/nachrichten/artikel/'])->all();
        foreach ($posts as $post) {
            if ($post->content->created_by != $user->id) {
                $post->content->updateAttributes(['created_by' => $user->id]);
                $post->updateAttributes(['created_by' => $user->id]);
                \humhub\modules\file\models\File::updateAll(['created_by' => $user->id],
                    ['object_model' => $post::class, 'object_id' => $post->id]);
                $post->follow($user->id);
                $this->stdout("~ Beitrag {$post->id}\n");
            }
        }
        return ExitCode::OK;
    }

    /**
     * Nachrichten der Stadt (Atom-Feed der Website) als Beiträge im Space „Rathaus Uffenheim“:
     * setup/rss  (per Cron stündlich). Artikel werden am Link im Beitragstext erkannt, geänderte
     * Artikel aktualisiert. Datum im Stream = Veröffentlichungsdatum auf der Website.
     * Beim ersten Import und für Artikel älter als 7 Tage gibt es keine Benachrichtigungen.
     * setup/rss stadt-uffenheim 1  zeigt die Beiträge nur an, ohne zu speichern.
     */
    public function actionRss($ownerUsername = self::RSS_USER, $dryRun = 0)
    {
        $owner = User::findOne(['username' => $ownerUsername]);
        if ($owner === null) {
            $this->stderr("Konto $ownerUsername fehlt, zuerst setup/rssuser ausführen\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->user->switchIdentity($owner);
        $space = Space::findOne(['url' => 'rathaus-uffenheim']);
        if ($space === null) {
            $this->stderr("Space fehlt, zuerst setup/spaces ausführen\n");
            return ExitCode::DATAERR;
        }

        $xml = @simplexml_load_string($this->download(self::RSS_URL));
        if ($xml === false || !isset($xml->entry)) {
            $this->stderr(date('Y-m-d H:i:s') . ": Feed nicht lesbar\n");
            return ExitCode::UNAVAILABLE;
        }

        $imported = fn() => \humhub\modules\post\models\Post::find()->contentContainer($space)
            ->andWhere(['like', 'post.message', 'uffenheim.de/nachrichten/artikel/']);
        $firstRun = !$imported()->exists();

        // Älteste zuerst, damit neuere Artikel im Stream oben stehen
        $entries = array_reverse(iterator_to_array($xml->entry, false));
        $new = $changed = $failed = 0;
        foreach ($entries as $entry) {
            $link = (string)($entry->link['href'] ?? $entry->id);
            $published = strtotime((string)$entry->published) ?: time();
            $date = date('Y-m-d H:i:s', $published);
            [$text, $images] = $this->htmlToMarkdown((string)$entry->content, $link);
            $message = '**' . trim(html_entity_decode((string)$entry->title, ENT_QUOTES | ENT_HTML5)) . "**\n\n"
                . ($text !== '' ? $text . "\n\n" : '')
                . '[Artikel auf uffenheim.de](' . $link . ')';
            if ($dryRun) {
                $this->stdout("----- $date\n$message\n" . implode("\n", $images) . "\n");
                continue;
            }

            $post = $imported()->andWhere(['like', 'post.message', '(' . $link . ')'])->one();
            if ($post !== null) {
                if ($post->message !== $message) {
                    $post->message = $message;
                    $post->save();
                    $post->content->updateAttributes(['stream_sort_date' => $date]);
                    $this->stdout("~ $link\n");
                    $changed++;
                }
                continue;
            }

            $post = new \humhub\modules\post\models\Post($space);
            $post->message = $message;
            $post->silentContentCreation = $firstRun || $published < time() - 7 * 86400;
            $post->content->visibility = \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
            if (!$post->save()) {
                $this->stderr("! $link: " . json_encode($post->getErrors()) . "\n");
                $failed++;
                continue;
            }
            // Bilder herunterladen und anhängen, statt sie von der Stadt-Website nachzuladen (Datenschutz)
            foreach ($images as $url) {
                $this->attachImage($post, $url);
            }
            $post->content->updateAttributes(['created_at' => $date, 'updated_at' => $date, 'stream_sort_date' => $date]);
            $this->stdout("+ $link" . ($post->silentContentCreation ? ' (still)' : '') . "\n");
            $new++;
        }
        if (!$dryRun) {
            $this->stdout(date('Y-m-d H:i:s') . ': ' . count($entries) . " Artikel im Feed, $new neu, $changed geändert"
                . ($failed ? ", $failed fehlgeschlagen" : '') . "\n");
        }
        return $failed ? ExitCode::SOFTWARE : ExitCode::OK;
    }

    private const EVENTS_URL = 'https://www.uffenheim.de/erleben/veranstaltungen/veranstaltungskalender/rss';
    private const EVENT_UID = 'uffenheim-de-';
    // Kategorie auf uffenheim.de -> Farbe im Kalender
    private const EVENT_COLORS = [
        'Kirchweihen' => '#c0392b',
        'Heimatfeste, Märkte, Events' => '#e67e22',
        'Vereinsveranstaltungen, JHV, Sommerfeste, Jubiläen usw.' => '#7b3fa0',
        'Kirchliche Veranstaltungen, Konzerte usw.' => '#1f5f8b',
        'Häckerstuben, Weinstuben' => '#8e1b4f',
        'Museen - Öffnungszeiten' => '#6d5c3d',
        'Veranstaltungen für Kinder' => '#16a085',
        'Veranstaltungen für Senioren' => '#2e7d32',
        'Vorträge' => '#34495e',
    ];

    /**
     * Veranstaltungskalender der Stadt als Termine im Space „Veranstaltungen“, Verfasser
     * „Stadt Uffenheim (Website)“: setup/veranstaltungen  (per Cron täglich).
     * Zeiten kommen aus dem Atom-Feed, Ort, Veranstalter und Text von der Detailseite.
     * Termine werden an der UID erkannt und bei Änderungen aktualisiert. Fehlt ein künftiger Termin
     * im Feed, wird er gelöscht, sofern noch niemand zugesagt oder kommentiert hat.
     * Beim ersten Import keine Benachrichtigungen, und nur Termine der nächsten 30 Tage im Stream.
     * setup/veranstaltungen 1  zeigt die Termine nur an, ohne zu speichern.
     */
    public function actionVeranstaltungen($dryRun = 0)
    {
        $owner = User::findOne(['username' => self::RSS_USER]);
        if ($owner === null) {
            $this->stderr("Konto " . self::RSS_USER . " fehlt, zuerst setup/rssuser ausführen\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->user->switchIdentity($owner);
        $space = Space::findOne(['url' => 'veranstaltungen']);
        if ($space === null || !$space->isMember($owner->id)) {
            $this->stderr("Space fehlt oder Konto ist kein Mitglied, zuerst setup/rssuser ausführen\n");
            return ExitCode::DATAERR;
        }

        $xml = @simplexml_load_string($this->download(self::EVENTS_URL));
        if ($xml === false || !isset($xml->entry)) {
            $this->stderr("Feed nicht lesbar\n");
            return ExitCode::UNAVAILABLE;
        }

        $events = [];
        foreach ($xml->entry as $entry) {
            $link = (string)($entry->link['href'] ?? $entry->id);
            if (!preg_match('/Start: \w+, (\d+ \S+ \d{4} [\d:]+)(?:, Ende: \w+, (\d+ \S+ \d{4} [\d:]+))?/u',
                (string)$entry->summary, $m)) {
                $this->stderr("! ohne Datum: $link\n");
                continue;
            }
            $start = $this->germanDate($m[1]);
            // Ohne Endzeit eine Stunde, HumHub verlangt ein Ende nach dem Beginn
            $end = !empty($m[2]) ? $this->germanDate($m[2]) : $start + 3600;
            if ($end <= $start) {
                $end = $start + 3600;
            }
            $events[self::EVENT_UID . substr(sha1($link), 0, 20)] = [
                'link' => $link,
                'title' => trim(html_entity_decode((string)$entry->title, ENT_QUOTES | ENT_HTML5)),
                'start' => date('Y-m-d H:i:s', $start),
                'end' => date('Y-m-d H:i:s', $end),
                'category' => (string)($entry->category['term'] ?? ''),
            ];
        }
        if (count($events) < 5) {
            // Lieber nichts löschen, wenn der Feed kaputt oder fast leer ankommt
            $this->stderr("Nur " . count($events) . " Termine im Feed, Abbruch\n");
            return ExitCode::UNAVAILABLE;
        }

        $query = fn() => \humhub\modules\calendar\models\CalendarEntry::find()->contentContainer($space)
            ->andWhere(['like', 'calendar_entry.uid', self::EVENT_UID . '%', false]);
        $firstRun = !$query()->exists();
        $stats = ['neu' => 0, 'geändert' => 0, 'gelöscht' => 0];

        // Späteste zuerst anlegen, damit im Stream die nächsten Termine oben stehen
        uasort($events, fn($a, $b) => strcmp($b['start'], $a['start']));
        foreach ($events as $uid => $ev) {
            try {
                [$ev['location'], $ev['description'], $images] = $this->eventDetails($ev['link']);
            } catch (\Throwable $e) {
                $this->stderr("! {$ev['link']}: {$e->getMessage()}\n");
                unset($events[$uid]); // nicht als „fehlt im Feed“ behandeln
                continue;
            }
            usleep(300000);
            if ($dryRun) {
                $this->stdout("----- {$ev['start']} – {$ev['end']} [{$ev['category']}] {$ev['title']}\n"
                    . "Ort: {$ev['location']}\n{$ev['description']}\n" . implode("\n", $images) . "\n");
                continue;
            }

            $entry = $query()->andWhere(['calendar_entry.uid' => $uid])->one();
            $isNew = $entry === null;
            if ($isNew) {
                $entry = new \humhub\modules\calendar\models\CalendarEntry($space);
                $entry->uid = $uid;
                $entry->time_zone = 'Europe/Berlin';
                $entry->all_day = 0;
                $entry->content->visibility = \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
                $entry->silentContentCreation = $firstRun;
            }
            $new = ['title' => mb_substr($ev['title'], 0, 255), 'description' => $ev['description'],
                'location' => mb_substr($ev['location'], 0, 1000), 'start_datetime' => $ev['start'],
                'end_datetime' => $ev['end']];
            if (!$isNew && array_diff_assoc($new, $entry->getAttributes(array_keys($new))) === []) {
                continue;
            }
            $entry->setAttributes($new, false);
            if ($isNew) {
                $entry->setDefaults();
            }
            if (!$entry->save()) {
                $this->stderr("! {$ev['link']}: " . json_encode($entry->getErrors()) . "\n");
                continue;
            }
            $calendarOnly = $isNew && $firstRun && strtotime($ev['start']) > time() + 30 * 86400;
            if ($calendarOnly) {
                $entry->content->updateAttributes(['stream_channel' => null]);
            }
            if ($isNew) {
                if ($ev['category'] !== '') {
                    $type = $this->eventType($space, $ev['category']);
                    $entry->setType($type);
                    // Kalender färbt nach der Farbe des Termins, nicht der Kategorie
                    $entry->updateAttributes(['color' => $type->color]);
                }
                foreach ($images as $url) {
                    $this->attachImage($entry, $url);
                }
            }
            $stats[$isNew ? 'neu' : 'geändert']++;
            $this->stdout(($isNew ? '+ ' : '~ ') . "{$ev['start']} {$ev['title']}"
                . ($calendarOnly ? ' (nur Kalender)' : '') . "\n");
        }
        if ($dryRun) {
            return ExitCode::OK;
        }

        // Künftige Termine, die die Stadt entfernt hat. Laufende/vergangene fallen aus dem Feed und bleiben.
        foreach ($query()->andWhere(['>', 'calendar_entry.start_datetime', date('Y-m-d H:i:s', time() + 86400)])
                     ->andWhere(['not in', 'calendar_entry.uid', array_keys($events)])->all() as $entry) {
            $used = \humhub\modules\calendar\models\CalendarEntryParticipant::find()
                    ->where(['calendar_entry_id' => $entry->id])->exists()
                || \humhub\modules\comment\models\Comment::find()
                    ->where(['object_model' => $entry::class, 'object_id' => $entry->id])->exists();
            if ($used) {
                $this->stdout("? nicht mehr auf uffenheim.de, bleibt wegen Zusagen/Kommentaren: {$entry->title}\n");
            } else {
                $entry->hardDelete();
                $stats['gelöscht']++;
                $this->stdout("- {$entry->start_datetime} {$entry->title}\n");
            }
        }
        $this->stdout(date('Y-m-d H:i:s') . ': ' . count($events) . ' Termine im Feed, '
            . implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($stats), $stats)) . "\n");
        return ExitCode::OK;
    }

    /** "05 Okt 2026 19:00:00" (Ortszeit) als Timestamp */
    private function germanDate($s)
    {
        $months = ['Jan' => 1, 'Feb' => 2, 'Mär' => 3, 'Mrz' => 3, 'Apr' => 4, 'Mai' => 5, 'Jun' => 6,
            'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Okt' => 10, 'Nov' => 11, 'Dez' => 12];
        [$d, $mon, $y, $t] = explode(' ', $s);
        $mon = $months[mb_substr($mon, 0, 3)] ?? throw new \RuntimeException("Monat unbekannt: $s");
        return (new \DateTime(sprintf('%04d-%02d-%02d %s', $y, $mon, $d, $t), new \DateTimeZone('Europe/Berlin')))
            ->getTimestamp();
    }

    /** Ort, Beschreibung (Markdown) und Bilder von der Detailseite einer Veranstaltung */
    private function eventDetails($link)
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $this->download($link), LIBXML_NOERROR);
        $xp = new \DOMXPath($doc);
        $cls = fn($c) => "contains(concat(' ', normalize-space(@class), ' '), ' $c ')";
        $article = $xp->query("//article[{$cls('event-detail')}]")->item(0);
        if ($article === null) {
            throw new \RuntimeException('Detailseite ohne Veranstaltung');
        }
        $text = fn($q) => trim(preg_replace('/\s+/u', ' ', $xp->query($q, $article)->item(0)?->textContent ?? ''));
        $location = $text(".//*[{$cls('event-location')}]//p") ?: $text(".//*[{$cls('calendar-detail--meta--location')}]");
        $organizer = $text(".//*[{$cls('event-detail--footer-badge--content')}]");

        $body = $xp->query(".//*[{$cls('event-detail--text')}]", $article)->item(0);
        [$description, $images] = $body ? $this->htmlToMarkdown($doc->saveHTML($body), $link) : ['', []];
        // Bilder der Veranstaltung außerhalb des Textes (Titelbild, Galerie)
        foreach ($xp->query('.//img', $article) as $img) {
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            if ($src !== '' && !str_contains($src, '/_assets/')) {
                $images[] = preg_match('#^https?://#', $src) ? $src : 'https://www.uffenheim.de/' . ltrim($src, '/');
            }
        }
        $description = trim($description
            . ($organizer !== '' ? "\n\n**Veranstalter:** $organizer" : '')
            . "\n\n[Veranstaltung auf uffenheim.de]($link)");
        return [$location, $description, array_values(array_unique($images))];
    }

    private function eventType(Space $space, $name)
    {
        $type = \humhub\modules\calendar\models\CalendarEntryType::find()
            ->where(['content_tag.module_id' => 'calendar', 'content_tag.name' => $name,
                'content_tag.contentcontainer_id' => $space->contentcontainer_id])->one();
        if ($type === null) {
            $type = new \humhub\modules\calendar\models\CalendarEntryType($space);
            $type->name = $name;
            $type->color = self::EVENT_COLORS[$name] ?? '#7f8c8d';
            $type->save();
            $this->stdout("+ Kategorie $name\n");
        }
        return $type;
    }

    /**
     * KI-Assistent testen: setup/ki  zeigt die Daten, die an Claude gehen;
     * setup/ki "Wann kommt die Biotonne?"  stellt die Frage (zählt nur im Testzähler, nicht zur Monatsgrenze).
     * setup/ki "Frage" 1  zeigt nur, welche Seiten des Stadtwissens mitgeschickt würden.
     */
    public function actionKi($question = null, $dryRun = 0)
    {
        $module = Yii::$app->getModule('ki-assistent');
        $teile = \humhub\modules\kiAssistent\controllers\ChatController::systemPrompt((string)$question);
        $prompt = implode("\n\n", $teile);
        if ($question === null) {
            $this->stdout($prompt . "\n\n" . mb_strlen($prompt) . " Zeichen, Modell " . $module->getModel() . "\n");
            return ExitCode::OK;
        }
        if ($dryRun) {
            preg_match_all('/^### (.+)$/m', $prompt, $m);
            $this->stdout(implode("\n", array_map(fn($t) => "  $t", $m[1])) . "\n  = " . mb_strlen($prompt) . " Zeichen\n");
            return ExitCode::OK;
        }
        if ($module->getApiKey() === null) {
            $this->stderr("Kein Schlüssel in secrets/anthropic_api_key\n");
            return ExitCode::CONFIG;
        }
        $t = microtime(true);
        [$answer, $in, $out] = (new \humhub\modules\kiAssistent\components\Claude($module->getApiKey(), $module->getModel()))
            ->ask($teile, [['role' => 'user', 'text' => $question]]);
        $module->addTestUsage($in, $out);
        $this->stdout($answer . "\n\n(" . round(microtime(true) - $t, 1) . " s, $in Eingabe- / $out Ausgabe-Tokens)\n");
        return ExitCode::OK;
    }

    /**
     * Zeitbedarf beim Aufbau des KI-Prompts je Schritt (ohne Anfrage an Claude): setup/ki-zeit "Frage"
     */
    public function actionKiZeit($question = 'Wo kann ich in Uffenheim Döner essen?')
    {
        $w = \humhub\modules\kiAssistent\components\Wissen::class;
        $ctx = \humhub\modules\kiAssistent\components\Context::class;
        $call = fn($name, ...$args) => (new \ReflectionMethod($w, $name))->invoke(null, ...$args);
        $mess = function ($was, callable $f) {
            $t = microtime(true);
            $r = $f();
            $this->stdout(sprintf("%7.0f ms  %s\n", 1000 * (microtime(true) - $t), $was));
            return $r;
        };
        $chunks = $mess('Abschnitte laden (ohne Vektoren, zwischengespeichert)', fn() => $w::chunks());
        $chunks = $mess('Sichtbarkeit filtern', fn() => $call('visible', $chunks));
        [, $rang] = $mess('Suche nach Bedeutung (Embedding-Dienst)', fn() => \humhub\modules\kiAssistent\components\Embeddings::suche($question));
        $mess('Stichwortsuche BM25', fn() => $call('rankByKeywords', $chunks, $question));
        $mess('Wissen::forQuestion gesamt', fn() => $w::forQuestion($question, fn($q) => \humhub\modules\kiAssistent\components\Embeddings::suche($q), false));
        $mess('Wissen::immer', fn() => $w::immer());
        $mess('Context::stabil (Zwischenspeicher)', fn() => $ctx::stabil());
        $mess('Context::aktuell', fn() => $ctx::aktuell());
        $mess('systemPrompt gesamt', fn() => \humhub\modules\kiAssistent\controllers\ChatController::systemPrompt($question));
        return ExitCode::OK;
    }

    /**
     * Altersbestätigung des KI-Assistenten anzeigen/setzen/zurücksetzen:
     * setup/ki-alter <benutzername> [zeigen|setzen|zuruecksetzen]
     */
    /**
     * Testfragen des KI-Assistenten prüfen: setup/ki-test [nur diese Nummer] [1 = Antworten immer ausgeben]
     * modules/ki-assistent/data/testfragen.json: je Frage „erwartet“ (Muster, die vorkommen müssen), „nicht“ (Muster, die
     * nicht vorkommen dürfen) und/oder „karte“ (erwartete Aktion: link, termin, download). Kostet je Frage 1–3 Anfragen.
     */
    public function actionKiTest($nr = null, $zeigen = 0)
    {
        Yii::$app->user->switchIdentity(User::findOne(['username' => 'admin']));
        $module = Yii::$app->getModule('ki-assistent');
        if ($module->getApiKey() === null) {
            $this->stderr("Kein Schlüssel in secrets/anthropic_api_key\n");
            return ExitCode::CONFIG;
        }
        $tests = json_decode(file_get_contents($module->getBasePath() . '/data/testfragen.json'), true);
        $claude = new \humhub\modules\kiAssistent\components\Claude($module->getApiKey(), $module->getModel());
        $ok = 0;
        $run = 0;
        foreach ($tests as $i => $t) {
            if ($nr !== null && (int)$nr !== $i + 1) {
                continue;
            }
            $run++;
            $history = [['role' => 'user', 'text' => $t['frage']]];
            $start = microtime(true);
            try {
                [$answer, $cards, , $in, $out] = \humhub\modules\kiAssistent\controllers\ChatController::converse($claude,
                    \humhub\modules\kiAssistent\controllers\ChatController::systemPrompt($t['frage']), $history);
                $module->addTestUsage($in, $out);
            } catch (\Throwable $e) {
                $this->stdout(sprintf("%2d ✗ %s\n   FEHLER %s\n", $i + 1, $t['frage'], $e->getMessage()));
                continue;
            }
            $problems = [];
            // Antwort plus Text und Adressen der Karten (z. B. Mitbring-Liste beim Rathaus-Termin, Kartenlinks)
            $haystack = $answer . "\n" . implode("\n", array_map(fn($c) => ($c['titel'] ?? '') . ' ' . implode(' ', $c['zeilen'] ?? [])
                . ' ' . ($c['url'] ?? '') . ' ' . implode(' ', array_column($c['knoepfe'] ?? [], 'url')), $cards));
            foreach ($t['erwartet'] ?? [] as $re) {
                if (!preg_match("/$re/iu", $haystack)) {
                    $problems[] = "fehlt /$re/";
                }
            }
            foreach ($t['nicht'] ?? [] as $re) {
                if (preg_match("/$re/iu", $answer)) {
                    $problems[] = "enthält /$re/";
                }
            }
            if (isset($t['karte']) && !in_array($t['karte'], array_column($cards, 'typ'), true)) {
                $problems[] = "keine Aktion „{$t['karte']}“";
            }
            foreach ($cards as $c) {
                if (preg_match('/token=([0-9a-f]{32})/', $c['url'] ?? '', $m)) {
                    @unlink(Yii::getAlias("@runtime/ki-formulare/{$m[1]}.pdf"));
                    @unlink(Yii::getAlias("@runtime/ki-formulare/{$m[1]}.json"));
                }
            }
            $problems ? null : $ok++;
            $this->stdout(sprintf("%2d %s %s (%.1f s%s)\n", $i + 1, $problems ? '✗' : '✓', $t['frage'],
                microtime(true) - $start, $cards ? ', Aktion: ' . implode('/', array_column($cards, 'typ')) : ''));
            if ($zeigen && !$problems) {
                $this->stdout("   Antwort: $answer\n");
            }
            if ($problems) {
                $this->stdout('   ' . implode('; ', $problems) . "\n   Antwort: " . mb_substr(preg_replace('/\s+/u', ' ', $answer), 0, 400) . "\n");
            }
        }
        $this->stdout("$ok von $run Testfragen bestanden\n");
        return ExitCode::OK;
    }

    /** Menüpunkt „Termin im Rathaus“ (Link zur Online-Terminbuchung der Stadt) im Space „Rathaus Uffenheim“: setup/rathaus-termin */
    public function actionRathausTermin()
    {
        Yii::$app->user->switchIdentity(User::findOne(['username' => 'admin']));
        $space = Space::findOne(['url' => 'rathaus-uffenheim']);
        if ($space === null) {
            $this->stderr("Space Rathaus Uffenheim fehlt\n");
            return ExitCode::DATAERR;
        }
        if (!$space->moduleManager->isEnabled('custom_pages')) {
            $space->moduleManager->enable('custom_pages');
            $this->stdout("+ custom_pages im Space {$space->name}\n");
        }
        $url = Yii::$app->getModule('ki-assistent')->terminlandUrl;
        $title = 'Termin im Rathaus';
        $page = \humhub\modules\custom_pages\models\CustomPage::find()->contentContainer($space)
            ->andWhere(['custom_pages_page.title' => $title])->one();
        if ($page === null) {
            $page = new \humhub\modules\custom_pages\models\CustomPage($space);
        }
        $page->type = \humhub\modules\custom_pages\types\LinkType::ID;
        $page->title = $title;
        $page->icon = 'fa-calendar-check-o';
        $page->page_content = $url;
        $page->target = \humhub\modules\custom_pages\helpers\PageType::TARGET_SPACE_MENU;
        $page->in_new_window = 1;
        $page->sort_order = 5;
        $page->visibility = \humhub\modules\custom_pages\models\CustomPage::VISIBILITY_PUBLIC;
        $page->abstract = 'Online-Terminbuchung der Stadt Uffenheim (Terminland) für Einwohnermeldeamt, Passamt, Standesamt, Gewerbeamt, Ordnungsamt und Fundsachen.';
        if (!$page->save()) {
            $this->stderr('Menüpunkt: ' . json_encode($page->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        // Nur Menüpunkt, kein Eintrag im Stream des Space
        $page->content->updateAttributes(['stream_channel' => null]);
        $this->stdout("Menüpunkt „{$title}“ → $url (id {$page->id})\n");
        return ExitCode::OK;
    }

    /**
     * Einmalige Ankündigung des Mitteilungsblatts im Space „Rathaus Uffenheim“ (Verfasser: admin, mit Titelbild der
     * neuesten Ausgabe; diese bekommt dadurch keinen eigenen Beitrag mehr): setup/mitteilungsblatt-ankuendigung [1=Probelauf]
     */
    public function actionMitteilungsblattAnkuendigung($dryRun = 0)
    {
        $ausgaben = \humhub\modules\mitteilungsblatt\models\Ausgabe::find()->orderBy(['jahr' => SORT_DESC, 'nr' => SORT_DESC]);
        $neueste = (clone $ausgaben)->one();
        $erstesJahr = (clone $ausgaben)->min('jahr');
        if ($neueste === null) {
            $this->stderr("Noch keine Ausgabe im Archiv\n");
            return ExitCode::DATAERR;
        }
        $url = \yii\helpers\Url::to(['/mitteilungsblatt/index'], true);
        $message = "**Neu in der App: das Mitteilungsblatt der Stadt Uffenheim** 📰\n\n"
            . "Ab sofort können Sie das Mitteilungsblatt direkt hier lesen – auf dem Handy wie am Computer:\n\n"
            . "- **Jede neue Ausgabe** kommt automatisch, sobald die Stadt sie veröffentlicht, mit Hinweis hier im Space.\n"
            . "- **Blättern, zoomen und im Heft suchen** im eingebauten Reader.\n"
            . "- **Archiv mit {$ausgaben->count()} Ausgaben seit $erstesJahr** und Suche über alle Ausgaben – z. B. nach „Kirchweih“, „Sperrung“ oder Ihrem Verein.\n"
            . "- Hinweis auf Erscheinungstermin und Redaktionsschluss der nächsten Ausgabe.\n\n"
            . "Zu finden hier im Space „Rathaus Uffenheim“ unter „Mitteilungsblatt“ und im Menü unter „Mehr“. "
            . "Aktuell ist die Ausgabe {$neueste->titel} vom {$neueste->datumText}.\n\n"
            . "Herausgeberin ist die Stadt Uffenheim – vielen Dank für die Erlaubnis, es hier anzubieten!\n\n"
            . "[Zum Mitteilungsblatt]($url)";
        if ($dryRun) {
            $this->stdout($message . "\n");
            return ExitCode::OK;
        }
        $admin = User::findOne(['username' => 'admin']);
        $space = Space::findOne(['url' => 'rathaus-uffenheim']);
        Yii::$app->user->switchIdentity($admin);
        $vorhanden = \humhub\modules\post\models\Post::find()->contentContainer($space)
            ->andWhere(['like', 'post.message', 'Neu in der App: das Mitteilungsblatt'])->exists();
        if ($vorhanden) {
            $this->stdout("Ankündigung gibt es schon\n");
            return ExitCode::OK;
        }
        $post = new \humhub\modules\post\models\Post($space);
        $post->message = $message;
        $post->content->visibility = \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
        if (!$post->save()) {
            $this->stderr(json_encode($post->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (is_file($neueste->bildPfad)) {
            $file = new \humhub\modules\file\models\File(['file_name' => "Mitteilungsblatt-{$neueste->name}.jpg",
                'mime_type' => 'image/jpeg', 'size' => filesize($neueste->bildPfad)]);
            $file->save();
            $file->setStoredFileContent(file_get_contents($neueste->bildPfad));
            $post->fileManager->attach($file);
        }
        $neueste->updateAttributes(['post_id' => $post->id]);
        $this->stdout("Ankündigung angelegt (Beitrag {$post->id})\n");
        return ExitCode::OK;
    }

    public function actionKiAlter($username, $mode = 'zeigen')
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("Konto $username fehlt\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->user->switchIdentity($user);
        $module = Yii::$app->getModule('ki-assistent');
        if ($mode === 'setzen') {
            $module->confirmAge();
        } elseif ($mode === 'zuruecksetzen') {
            $module->settings->user()->delete('age-confirmed');
        }
        $this->stdout("$username: " . ($module->settings->user()->get('age-confirmed') ?: 'nicht bestätigt') . "\n");
        return ExitCode::OK;
    }

    /**
     * Menüpunkt „Mitglieder“ (Personenverzeichnis /user/people) für alle Gruppen ausblenden: setup/mitglieder
     * Das Recht „Zugriff auf Mitglieder“ steht in HumHub ohne gespeicherten Wert für jede Gruppe auf „Erlauben“,
     * auch für Administratoren – daher je Gruppe ausdrücklich verweigern (Admins finden Nutzer unter Administration).
     */
    public function actionMitglieder()
    {
        $permission = new \humhub\modules\user\permissions\PeopleAccess();
        foreach (\humhub\modules\user\models\Group::find()->all() as $group) {
            Yii::$app->user->permissionManager->setGroupState($group->id, $permission, \humhub\libs\BasePermission::STATE_DENY);
            $this->stdout("Mitglieder verborgen für Gruppe {$group->name}\n");
        }
        Yii::$app->cache->flush();
        return ExitCode::OK;
    }

    /**
     * Benachrichtigungen über neue Beiträge (Push, Glocke, Mail) standardmäßig nur aus „Rathaus Uffenheim“:
     * setup/push-rathaus
     * Setzt die Standard-Spaces auf Rathaus und alle Konten auf diesen Standard zurück (eigene Space-Auswahl
     * wird verworfen, send_notifications überall 0, „is_touched_settings“ gelöscht). Wer später in den
     * Benachrichtigungseinstellungen andere Spaces wählt, behält seine Wahl. Andere Kategorien (Kommentare,
     * Erwähnungen, Abfuhr-Erinnerung, Bauhof …) bleiben unverändert.
     */
    public function actionPushRathaus()
    {
        $rathaus = Space::findOne(['url' => 'rathaus-uffenheim']);
        if ($rathaus === null) {
            $this->stderr("Space rathaus-uffenheim nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->notification->setSpaces([$rathaus->guid]);
        Yii::$app->notification->resetSpaces();
        $geloescht = \humhub\modules\content\models\ContentContainerSetting::deleteAll([
            'module_id' => 'notification',
            'name' => \humhub\modules\notification\components\NotificationManager::IS_TOUCHED_SETTINGS,
        ]);
        Yii::$app->cache->flush();
        $this->stdout("Standard-Spaces für Benachrichtigungen: {$rathaus->name}; $geloescht Konten auf Standard zurückgesetzt\n");
        return ExitCode::OK;
    }

    /**
     * Probelauf Vereinsbeiträge: setup/vereinsbeitrag-test <verwalter-username> <prüf-username>
     * Legt als Verwalter einen Testbeitrag für dessen ersten Verein an, gibt ihn für Vereinsseite + Übersicht frei,
     * prüft, ob er in der Übersicht der zweiten Person (kein Mitglied des Verzeichnis-Space) und von Gästen
     * erscheint, und entfernt ihn wieder. Ohne Benachrichtigungen und Mails.
     */
    public function actionVereinsbeitragTest($verwalter, $pruefer, $behalten = 0)
    {
        $autor = User::findOne(['username' => $verwalter]);
        $leser = User::findOne(['username' => $pruefer]);
        $vv = $autor ? \humhub\modules\vereine\models\VereinVerwalter::findOne(['user_id' => $autor->id, 'status' => 'bestaetigt']) : null;
        if (!$autor || !$leser || !$vv) {
            $this->stderr("Verwalter/Leser nicht gefunden oder kein bestätigter Verwalter\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->user->switchIdentity($autor); // Verfasser = Prüfer → keine Glocke „freigegeben“
        Yii::$app->getModule('dashboard')->memberFilterClass = \humhub\modules\uebersicht\filters\MeineUebersicht::class;
        $b = new \humhub\modules\vereine\models\VereinBeitrag(['verein_id' => $vv->verein_id, 'user_id' => $autor->id,
            'status' => 'eingereicht', 'text' => 'TEST – bitte ignorieren (Probelauf Vereinsbeiträge)']);
        $b->save(false);
        \humhub\modules\vereine\components\BeitragDienst::freigeben($b, true, true);
        $b->refresh();
        $this->stdout("Beitrag {$b->id} für „{$b->verein->name}“: Status {$b->status}, Content {$b->content_id}\n");
        // wie die Übersicht im Browser: Beitrags-Stream (nicht der Aktivitäten-Kanal), aus Sicht der jeweiligen Person
        $ids = function (?User $user) {
            Yii::$app->user->switchIdentity($user);
            $q = new \humhub\modules\dashboard\stream\DashboardStreamQuery(['user' => $user, 'limit' => 5]);
            return array_map(fn($c) => $c->id, $q->all());
        };
        $this->stdout("Übersicht {$leser->username}: " . (in_array($b->content_id, $ids($leser)) ? 'sichtbar' : 'NICHT sichtbar') . "\n");
        $this->stdout('Übersicht Gast: ' . (in_array($b->content_id, $ids(null)) ? 'sichtbar' : 'NICHT sichtbar') . "\n");
        Yii::$app->getModule('dashboard')->memberFilterClass = \humhub\modules\dashboard\stream\filters\DashboardMemberStreamFilter::class;
        $this->stdout("Gegenprobe ohne Erweiterung, {$leser->username}: " . (in_array($b->content_id, $ids($leser)) ? 'sichtbar' : 'nicht sichtbar') . "\n");
        Yii::$app->getModule('dashboard')->memberFilterClass = \humhub\modules\uebersicht\filters\MeineUebersicht::class;
        $rest = array_filter($ids($leser), fn($id) => $id !== $b->content_id);
        $this->stdout('Übrige Übersicht unverändert lieferbar: ' . count($rest) . " weitere Einträge\n");
        // Text der Ablehnungs-Mail (wird hier nur erzeugt, nicht verschickt)
        $b->begruendung = 'Beispiel-Begründung';
        $this->stdout("--- Ablehnungs-Mail (Vorschau) ---\n" . Yii::$app->view->renderFile('@vereine/views/mail/abgelehnt_text.php', [
            'beitrag' => $b, 'name' => $autor->displayName, 'link' => $b->verein->getUrl(true), 'kontaktMail' => 'smartcity@buergerliste-uffenheim.de',
        ]) . "---\n");
        if ($behalten) {
            $this->stdout("Behalten – entfernen mit: setup/vereinsbeitrag-entfernen {$b->id}\n");
            return ExitCode::OK;
        }
        \humhub\modules\vereine\components\BeitragDienst::entfernen($b);
        $this->stdout('Entfernt: ' . (\humhub\modules\content\models\Content::findOne($b->content_id) ? 'NEIN' : 'ja') . "\n");
        return ExitCode::OK;
    }

    /**
     * Mängelmeldungen für alle Angemeldeten: setup/maengelmelder-alle
     * Alle Konten (außer Import-/Systemkonten) werden still Mitglied im Space `mangelmelder`, neue automatisch
     * (auto_add). Die Meldungen bleiben „nur für Mitglieder“ – Gäste sehen weiter nur die Karte (Datenschutz Abschn. 9).
     * Benachrichtigungen aus dem Space: aus (Standard nur Rathaus); ob sie in der Übersicht erscheinen, regelt
     * „Meine Übersicht“. Idempotent.
     */
    public function actionMaengelmelderAlle()
    {
        $space = Space::findOne(['url' => 'mangelmelder']);
        if ($space === null) {
            $this->stderr("Space mangelmelder nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        $space->updateAttributes(['auto_add_new_members' => 1]);
        $uebersicht = Yii::$app->getModule('uebersicht');
        $neu = 0;
        foreach (User::find()->active()->all() as $user) {
            if (in_array($user->username, [self::RSS_USER, self::FB_USER, 'gastmeldung'], true) || $space->isMember($user->id)) {
                continue;
            }
            $space->addMember($user->id, 1, true);
            $m = \humhub\modules\space\models\Membership::findOne(['space_id' => $space->id, 'user_id' => $user->id]);
            $m?->updateAttributes(['send_notifications' => 0,
                'show_at_dashboard' => $uebersicht && in_array($space->guid, $uebersicht->getAusgeblendet($user), true) ? 0 : 1]);
            $this->stdout("+ Mitglied {$user->username}\n");
            $neu++;
        }
        $this->stdout("Mängelmelder: $neu neue Mitglieder, neue Konten werden automatisch aufgenommen\n");
        return ExitCode::OK;
    }

    /** Vereinsbeitrag samt HumHub-Beitrag entfernen: setup/vereinsbeitrag-entfernen <id> */
    public function actionVereinsbeitragEntfernen($id)
    {
        $b = \humhub\modules\vereine\models\VereinBeitrag::findOne((int)$id);
        if ($b === null) {
            $this->stderr("Beitrag $id nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        \humhub\modules\vereine\components\BeitragDienst::entfernen($b);
        $this->stdout("Beitrag $id entfernt\n");
        return ExitCode::OK;
    }

    /**
     * Minimale Profildaten: setup/profil-minimal [1=Probelauf]
     * Alle ausgeblendeten Profilfelder (außer Vor-/Nachname) nicht durchsuchbar und nicht bearbeitbar, Inhalte bei allen
     * Konten leeren, Online-Status ausblenden (Datenminimierung, Datenschutzerklärung Abschnitt 5). Idempotent.
     */
    public function actionProfilMinimal($probelauf = 0)
    {
        $felder = \humhub\modules\user\models\ProfileField::find()->where(['visible' => 0])
            ->andWhere(['not in', 'internal_name', ['firstname', 'lastname']])->all();
        $spalten = [];
        foreach ($felder as $feld) {
            if (!$probelauf && ($feld->searchable || $feld->editable)) {
                $feld->searchable = 0;
                $feld->editable = 0;
                $feld->save(false);
            }
            if (Yii::$app->db->getTableSchema('profile')->getColumn($feld->internal_name) !== null) {
                $spalten[] = $feld->internal_name;
            }
        }
        // Zusatzspalte des Geburtstagsfelds
        if (Yii::$app->db->getTableSchema('profile')->getColumn('birthday_hide_year') !== null) {
            $spalten[] = 'birthday_hide_year';
        }
        $befuellt = 0;
        foreach ($spalten as $s) {
            $befuellt += (int)(new \yii\db\Query())->from('profile')->where(['not', [$s => null]])->andWhere(['<>', $s, ''])->count();
        }
        $this->stdout(count($felder) . " ausgeblendete Felder, $befuellt befüllte Werte" . ($probelauf ? " (Probelauf)\n" : "\n"));
        if (!$probelauf) {
            Yii::$app->getModule('user')->settings->set('auth.hideOnlineStatus', 1); // Online-Status für niemanden sichtbar
        }
        if (!$probelauf && $spalten) {
            $n = \humhub\modules\user\models\Profile::updateAll(array_fill_keys($spalten, null));
            $this->stdout("Geleert in $n Profilen; Felder nicht durchsuchbar/bearbeitbar\n");
        }
        return ExitCode::OK;
    }

    /**
     * Keine Beiträge, Umfragen oder Fragen von Mitgliedern im Stream: setup/keine-beitraege [url1,url2] (Standard: veranstaltungen)
     * Space-Inhaber/-Admins/-Moderatoren dürfen weiter; Kommentare, Termine und Mängelmeldungen (eigenes Recht) bleiben. Idempotent.
     */
    public function actionKeineBeitraege($urls = 'veranstaltungen')
    {
        foreach (array_filter(array_map('trim', explode(',', $urls))) as $url) {
            $space = Space::findOne(['url' => $url]);
            if ($space === null) {
                $this->stderr("Space $url nicht gefunden\n");
                return ExitCode::DATAERR;
            }
            // Beiträge, Umfragen, Fragen – alles, was Mitglieder sonst in den Stream stellen könnten
            $rechte = array_filter([
                \humhub\modules\post\permissions\CreatePost::class,
                'humhub\modules\polls\permissions\CreatePoll',
                'humhub\modules\questions\permissions\CreateQuestion',
            ], 'class_exists');
            foreach ([Space::USERGROUP_MEMBER, Space::USERGROUP_USER] as $group) {
                foreach ($rechte as $recht) {
                    $space->permissionManager->setGroupState($group, new $recht(), \humhub\libs\BasePermission::STATE_DENY);
                }
            }
            $this->stdout("$url: Mitglieder und andere Nutzer stellen nichts in den Stream (" . count($rechte) . " Rechte)\n");
        }
        return ExitCode::OK;
    }

    /**
     * Einladen nur durch Admins: setup/einladen
     * Mitglieder können weder per E-Mail noch per Link einladen (die Knöpfe blendet designwahl aus).
     * Admins und Gruppen mit „Benutzer/Gruppen verwalten“ dürfen laut HumHub weiter einladen. Idempotent.
     */
    public function actionEinladen()
    {
        $user = Yii::$app->getModule('user')->settings;
        $user->set('auth.internalUsersCanInviteByEmail', 0);
        $user->set('auth.internalUsersCanInviteByLink', 0);
        $this->stdout("Einladen für Mitglieder aus (E-Mail und Link)\n");
        // In Spaces einladen: nur noch Space-Inhaber/-Admins/-Moderatoren (neue Spaces: Aktion erneut ausführen)
        foreach (Space::find()->all() as $space) {
            $space->permissionManager->setGroupState(Space::USERGROUP_MEMBER,
                new \humhub\modules\space\permissions\InviteUsers(), \humhub\libs\BasePermission::STATE_DENY);
            $this->stdout("{$space->url}: Mitglieder dürfen nicht einladen\n");
        }
        return ExitCode::OK;
    }

    /**
     * Lesen ohne Anmeldung: setup/gastzugang
     * Gastzugang an, Registrierung ohne Admin-Freigabe, alle Spaces „für alle sichtbar“. Inhalte der Stadt-/Admin-Konten
     * werden öffentlich, neue Inhalte dort standardmäßig auch – außer im Mängelmelder (Meldungen bleiben privat,
     * Gäste sehen nur die Karte). Beiträge von Bürgern bleiben, wie sie sind. Idempotent.
     */
    public function actionGastzugang()
    {
        $user = Yii::$app->getModule('user')->settings;
        $user->set('auth.allowGuestAccess', 1);
        $user->set('auth.needApproval', 0);
        $this->stdout("Gastzugang an, Registrierung ohne Freigabe\n");

        $konten = User::find()->select('id')->where(['username' => ['admin', self::RSS_USER, self::FB_USER]])->column();
        $arten = [
            \humhub\modules\post\models\Post::class,
            \humhub\modules\calendar\models\CalendarEntry::class,
            'humhub\modules\external_calendar\models\ExternalCalendar',
            'humhub\modules\external_calendar\models\ExternalCalendarEntry',
            \humhub\modules\custom_pages\models\CustomPage::class,
        ];
        foreach (Space::find()->all() as $space) {
            $privat = $space->url === 'mangelmelder';
            $space->visibility = Space::VISIBILITY_ALL;
            $space->default_content_visibility = $privat ? 0 : 1;
            $space->save(false);
            $n = 0;
            if (!$privat) {
                $n = \humhub\modules\content\models\Content::updateAll(
                    ['visibility' => \humhub\modules\content\models\Content::VISIBILITY_PUBLIC],
                    ['contentcontainer_id' => $space->contentcontainer_id, 'object_model' => $arten, 'created_by' => $konten,
                        'visibility' => \humhub\modules\content\models\Content::VISIBILITY_PRIVATE]);
            }
            $this->stdout("{$space->name}: für alle sichtbar, neue Inhalte " . ($privat ? 'privat' : 'öffentlich') . ", $n Inhalte öffentlich gemacht\n");
        }
        if (Yii::$app->hasModule('custom_pages')) {
            $n = \humhub\modules\custom_pages\models\CustomPage::updateAll(
                ['visibility' => \humhub\modules\custom_pages\models\CustomPage::VISIBILITY_PUBLIC],
                ['visibility' => \humhub\modules\custom_pages\models\CustomPage::VISIBILITY_PRIVATE]);
            $this->stdout("Eigene Seiten öffentlich: $n\n");
        }
        Yii::$app->cache->flush();
        return ExitCode::OK;
    }

    /**
     * Modul in einem Space ein-/ausschalten: setup/space-modul veranstaltungen gallery 0 (URL oder Name des Space)
     * Ausschalten entfernt den Menüpunkt; Inhalte des Moduls im Space (z. B. die automatische
     * Galerie „Posted pictures“) löscht HumHub dabei mit, Bilder an Beiträgen/Terminen bleiben.
     */
    public function actionSpaceModul($spaceName, $modulId, $an = 0)
    {
        $space = Space::findOne(['url' => $spaceName]) ?? Space::findOne(['name' => $spaceName]);
        if ($space === null) {
            $this->stderr("Space $spaceName nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        $mm = $space->moduleManager;
        if ($an) {
            $mm->isEnabled($modulId) || $mm->enable($modulId);
        } elseif ($mm->isEnabled($modulId)) {
            $mm->disable($modulId);
        }
        // Frisch laden: der ModuleManager merkt sich den alten Stand
        $aktiv = Space::findOne($space->id)->moduleManager->isEnabled($modulId);
        $this->stdout("$spaceName: $modulId " . ($aktiv ? 'an' : 'aus') . "\n");
        return ExitCode::OK;
    }

    /**
     * Profilmodule (Kalender, Galerie, Umfragen … im eigenen Profil) für Nutzer abschalten: setup/profilmodule
     * Setzt für alle aktivierten Module, die Profile unterstützen, „nicht verfügbar“ als Vorgabe für Benutzer;
     * Inhalte gibt es nur in Spaces. Profile, die ein Modul schon eingeschaltet haben, werden nur gemeldet.
     */
    public function actionProfilmodule()
    {
        $state = \humhub\modules\content\models\ContentContainerModuleState::class;
        foreach (Yii::$app->moduleManager->getEnabledModules() as $module) {
            if (!$module instanceof \humhub\modules\content\components\ContentContainerModule
                || !in_array(User::class, $module->getContentContainerTypes(), true)) {
                continue;
            }
            \humhub\modules\content\components\ContentContainerModuleManager::setDefaultState(User::class, $module->id, $state::STATE_NOT_AVAILABLE);
            $an = $state::find()->alias('s')->innerJoin('contentcontainer c', 'c.id = s.contentcontainer_id')
                ->where(['s.module_id' => $module->id, 'c.class' => User::class, 's.module_state' => [$state::STATE_ENABLED, $state::STATE_FORCE_ENABLED]])
                ->count();
            $this->stdout("{$module->id}: im Profil nicht verfügbar" . ($an ? ", aber in $an Profil(en) eingeschaltet" : '') . "\n");
        }
        return ExitCode::OK;
    }

    /**
     * WhatsApp-Eingang im Space „Veranstaltungen“ aktivieren: setup/whatsapp
     * (Modul vorher global aktivieren: /app/yii module/enable whatsapp – legt die Tabellen an)
     */
    public function actionWhatsapp()
    {
        $space = Space::findOne(['url' => 'veranstaltungen']);
        if ($space === null || !Yii::$app->hasModule('whatsapp')) {
            $this->stderr("Space Veranstaltungen fehlt oder Modul whatsapp nicht aktiv\n");
            return ExitCode::DATAERR;
        }
        if (!$space->moduleManager->isEnabled('whatsapp')) {
            $space->moduleManager->enable('whatsapp');
        }
        $this->stdout("WhatsApp-Eingang im Space {$space->name} aktiv\n");
        return ExitCode::OK;
    }

    /** Mängelmelder-Karte im Space „Mängelmelder“ aktivieren: setup/maengelmelder */
    public function actionMaengelmelder()
    {
        $space = Space::findOne(['url' => 'mangelmelder']);
        if ($space === null) {
            $this->stderr("Space Mängelmelder fehlt\n");
            return ExitCode::DATAERR;
        }
        if (!$space->moduleManager->isEnabled('maengelmelder')) {
            $space->moduleManager->enable('maengelmelder');
        }
        $this->stdout("maengelmelder im Space {$space->name} aktiv\n");

        // Gruppe „Bauhof“ mit dem Recht, den Status von Meldungen zu ändern (Mitglieder trägt ein Admin ein)
        $group = \humhub\modules\user\models\Group::findOne(['name' => 'Bauhof']);
        if ($group === null) {
            $group = new \humhub\modules\user\models\Group();
            $group->name = 'Bauhof';
            $group->description = 'Mitarbeitende des Stadtbauhofs: dürfen den Status von Mängelmeldungen ändern.';
            $group->show_at_registration = 0;
            $group->show_at_directory = 0;
            if (!$group->save()) {
                $this->stderr("Gruppe: " . json_encode($group->getErrors()) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("+ Gruppe Bauhof\n");
        }
        Yii::$app->user->permissionManager->setGroupState($group->id,
            new \humhub\modules\maengelmelder\permissions\ManageStatus(), \humhub\libs\BasePermission::STATE_ALLOW);
        $this->stdout("Gruppe Bauhof (id {$group->id}) darf den Mängelstatus ändern, Mitglieder: "
            . $group->getGroupUsers()->count() . "\n");
        return ExitCode::OK;
    }

    /**
     * Vereinsverzeichnis einrichten: setup/vereine
     * Modul im Space „Vereine & Ehrenamt“ aktivieren (dort ohne eigenen Kalender), im gemeinsamen Kalender
     * („Veranstaltungen“) dürfen normale Mitglieder keine Termine mehr anlegen (Vereinstermine kommen über das
     * Modul), Startliste der Vereine aus modules/vereine/data/start.json anlegen (nur fehlende, nur Name/Kategorie/Ort/Website).
     */
    /**
     * Gewerbeverzeichnis: setup/gewerbe (idempotent)
     * Legt den Space „Gewerbe & Einkaufen“ (URL gewerbe) an, schaltet das Modul ein, macht das Verzeichnis zur
     * Startseite des Space, sperrt Stream-Beiträge von Mitgliedern und macht den Space für alle (auch Gäste) sichtbar.
     */
    public function actionGewerbe($ownerUsername = 'admin')
    {
        $space = Space::findOne(['url' => 'gewerbe']);
        if ($space === null) {
            // Nur diesen Space anlegen – nicht actionSpaces(), das bewusst gelöschte Spaces wieder anlegen würde
            $def = current(array_filter(self::SPACES, fn($d) => ($d['url'] ?? '') === 'gewerbe'));
            $owner = User::findOne(['username' => $ownerUsername]);
            if ($owner === null) {
                $this->stderr("User $ownerUsername not found\n");
                return ExitCode::DATAERR;
            }
            Yii::$app->user->switchIdentity($owner);
            $space = new Space();
            $space->scenario = Space::SCENARIO_CREATE;
            $space->name = $def['name'];
            $space->description = $def['description'];
            $space->color = $def['color'];
            $space->visibility = Space::VISIBILITY_ALL;
            $space->join_policy = Space::JOIN_POLICY_FREE;
            $space->auto_add_new_members = 0;
            if (!$space->save()) {
                $this->stderr("! {$def['name']}: " . json_encode($space->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                return ExitCode::DATAERR;
            }
            if ($space->url !== 'gewerbe') {
                $space->updateAttributes(['url' => 'gewerbe']);
            }
            $this->stdout("+ {$space->name}\n");
        }
        if (!$space->moduleManager->isEnabled('gewerbe')) {
            $space->moduleManager->enable('gewerbe');
            $this->stdout("+ gewerbe im Space {$space->name}\n");
        }
        $space->visibility = Space::VISIBILITY_ALL;
        $space->default_content_visibility = 1;
        $space->save(false);
        // Startseite des Space = Verzeichnis (für angemeldete Nutzer und Gäste)
        $start = $space->createUrl('/gewerbe/betrieb/index');
        $space->getSettings()->set('indexUrl', $start);
        $space->getSettings()->set('indexGuestUrl', $start);
        $this->stdout("Startseite: $start\n");
        $this->actionKeineBeitraege('gewerbe');
        $space->permissionManager->setGroupState(Space::USERGROUP_MEMBER,
            new \humhub\modules\space\permissions\InviteUsers(), \humhub\libs\BasePermission::STATE_DENY);
        Yii::$app->cache->flush();
        return ExitCode::OK;
    }

    /**
     * Space „Ideen & Vorschläge“ (url vorschlaege) mit Modul vorschlaege: setup/vorschlaege (idempotent).
     * Startseite ist die Übersicht; Beiträge entstehen nur über „Vorschlag einreichen“, kommentieren dürfen alle Angemeldeten.
     */
    public function actionVorschlaege($ownerUsername = 'admin')
    {
        $space = Space::findOne(['url' => 'vorschlaege']);
        if ($space === null) {
            // Nur diesen Space anlegen – nicht actionSpaces(), das bewusst gelöschte Spaces wieder anlegen würde
            $owner = User::findOne(['username' => $ownerUsername]);
            if ($owner === null) {
                $this->stderr("User $ownerUsername not found\n");
                return ExitCode::DATAERR;
            }
            Yii::$app->user->switchIdentity($owner);
            $space = new Space();
            $space->scenario = Space::SCENARIO_CREATE;
            $space->name = 'Ideen & Vorschläge';
            $space->description = 'Ideen für die Plattform einreichen, kommentieren und abstimmen.';
            $space->color = '#7a4fa0';
            $space->visibility = Space::VISIBILITY_ALL;
            $space->join_policy = Space::JOIN_POLICY_FREE;
            $space->auto_add_new_members = 0;
            if (!$space->save()) {
                $this->stderr('! ' . json_encode($space->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                return ExitCode::DATAERR;
            }
            if ($space->url !== 'vorschlaege') {
                $space->updateAttributes(['url' => 'vorschlaege']);
            }
            $this->stdout("+ {$space->name}\n");
        }
        if (!$space->moduleManager->isEnabled('vorschlaege')) {
            $space->moduleManager->enable('vorschlaege');
            $this->stdout("+ vorschlaege im Space {$space->name}\n");
        }
        $space->visibility = Space::VISIBILITY_ALL;
        $space->default_content_visibility = 1;
        $space->save(false);
        $start = $space->createUrl('/vorschlaege/vorschlag/index');
        $space->getSettings()->set('indexUrl', $start);
        $space->getSettings()->set('indexGuestUrl', $start);
        $this->stdout("Startseite: $start\n");
        $this->actionKeineBeitraege('vorschlaege');
        $space->permissionManager->setGroupState(Space::USERGROUP_MEMBER,
            new \humhub\modules\space\permissions\InviteUsers(), \humhub\libs\BasePermission::STATE_DENY);
        Yii::$app->cache->flush();
        return ExitCode::OK;
    }

    /**
     * Selbsttest Ideen & Vorschläge (setup/vorschlaege-test): Einreichen als normales Konto (kein Mitglied), Abstimmen,
     * Zurücknehmen, Sperren für eigene/abgeschlossene, Stand setzen mit Kommentar, Übersicht und Addon rendern.
     * Alles in einer Transaktion mit Rollback, ohne Glocke.
     */
    public function actionVorschlaegeTest()
    {
        $modul = Yii::$app->getModule('vorschlaege');
        $modul->benachrichtigen = false;
        $sprache = Yii::$app->language;
        Yii::$app->language = 'de';
        $space = $modul->getSpace();
        $admin = User::findOne(['username' => 'admin']);
        $nutzer = User::find()->active()->andWhere(['!=', 'id', $admin->id])->all();
        $nutzer = array_values(array_filter($nutzer, fn($u) => !$u->isSystemAdmin() && !$space->isMember($u->id)));
        if (count($nutzer) < 1) {
            $this->stderr("Kein normales Konto ohne Mitgliedschaft gefunden\n");
            return ExitCode::DATAERR;
        }
        $ok = 0;
        $fehler = 0;
        $pruefe = function (string $was, bool $bedingung) use (&$ok, &$fehler) {
            $bedingung ? $ok++ : $fehler++;
            $this->stdout(($bedingung ? 'ok   ' : 'FEHL ') . "$was\n");
        };
        $tx = Yii::$app->db->beginTransaction();
        try {
            Yii::$app->user->switchIdentity($nutzer[0]);
            $form = new \humhub\modules\vorschlaege\models\VorschlagForm(['titel' => 'TEST Abfuhrtermine als Kalender-Abo',
                'kategorie' => 'Neue Funktion', 'text' => 'Bitte die Abfuhrtermine als iCal-Abo anbieten.']);
            $pruefe('Formular gültig', $form->validate());
            $kurz = new \humhub\modules\vorschlaege\models\VorschlagForm(['titel' => 'x', 'kategorie' => 'Quatsch', 'text' => '']);
            $pruefe('Formular lehnt leere/ungültige Eingaben ab', !$kurz->validate() && count($kurz->getErrors()) === 3);
            $post = \humhub\modules\vorschlaege\models\Vorschlag::einreichen($space, $form);
            $v = \humhub\modules\vorschlaege\models\Vorschlag::findOne(['content_id' => $post->content->id]);
            $pruefe('Beitrag + Vorschlag angelegt (Nicht-Mitglied)', $v !== null && $v->status === 'neu');
            $pruefe('Beitrag öffentlich im Space', (int)$post->content->visibility === \humhub\modules\content\models\Content::VISIBILITY_PUBLIC
                && (int)$post->content->contentcontainer_id === (int)$space->contentcontainer_id);
            $pruefe('Beitragstext mit Titel', str_starts_with($post->message, '**TEST Abfuhrtermine'));
            $html = \humhub\modules\vorschlaege\widgets\VorschlagAddon::abstimmung($v, null, 0);
            $pruefe('Verfasser sieht keine Abstimm-Knöpfe', !str_contains($html, '<form') && str_contains($html, 'vs-nur-zahl'));

            Yii::$app->user->switchIdentity($admin);
            $S = \humhub\modules\vorschlaege\models\Stimme::class;
            $pruefe('Stimme dafür', $S::setzen($v->content_id, $admin->id, 1) === 1);
            $pruefe('Summe 1/0', ($S::summen([$v->content_id])[$v->content_id] ?? null) === ['dafuer' => 1, 'dagegen' => 0]);
            $pruefe('Wechsel zu dagegen', $S::setzen($v->content_id, $admin->id, -1) === -1
                && $S::summen([$v->content_id])[$v->content_id] === ['dafuer' => 0, 'dagegen' => 1]);
            $pruefe('Nochmal dagegen = zurückgenommen', $S::setzen($v->content_id, $admin->id, -1) === 0
                && !isset($S::summen([$v->content_id])[$v->content_id]));
            $S::setzen($v->content_id, $admin->id, 1);
            $pruefe('Eigene Stimme gefunden', ($S::meine([$v->content_id])[$v->content_id] ?? 0) === 1);
            $html = \humhub\modules\vorschlaege\widgets\VorschlagAddon::abstimmung($v, $S::summen([$v->content_id])[$v->content_id], 1);
            $pruefe('Andere sehen Knöpfe, Dafür markiert', substr_count($html, '<form') === 2 && str_contains($html, 'vs-knopf-dafuer vs-an'));

            $pruefe('Admin darf Stand setzen', \humhub\modules\vorschlaege\models\Vorschlag::canManage());
            $kommentare = \humhub\modules\comment\models\Comment::find()->where(['content_id' => $post->content->id])->count();
            $pruefe('Stand „Geplant“ gesetzt', $v->statusSetzen('geplant', 'kommt im November'));
            $c = \humhub\modules\comment\models\Comment::find()->where(['content_id' => $post->content->id])
                ->orderBy(['id' => SORT_DESC])->one();
            $pruefe('Kommentar zum Stand', $c !== null && (int)$kommentare + 1 === (int)\humhub\modules\comment\models\Comment::find()
                ->where(['content_id' => $post->content->id])->count()
                && $c->message === '**Stand: Geplant** – kommt im November');
            $pruefe('Gleicher Stand ohne Hinweis = keine Änderung', !$v->statusSetzen('geplant', ''));
            $v->statusSetzen('umgesetzt', null);
            $html = \humhub\modules\vorschlaege\widgets\VorschlagAddon::abstimmung($v, null, 0);
            $pruefe('Abgeschlossen: keine Knöpfe', !str_contains($html, '<form') && str_contains($html, 'vs-nur-zahl'));
            $v->statusSetzen('neu', null);

            Yii::$app->user->switchIdentity($nutzer[0]);
            $pruefe('Normales Konto darf Stand nicht setzen', !\humhub\modules\vorschlaege\models\Vorschlag::canManage());
            $pruefe('Normales Konto darf kommentieren', $space->permissionManager->can(\humhub\modules\comment\permissions\CreateComment::class));
            $addon = \humhub\modules\vorschlaege\widgets\VorschlagAddon::widget(['vorschlag' => $v]);
            $pruefe('Addon gerendert ohne Stand-Formular', str_contains($addon, 'vs-status-neu') && !str_contains($addon, 'vs-formular'));
            Yii::$app->user->switchIdentity($admin);
            $addon = \humhub\modules\vorschlaege\widgets\VorschlagAddon::widget(['vorschlag' => $v]);
            $pruefe('Addon für Admin mit Stand-Formular', str_contains($addon, 'vs-formular'));

            // Übersicht wie im Controller (Sortierung nach Stimmen)
            $liste = \humhub\modules\vorschlaege\models\Vorschlag::find()->alias('v')->innerJoinWith('content c')
                ->where(['c.contentcontainer_id' => $space->contentcontainer_id])->all();
            $seite = Yii::$app->view->renderFile('@vorschlaege/views/vorschlag/index.php', ['module' => $modul, 'space' => $space,
                'vorschlaege' => $liste, 'summen' => $S::summen([$v->content_id]), 'meine' => $S::meine([$v->content_id]),
                'kommentare' => [$v->content_id => 2], 'anzahl' => ['neu' => 1], 'stand' => 'offen', 'sortierung' => 'stimmen']);
            $pruefe('Übersicht gerendert', str_contains($seite, 'TEST Abfuhrtermine') && str_contains($seite, '2 Kommentare')
                && str_contains($seite, 'Bitte die Abfuhrtermine'));
            $leer = Yii::$app->view->renderFile('@vorschlaege/views/vorschlag/index.php', ['module' => $modul, 'space' => $space,
                'vorschlaege' => [], 'summen' => [], 'meine' => [], 'kommentare' => [], 'anzahl' => [], 'stand' => 'offen', 'sortierung' => 'neu']);
            $pruefe('Leere Übersicht', str_contains($leer, 'mach den Anfang'));
            Yii::$app->language = 'en-US';
            $en = Yii::$app->view->renderFile('@vorschlaege/views/vorschlag/index.php', ['module' => $modul, 'space' => $space,
                'vorschlaege' => [], 'summen' => [], 'meine' => [], 'kommentare' => [], 'anzahl' => [], 'stand' => 'offen', 'sortierung' => 'neu']);
            $pruefe('Übersetzung Englisch', str_contains($en, 'No suggestions yet') && \humhub\modules\vorschlaege\models\Vorschlag::anzeigeLabel('geplant') === 'Planned');
            Yii::$app->language = 'de';
            $neu = Yii::$app->view->renderFile('@vorschlaege/views/vorschlag/create.php', ['form' => $form, 'space' => $space, 'module' => $modul]);
            $pruefe('Formularseite gerendert', str_contains($neu, 'VorschlagForm[titel]'));
            $post->delete();
            $pruefe('Gelöschter Beitrag fällt aus der Übersicht', !\humhub\modules\vorschlaege\models\Vorschlag::find()->alias('v')
                ->innerJoinWith('content c')->where(['v.content_id' => $v->content_id, 'c.state' => \humhub\modules\content\models\Content::STATE_PUBLISHED])->exists());
            \humhub\modules\content\models\Content::findOne($v->content_id)->hardDelete();
            $pruefe('Endgültiges Löschen entfernt Vorschlag und Stimmen', \humhub\modules\vorschlaege\models\Vorschlag::findOne($v->content_id) === null
                && !$S::find()->where(['content_id' => $v->content_id])->exists());
        } catch (\Throwable $e) {
            $fehler++;
            $this->stderr("FEHL Ausnahme: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        } finally {
            $tx->rollBack();
            Yii::$app->language = $sprache;
        }
        $this->stdout("\n$ok ok, $fehler Fehler (alles zurückgerollt)\n");
        return $fehler ? ExitCode::SOFTWARE : ExitCode::OK;
    }

    /**
     * Uffi und die Vorschläge mit Testdaten (setup/vorschlaege-ki-test): legt in einer Transaktion zwei Vorschläge an,
     * prüft den Kontext-Abschnitt und stellt Uffi zwei Fragen (2 API-Anfragen). Danach Rollback, Kontextspeicher geleert.
     */
    public function actionVorschlaegeKiTest()
    {
        $modul = Yii::$app->getModule('vorschlaege');
        $modul->benachrichtigen = false;
        $ki = Yii::$app->getModule('ki-assistent');
        $space = $modul->getSpace();
        $admin = User::findOne(['username' => 'admin']);
        $nutzer = array_values(array_filter(User::find()->active()->andWhere(['!=', 'id', $admin->id])->all(), fn($u) => !$u->isSystemAdmin()));
        $ok = 0;
        $fehler = 0;
        $pruefe = function (string $was, bool $bedingung, string $zeige = '') use (&$ok, &$fehler) {
            $bedingung ? $ok++ : $fehler++;
            $this->stdout(($bedingung ? 'ok   ' : 'FEHL ') . "$was\n" . (!$bedingung && $zeige !== '' ? "     $zeige\n" : ''));
        };
        $tx = Yii::$app->db->beginTransaction();
        Yii::$app->cache->delete('ki-assistent-context');
        try {
            Yii::$app->user->switchIdentity($nutzer[0]);
            $F = \humhub\modules\vorschlaege\models\VorschlagForm::class;
            $abo = \humhub\modules\vorschlaege\models\Vorschlag::einreichen($space, new $F(['titel' => 'Abfuhrtermine als Kalender-Abo',
                'kategorie' => 'Neue Funktion', 'text' => 'Die Abfuhrtermine sollten sich als iCal-Abo ins Handy übernehmen lassen.']));
            \humhub\modules\vorschlaege\models\Vorschlag::einreichen($space, new $F(['titel' => 'Dunkles Design für die App',
                'kategorie' => 'Verbesserung', 'text' => 'Abends wäre ein dunkles Design angenehmer für die Augen.']));
            Yii::$app->user->switchIdentity($admin);
            \humhub\modules\vorschlaege\models\Stimme::setzen($abo->content->id, $admin->id, 1);
            \humhub\modules\vorschlaege\models\Vorschlag::findOne($abo->content->id)->statusSetzen('geplant', 'kommt im November');

            $abschnitt = \humhub\modules\vorschlaege\components\Uffi::forAssistant();
            $pruefe('Kontext: Vorschlag mit Stand, Hinweis und Stimmen', str_contains($abschnitt, 'Abfuhrtermine als Kalender-Abo (Neue Funktion) – Stand: Geplant')
                && str_contains($abschnitt, 'Hinweis des Teams: kommt im November') && str_contains($abschnitt, '1 dafür, 0 dagegen, 1 Kommentare'), $abschnitt);
            $pruefe('Kontext: beliebtester zuerst, Beschreibung ohne Titel', strpos($abschnitt, 'Kalender-Abo') < strpos($abschnitt, 'Dunkles Design')
                && str_contains($abschnitt, "\n  Die Abfuhrtermine sollten"));
            $pruefe('Kontext: keine Namen der Verfasser', !str_contains($abschnitt, $nutzer[0]->displayName));
            $pruefe('Kontext im Prompt', str_contains(\humhub\modules\kiAssistent\components\Context::get(), 'Dunkles Design für die App'));

            $claude = new \humhub\modules\kiAssistent\components\Claude($ki->getApiKey(), $ki->getModel());
            $fragen = [
                ['Kann man die Abfuhrtermine eigentlich als Kalender abonnieren? Das wäre echt praktisch.',
                    ['geplant', 'November', preg_quote($abo->content->getUrl(), '/')]],
                ['Ich hätte gern einen Dark Mode für die App. Hat das schon jemand vorgeschlagen?',
                    ['Dunkles Design', 'dafür|abstimm|stimm']],
            ];
            foreach ($fragen as [$frage, $muster]) {
                [$antwort, , , $in, $out] = \humhub\modules\kiAssistent\controllers\ChatController::converse($claude,
                    \humhub\modules\kiAssistent\controllers\ChatController::systemPrompt($frage), [['role' => 'user', 'text' => $frage]]);
                $ki->addTestUsage($in, $out);
                $fehlt = array_filter($muster, fn($re) => !preg_match("/$re/iu", $antwort));
                $pruefe("Uffi: $frage", !$fehlt, 'fehlt ' . implode(', ', $fehlt) . ' – ' . mb_substr(preg_replace('/\s+/u', ' ', $antwort), 0, 400));
            }
        } catch (\Throwable $e) {
            $fehler++;
            $this->stderr("FEHL Ausnahme: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        } finally {
            $tx->rollBack();
            Yii::$app->cache->delete('ki-assistent-context');
        }
        $this->stdout("\n$ok ok, $fehler Fehler (alles zurückgerollt)\n");
        return $fehler ? ExitCode::SOFTWARE : ExitCode::OK;
    }

    /**
     * „Meine Übersicht“ mit standardmäßig ausgeschalteten Bereichen (setup/uebersicht-standard-test): Testvorschlag in
     * einer Transaktion, Übersicht eines normalen Kontos (Standard, eingeschaltet, wieder aus) und eines Gasts. Rollback.
     */
    /**
     * Reihenfolge und Ausblenden der Kästen rechts in der Übersicht („Meine Übersicht“, Module::KAESTEN): Standard, eigene
     * Reihenfolge, ungültige Schlüssel, zurück zum Standard, ausgeblendete Kästen, Wirkung auf die Seitenleiste (angemeldet/Gast). Mit Rollback.
     *   scripts/run-setup.sh uebersicht-kaesten-test
     */
    public function actionUebersichtKaestenTest()
    {
        $ue = Yii::$app->getModule('uebersicht');
        $M = \humhub\modules\uebersicht\Module::class;
        $ok = 0;
        $fehler = 0;
        $gleich = function (string $was, $ist, $soll) use (&$ok, &$fehler) {
            $bedingung = $ist === $soll;
            $bedingung ? $ok++ : $fehler++;
            $this->stdout(($bedingung ? 'ok   ' : 'FEHL ') . "$was\n" . (!$bedingung ? '     ist: ' . var_export($ist, true) . ', soll: ' . var_export($soll, true) . "\n" : ''));
        };
        $nutzer = User::find()->active()->one();
        $standard = array_keys(array_filter($M::KAESTEN, fn($k) => class_exists($k[0])));
        $sidebar = function () use ($M) {
            $sb = new class { public $widgets = []; };
            foreach ($M::KAESTEN as $k) {
                $sb->widgets[] = [$k[0], [], ['sortOrder' => 77]];
            }
            $sb->widgets[] = ['humhub\modules\space\widgets\NewSpaces', [], ['sortOrder' => 400]];
            \humhub\modules\uebersicht\Events::onDashboardSidebarRun((object)['sender' => $sb]);
            $reihe = $sb->widgets;
            usort($reihe, fn($a, $b) => $a[2]['sortOrder'] <=> $b[2]['sortOrder']);
            return array_map(fn($w) => $w[0], $reihe);
        };
        $klasse = fn(string $k) => $M::KAESTEN[$k][0];
        $tx = Yii::$app->db->beginTransaction();
        try {
            $ue->setKaesten($nutzer, $standard);
            Yii::$app->cache->flush();
            $gleich('Standardreihenfolge', $ue->getKaesten($nutzer), $standard);
            $ue->setKaesten($nutzer, ['sprit', 'gibtsnicht', 'notdienste', 'sprit']);
            Yii::$app->cache->flush();
            $soll = array_merge(['sprit', 'notdienste'], array_values(array_diff($standard, ['sprit', 'notdienste'])));
            $gleich('eigene Reihenfolge, Ungültiges und Doppeltes entfernt, Rest hinten an', $ue->getKaesten($nutzer), $soll);
            Yii::$app->user->switchIdentity($nutzer);
            $reihe = $sidebar();
            $gleich('Seitenleiste: Spritpreise vor Notdiensten, „Neue Spaces“ bleibt hinten',
                [array_search($klasse('sprit'), $reihe, true) < array_search($klasse('notdienste'), $reihe, true), end($reihe)],
                [true, 'humhub\modules\space\widgets\NewSpaces']);
            Yii::$app->user->switchIdentity(null);
            $gleich('Gast: Reihenfolge unverändert (sortOrder der Module bleibt)', $sidebar(),
                array_merge(array_values(array_map(fn($k) => $k[0], $M::KAESTEN)), ['humhub\modules\space\widgets\NewSpaces']));
            $ue->setKaesten($nutzer, $standard);
            Yii::$app->cache->flush();
            $gleich('zurück zum Standard: keine Einstellung gespeichert', $ue->settings->user($nutzer)->get('kaesten'), null);
            // Ausblenden
            $ue->setKaestenAus($nutzer, ['sprit', 'gibtsnicht']);
            Yii::$app->cache->flush();
            $gleich('ausgeblendet gespeichert (nur gültige)', $ue->getKaestenAus($nutzer), ['sprit']);
            Yii::$app->user->switchIdentity($nutzer);
            $reihe = $sidebar();
            $gleich('Seitenleiste: Spritpreise fehlen, Notdienste da', [in_array($klasse('sprit'), $reihe, true), in_array($klasse('notdienste'), $reihe, true)], [false, true]);
            Yii::$app->user->switchIdentity(null);
            $gleich('Gast: Spritpreise trotzdem da', in_array($klasse('sprit'), $sidebar(), true), true);
            $ue->setKaestenAus($nutzer, []);
            Yii::$app->cache->flush();
            $gleich('alle wieder an: keine Einstellung gespeichert', $ue->settings->user($nutzer)->get('kaesten_aus'), null);
        } finally {
            $tx->rollBack();
            Yii::$app->cache->flush();
        }
        $this->stdout("\n$ok ok, $fehler Fehler\n");
        return $fehler ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionUebersichtStandardTest()
    {
        $modul = Yii::$app->getModule('vorschlaege');
        $modul->benachrichtigen = false;
        $ue = Yii::$app->getModule('uebersicht');
        Yii::$app->getModule('dashboard')->memberFilterClass = \humhub\modules\uebersicht\filters\MeineUebersicht::class;
        $space = $modul->getSpace();
        $nutzer = array_values(array_filter(User::find()->active()->all(), fn($u) => !$u->isSystemAdmin()));
        $ok = 0;
        $fehler = 0;
        $pruefe = function (string $was, bool $bedingung) use (&$ok, &$fehler) {
            $bedingung ? $ok++ : $fehler++;
            $this->stdout(($bedingung ? 'ok   ' : 'FEHL ') . "$was\n");
        };
        $imStream = function (?User $user, int $contentId): bool {
            Yii::$app->user->switchIdentity($user);
            $q = new \humhub\modules\dashboard\stream\DashboardStreamQuery(['user' => $user, 'limit' => 50]);
            return in_array($contentId, array_map(fn($c) => (int)$c->id, $q->all()), true);
        };
        $tx = Yii::$app->db->beginTransaction();
        try {
            Yii::$app->user->switchIdentity($nutzer[0]);
            $post = \humhub\modules\vorschlaege\models\Vorschlag::einreichen($space, new \humhub\modules\vorschlaege\models\VorschlagForm([
                'titel' => 'TEST Übersicht', 'kategorie' => 'Sonstiges', 'text' => 'Nur ein Test für die Übersicht.']));
            $id = (int)$post->content->id;
            $leser = $nutzer[1];
            $alt = $ue->getAusgeblendet($leser);
            $pruefe('Standard: Bereich ist ausgeschaltet', in_array($space->guid, $alt, true));
            $pruefe('Standard: Vorschlag nicht in der Übersicht', !$imStream($leser, $id));
            $ue->setAusgeblendet($leser, array_values(array_diff($alt, [$space->guid])));
            Yii::$app->cache->flush();
            $pruefe('Eingeschaltet: Bereich nicht mehr ausgeblendet', !in_array($space->guid, $ue->getAusgeblendet($leser), true));
            $pruefe('Eingeschaltet: Vorschlag in der Übersicht', $imStream($leser, $id));
            $ue->setAusgeblendet($leser, array_merge($alt, [$space->guid]));
            Yii::$app->cache->flush();
            $pruefe('Wieder aus: Vorschlag nicht in der Übersicht', !$imStream($leser, $id));
            $pruefe('Andere Bereiche unverändert', array_values(array_diff($ue->getAusgeblendet($leser), [$space->guid])) === array_values(array_diff($alt, [$space->guid])));
            $pruefe('Gast: Vorschlag nicht in der Übersicht', !$imStream(null, $id));
            $standard = $ue->standardAus;
            $ue->standardAus = [];
            $pruefe('Gegenprobe: ohne Standard-Aus sieht der Gast ihn', $imStream(null, $id));
            $ue->standardAus = $standard;
            $pruefe('Vorschlag selbst für Gäste sichtbar', $post->content->canView(null) || (int)$post->content->visibility === 1);
        } catch (\Throwable $e) {
            $fehler++;
            $this->stderr("FEHL Ausnahme: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        } finally {
            $tx->rollBack();
            Yii::$app->cache->flush();
        }
        $this->stdout("\n$ok ok, $fehler Fehler (alles zurückgerollt)\n");
        return $fehler ? ExitCode::SOFTWARE : ExitCode::OK;
    }

    /**
     * Selbsttest Neuigkeiten (setup/neuigkeiten-test): Einträge, Popup-Auswahl je Konto, Wegklicken, Popup und Seite
     * rendern (deutsch und englisch). Einstellungen in einer Transaktion mit Rollback.
     */
    public function actionNeuigkeitenTest($html = 0)
    {
        $modul = Yii::$app->getModule('neuigkeiten');
        $sprache = Yii::$app->language;
        $locale = Yii::$app->formatter->locale;
        Yii::$app->language = Yii::$app->formatter->locale = 'de';
        $ok = 0;
        $fehler = 0;
        $pruefe = function (string $was, bool $bedingung, string $zeige = '') use (&$ok, &$fehler) {
            $bedingung ? $ok++ : $fehler++;
            $this->stdout(($bedingung ? 'ok   ' : 'FEHL ') . "$was\n" . (!$bedingung && $zeige !== '' ? "     $zeige\n" : ''));
        };
        $tx = Yii::$app->db->beginTransaction();
        try {
            $alle = $modul->eintraege();
            $ids = array_column($alle, 'id');
            $pruefe('Einträge eindeutig und vollständig', count($ids) === count(array_unique($ids)) && !array_filter($alle,
                fn($e) => empty($e['titel']) || empty($e['text']) || !isset(\humhub\modules\neuigkeiten\Module::ARTEN[$e['art']])
                    || !preg_match('/^\d{4}-\d\d-\d\d$/', $e['datum'])));
            $pruefe('Neueste zuerst', $alle[0]['datum'] >= end($alle)['datum']);
            $ohneLink = array_filter($alle, fn($e) => !empty($e['link']) && $modul->linkUrl($e) === null);
            $pruefe('Alle Links auflösbar', !$ohneLink, implode(', ', array_column($ohneLink, 'id')));
            $tr = $modul->uebersetzungen();
            $fehlt = [];
            foreach ($alle as $e) {
                foreach (array_keys(\humhub\modules\kiAssistent\components\Uebersetzer::SPRACHEN) as $l) {
                    if (($tr[$e['id']][$l]['quelle'] ?? '') !== sha1($e['titel'] . "\n" . $e['text'])) {
                        $fehlt[] = "{$e['id']}/$l";
                    }
                }
            }
            $pruefe('Übersetzungen aktuell', !$fehlt, implode(', ', array_slice($fehlt, 0, 10)));

            $alt = User::find()->active()->andWhere(['<', 'created_at', '2026-10-01'])->one();
            $modul->settings->user($alt)->delete('gesehen');
            Yii::$app->cache->flush();
            $popup = $modul->fuerPopup($alt);
            $pruefe('Popup für älteres Konto: nur neue Funktionen mit popup, höchstens ' . $modul->popupMax, $popup
                && count($popup) <= $modul->popupMax && !array_filter($popup, fn($e) => empty($e['popup'])), json_encode(array_column($popup, 'id')));
            Yii::$app->user->switchIdentity($alt);
            $popupHtml = \humhub\modules\neuigkeiten\widgets\Popup::widget();
            if ($html) {
                file_put_contents('/data/neuigkeiten/popup-test.html', $popupHtml); // nur für einen Screenshot, danach löschen
            }
            $pruefe('Popup gerendert', str_contains($popupHtml, 'id="nk-popup"') && str_contains($popupHtml, \humhub\helpers\Html::encode($popup[0]['titel'] ?? '###'))
                && str_contains($popupHtml, 'neuigkeiten/index/gesehen'));
            $modul->alleGesehen($alt);
            Yii::$app->cache->flush();
            $pruefe('Nach Wegklicken kein Popup mehr', $modul->fuerPopup($alt) === [] && \humhub\modules\neuigkeiten\widgets\Popup::widget() === '');

            $modul->settings->user($alt)->delete('rundgang');
            $pruefe('Rundgang offen für Konto ohne Einstellung', $modul->rundgangOffen($alt));
            $rg = \humhub\modules\neuigkeiten\widgets\Rundgang::widget();
            if ($html) {
                file_put_contents('/data/neuigkeiten/rundgang-test.html', $rg); // nur für einen Screenshot, danach löschen
            }
            $pruefe('Rundgang gerendert (5 Schritte, Ziele Uffi/Abfuhr/Mängelmelder)', substr_count($rg, 'data-nk-rg-schritt=') === 5
                && str_contains($rg, 'data-nk-rg-uffi') && str_contains($rg, 'abfuhr/einstellungen')
                && str_contains($rg, 'maengelmelder/meldung/create') && str_contains($rg, 'neuigkeiten/index/rundgang'), mb_substr(strip_tags($rg), 0, 300));
            $modul->rundgangGesehen($alt);
            Yii::$app->cache->flush();
            $pruefe('Nach Rundgang nicht mehr offen', !$modul->rundgangOffen($alt));

            $neu = new User(['created_at' => date('Y-m-d H:i:s', strtotime('+1 day'))]);
            $pruefe('Konto jünger als alle Einträge: kein Popup', $modul->fuerPopup($neu) === []);

            $seite = Yii::$app->view->renderFile('@neuigkeiten/views/index/index.php', ['module' => $modul, 'art' => '', 'eintraege' => $alle]);
            $pruefe('Seite gerendert (alle Einträge, Monate)', substr_count($seite, 'class="nk-eintrag"') === count($alle)
                && str_contains($seite, 'Oktober 2026') && str_contains($seite, 'September 2026'));
            Yii::$app->language = Yii::$app->formatter->locale = 'en-US';
            $en = Yii::$app->view->renderFile('@neuigkeiten/views/index/index.php', ['module' => $modul, 'art' => 'neu', 'eintraege' => array_slice($alle, 0, 2)]);
            $pruefe('Seite englisch', str_contains($en, 'What’s new') && str_contains($en, \humhub\helpers\Html::encode($tr[$alle[0]['id']]['en']['titel'] ?? '###'))
                && str_contains($en, 'October 2026'));
            foreach (array_keys(\humhub\modules\neuigkeiten\Module::HILFE) as $seite) {
                $de = (string)file_get_contents($modul->getBasePath() . "/hilfe/de/$seite.html");
                $veraltet = [];
                foreach (array_keys(\humhub\modules\kiAssistent\components\Uebersetzer::SPRACHEN) as $l) {
                    $tr = @file_get_contents($modul->getBasePath() . "/hilfe/$l/$seite.html");
                    if ($tr === false || !str_starts_with($tr, '<!-- quelle: ' . sha1($de) . ' -->')) {
                        $veraltet[] = $l;
                    }
                }
                $pruefe("Hilfe $seite: Übersetzungen aktuell", !$veraltet, implode(', ', $veraltet));
                $h = $modul->hilfeHtml($seite, 'de');
                $pruefe("Hilfe $seite: alle Platzhalter ersetzt, keine Links auf die Startseite", !str_contains($h, '{{')
                    && !preg_match('#href="/?(dashboard)?"#', $h), mb_substr($h, 0, 200));
            }
            \humhub\modules\neuigkeiten\Events::onBeforeRequest(null); // Konsole: Regeln wie im Web
            $pruefe('Hilfe-Adressen kurz', \yii\helpers\Url::to(['/neuigkeiten/hilfe/seite', 'seite' => 'regeln']) === '/hilfe/regeln',
                \yii\helpers\Url::to(['/neuigkeiten/hilfe/seite', 'seite' => 'regeln']));
            $hEn = $modul->hilfeHtml('app');
            $pruefe('Hilfe englisch', !str_contains($hEn, 'So nutzt du') && str_contains($hEn, 'community.rundumuffni.de'));
            $rgEn = \humhub\modules\neuigkeiten\widgets\Rundgang::widget();
            $pruefe('Rundgang englisch', str_contains($rgEn, 'Ask Uffi') && str_contains($rgEn, 'Skip the tour') && !str_contains($rgEn, 'Weiter'));
            Yii::$app->language = 'de';
        } catch (\Throwable $e) {
            $fehler++;
            $this->stderr("FEHL Ausnahme: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        } finally {
            $tx->rollBack();
            Yii::$app->cache->flush();
            Yii::$app->language = $sprache;
            Yii::$app->formatter->locale = $locale;
        }
        $this->stdout("\n$ok ok, $fehler Fehler (alles zurückgerollt)\n");
        return $fehler ? ExitCode::SOFTWARE : ExitCode::OK;
    }

    /**
     * Neuigkeiten übersetzen (setup/neuigkeiten-uebersetzen [1 = alles neu]): fehlende oder seit der letzten Übersetzung
     * geänderte Einträge aus modules/neuigkeiten/data/eintraege.php per Claude, eine Anfrage je Sprache. Ergebnis nach
     * /data/neuigkeiten/uebersetzungen.json – von dort ins Repo kopieren (siehe README), das Modul liest die Repo-Datei.
     */
    public function actionNeuigkeitenUebersetzen($alles = 0)
    {
        $modul = Yii::$app->getModule('neuigkeiten');
        $ki = Yii::$app->getModule('ki-assistent');
        if ($ki->getApiKey() === null) {
            $this->stderr("Kein Schlüssel in secrets/anthropic_api_key\n");
            return ExitCode::CONFIG;
        }
        $tr = $alles ? [] : $modul->uebersetzungen();
        $claude = new \humhub\modules\kiAssistent\components\Claude($ki->getApiKey(), $ki->getModel());
        $ids = array_column($modul->eintraege(), 'id');
        foreach (\humhub\modules\kiAssistent\components\Uebersetzer::SPRACHEN as $lang => $sprache) {
            $offen = [];
            foreach ($modul->eintraege() as $e) {
                $quelle = sha1($e['titel'] . "\n" . $e['text']);
                if (($tr[$e['id']][$lang]['quelle'] ?? null) !== $quelle) {
                    $offen[$e['id']] = ['titel' => $e['titel'], 'text' => $e['text']];
                }
            }
            if (!$offen) {
                $this->stdout("$lang: nichts zu tun\n");
                continue;
            }
            $system = "You translate release notes (\"what's new\") of a German local community platform for the town of Uffenheim "
                . "(Bavaria) into {$sprache}. Input and output are JSON objects: id => {\"titel\": …, \"text\": …}. Translate titel and text, "
                . "keep the ids, use the informal friendly form of address, keep names (Uffi, Rund um Uffni, Uffenheim, street, club and "
                . "service names such as OpenStreetMap, Google Maps) unchanged. Output only the JSON object, no explanation, no code fence.";
            [$antwort, $in, $out] = $claude->ask($system, [['role' => 'user', 'text' => json_encode($offen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]]);
            $ki->addUebersetzungUsage($in, $out);
            $antwort = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($antwort));
            $daten = json_decode($antwort, true);
            if (!is_array($daten)) {
                $this->stderr("$lang: Antwort ist kein JSON: " . mb_substr($antwort, 0, 300) . "\n");
                return ExitCode::SOFTWARE;
            }
            $n = 0;
            foreach ($offen as $id => $de) {
                if (!empty($daten[$id]['titel']) && !empty($daten[$id]['text'])) {
                    $tr[$id][$lang] = ['titel' => (string)$daten[$id]['titel'], 'text' => (string)$daten[$id]['text'],
                        'quelle' => sha1($de['titel'] . "\n" . $de['text'])];
                    $n++;
                }
            }
            $this->stdout("$lang: $n von " . count($offen) . " übersetzt\n");
        }
        // Gelöschte Einträge entfernen, Reihenfolge wie in eintraege.php
        $tr = array_intersect_key(array_replace(array_flip($ids), $tr), array_flip($ids));
        $tr = array_filter($tr, 'is_array');
        @mkdir('/data/neuigkeiten', 0775, true);
        file_put_contents('/data/neuigkeiten/uebersetzungen.json', json_encode($tr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        $this->stdout("Gespeichert: /data/neuigkeiten/uebersetzungen.json\n");
        return ExitCode::OK;
    }

    /**
     * Mail-Passwort (App-Passwort von Google) setzen: setup/mail-passwort – liest /tmp/mailpw (von scripts/mail-passwort.sh
     * aus secrets/mail_app_passwort kopiert), entfernt Leerzeichen, speichert es als mailerPassword (gilt für Versand und
     * Mail-Eingang) und löscht die Datei. Gibt das Passwort nie aus.
     */
    public function actionMailPasswort()
    {
        $datei = '/tmp/mailpw';
        $pw = preg_replace('/\s+/', '', (string)@file_get_contents($datei));
        @unlink($datei);
        if (strlen($pw) < 8) {
            $this->stderr("Kein Passwort gefunden (Datei leer oder fehlt)\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->settings->set('mailerPassword', $pw);
        Yii::$app->cache->flush();
        $this->stdout('mailerPassword gesetzt (' . strlen($pw) . " Zeichen)\n");
        return ExitCode::OK;
    }

    /**
     * Hilfe-Seiten übersetzen (setup/hilfe-uebersetzen [1 = alles neu]): modules/neuigkeiten/hilfe/de/*.html per Claude in alle
     * Sprachen, nur wenn sich der deutsche Text geändert hat (Prüfsumme in der ersten Zeile). Ergebnis nach /data/neuigkeiten/hilfe/
     * – von dort ins Repo kopieren (siehe README). Platzhalter {{…}} und HTML bleiben unverändert (wird geprüft).
     */
    public function actionHilfeUebersetzen($alles = 0)
    {
        $modul = Yii::$app->getModule('neuigkeiten');
        $ki = Yii::$app->getModule('ki-assistent');
        if ($ki->getApiKey() === null) {
            $this->stderr("Kein Schlüssel in secrets/anthropic_api_key\n");
            return ExitCode::CONFIG;
        }
        $claude = new \humhub\modules\kiAssistent\components\Claude($ki->getApiKey(), $ki->getModel());
        $basis = $modul->getBasePath() . '/hilfe';
        $fehler = 0;
        foreach (array_keys(\humhub\modules\neuigkeiten\Module::HILFE) as $seite) {
            $de = (string)file_get_contents("$basis/de/$seite.html");
            $kopf = '<!-- quelle: ' . sha1($de) . ' -->';
            foreach (\humhub\modules\kiAssistent\components\Uebersetzer::SPRACHEN as $lang => $sprache) {
                $alt = @file_get_contents("$basis/$lang/$seite.html");
                if (!$alles && $alt !== false && str_starts_with($alt, $kopf)) {
                    $this->stdout("$lang/$seite: aktuell\n");
                    continue;
                }
                $system = "You translate an HTML help page of a German local community platform for the town of Uffenheim (Bavaria) into {$sprache}. "
                    . "Translate only the visible text and the aria-label values. Keep every HTML tag, attribute, class, id, href and every placeholder "
                    . "in double curly braces exactly as it is. Use the informal friendly form of address. Keep names unchanged: Uffi, Rund um Uffni, "
                    . "Uffenheim, HumHub, community.rundumuffni.de, App Store, Google Play, Safari, Chrome, e-mail addresses. Translate feature and menu names such as „Mängelmelder“ (report a problem), „Ideen & Vorschläge“ (ideas & suggestions) and „Melden“ (report) like a localized app would. Button and menu labels "
                    . "of iOS/Android (e.g. „Zum Home-Bildschirm“, „App installieren“) use the official {$sprache} wording of those systems. "
                    . "Output only the translated HTML, no explanation, no code fence.";
                [$antwort, $in, $out] = $claude->ask($system, [['role' => 'user', 'text' => $de]]);
                $ki->addUebersetzungUsage($in, $out);
                $html = trim(preg_replace('/^```(?:html)?\s*|\s*```$/', '', trim($antwort)));
                preg_match_all('/\{\{[^}]+\}\}/', $de, $soll);
                preg_match_all('/\{\{[^}]+\}\}/', $html, $ist);
                if ($soll[0] !== $ist[0] || preg_match('/<script|<iframe|\son\w+=|javascript:/i', $html)
                    || substr_count($html, '<') !== substr_count($de, '<')) {
                    $this->stderr("FEHL $lang/$seite: Platzhalter oder HTML verändert – nicht gespeichert\n");
                    $fehler++;
                    continue;
                }
                @mkdir("/data/neuigkeiten/hilfe/$lang", 0775, true);
                file_put_contents("/data/neuigkeiten/hilfe/$lang/$seite.html", "$kopf\n$html\n");
                $this->stdout("$lang/$seite: übersetzt\n");
            }
        }
        $this->stdout("Gespeichert unter /data/neuigkeiten/hilfe/\n");
        return $fehler ? ExitCode::SOFTWARE : ExitCode::OK;
    }

    /**
     * Startliste des Gewerbeverzeichnisses: setup/gewerbe-import (idempotent, Status „offen“ mit Quelle)
     * Quelle: Liste „Gastronomie und Unterkünfte“ der Stadt (modules/gewerbe/data/start.json, ohne Personennamen und
     * private E-Mail-Adressen). Danach den Ort aller Betriebe ohne Kartenpunkt über Nominatim bestimmen (1 Abfrage/s).
     */
    public function actionGewerbeImport()
    {
        $quelle = 'https://www.uffenheim.de/erleben/tourismus/gastronomie-unterkuenfte';
        $neu = 0;
        foreach (json_decode(file_get_contents('/data/modules/gewerbe/data/start.json'), true) as $row) {
            if (\humhub\modules\gewerbe\models\Betrieb::find()->where(['name' => $row['name']])->exists()) {
                continue;
            }
            $b = new \humhub\modules\gewerbe\models\Betrieb(array_filter($row, fn($v) => $v !== null) + [
                'status' => 'offen', 'quelle' => $quelle, 'recherchiert_am' => date('Y-m-d')]);
            $b->geocodeAus = true;
            if (!$b->save()) {
                $this->stderr("! {$row['name']}: " . json_encode($b->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                continue;
            }
            $neu++;
        }
        $this->stdout("$neu Betriebe neu angelegt\n");
        $ohne = \humhub\modules\gewerbe\models\Betrieb::find()->where(['lat' => null])->andWhere(['not', ['adresse' => null]])->all();
        foreach ($ohne as $b) {
            $b->ortBestimmen();
            $this->stdout(($b->lat !== null ? '+' : '?') . " {$b->name}: " . ($b->lat !== null ? "{$b->lat}, {$b->lng}" : 'Ort nicht gefunden') . "\n");
        }
        Yii::$app->cache->delete('ki-link-domains');
        return ExitCode::OK;
    }

    public function actionVereine()
    {
        $space = Space::findOne(['url' => 'vereine-and-ehrenamt']);
        $calendarSpace = Space::findOne(['url' => 'veranstaltungen']);
        if ($space === null || $calendarSpace === null) {
            $this->stderr("Space Vereine & Ehrenamt oder Veranstaltungen fehlt\n");
            return ExitCode::DATAERR;
        }
        foreach (self::SPACES as $def) {
            if ($def['name'] === $space->name && $space->description !== $def['description']) {
                $space->updateAttributes(['description' => $def['description']]);
                $this->stdout("~ Beschreibung {$space->name}\n");
            }
        }
        if (!$space->moduleManager->isEnabled('vereine')) {
            $space->moduleManager->enable('vereine');
            $this->stdout("+ vereine im Space {$space->name}\n");
        }
        if ($space->moduleManager->isEnabled('calendar')) {
            $count = \humhub\modules\calendar\models\CalendarEntry::find()->contentContainer($space)->count();
            if ($count == 0) {
                $space->moduleManager->disable('calendar');
                $this->stdout("- Kalender im Space {$space->name} (leer; gemeinsamer Kalender ist in Veranstaltungen)\n");
            } else {
                $this->stdout("? Kalender im Space {$space->name} hat $count Einträge, bleibt aktiv\n");
            }
        }
        foreach ([Space::USERGROUP_MEMBER, Space::USERGROUP_USER] as $group) {
            $calendarSpace->permissionManager->setGroupState($group, new \humhub\modules\calendar\permissions\CreateEntry(),
                \humhub\libs\BasePermission::STATE_DENY);
        }
        $this->stdout("Veranstaltungen: Mitglieder legen keine Termine mehr direkt an (Moderatoren/Admins weiterhin)\n");

        $new = 0;
        foreach (json_decode(file_get_contents('/data/modules/vereine/data/start.json'), true) as $row) {
            if (\humhub\modules\vereine\models\Verein::find()->where(['name' => $row['name']])->exists()) {
                continue;
            }
            $verein = new \humhub\modules\vereine\models\Verein($row + ['status' => 'offen']);
            $verein->website = $verein->website ?: null;
            if (!$verein->save()) {
                $this->stderr("! {$row['name']}: " . json_encode($verein->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                continue;
            }
            $new++;
        }
        $this->stdout("$new Vereine neu angelegt, insgesamt " . \humhub\modules\vereine\models\Verein::find()->count() . "\n");

        // Recht „Vereine verwalten“ zusätzlich zu den Systemadmins vergeben: Administration → Benutzer → Gruppen → Berechtigungen
        return ExitCode::OK;
    }

    /**
     * Recherchierte Vereinsangaben einspielen: setup/vereine-import /tmp/recherche.json [1 = nur anzeigen]
     * JSON-Array je Verein (id, beschreibung, treffpunkt, website, email, telefon, ansprechpartner, social, logo_url,
     * logo_quelle, bilder [{url, beschreibung, urheber, lizenz, seite}], quellen). Nur Vereine mit Status „offen“
     * (vom Verein noch nicht übernommen) werden geändert; Bilder eines früheren Imports werden ersetzt.
     */
    public function actionVereineImport($path, $dryRun = 0)
    {
        Yii::$app->user->switchIdentity(User::findOne(['username' => 'admin']));
        $stats = ['geändert' => 0, 'übersprungen' => 0, 'logos' => 0, 'bilder' => 0];
        foreach (json_decode(file_get_contents($path), true) as $row) {
            $verein = \humhub\modules\vereine\models\Verein::findOne((int)($row['id'] ?? 0));
            if ($verein === null || $verein->status !== 'offen' || empty($row['gefunden'])) {
                $stats['übersprungen']++;
                continue;
            }
            $clean = fn($v, $max = 255) => mb_substr(trim((string)$v), 0, $max);
            $verein->beschreibung = $clean($row['beschreibung'] ?? '', 20000) ?: null;
            $verein->treffpunkt = $clean($row['treffpunkt'] ?? '') ?: null;
            $verein->website = $clean($row['website'] ?? '') ?: $verein->website;
            $verein->email = $clean($row['email'] ?? '', 150) ?: null;
            $verein->telefon = $clean($row['telefon'] ?? '', 50) ?: null;
            $verein->apRows = array_map(fn($p) => (array)$p, (array)($row['ansprechpartner'] ?? []));
            $verein->socialRows = array_map(fn($p) => (array)$p, (array)($row['social'] ?? []));
            $verein->quellen = json_encode(array_values(array_filter((array)($row['quellen'] ?? []),
                fn($u) => preg_match('#^https?://#', (string)$u))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $verein->recherchiert_am = date('Y-m-d');
            // Ungültige Einzelangaben weglassen statt den ganzen Verein zu verwerfen
            if (!$verein->validate()) {
                foreach (array_keys($verein->getErrors()) as $attr) {
                    $this->stderr("  {$verein->name}: $attr verworfen (" . implode(' ', $verein->getErrors($attr)) . ")\n");
                    if ($attr === 'apRows') {
                        $verein->apRows = array_map(fn($p) => ['funktion' => $p['funktion'] ?? '', 'name' => $p['name'] ?? ''], $verein->apRows);
                    } elseif ($attr === 'socialRows') {
                        $verein->socialRows = [];
                    } else {
                        $verein->$attr = null;
                    }
                }
            }
            if ($dryRun) {
                $this->stdout("= {$verein->name}: " . mb_strlen((string)$verein->beschreibung) . ' Zeichen, '
                    . count($verein->apRows) . ' Ansprechpartner, Logo ' . (empty($row['logo_url']) ? 'nein' : 'ja')
                    . ', ' . count($row['bilder'] ?? []) . " Bilder\n");
                continue;
            }
            if (!$verein->save()) {
                $this->stderr("! {$verein->name}: " . json_encode($verein->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                continue;
            }
            foreach ($verein->fileManager->findAll() as $file) {
                $file->delete();
            }
            $verein->updateAttributes(['logo_guid' => null, 'logo_quelle' => null, 'bildnachweise' => null]);
            if (!empty($row['logo_url']) && ($file = $this->vereinImage($row['logo_url'], 600))) {
                $verein->fileManager->attach($file);
                $verein->updateAttributes(['logo_guid' => $file->guid, 'logo_quelle' => $clean($row['logo_quelle'] ?? $row['logo_url'], 500)]);
                $stats['logos']++;
            }
            $credits = [];
            foreach (array_slice((array)($row['bilder'] ?? []), 0, 4) as $img) {
                $lizenz = (string)($img['lizenz'] ?? '');
                if (!preg_match('/^(CC0|CC BY|CC-BY|Public Domain|gemeinfrei|PD)/i', $lizenz) || empty($img['urheber']) && !preg_match('/CC0|Public|gemeinfrei|PD/i', $lizenz)) {
                    $this->stderr("  {$verein->name}: Bild ohne freie Lizenz/Urheber übersprungen ({$img['url']})\n");
                    continue;
                }
                if ($file = $this->vereinImage($img['url'], 1600)) {
                    $verein->fileManager->attach($file);
                    $credits[$file->guid] = ['beschreibung' => $clean($img['beschreibung'] ?? ''), 'urheber' => $clean($img['urheber'] ?? ''),
                        'lizenz' => $clean($lizenz, 60), 'seite' => $clean($img['seite'] ?? '', 500)];
                    $stats['bilder']++;
                }
            }
            if ($credits) {
                $verein->updateAttributes(['bildnachweise' => json_encode($credits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
            $stats['geändert']++;
            $this->stdout("~ {$verein->name}\n");
        }
        $this->stdout(implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($stats), $stats)) . "\n");
        return ExitCode::OK;
    }

    /** Bild laden, SVG/WebP/GIF in PNG umwandeln, auf $max Pixel verkleinern, Metadaten entfernen; als File speichern */
    private function vereinImage($url, $max)
    {
        try {
            // Wikimedia-Originale sind oft über 15 MB: verkleinerte Fassung laden
            if (preg_match('#^(https://upload\.wikimedia\.org/wikipedia/commons)/(\w/\w\w)/([^/]+\.(?:jpe?g|png))$#i', $url, $m)) {
                $url = "{$m[1]}/thumb/{$m[2]}/{$m[3]}/1920px-{$m[3]}"; // nur Standardbreiten erlaubt
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
                CURLOPT_MAXFILESIZE => 15_000_000,
                CURLOPT_USERAGENT => 'community.rundumuffni.de Vereinsverzeichnis (https://community.rundumuffni.de; Kontakt siehe Impressum)']);
            $data = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($data === false || $status !== 200) {
                throw new \RuntimeException("HTTP $status");
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
            if (!str_starts_with($mime, 'image/') && !str_contains($mime, 'svg')) {
                throw new \RuntimeException("kein Bild ($mime)");
            }
            $im = new \Imagick();
            if (str_contains($mime, 'svg')) {
                $im->setBackgroundColor(new \ImagickPixel('transparent'));
                $im->setResolution(300, 300);
            }
            $im->readImageBlob($data);
            $im = $im->coalesceImages()->getImage(); // erstes Bild bei GIF
            if ($im->getImageWidth() < 48 || $im->getImageHeight() < 48) {
                throw new \RuntimeException('zu klein');
            }
            if ($im->getImageWidth() > $max || $im->getImageHeight() > $max) {
                $im->thumbnailImage($max, $max, true);
            }
            $im->stripImage();
            $keepJpeg = $mime === 'image/jpeg';
            $im->setImageFormat($keepJpeg ? 'jpeg' : 'png');
            if ($keepJpeg) {
                $im->setImageCompressionQuality(85);
            }
            $blob = $im->getImageBlob();
            $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo(rawurldecode(parse_url($url, PHP_URL_PATH)), PATHINFO_FILENAME)) ?: 'bild';
            $file = new \humhub\modules\file\models\File([
                'file_name' => mb_substr($name, 0, 80) . ($keepJpeg ? '.jpg' : '.png'),
                'mime_type' => $keepJpeg ? 'image/jpeg' : 'image/png',
                'size' => strlen($blob),
            ]);
            $file->save();
            $file->setStoredFileContent($blob);
            return $file;
        } catch (\Throwable $e) {
            $this->stderr("  Bild $url: {$e->getMessage()}\n");
            return null;
        }
    }

    private function download($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'community.rundumuffni.de RSS-Import',
        ]);
        $data = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($data === false || $status !== 200) {
            throw new \RuntimeException("Download fehlgeschlagen ($status): $url");
        }
        return $data;
    }

    private function attachImage($post, $url)
    {
        try {
            $data = $this->download($url);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
            if (!str_starts_with($mime, 'image/')) {
                return;
            }
            $file = new \humhub\modules\file\models\File([
                'file_name' => basename(parse_url($url, PHP_URL_PATH)),
                'mime_type' => $mime,
                'size' => strlen($data),
            ]);
            $file->save();
            $file->setStoredFileContent($data);
            $post->fileManager->attach($file);
        } catch (\Throwable $e) {
            $this->stderr("! Bild $url: {$e->getMessage()}\n");
        }
    }

    /** HTML aus dem Feed in HumHub-Markdown; Bilder werden separat zurückgegeben. */
    private function htmlToMarkdown($html, $base)
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR);
        $images = [];
        $abs = function ($href) use ($base) {
            if (preg_match('#^(https?:|mailto:|tel:)#i', $href)) {
                return $href;
            }
            $origin = preg_replace('#^(https?://[^/]+).*#', '$1', $base);
            return $origin . '/' . ltrim($href, '/');
        };
        $walk = function (\DOMNode $node) use (&$walk, &$images, $abs) {
            if ($node instanceof \DOMText) {
                return preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $node->textContent));
            }
            $inner = '';
            foreach ($node->childNodes as $child) {
                $inner .= $walk($child);
            }
            switch (strtolower($node->nodeName)) {
                case 'img':
                    $images[] = $abs($node->getAttribute('src'));
                    return '';
                case 'br':
                    return "\n";
                case 'strong':
                case 'b':
                    return trim($inner) === '' ? $inner : '**' . trim($inner) . '** ';
                case 'em':
                case 'i':
                    return trim($inner) === '' ? $inner : '*' . trim($inner) . '* ';
                case 'a':
                    $href = $node->getAttribute('href');
                    // TYPO3 verschleiert E-Mail-Adressen per JavaScript, der Link ist dann nur "#"
                    if (preg_match('/^(#|javascript:)/i', $href)) {
                        $href = filter_var(trim($inner), FILTER_VALIDATE_EMAIL) ? 'mailto:' . trim($inner) : '';
                    }
                    return $href === '' || trim($inner) === '' ? $inner : '[' . trim($inner) . '](' . $abs($href) . ')';
                case 'li':
                    return '- ' . trim($inner) . "\n";
                case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                    return "\n\n**" . trim($inner) . "**\n\n";
                case 'p':
                case 'div':
                case 'ul':
                case 'ol':
                case 'tr':
                    return "\n\n" . trim($inner) . "\n\n";
                case 'td':
                case 'th':
                    return trim($inner) . ' ';
                default:
                    return $inner;
            }
        };
        $text = $walk($doc->getElementsByTagName('div')->item(0));
        $text = preg_replace(["/[ \t]+\n/", "/\n[ \t]+/", "/ +([.,;:!?])/", "/\n{3,}/"], ["\n", "\n", '$1', "\n\n"], $text);
        return [trim($text), array_values(array_unique($images))];
    }

    /**
     * Probelauf Gutscheine: setup/gutschein-test [behalten=0]
     * Legt einen Testbetrieb und zwei Testkonten an (ohne Glocken an echte Personen), prüft Einlösen, Limits,
     * Zurücknehmen, Nummer, automatische Pause und QR; räumt danach alles wieder ab. Mit behalten=1 bleiben
     * Betrieb, Gutschein und Konten (mit Passwort) für einen Test im Browser; abräumen: setup/gutschein-test-aufraeumen
     */
    /**
     * Kostenlose WhatsApp-Variante (Module::$vorlagenNutzen = false) prüfen, alles in einer Transaktion mit Rollback:
     * Auftrag/Nachricht an einen Mitarbeiter mit geschlossenem Fenster werden vorgemerkt (keine Vorlage, kein API-Aufruf),
     * die Bauhofleitung bekommt „bitte anrufen“ (Queue-Job, wird zurückgerollt), Meldende bekommen keine Vorlage.
     *   scripts/run-setup.sh bauhof-kostenlos-test [content-id einer Meldung]
     */
    public function actionBauhofKostenlosTest($id = 478)
    {
        $B = \humhub\modules\maengelmelder\components\Bauhof::class;
        $N = \humhub\modules\maengelmelder\models\BauhofNachricht::class;
        $ok = 0;
        $fehl = 0;
        $pruefe = function (string $was, bool $bed) use (&$ok, &$fehl) {
            $bed ? $ok++ : $fehl++;
            $this->stdout(($bed ? '  ok   ' : '  FEHL ') . "$was\n");
        };
        $pruefe('Vorlagen aus (vorlagenNutzen = false)', Yii::$app->getModule('maengelmelder')->vorlagenNutzen === false);
        $content = \humhub\modules\content\models\Content::findOne((int)$id);
        $admin = User::findOne(['username' => 'admin']);
        if (!$content || !$admin) {
            $this->stderr("Meldung $id oder Konto admin fehlt.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $jobsVorher = (int)Yii::$app->db->createCommand('SELECT COUNT(*) FROM queue')->queryScalar();
        $tx = Yii::$app->db->beginTransaction();
        try {
            $m = new \humhub\modules\maengelmelder\models\Mitarbeiter(['name' => 'Test Kostenlos', 'telefon' => '490000000001', 'aktiv' => 1]);
            $m->save(false);
            $pruefe('Fenster des Test-Mitarbeiters ist zu', !$m->isFensterOffen());
            $auftrag = \humhub\modules\maengelmelder\models\Auftrag::forContentId($content->id);
            $auftrag->mitarbeiter_id = $m->id;
            $auftrag->zugewiesen_by = $admin->id;
            $auftrag->save(false);
            $hinweis = $B::sendeAuftrag($auftrag, $admin);
            $this->stdout("       Hinweis: $hinweis\n");
            $pruefe('Auftrag: Hinweis „bitte anrufen“ mit Nummer', $hinweis !== null && str_contains($hinweis, 'anrufen') && str_contains($hinweis, '+49 000'));
            $pruefe('Auftrag vorgemerkt (wartet)', $N::find()->where(['mitarbeiter_id' => $m->id, 'typ' => 'auftrag', 'zustellung' => 'wartet'])->exists());
            $pruefe('Auftrag nicht als gesendet markiert', \humhub\modules\maengelmelder\models\Auftrag::findOne($content->id)->gesendet_at === null);
            $fehler = $B::nachricht($m, $content->id, 'Testnachricht', $admin);
            $pruefe('Nachricht ohne Fehler vorgemerkt', $fehler === null
                && $N::find()->where(['mitarbeiter_id' => $m->id, 'typ' => 'text', 'zustellung' => 'wartet'])->exists());
            $jobs = (int)Yii::$app->db->createCommand('SELECT COUNT(*) FROM queue')->queryScalar() - $jobsVorher;
            $pruefe("Glocke „bitte anrufen“ nur einmal je Wartezeit (Queue-Jobs: $jobs)", $jobs === 1);
            $text = \humhub\modules\maengelmelder\notifications\BauhofNichtZugestellt::instance()
                ->about($N::find()->where(['mitarbeiter_id' => $m->id, 'typ' => 'auftrag'])->one())->text();
            $this->stdout("       Glocke: $text\n");
            $pruefe('Glocke nennt Auftrag und Nummer', str_contains($text, "Auftrag Nr. {$content->id}") && str_contains($text, '+49 000'));
            // Meldende Person: kein offenes Fenster → keine Vorlage, ohne Ersatz-Mail „fehler“ mit Grund
            $k = new \humhub\modules\maengelmelder\models\MaengelKontakt(['content_id' => $content->id, 'wa_id' => '490000000002']);
            $ref = new \ReflectionMethod(\humhub\modules\maengelmelder\components\Buergerinfo::class, 'whatsapp');
            $log = $ref->invoke(null, $k, $content->id, 'Test', 'maengel_status_du', ['x'], 'status');
            $pruefe('Meldende: keine Vorlage, Grund „kostenlos“', $log->wamid === null && str_contains((string)$log->fehler, 'kostenlos'));
        } finally {
            $tx->rollBack();
        }
        $pruefe('Alles zurückgerollt', !\humhub\modules\maengelmelder\models\Mitarbeiter::find()->where(['telefon' => '490000000001'])->exists());
        $this->stdout("$ok ok, $fehl Fehler\n");
        return $fehl ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Fotos von Bauhof-Mitarbeitern ohne Antwort-Bezug: Rückfrage „Wohin gehört das Foto?“ statt stiller Zuordnung.
     * Prüft Zuordnung (sicher/vermutet), Frist und Knopf „Keinem Auftrag“ in einer Transaktion mit Rollback
     * (Antworten gehen an eine Fantasienummer und scheitern bei Meta).
     *   scripts/run-setup.sh bauhof-foto-test [content-id einer offenen Meldung]
     */
    public function actionBauhofFotoTest($id = 524)
    {
        $B = \humhub\modules\maengelmelder\components\Bauhof::class;
        $N = \humhub\modules\maengelmelder\models\BauhofNachricht::class;
        $ok = 0;
        $fehl = 0;
        $pruefe = function (string $was, bool $bed) use (&$ok, &$fehl) {
            $bed ? $ok++ : $fehl++;
            $this->stdout(($bed ? '  ok   ' : '  FEHL ') . "$was\n");
        };
        $zuordnen = new \ReflectionMethod($B, 'zuordnen');
        $knopf = new \ReflectionMethod($B, 'knopf');
        $tx = Yii::$app->db->beginTransaction();
        try {
            $m = new \humhub\modules\maengelmelder\models\Mitarbeiter(['name' => 'Test Foto', 'telefon' => '490000000003', 'aktiv' => 1]);
            $m->save(false);
            $auftrag = \humhub\modules\maengelmelder\models\Auftrag::forContentId((int)$id);
            $auftrag->mitarbeiter_id = $m->id;
            $auftrag->save(false);
            $pruefe("Meldung $id ist einziger offener Auftrag", count($m->getOffeneAuftraege()) === 1);
            $foto = function (array $a = []) use ($m, $N) {
                $n = new $N($a + ['mitarbeiter_id' => $m->id, 'richtung' => $N::EIN, 'typ' => 'image',
                    'wamid' => 'test-' . uniqid(), 'verarbeitet' => 1, 'created_at' => date('Y-m-d H:i:s')]);
                $n->save(false);
                return $n;
            };
            $n = $foto();
            $sicher = null;
            $r = $zuordnen->invokeArgs(null, [$m, $n, &$sicher]);
            $pruefe('Foto ohne Bezug, nichts los am Auftrag → vermutet, nicht sicher (Rückfrage)', $r === (int)$id && $sicher === false);
            $aus = new $N(['mitarbeiter_id' => $m->id, 'content_id' => (int)$id, 'richtung' => $N::AUS, 'typ' => 'text',
                'wamid' => 'test-aus-' . uniqid(), 'verarbeitet' => 1, 'created_at' => date('Y-m-d H:i:s', time() - 600)]);
            $aus->save(false);
            $r = $zuordnen->invokeArgs(null, [$m, $n, &$sicher]);
            $pruefe('Auftrag vor 10 Min. angezeigt → sicher (keine Rückfrage)', $r === (int)$id && $sicher === true);
            $aus->updateAttributes(['created_at' => date('Y-m-d H:i:s', time() - 3600)]);
            $r = $zuordnen->invokeArgs(null, [$m, $n, &$sicher]);
            $pruefe('Auftrag vor 1 Std. angezeigt → wieder Rückfrage', $sicher === false);
            $antwort = $foto(['bezug_wamid' => $aus->wamid]);
            $r = $zuordnen->invokeArgs(null, [$m, $antwort, &$sicher]);
            $pruefe('Antwort auf Auftragsnachricht → sicher', $r === (int)$id && $sicher === true);

            $n->updateAttributes(['zustellung' => 'frage']);
            $B::frageFrist($n->id);
            $n->refresh();
            $pruefe('Frist abgelaufen → Foto beim vermuteten Auftrag, Frage erledigt', (int)$n->content_id === (int)$id && $n->zustellung === null);

            $f1 = $foto(['zustellung' => 'frage']);
            $f2 = $foto(['zustellung' => 'frage']);
            $knopf->invoke(null, $m, $foto(['typ' => 'knopf', 'text' => 'n']));
            $f1->refresh();
            $f2->refresh();
            $pruefe('Knopf „Keinem Auftrag“ → beide Fotos ohne Auftrag, Frage erledigt',
                $f1->content_id === null && $f2->content_id === null && $f1->zustellung === null && $f2->zustellung === null);
            $f3 = $foto(['zustellung' => 'frage']);
            $knopf->invoke(null, $m, $foto(['typ' => 'knopf', 'text' => "z:$id"]));
            $f3->refresh();
            $pruefe('Knopf „Auftrag Nr.“ → Foto beim Auftrag', (int)$f3->content_id === (int)$id && $f3->zustellung === null);

            // Meldende Person (Auftrag ist oben dem Test-Mitarbeiter zugewiesen → keine echte Weiterleitung)
            $I = \humhub\modules\maengelmelder\components\Buergerinfo::class;
            $buerger = '490000000004';
            \humhub\modules\maengelmelder\models\MaengelKontakt::deleteAll(['content_id' => (int)$id]);
            (new \humhub\modules\maengelmelder\models\MaengelKontakt(['content_id' => (int)$id, 'wa_id' => $buerger,
                'info_whatsapp' => 1, 'wa_bestaetigt' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]))->save(false);
            $N::deleteAll(['content_id' => (int)$id, 'buerger' => 1]);
            $empfang = function (array $extra = []) use ($I, $buerger) {
                $d = $extra + ['id' => 'test-b-' . uniqid(), 'from' => $buerger, 'type' => 'image',
                    'image' => ['id' => 'x'], 'timestamp' => time()];
                $ev = new \humhub\modules\whatsapp\events\EmpfangEvent(['daten' => $d]);
                $I::onEmpfangen($ev);
                return [$ev->handled, \humhub\modules\maengelmelder\models\BauhofNachricht::findOne(['wamid' => $d['id']])];
            };
            [$h, $b1] = $empfang();
            $pruefe('Bürger: Foto ohne Bezug → bei der Meldung, aber Rückfrage offen', $h && $b1 && (int)$b1->content_id === (int)$id && $b1->zustellung === 'frage');
            [$h] = $empfang(['context' => ['forwarded' => true]]);
            $pruefe('Bürger: weitergeleitetes Foto → Termin-Eingang', $h === false);
            $knopfDaten = ['type' => 'interactive', 'from' => $buerger, 'interactive' => ['button_reply' => ['id' => 'bt', 'title' => 'x']]];
            $pruefe('Bürger-Knopf gehört zur Meldung (auch bei Mitarbeiter-Nummern)', $I::gehoertZuMeldung($knopfDaten));
            $I::knopf($buerger, "bm:$id");
            $b1->refresh();
            $pruefe('Bürger-Knopf „Meldung Nr.“ → Rückfrage erledigt', $b1->zustellung === null && (int)$b1->content_id === (int)$id);
            [$h, $b2] = $empfang();
            $pruefe('Bürger: gerade erst geschrieben → keine Rückfrage', $h && $b2 && $b2->zustellung === null);
            $b2->updateAttributes(['zustellung' => 'frage']);
            $I::frageFrist($b2->id);
            $b2->refresh();
            $pruefe('Bürger: Frist abgelaufen → Foto bleibt bei der Meldung', $b2->zustellung === null);
        } finally {
            $tx->rollBack();
        }
        $pruefe('Alles zurückgerollt', !\humhub\modules\maengelmelder\models\Mitarbeiter::find()->where(['telefon' => '490000000003'])->exists());
        $this->stdout("$ok ok, $fehl Fehler\n");
        return $fehl ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionGutscheinTest($behalten = 0)
    {
        $D = \humhub\modules\gewerbe\components\GutscheinDienst::class;
        $D::$stumm = true;
        // Die Texte werden auf Deutsch verglichen; die Konsole läuft sonst auf en-US (seit der Mehrsprachigkeit)
        \Yii::$app->language = 'de';
        $this->actionGutscheinTestAufraeumen(true);
        $ok = 0; $fehl = 0;
        $pruefe = function (string $was, bool $bed) use (&$ok, &$fehl) {
            $bed ? $ok++ : $fehl++;
            $this->stdout(($bed ? '  ok   ' : '  FEHL ') . $was . "\n");
        };
        $konto = function (string $name) {
            $u = new User(['scenario' => 'editAdmin']);
            $u->username = $name;
            $u->email = "$name@example.invalid";
            $u->status = User::STATUS_ENABLED;
            $u->save() || throw new \Exception(json_encode($u->getErrors()));
            $u->profile->scenario = 'editAdmin';
            $u->profile->firstname = 'Test';
            $u->profile->lastname = $name;
            $u->profile->save(false);
            $pw = new \humhub\modules\user\models\Password();
            $pw->user_id = $u->id;
            $pw->setPassword('Gutschein-Test-2026!');
            $pw->save(false);
            return $u;
        };
        $verwalter = $konto('gs-test-verwalter');
        $kunde = $konto('gs-test-kunde');
        $b = new \humhub\modules\gewerbe\models\Betrieb(['name' => 'TEST Gutscheinbetrieb', 'kategorie' => 'Sonstiges',
            'ortsteil' => 'Uffenheim', 'status' => 'aktiv', 'geocodeAus' => true]);
        $b->save(false);
        (new \humhub\modules\gewerbe\models\BetriebVerwalter(['betrieb_id' => $b->id, 'user_id' => $verwalter->id, 'status' => 'bestaetigt']))->save(false);
        $D::theke($b);
        $this->stdout("Betrieb {$b->id}, Theke {$b->theke_nummer}\n");
        $g = new \humhub\modules\gewerbe\models\Gutschein(['betrieb_id' => $b->id, 'titel' => 'TEST 10 % auf alles', 'pro_person' => 'einmal',
            'gueltig_ab' => date('Y-m-d'), 'gueltig_bis' => date('Y-m-d', strtotime('+7 days')), 'limit_gesamt' => 2, 'created_by' => $verwalter->id]);
        $g->scenario = 'formular';
        $pruefe('Formular ohne Bestätigung der Hinweise abgelehnt', !$g->validate() && $g->hasErrors('regelnOk'));
        $g->regelnOk = 1;
        $pruefe('Gutschein gespeichert', $g->save());
        $zuLang = new \humhub\modules\gewerbe\models\Gutschein(['titel' => 'Zu lang', 'pro_person' => 'einmal', 'gueltig_ab' => date('Y-m-d'),
            'gueltig_bis' => date('Y-m-d', strtotime('+400 days'))]);
        $pruefe('Laufzeit über ein Jahr abgelehnt', !$zuLang->validate() && $zuLang->hasErrors('gueltig_bis'));
        $pruefe('Gast: Anmeldung nötig', $D::grund($g, null) === 'Zum Einlösen musst du angemeldet sein.');
        $pruefe('Falsche Nummer abgelehnt', $D::nummerPruefen($b, '0000' === $b->theke_nummer ? '1111' : '0000', $kunde->id) !== null);
        $pruefe('Richtige Nummer angenommen', $D::nummerPruefen($b, $b->theke_nummer, $kunde->id) === null);
        $pruefe('Token findet Betrieb', $D::betriebZuToken($b->theke_token)?->id === $b->id);
        $pruefe('Falsches Token findet nichts', $D::betriebZuToken('x' . substr($b->theke_token, 1)) === null || $b->theke_token[0] === 'x');
        $e = $D::einloesen($g, $kunde->id);
        $pruefe('Kunde löst ein (Code ' . ($e->code ?? '-') . ')', $e instanceof \humhub\modules\gewerbe\models\Einloesung);
        $g->refresh();
        $pruefe('Zähler 1', $g->eingeloest === 1);
        $pruefe('Zweites Mal abgelehnt (einmal pro Person)', is_string($D::einloesen($g, $kunde->id)));
        $pruefe('Zurücknehmen klappt', $D::stornieren($e));
        $g->refresh();
        $pruefe('Zähler nach Rücknahme 0', $g->eingeloest === 0);
        $pruefe('Nach Rücknahme wieder einlösbar', $D::einloesen($g, $kunde->id) instanceof \humhub\modules\gewerbe\models\Einloesung);
        $pruefe('Verwalter löst ein (2 von 2)', $D::einloesen($g, $verwalter->id) instanceof \humhub\modules\gewerbe\models\Einloesung);
        $g->refresh();
        $pruefe('Limit erreicht: aufgebraucht', $g->istAufgebraucht() && $D::grundAllgemein($g) === 'Alle Gutscheine sind schon eingelöst.');
        // Woche: zweite Einlösung erst nach 7 Tagen
        $w = new \humhub\modules\gewerbe\models\Gutschein(['betrieb_id' => $b->id, 'titel' => 'TEST Wochenrabatt', 'pro_person' => 'woche',
            'gueltig_ab' => date('Y-m-d'), 'gueltig_bis' => date('Y-m-d', strtotime('+30 days'))]);
        $w->save();
        $D::einloesen($w, $kunde->id);
        $grund = $D::grund($w, $kunde->id);
        $pruefe("Woche: Sperre mit Datum ($grund)", str_contains((string)$grund, 'Wieder möglich ab'));
        // Pausiert / kommend
        $D::pausieren($w, null);
        $w->refresh();
        $pruefe('Pausiert: nicht einlösbar', $D::grund($w, $verwalter->id) === 'Der Betrieb hat diesen Gutschein gerade pausiert.');
        $k = new \humhub\modules\gewerbe\models\Gutschein(['betrieb_id' => $b->id, 'titel' => 'TEST kommt noch', 'pro_person' => 'unbegrenzt',
            'gueltig_ab' => date('Y-m-d', strtotime('+3 days')), 'gueltig_bis' => date('Y-m-d', strtotime('+9 days'))]);
        $k->save();
        $pruefe('Kommend: noch nicht einlösbar', str_starts_with((string)$D::grund($k, $kunde->id), 'Der Gutschein gilt erst ab'));
        // Automatische Pause bei auffällig vielen Einlösungen
        $module = Yii::$app->getModule('gewerbe');
        $alt = $module->sperreAnzahl;
        $module->sperreAnzahl = 3;
        $u = new \humhub\modules\gewerbe\models\Gutschein(['betrieb_id' => $b->id, 'titel' => 'TEST unbegrenzt', 'pro_person' => 'unbegrenzt',
            'gueltig_ab' => date('Y-m-d'), 'gueltig_bis' => date('Y-m-d', strtotime('+3 days'))]);
        $u->save();
        for ($i = 0; $i < 3; $i++) {
            $D::einloesen($u, $kunde->id);
        }
        $u->refresh();
        $module->sperreAnzahl = $alt;
        $pruefe('Viele Einlösungen: automatisch pausiert', $u->status === 'pausiert' && str_starts_with((string)$u->pause_grund, 'Automatisch'));
        $svg = $D::qrSvg($D::thekeUrl($b));
        $pruefe('QR-Code als SVG', str_contains($svg, '<svg') && strlen($svg) > 1000);
        $this->stdout("Theken-Link: " . $D::thekeUrl($b) . "\n");
        $pruefe('Sichtbar-Liste enthält Gutschein', \humhub\modules\gewerbe\models\Gutschein::sichtbar()->andWhere(['gewerbe_gutschein.id' => $g->id])->exists());
        // Benachrichtigung „Neue Gutscheine“ an alle (nur aufbauen, nicht senden)
        $fuerAlle = \humhub\modules\gewerbe\notifications\GutscheinFuerAlle::instance()->about($g);
        $pruefe('Benachrichtigung „Neue Gutscheine“: Text und Kategorie',
            str_contains($fuerAlle->html(), $g->titel) && $fuerAlle->getCategory()->id === 'gutscheine');
        // Kontolöschung räumt Einlösungen ab
        $n = \humhub\modules\gewerbe\models\Einloesung::find()->where(['user_id' => $kunde->id])->count();
        \humhub\modules\gewerbe\Events::onUserLoeschen((object)['user' => $kunde]);
        $pruefe("Kontolöschung entfernt $n Einlösungen", !\humhub\modules\gewerbe\models\Einloesung::find()->where(['user_id' => $kunde->id])->exists());
        if ($behalten) {
            $D::thekeErneuern($b);
            $b->refresh();
            $u->updateAttributes(['status' => 'aktiv', 'pause_grund' => null]);
            $this->stdout("Behalten. Konten gs-test-verwalter / gs-test-kunde, Passwort Gutschein-Test-2026!\n"
                . "Gutschein {$u->id}, Theken-Link " . $D::thekeUrl($b) . "\nAbräumen: setup/gutschein-test-aufraeumen\n");
        } else {
            $this->actionGutscheinTestAufraeumen(true);
        }
        $this->stdout("$ok ok, $fehl Fehler\n");
        return $fehl ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionGutscheinTestAufraeumen($still = false)
    {
        foreach (\humhub\modules\gewerbe\models\Betrieb::findAll(['name' => 'TEST Gutscheinbetrieb']) as $b) {
            $b->delete();
            $still || $this->stdout("- Betrieb {$b->id}\n");
        }
        foreach (['gs-test-verwalter', 'gs-test-kunde'] as $name) {
            if ($u = User::findOne(['username' => $name])) {
                $u->delete();
                $still || $this->stdout("- Konto $name\n");
            }
        }
        return ExitCode::OK;
    }

    /**
     * Spiele & Ergebnisse vom BFV: Abruf mit nachgestellten Antworten (Mannschaften, Spiele, SPIELFREI, Spielorte,
     * „Keine Angabe“, Tabellen, keine Tabelle im Kinderfußball, Entfernen), Wertung, Zustand, Bezeichnungen, Uffi;
     * dazu Basketball (DBB), Volleyball (BVV) und Badminton (BBV) über components/Spielquellen.php.
     * Läuft in einer Transaktion, die zurückgerollt wird; ruft nichts bei den Verbänden ab.
     *   scripts/run-setup.sh vereine-spiele-test
     */
    public function actionVereineSpieleTest()
    {
        $Bfv = \humhub\modules\vereine\components\Bfv::class;
        $ok = 0;
        $fehler = 0;
        $gleich = function (string $was, $ist, $soll) use (&$ok, &$fehler) {
            $bedingung = $ist === $soll;
            $bedingung ? $ok++ : $fehler++;
            $this->stdout(($bedingung ? 'ok   ' : 'FEHL ') . "$was\n" . (!$bedingung ? '     ist: ' . var_export($ist, true) . ', soll: ' . var_export($soll, true) . "\n" : ''));
        };
        $sprache = Yii::$app->language;
        Yii::$app->language = 'de';
        $tx = Yii::$app->db->beginTransaction();
        try {
            $club = str_repeat('C', 32);
            $h1 = str_repeat('H', 32);
            $e1 = str_repeat('E', 32);
            $morgen = date('d.m.Y', strtotime('+1 day'));
            $spiel = fn($id, $datum, $zeit, $heim, $gast, $heimId, $ergebnis, $typ = 'Meisterschaften') => ['matchId' => $id, 'kickoffDate' => $datum,
                'kickoffTime' => $zeit, 'competitionName' => 'Liga', 'competitionType' => $typ, 'homeTeamName' => $heim, 'guestTeamName' => $gast,
                'homeTeamPermanentId' => $heimId, 'result' => $ergebnis];
            $antworten = [
                'https://www.bfv.de/vereine/-/' . $club => '<a href="https://www.bfv.de/mannschaften/test/' . $h1 . '">x</a><a href="https://www.bfv.de/mannschaften/test-e/' . $e1 . '">x</a>'
                    . '<div class="bfv-spieltag-eintrag"><a href="https://www.bfv.de/ergebnisse/spiel/-/' . str_repeat('1', 32) . '"></a>'
                    . '<div class="bfv-spieltag-eintrag__location"> Sportanlage Uffenheim An den Seewiesen, Platz 1 | An den Seewiesen | 97215 Uffenheim </div></div>',
                $Bfv::API . "team/$h1/matches" => json_encode(['state' => 0, 'data' => ['team' => ['name' => 'Test FV II', 'typeName' => 'Herren', 'clubId' => $club,
                    'clubName' => 'Test FV', 'compoundId' => 'LIGA-G', 'competitionName' => 'Kreisliga'], 'matches' => [
                    $spiel(str_repeat('1', 32), $morgen, '15:00', 'Test FV II', 'Gast SV', $h1, ''),
                    $spiel(str_repeat('2', 32), '01.09.2026', '15:00', 'Heim SV', 'Test FV II', 'X', '1:3'),
                    $spiel(str_repeat('3', 32), '02.09.2026', '15:00', 'Test FV II', 'Gast SV', $h1, '2:2', 'Pokale'),
                    $spiel(str_repeat('4', 32), '03.09.2026', '', 'Test FV II', 'Gast SV', $h1, 'Abse.'),
                    $spiel(str_repeat('5', 32), $morgen, '', 'SPIELFREI', 'Test FV II', 'X', ''),
                    $spiel(str_repeat('6', 32), $morgen, '17:00', 'Test FV II', 'Gast SV', $h1, ''),
                ]]]),
                $Bfv::API . "team/$e1/matches" => json_encode(['state' => 0, 'data' => ['team' => ['name' => '(SG) Test/Nachbar I', 'typeName' => 'E-Junioren',
                    'clubId' => $club, 'compoundId' => 'E-LIGA-G', 'competitionName' => 'U11'], 'matches' => [
                    $spiel(str_repeat('7', 32), '05.09.2026', '10:00', 'Fremd', '(SG) Test/Nachbar I', 'X', '7:9 nE'),
                ]]]),
                $Bfv::API . 'competition/LIGA-G/table' => json_encode(['state' => 0, 'data' => ['table' => [
                    ['position' => 1, 'matches' => 3, 'matchesWon' => 3, 'matchesDrawn' => 0, 'matchesLost' => 0, 'goalsDiff' => 5, 'points' => 9, 'team' => ['permanentId' => 'X', 'name' => 'Heim SV']],
                    ['position' => 2, 'matches' => 3, 'matchesWon' => 1, 'matchesDrawn' => 1, 'matchesLost' => 1, 'goalsDiff' => 1, 'points' => 4, 'team' => ['permanentId' => $h1, 'name' => 'Test FV II']],
                ]]]),
                'https://www.bfv.de/ergebnisse/spiel/-/' . str_repeat('6', 32) => '<span class="a">Adresse</span><span class="b">Keine Angabe</span>',
            ];
            $abgerufen = [];
            $Bfv::$holen = function (string $url) use (&$antworten, &$abgerufen) {
                $abgerufen[] = $url;
                if (!isset($antworten[$url])) {
                    throw new \RuntimeException("HTTP 404 bei $url");
                }
                return $antworten[$url];
            };
            $verein = new \humhub\modules\vereine\models\Verein(['name' => 'Testverein Spiele ' . uniqid(), 'kategorie' => 'Sport', 'ortsteil' => 'Uffenheim', 'status' => 'offen']);
            $verein->save(false);
            $verein->updateAttributes(['bfv_id' => $club]);
            $log = $Bfv::abrufen($verein);
            $gleich('Abruf ohne Fehler', preg_grep('/^!/', $log), []);
            $gleich('BFV-Name übernommen', $verein->bfv_name, 'Test FV');
            $S = \humhub\modules\vereine\models\VereinSpiel::class;
            $M = \humhub\modules\vereine\models\VereinMannschaft::class;
            $gleich('Spiele Herren II (ohne SPIELFREI)', (int)$S::find()->where(['mannschaft_id' => $h1])->count(), 5);
            $s1 = $S::findOne(['spiel_id' => str_repeat('1', 32)]);
            $gleich('Spielort aus der Vereinsseite', $s1->ort, 'Sportanlage Uffenheim An den Seewiesen, Platz 1 | An den Seewiesen | 97215 Uffenheim');
            $gleich('Ort kurz', $Bfv::ortKurz($s1->ort), 'Sportanlage Uffenheim An den Seewiesen, Platz 1');
            $gleich('„Keine Angabe“ wird leer', $S::findOne(['spiel_id' => str_repeat('6', 32)])->ort, '');
            $gleich('Heimspiel erkannt', (int)$s1->heim_eigen, 1);
            $gleich('kommendes Spiel nicht vorbei', $s1->istVorbei(), false);
            $s2 = $S::findOne(['spiel_id' => str_repeat('2', 32)]);
            $gleich('Auswärtssieg 1:3', [$s2->getWertung(), $s2->getGegner(), (int)$s2->heim_eigen], ['s', 'Heim SV', 0]);
            $gleich('Unentschieden', $S::findOne(['spiel_id' => str_repeat('3', 32)])->getWertung(), 'u');
            $s4 = $S::findOne(['spiel_id' => str_repeat('4', 32)]);
            $gleich('abgesetzt ohne Uhrzeit', [$s4->getZustand(), $s4->uhrzeit, $s4->getWertung()], ['abgesetzt', null, null]);
            $gleich('Elfmeterschießen 7:9 nE als Sieg der SG (Gast)', $S::findOne(['spiel_id' => str_repeat('7', 32)])->getWertung(), 's');
            $herren = $M::findOne($h1);
            $gleich('Bezeichnung Herren II', $herren->getLabel(), 'Herren II');
            $gleich('Bezeichnung SG E-Junioren I', $M::findOne($e1)->getLabel(), 'E-Junioren I (SG)');
            $gleich('Tabellenplatz', [$herren->getPlatz()['platz'] ?? null, $herren->getPlatz()['punkte'] ?? null], [2, 4]);
            $gleich('keine Tabelle im Kinderfußball', [$M::findOne($e1)->tabelle, in_array($Bfv::API . 'competition/E-LIGA-G/table', $abgerufen, true)], [null, false]);
            $gleich('Reihenfolge Herren vor E-Junioren', array_map(fn($m) => $m->typ, $M::sortieren([$M::findOne($e1), $herren])), ['Herren', 'E-Junioren']);
            $kurz = $Bfv::kurz($verein, 2);
            $gleich('Kurzübersicht: nächste Spiele', array_map(fn($s) => $s->spiel_id, $kurz['kommend']), [str_repeat('1', 32), str_repeat('6', 32)]);
            $uffi = (string)$Bfv::forAssistant();
            $gleich('Uffi: Heimspiel mit Ort', str_contains($uffi, 'Herren II: Test FV II – Gast SV (Heimspiel, Sportanlage Uffenheim An den Seewiesen, Platz 1)'), true);
            $gleich('Uffi: Tabellenplatz', str_contains($uffi, '- Herren II (Kreisliga): Platz 2 von 2, 4 Punkte aus 3 Spielen'), true);
            $gleich('Uffi: Link zur Seite', str_contains($uffi, '/vereine/spiele/index?verein=' . $verein->id), true);

            // Liveticker: Seite mit eingebettetem JSON wie bei bfv.de
            $e = fn($typ, $min, $text, $score, $sek, $kommentar = '') => ['typeId' => $typ, 'minute' => $min, 'description' => $text,
                'comment' => $kommentar, 'score' => $score, 'timestamp' => '$D2026-11-01T15:' . sprintf('%02d', intdiv($sek, 60)) . ':' . sprintf('%02d', $sek % 60) . '.000Z'];
            $tickerSeite = fn(array $ev) => '<html><script>self.__next_f.push([1,' . json_encode('5:["$","x",{"events":[{"icon":"goal"}]}]' . "\n" . '7:{"events":' . json_encode($ev) . '}') . '])</script></html>';
            $ev = [$e(28, 1, 'Anpfiff der Begegnung', '0:0', 1), $e(1, 12, 'Tor durch Max Muster (9)', '1:0', 12, 'Toller Schuss'),
                $e(5, 20, 'Ecke für Gast SV', '1:0', 20), $e(2, 30, 'Gelbe Karte für Erika Beispiel (4)', '1:0', 30),
                $e(3, 40, 'Rote Karte für Hans Probe (7)', '1:0', 40), $e(4, 41, 'Anna (3) für Berta (5)', '1:0', 41)];
            $s6 = str_repeat('6', 32);
            $antworten["https://www.bfv.de/ergebnisse/spiel/-/$s6/liveticker"] = $tickerSeite($ev);
            $t = $Bfv::ticker($s6, true);
            $gleich('Ticker: läuft, Stand, Minute', [$t['status'], $t['stand'], $t['minute']], ['live', '1:0', 41]);
            $gleich('Ticker: nur Tore, Karten, Phasen (neueste zuerst)', array_column($t['ereignisse'], 'art'), ['rot', 'gelb', 'tor', 'phase']);
            $gleich('Ticker: Torschütze und Kommentar (Erwachsene)', [$t['ereignisse'][2]['t'], $t['ereignisse'][2]['k']], ['Tor durch Max Muster (9)', 'Toller Schuss']);
            $t = $Bfv::ticker($s6, false);
            $gleich('Ticker Jugend: ohne Namen und Kommentare', array_map(fn($x) => $x['t'] . '|' . $x['k'], $t['ereignisse']),
                ['Rote Karte|', 'Gelbe Karte|', 'Tor|', 'Anpfiff der Begegnung|']);
            Yii::$app->getModule('vereine')->settings->set('tickerGesperrt', json_encode(['Max Muster']));
            $t = $Bfv::ticker($s6, true);
            $gleich('gesperrter Name entfernt', [$t['ereignisse'][2]['t'], $t['ereignisse'][1]['t']], ['Tor durch – (9)', 'Gelbe Karte für Erika Beispiel (4)']);
            Yii::$app->getModule('vereine')->settings->delete('tickerGesperrt');
            $antworten["https://www.bfv.de/ergebnisse/spiel/-/x/liveticker"] = '<html>kein Ticker</html>';
            $gleich('ohne Ticker: null', $Bfv::ticker('x', true), null);

            $sp6 = fn() => $S::findOne(['mannschaft_id' => $h1, 'spiel_id' => $s6]);
            $anstoss = $sp6()->getZeitpunkt();
            $gleich('Live: ohne laufendes Spiel nichts', $Bfv::live($anstoss - 4 * 3600), []);
            $Bfv::live($anstoss + 41 * 60);
            $gleich('Live: Ticker gespeichert, Spiel läuft', [$sp6()->ticker_status, $sp6()->ticker_stand, $sp6()->laeuft($anstoss + 41 * 60)], ['live', '1:0', true]);
            $gleich('Live: Herren mit Namen', str_contains((string)$sp6()->ticker, 'Max Muster'), true);
            $ev[] = $e(29, 92, 'Abpfiff der Begegnung', '1:0', 200);
            $antworten["https://www.bfv.de/ergebnisse/spiel/-/$s6/liveticker"] = $tickerSeite($ev);
            $daten = json_decode($antworten[$Bfv::API . "team/$h1/matches"], true);
            foreach ($daten['data']['matches'] as &$x) {
                if ($x['matchId'] === $s6) {
                    $x['result'] = '1:0';
                }
            }
            unset($x);
            $antworten[$Bfv::API . "team/$h1/matches"] = json_encode($daten);
            $abgerufen = [];
            $Bfv::live($anstoss + 100 * 60);
            $gleich('Live: Abpfiff → beendet, Sieg', [$sp6()->ticker_status, $sp6()->istVorbei($anstoss + 100 * 60), $sp6()->getWertung()], ['beendet', true, 's']);
            $gleich('Live: ab Minute 100 Ergebnis aus der Schnittstelle', $sp6()->ergebnis, '1:0');
            $abgerufen = [];
            $gleich('Live: beendetes Spiel nicht erneut abgerufen', [$Bfv::live($anstoss + 105 * 60), $abgerufen], [[], []]);
            $S::updateAll(['ticker_status' => 'live', 'ergebnis' => null], ['spiel_id' => $s6]);
            $Bfv::live($anstoss + 200 * 60);
            $gleich('Live: Ticker ohne Abpfiff nach 3 Stunden beendet', $sp6()->ticker_status, 'beendet');
            // Startseite: Kasten nur an Spieltagen, mit Live-Stand
            $S::updateAll(['datum' => date('Y-m-d', strtotime('+3 days'))], ['and', ['mannschaft_id' => $h1], ['datum' => date('Y-m-d')]]);
            if (!Yii::$app->has('session')) { // Html::nonce() braucht eine Sitzung
                Yii::$app->set('session', ['class' => \yii\web\Session::class]);
            }
            $kasten = fn() => \humhub\modules\vereine\widgets\SpieltagKasten::widget();
            $gleich('Startseite: Kasten nur an Spieltagen', str_contains($kasten(), 'Test-Herren-Gegner'), false);
            $S::updateAll(['datum' => date('Y-m-d'), 'uhrzeit' => date('H:i', strtotime('-40 minutes')), 'ticker_status' => 'live', 'ticker_stand' => '2:1',
                'ticker_minute' => 41, 'ergebnis' => null, 'gast' => 'Test-Herren-Gegner'], ['spiel_id' => $s6]);
            $html = $kasten();
            $gleich('Startseite: laufendes Spiel mit Stand und Minute', [str_contains($html, 'Test-Herren-Gegner'), str_contains($html, '2:1'), str_contains($html, '41.')], [true, true, true]);
            $S::updateAll(['ergebnis' => 'Abse.', 'ticker_status' => null], ['spiel_id' => $s6]);
            $html = $kasten();
            $gleich('Startseite: abgesetztes Spiel mit Hinweis im Kasten', [str_contains($html, 'Test-Herren-Gegner'), str_contains($html, 'abgesetzt')], [true, true]);
            // Täglicher Abgleich: Ticker älterer Spiele werden geleert
            $S::updateAll(['ticker' => '[]', 'ticker_status' => 'beendet'], ['spiel_id' => str_repeat('2', 32)]);
            $Bfv::abrufen($verein);
            $gleich('älterer Spielbericht geleert', $S::findOne(['spiel_id' => str_repeat('2', 32)])->ticker, null);

            // Zweiter Lauf: ein Spiel und die E-Junioren sind beim BFV verschwunden, Mannschaftsabruf scheitert nicht
            $daten = json_decode($antworten[$Bfv::API . "team/$h1/matches"], true);
            array_shift($daten['data']['matches']);
            $antworten[$Bfv::API . "team/$h1/matches"] = json_encode($daten);
            $antworten['https://www.bfv.de/vereine/-/' . $club] = '<a href="https://www.bfv.de/mannschaften/test/' . $h1 . '">x</a>';
            $Bfv::abrufen($verein);
            $gleich('verschwundenes Spiel entfernt', $S::findOne(['spiel_id' => str_repeat('1', 32)]), null);
            $gleich('verschwundene Mannschaft samt Spielen entfernt', [$M::findOne($e1), (int)$S::find()->where(['mannschaft_id' => $e1])->count()], [null, 0]);
            // Dritter Lauf: Spiele-Abruf scheitert → Daten bleiben
            unset($antworten[$Bfv::API . "team/$h1/matches"]);
            $log = $Bfv::abrufen($verein);
            $gleich('Fehler wird gemeldet', count(preg_grep('/^! Mannschaft/', $log)), 1);
            $gleich('bei Fehler bleiben die Spiele', (int)$S::find()->where(['mannschaft_id' => $h1])->count(), 4);

            // ---------- Weitere Sportarten (components/Spielquellen.php) ----------
            $Q = \humhub\modules\vereine\components\Spielquellen::class;
            $neuerVerein = function (string $quelle, string $kennung) {
                $v = new \humhub\modules\vereine\models\Verein(['name' => "Testverein $quelle " . uniqid(), 'kategorie' => 'Sport', 'ortsteil' => 'Uffenheim', 'status' => 'offen']);
                $v->save(false);
                $v->updateAttributes(['bfv_id' => $kennung, 'spiele_quelle' => $quelle, 'bfv_name' => 'Test SB']);
                return $v;
            };
            $gleich('Art aus Ligaangaben', [$Q::typAus('U14 männlich'), $Q::typAus('männlich'), $Q::typAus('U18 Bezirksliga weiblich', 'Herren', 'Frauen'),
                $Q::typAus('Landesliga Nord-West Frauen', 'Herren', 'Frauen'), $Q::typAus('MFR U20m'), $Q::typAus('Aktive Bezirksliga - Süd')],
                ['U14 männlich', 'Herren', 'U18 weiblich', 'Frauen', 'U20 männlich', 'Erwachsene']);
            $gleich('Spielklasse als Zusatz', [$Q::stufe('UFR Bezirksklasse Herren Ost'), $Q::stufe('Landesliga Nord-West Frauen'), $Q::stufe(null)],
                ['Bezirksklasse', 'Landesliga', 'Pokal']);

            // Basketball: Spiele der nächsten Wochen nach Verein → Ligen; Spielplan, Tabelle, Halle
            $morgenIso = date('Y-m-d', strtotime('+1 day'));
            $team = fn($id, $name, $club, $saison) => ['teamPermanentId' => $id, 'seasonTeamId' => $saison, 'teamname' => $name, 'clubId' => $club];
            $bb = fn($id, $datum, $heim, $gast, $ergebnis, $abgesagt = false) => ['matchId' => $id, 'kickoffDate' => $datum, 'kickoffTime' => '18:00',
                'homeTeam' => $heim, 'guestTeam' => $gast, 'result' => $ergebnis, 'abgesagt' => $abgesagt, 'verzicht' => false];
            $wir = $team(111, 'Test SB', 9001, 501);
            $sie = $team(222, 'Fremd BC', 9002, 502);
            $liga = fn($id, $name, $ak, $g) => ['ligaId' => $id, 'liganame' => $name, 'akName' => $ak, 'geschlecht' => $g, 'tableExists' => true];
            $antworten[$Q::DBB . 'rest/club/id/9001/actualmatches?justHome=false&rangeDays=400'] = json_encode(['status' => '0', 'data' => ['club' => ['vereinsname' => 'SG Test'],
                'matches' => [['ligaData' => ['ligaId' => 70001]], ['ligaData' => ['ligaId' => 70002]]]]]);
            $antworten[$Q::DBB . 'rest/competition/spielplan/id/70001'] = json_encode(['status' => '0', 'data' => ['ligaData' => $liga(70001, 'UFR Bezirksklasse Herren Ost', 'Senioren', 'männlich'),
                'matches' => [$bb(1, '2026-10-11', $wir, $sie, '57:49'), $bb(2, $morgenIso, $sie, $wir, null), $bb(3, '2026-10-01', $wir, $sie, null, true),
                    $bb(4, '2026-10-02', $sie, $team(333, 'Dritte', 9003, 503), '10:20')]]]);
            $antworten[$Q::DBB . 'rest/competition/spielplan/id/70002'] = json_encode(['status' => '0', 'data' => ['ligaData' => $liga(70002, 'UFR Bezirkspokal Herren', 'Senioren', 'männlich'),
                'matches' => [$bb(5, '2026-10-27', $wir, $sie, '80:70')]]]);
            $antworten[$Q::DBB . 'rest/competition/table/id/70001'] = json_encode(['status' => '0', 'data' => ['tabelle' => ['entries' => [
                ['rang' => 1, 'team' => $wir, 'anzspiele' => 1, 's' => 1, 'n' => 0, 'korbdiff' => 8, 'anzGewinnpunkte' => 2],
                ['rang' => 2, 'team' => $sie, 'anzspiele' => 1, 's' => 0, 'n' => 1, 'korbdiff' => -8, 'anzGewinnpunkte' => 0]]]]]);
            $antworten[$Q::DBB . 'rest/match/id/2/matchInfo'] = json_encode(['status' => '0', 'data' => ['matchInfo' => ['spielfeld' =>
                ['bezeichnung' => 'Halle Fremd', 'strasse' => 'Weg 1', 'plz' => '97000', 'ort' => 'Fremdstadt'], 'srList' => [['personData' => ['vorname' => 'Schieds', 'nachname' => 'Richter']]]]]]);
            $vb = $neuerVerein('dbb', '9001');
            $log = $Q::abrufen($vb);
            $gleich('Basketball: Abruf ohne Fehler', preg_grep('/^!/', $log), []);
            $herrenB = $M::findOne('dbb:111');
            $gleich('Basketball: eine Mannschaft (Pokal dazu), Art, Liga', [(int)$M::find()->where(['verein_id' => $vb->id])->count(), $herrenB->typ, $herrenB->liga, $herrenB->getLabel()],
                [1, 'Herren', 'UFR Bezirksklasse Herren Ost', 'Herren']);
            $gleich('Basketball: 4 eigene Spiele, fremdes nicht', (int)$S::find()->where(['mannschaft_id' => 'dbb:111'])->count(), 4);
            $sb1 = $S::findOne(['mannschaft_id' => 'dbb:111', 'spiel_id' => '1']);
            $gleich('Basketball: Heimsieg', [$sb1->getWertung(), (bool)$sb1->heim_eigen, $sb1->getHeimspiel(), $sb1->getQuelle()], ['s', true, true, 'dbb']);
            $gleich('Basketball: abgesagt', $S::findOne(['mannschaft_id' => 'dbb:111', 'spiel_id' => '3'])->getZustand(), 'abgesetzt');
            $gleich('Basketball: Pokal', $S::findOne(['mannschaft_id' => 'dbb:111', 'spiel_id' => '5'])->wettbewerb_typ, 'Pokale');
            $gleich('Basketball: Halle ohne Schiedsrichter', $S::findOne(['mannschaft_id' => 'dbb:111', 'spiel_id' => '2'])->ort, 'Halle Fremd | Weg 1 | 97000 Fremdstadt');
            $gleich('Basketball: Tabelle mit eigener Zeile', [$herrenB->getPlatz()['platz'] ?? null, $herrenB->getPlatz()['diff'] ?? null], [1, 8]);
            $gleich('Basketball: kein Live ohne Ticker', $S::findOne(['mannschaft_id' => 'dbb:111', 'spiel_id' => '2'])->laeuft(strtotime("$morgenIso 18:30")), false);
            $gleich('Basketball: Link zur Liga', $S::findOne(['mannschaft_id' => 'dbb:111', 'spiel_id' => '1'])->getUrl(), $Q::DBB . 'static/#/liga/70001');

            // Volleyball: Ligen suchen (Bereiche), Liga-Seite mit Sätzen, Tabelle, Pokal zur Ligamannschaft, Halle
            $vbBlock = fn($id, $datum, $a, $b, array $sa, array $sb, $pfad = 'erwachsene/mittelfranken') => '<h4 class="fs-5 bg-primary">Sa., ' . $datum . ' , 14:00 Uhr <span>Spielnr. 1</span>'
                . '<a href="/ergebnisse/' . $pfad . '/spiel-' . $id . '">x</a></h4><table><tbody>'
                . '<tr><th scope="row"> ' . $a . ' </th><td><strong>' . ($sa ? array_sum(array_map(fn($x, $y) => (int)($x > $y), $sa, $sb)) : 0) . '</strong></td>' . implode('', array_map(fn($x) => "<td>$x</td>", $sa)) . '</tr>'
                . '<tr><th scope="row"> ' . $b . ' </th><td><strong>' . ($sb ? array_sum(array_map(fn($x, $y) => (int)($y > $x), $sa, $sb)) : 0) . '</strong></td>' . implode('', array_map(fn($x) => "<td>$x</td>", $sb)) . '</tr></tbody></table>';
            $vbLiga = fn($titel, $bloecke, $tabelle = '') => '<h2 class="text-uppercase flex-grow-1 text-center mb-0"> ' . $titel . ' </h2>' . $bloecke . $tabelle;
            $vbTab = '<div class="mt-4" id="tabellenrunde0"><table><thead><tr><th>#</th></tr></thead><tbody>'
                . '<tr><th>1</th><td></td><td><a href="/ergebnisse/erwachsene/mittelfranken/team-501">VSG Test / SB Uffenheim</a></td><td>1</td><td>1</td><td>0</td><td>3</td><td>3:0</td><td>Max</td><td>75:40</td><td>1,8</td></tr>'
                . '<tr><th>2</th><td></td><td><a href="/ergebnisse/erwachsene/mittelfranken/team-502">TV Gegner</a></td><td>1</td><td>0</td><td>1</td><td>0</td><td>0:3</td><td>0</td><td>40:75</td><td>0,5</td></tr></tbody></table>';
            $morgenDe = date('d.m.Y', strtotime('+1 day'));
            foreach ($Q::BVV_BEREICHE as $bereich) {
                $antworten[$Q::BVV . "/ergebnisse/$bereich"] = $bereich === 'erwachsene/mittelfranken'
                    ? '<a href="/ergebnisse/erwachsene/mittelfranken/wettbewerb-1">x</a><a href="/ergebnisse/erwachsene/mittelfranken/wettbewerb-2">x</a><a href="/ergebnisse/erwachsene/mittelfranken/wettbewerb-3">x</a>' : '<html></html>';
            }
            $antworten[$Q::BVV . '/ergebnisse/erwachsene/mittelfranken/wettbewerb-1'] = $vbLiga('Bezirksklasse 2 Frauen',
                $vbBlock(11, '24.10.2026', 'VSG Test / SB Uffenheim', 'TV Gegner', [25, 25, 25], [20, 7, 20])
                . $vbBlock(11, '24.10.2026', 'VSG Test / SB Uffenheim', 'TV Gegner', [25, 25, 25], [20, 7, 20]) // „Aktuelle“ und „Alle“: doppelt
                . $vbBlock(12, $morgenDe, 'TV Gegner', 'VSG Test / SB Uffenheim', [], []), $vbTab);
            $antworten[$Q::BVV . '/ergebnisse/erwachsene/mittelfranken/wettbewerb-2'] = $vbLiga('Bezirkspokal Vorrunde Frauen',
                $vbBlock(13, '21.10.2026', 'VSG Test/SB Uffenheim', 'TV Pokal', [25, 25, 25], [17, 22, 21]));
            $antworten[$Q::BVV . '/ergebnisse/erwachsene/mittelfranken/wettbewerb-3'] = $vbLiga('Landesliga Frauen', $vbBlock(14, '01.10.2026', 'Fremd A', 'Fremd B', [25], [1]));
            $antworten[$Q::BVV . '/ergebnisse/erwachsene/mittelfranken/spiel-12'] = '<h3><svg></svg> Halle </h3> <div> <p><strong>Halle Test</strong><br> Weg 2<br> 91438 Bad Windsheim </p></div>'
                . '<h3>Spieler</h3><p>Erika Muster</p>';
            $vv = $neuerVerein('bvv', 'SB Uffenheim');
            $log = $Q::abrufen($vv);
            $gleich('Volleyball: Abruf ohne Fehler, 2 von 3 Ligen gefunden', [preg_grep('/^!/', $log), $log[0]], [[], 'Ligen durchsucht: 3, mit eigenen Mannschaften: 2']);
            $frauen = $M::findOne('bvv:501');
            $gleich('Volleyball: Mannschaft aus der Tabelle, Art', [$frauen->typ, $frauen->liga, $frauen->liga_id, (int)$M::find()->where(['verein_id' => $vv->id])->count()],
                ['Frauen', 'Bezirksklasse 2 Frauen', 'wettbewerb-1', 1]);
            $gleich('Volleyball: doppelte Spiele einmal, Pokal dazu', array_map(fn($s) => $s->spiel_id . '/' . $s->wettbewerb_typ,
                $S::find()->where(['mannschaft_id' => 'bvv:501'])->orderBy(['spiel_id' => SORT_ASC])->all()), ['11/Meisterschaften', '12/Meisterschaften', '13/Pokale']);
            $sv = $S::findOne(['mannschaft_id' => 'bvv:501', 'spiel_id' => '11']);
            $gleich('Volleyball: Sätze und Ergebnis', [$sv->ergebnis, $sv->details, $sv->getWertung(), $sv->getHeimspiel()], ['3:0', '25:20, 25:7, 25:20', 's', null]);
            $sk = $S::findOne(['mannschaft_id' => 'bvv:501', 'spiel_id' => '12']);
            $gleich('Volleyball: kommendes Spiel ohne Ergebnis, Halle ohne Spielernamen', [$sk->ergebnis, $sk->ort, $sk->getUrl()],
                [null, 'Halle Test | Weg 2 | 91438 Bad Windsheim', $Q::BVV . '/ergebnisse/erwachsene/mittelfranken/spiel-12']);
            $gleich('Volleyball: Tabelle mit Sätzen', [$frauen->getPlatz()['platz'] ?? null, $frauen->getPlatz()['diff'] ?? null], [1, '3:0']);
            // Zweiter Lauf nutzt die gespeicherte Ligenliste (keine Bereichsseiten); Liga weg (410) → neu suchen
            $abgerufen = [];
            $Q::abrufen($vv);
            $gleich('Volleyball: zweiter Lauf ohne Suche', count(array_filter($abgerufen, fn($u) => !str_contains($u, 'wettbewerb-') && !str_contains($u, 'spiel-'))), 0);
            unset($antworten[$Q::BVV . '/ergebnisse/erwachsene/mittelfranken/wettbewerb-2']);
            $antworten[$Q::BVV . '/ergebnisse/erwachsene/mittelfranken'] = '<a href="/ergebnisse/erwachsene/mittelfranken/wettbewerb-1">x</a>';
            $log = $Q::abrufen($vv);
            $gleich('Volleyball: fehlende Liga → neu gesucht, Pokalspiel entfernt', [$log[0], (int)$S::find()->where(['mannschaft_id' => 'bvv:501'])->count()],
                ['Ligen durchsucht: 1, mit eigenen Mannschaften: 1', 2]);

            // Badminton: Mannschaften (ohne Mannschaftsführer), Spiele mit Halle, Tabelle
            $bbvPost = fn($teil) => $Q::BBV . '/liga2/backend/ajax.php|' . http_build_query(['path' => "/liga/club/BBV/07-9999/$teil"]);
            $antworten[$bbvPost('teams')] = '<table><tr><td><a href="/liga/team/700/26/27">Aktive</a></td><td><a href="/liga/liga/26/27/41">Bezirksliga - Süd</a></td>'
                . '<td>Muster Max (0160/1234567)</td><td>5(2:4)</td></tr></table>';
            $bmZeile = fn($id, $datum, $heim, $gast, $a, $b, $halle) => '<tr><td>Sa. ' . $datum . ' <span>14:00</span></td>'
                . '<td><a href="/liga/team/' . $heim[0] . '/26/27">' . $heim[1] . '</a></td><td>-</td><td><a href="/liga/team/' . $gast[0] . '/26/27">' . $gast[1] . '</a></td>'
                . '<td class="align-right score">' . $a . '</td><td>:</td><td class="score">' . $b . '</td><td></td>'
                . '<td><a href="/liga/hall/1">' . $halle . '</a></td><td><a href="https://badminton-bbv.de/liga2/ics.php?meeting=' . $id . '">k</a></td></tr>';
            $bWir = [700, 'SG Test Uffenheim'];
            $bSie = [800, 'TSV Fremd'];
            $zeilen = $bmZeile(91, '10.10.2026', $bSie, $bWir, 6, 2, 'Halle Fremd') . $bmZeile(92, $morgenDe, $bWir, $bSie, '', '', 'Turnhalle III');
            $antworten[$bbvPost('meetings')] = "<table>$zeilen</table><table>$zeilen</table>";
            $antworten[$bbvPost('tables')] = '<table class="d-md-block"><thead><tr><th>gespielt</th></tr></thead><tbody>'
                . '<tr><td>1</td><td><a href="/liga/team/800/26/27">TSV Fremd</a></td><td>1</td><td>2</td><td>:</td><td>0</td><td>1</td><td>0</td><td>0</td><td>6</td><td>:</td><td>2</td></tr>'
                . '<tr><td>2</td><td><a href="/liga/team/700/26/27">SG Test Uffenheim</a></td><td>1</td><td>0</td><td>:</td><td>2</td><td>0</td><td>0</td><td>1</td><td>2</td><td>:</td><td>6</td></tr></tbody></table>';
            $vd = $neuerVerein('bbv', '07-9999');
            $log = $Q::abrufen($vd);
            $gleich('Badminton: Abruf ohne Fehler', preg_grep('/^!/', $log), []);
            $bm = $M::findOne('bbv:700');
            $gleich('Badminton: Name aus den Spielen, Art, keine Telefonnummer', [$bm->name, $bm->typ, str_contains(json_encode($bm->attributes), '0160')], ['SG Test Uffenheim', 'Erwachsene', false]);
            $gleich('Badminton: Spiele einmal (Liste doppelt), kommendes mit Kennung aus dem Kalender-Link',
                array_map(fn($s) => [$s->spiel_id, $s->ergebnis, $s->ort], $S::find()->where(['mannschaft_id' => 'bbv:700'])->orderBy(['datum' => SORT_ASC])->all()),
                [['91', '6:2', 'Halle Fremd'], ['92', null, 'Turnhalle III']]);
            $gleich('Badminton: Auswärtsniederlage, Link', [$S::findOne(['spiel_id' => '91', 'mannschaft_id' => 'bbv:700'])->getWertung(),
                $S::findOne(['spiel_id' => '92', 'mannschaft_id' => 'bbv:700'])->getUrl()], ['n', $Q::BBV . '/liga/liga/26/27/41/meeting/92']);
            $gleich('Badminton: Tabelle', [$bm->getPlatz()['platz'] ?? null, $bm->getPlatz()['diff'] ?? null, $bm->getPlatz()['punkte'] ?? null], [2, '2:6', 0]);

            // Gemeinsam: Uffi-Abschnitt je Sportart, Live nur Fußball, Menü-Reihenfolge, Startseite
            $uffi = (string)$Bfv::forAssistant();
            $gleich('Uffi: Basketball und Volleyball mit Sätzen', [str_contains($uffi, '## Basketball: Spiele und Ergebnisse von Test SB (Quelle: DBB'),
                str_contains($uffi, '3:0 (25:20, 25:7, 25:20)'), str_contains($uffi, '## Badminton:')], [true, true, true]);
            $abgerufen = [];
            $Bfv::live(strtotime("$morgenIso 18:30"));
            $gleich('Live: keine Abrufe für andere Sportarten', array_values(array_filter($abgerufen, fn($u) => !str_starts_with($u, 'https://www.bfv.de/')
                && !str_starts_with($u, $Bfv::API))), []);
            $reihe = array_map(fn($v) => $v->spiele_quelle, \humhub\modules\vereine\models\Verein::find()->where(['id' => [$verein->id, $vb->id, $vv->id, $vd->id]])
                ->orderBy($Q::reihenfolge())->all());
            $gleich('Reihenfolge Fußball, Basketball, Volleyball, Badminton', $reihe, ['bfv', 'dbb', 'bvv', 'bbv']);
            $S::updateAll(['datum' => date('Y-m-d')], ['mannschaft_id' => ['bvv:501', 'bbv:700'], 'spiel_id' => ['11', '91']]);
            $html = $kasten();
            // Badminton hat Heimrecht: „bei TSV Fremd 6:2“ wie beim Fußball
            $gleich('Startseite: „Sport heute“, Volleyball eigenes Ergebnis zuerst, Badminton Heim:Gast, Links je Sportart',
                [str_contains($html, 'Sport heute'), str_contains($html, '>3:0<'), str_contains($html, 'bei TSV Fremd'), str_contains($html, '>6:2<'), str_contains($html, 'Badminton: Test SB')],
                [true, true, true, true, true]);
        } finally {
            $Bfv::$holen = null;
            $tx->rollBack();
            Yii::$app->language = $sprache;
        }
        $this->stdout("\n$ok ok, $fehler Fehler\n");
        return $fehler ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Öffnungszeiten: Berechnung „jetzt geöffnet“/„öffnet …“ zu festen Zeitpunkten, Feiertage, Saisons, Zeilen,
     * Uffi-Abschnitt, Quellen der nächtlichen Prüfung und Übersetzungen (alle Texte des Moduls in allen Sprachen).
     *   scripts/run-setup.sh oeffnungszeiten-test
     */
    public function actionOeffnungszeitenTest()
    {
        $Z = \humhub\modules\oeffnungszeiten\components\Zeiten::class;
        $sprache = Yii::$app->language;
        $locale = Yii::$app->formatter->locale;
        Yii::$app->language = Yii::$app->formatter->locale = 'de';
        $ok = 0;
        $fehler = 0;
        $gleich = function (string $was, $ist, $soll) use (&$ok, &$fehler) {
            $bedingung = $ist === $soll;
            $bedingung ? $ok++ : $fehler++;
            $this->stdout(($bedingung ? 'ok   ' : 'FEHL ') . "$was\n" . (!$bedingung ? '     ist: ' . var_export($ist, true) . ', soll: ' . var_export($soll, true) . "\n" : ''));
        };
        try {
            $o = $Z::modul()->orte();
            $text = fn(string $id, string $zeit) => $Z::status($o[$id], strtotime($zeit))['text'];
            $gleich('Rathaus Mo 9:00 geöffnet', $text('rathaus', '2026-10-26 09:00'), 'geöffnet bis 12:00 Uhr');
            $gleich('Rathaus Mo 11:45 schließt bald', $Z::status($o['rathaus'], strtotime('2026-10-26 11:45'))['bald'], true);
            $gleich('Rathaus Mo Mittagspause', $text('rathaus', '2026-10-26 12:30'), 'öffnet um 14:00 Uhr');
            $gleich('Rathaus Mi nachmittags zu', $text('rathaus', '2026-10-28 13:00'), 'öffnet morgen um 8:00 Uhr');
            $gleich('Rathaus 1. Donnerstag bis 18 Uhr', $text('rathaus', '2026-11-05 17:00'), 'geöffnet bis 18:00 Uhr');
            $gleich('Rathaus 2. Donnerstag nur bis 15:30', $text('rathaus', '2026-11-12 17:00'), 'öffnet morgen um 8:00 Uhr');
            $gleich('Rathaus Samstag', $text('rathaus', '2026-10-24 10:00'), 'öffnet Montag um 8:00 Uhr');
            $gleich('Rathaus Allerheiligen am Sonntag', $text('rathaus', '2026-10-31 10:00'), 'öffnet Montag um 8:00 Uhr');
            $gleich('Rathaus über Weihnachten', $text('rathaus', '2026-12-25 10:00'), 'öffnet Montag um 8:00 Uhr');
            $gleich('Rathaus Heiligabend zu', $text('rathaus', '2026-12-24 07:00'), 'öffnet Montag um 8:00 Uhr');
            $gleich('Rathaus Silvester vormittags zu', $text('rathaus', '2026-12-31 10:00'), 'öffnet Montag um 8:00 Uhr');
            $gleich('Rathaus Hinweis Schließtage', $Z::geschlossenText($o['rathaus']), 'An Feiertagen sowie an Heiligabend und Silvester geschlossen.');
            $gleich('Bücherei Heiligabend zu', $text('buecherei', '2027-12-24 14:00'), 'öffnet Montag um 11:30 Uhr');
            $gleich('Wertstoffhof Silvester zu', $text('wertstoffhof', '2027-12-31 10:00'), 'öffnet Montag um 9:00 Uhr');
            $gleich('Kompostplatz Silvester zu', $text('kompostplatz', '2027-12-31 10:00'), 'öffnet Montag um 8:00 Uhr');
            $gleich('Hallenbad Heiligabend zu', $text('hallenbad', '2027-12-24 17:00'), 'öffnet Sonntag um 9:00 Uhr');
            $gleich('JUFF Silvester zu', $text('juff', '2026-12-31 15:00'), 'öffnet morgen um 14:30 Uhr');
            $gleich('Hallenbad Hinweis Schließtage', $Z::geschlossenText($o['hallenbad']), 'Heiligabend und Silvester geschlossen.');
            $gleich('Rathaus Ostermontag 2027 zu', $text('rathaus', '2027-03-29 09:00'), 'öffnet morgen um 8:00 Uhr');
            $gleich('Feiertag Karfreitag 2027', $Z::feiertag('2027-03-26'), 'Karfreitag');
            $gleich('Feiertag Fronleichnam 2026', $Z::feiertag('2026-06-04'), 'Fronleichnam');
            $gleich('Mariä Himmelfahrt in Uffenheim kein Feiertag', $Z::feiertag('2026-08-15'), null);
            $gleich('Bücherei Mo Pause', $text('buecherei', '2026-10-26 14:30'), 'öffnet um 15:00 Uhr');
            $gleich('Freibad nach Saisonende', $text('freibad', '2026-10-24 12:00'), 'Saisonpause');
            $gleich('Freibad nicht im Kasten', $Z::status($o['freibad'], strtotime('2026-10-24 12:00'))['saison'], false);
            $gleich('Hallenbad vor Saisonbeginn', $text('hallenbad', '2026-09-28 12:00'), 'öffnet Mittwoch um 16:00 Uhr');
            $gleich('Hallenbad Sonntag', $text('hallenbad', '2026-10-25 10:00'), 'geöffnet bis 14:00 Uhr');
            $gleich('Museum letzter Oktobersonntag offen', $Z::status($o['museum'], strtotime('2026-10-25 14:00'))['offen'], true);
            $gleich('Museum November Saisonpause', $text('museum', '2026-11-01 14:00'), 'Saisonpause');
            $gleich('Museum Saisontext', $Z::saisonText($o['museum'], strtotime('2026-11-01 12:00')), 'Saison 01.05.2027 bis 31.10.2027');
            $gleich('Freibad Saisontext', $Z::saisonText($o['freibad'], strtotime('2026-10-24 12:00')), 'Saison beendet (letzter Tag 13.09.2026) – der neue Termin steht noch nicht fest.');
            $gleich('Hallenbad Saisontext', $Z::saisonText($o['hallenbad'], strtotime('2026-10-24 12:00')), 'Saison seit 30.09.2026');
            $zeilen = fn(string $id, string $zeit) => array_map(fn($z) => $z[0] . ' ' . $z[1] . ($z[2] ? ' *' : ''), $Z::zeilen($o[$id], strtotime($zeit)));
            $gleich('Rathaus Zeilen (Mo heute)', $zeilen('rathaus', '2026-10-26 09:00'), ['Mo., Di., Do. 8:00–12:00, 14:00–15:30 Uhr *', 'Mi., Fr. 8:00–12:00 Uhr', 'am 1. Donnerstag im Monat 8:00–12:00, 14:00–18:00 Uhr']);
            $gleich('Rathaus Zeilen (1. Do heute)', $zeilen('rathaus', '2026-11-05 09:00')[2], 'am 1. Donnerstag im Monat 8:00–12:00, 14:00–18:00 Uhr *');
            $gleich('Kompostplatz Zeilen', $zeilen('kompostplatz', '2026-10-24 09:00'), ['Mo.–Mi., Fr. 8:00–12:30, 13:00–16:00 Uhr', 'Do. 12:30–17:00 Uhr', 'Sa. 9:00–14:00 Uhr *']);
            $gleich('Freibad täglich', $zeilen('freibad', '2026-10-24 09:00')[0], 'täglich 10:00–20:00 Uhr *');
            $ohneQuelle = [];
            foreach ($o as $id => $ort) {
                if (empty($ort['pruefen']) || \humhub\modules\oeffnungszeiten\commands\OeffnungszeitenController::auszug($ort['pruefen']) === null) {
                    $ohneQuelle[] = $id;
                }
            }
            $gleich('Nächtliche Prüfung findet alle Quellen in wissen/', $ohneQuelle, []);
            $meldung = (new \ReflectionMethod(\humhub\modules\oeffnungszeiten\commands\OeffnungszeitenController::class, 'meldung'))
                ->invoke(null, $o['rathaus'], ['Montag bis Freitag: 08:00-12:00 Uhr'], null);
            $gleich('Mailtext bei Änderung', str_contains($meldung, "Vorher:\n  Montag bis Freitag") && str_contains($meldung, 'nicht gefunden')
                && str_contains($meldung, "Bei uns eingetragen:\n  Mo., Di., Do.: 8:00–12:00, 14:00–15:30 Uhr"), true);
            $uffi = $Z::forAssistant();
            $gleich('Uffi-Abschnitt mit allen Einrichtungen', count(array_filter(array_column($o, 'name'), fn($n) => str_contains($uffi, "- $n"))), count($o));
            // Übersetzungen: alle Texte aus Code und Daten in jeder Sprache
            $quellen = '';
            foreach (\yii\helpers\FileHelper::findFiles('/data/modules/oeffnungszeiten', ['only' => ['*.php'], 'except' => ['messages/']]) as $f) {
                $quellen .= file_get_contents($f);
            }
            preg_match_all("/(?:Yii::t\('OeffnungszeitenModule\.base', |\\\$t\()'((?:[^'\\\\]|\\\\.)*)'/", $quellen, $m);
            $texte = array_map('stripslashes', $m[1]);
            foreach ($o as $ort) {
                foreach (['name', 'unterzeile', 'adresse', 'hinweis'] as $feld) {
                    if (!empty($ort[$feld])) {
                        $texte[] = $ort[$feld];
                    }
                }
                $texte = array_merge($texte, array_values($ort['zuAn'] ?? []));
                if (!empty($ort['link'])) {
                    $texte[] = $ort['link'][0];
                }
            }
            $texte = array_merge($texte, array_values($Z::TAGE), array_values($Z::KURZ), array_values($Z::feiertage(2026)));
            $texte = array_unique(array_filter($texte, fn($t) => !str_contains($t, '$')));
            foreach (['en', 'uk', 'ar', 'tr', 'ru'] as $l) {
                $msg = require "/data/modules/oeffnungszeiten/messages/$l/base.php";
                $fehlt = array_values(array_filter($texte, fn($t) => !isset($msg[$t])));
                $gleich("Übersetzung $l vollständig", $fehlt, []);
            }
        } finally {
            Yii::$app->language = $sprache;
            Yii::$app->formatter->locale = $locale;
        }
        $this->stdout("\n$ok ok, $fehler Fehler\n");
        return $fehler ? 1 : 0;
    }
}
