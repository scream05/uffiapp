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
            'description' => 'Schlagloch, defekte Laterne, wilder Müll? Hier mit Foto und Ort melden.',
            'color' => '#c0392b',
            'auto_add' => false,
            'modules' => [],
        ],
        [
            'name' => 'Vereine & Ehrenamt',
            'description' => 'Vereinsverzeichnis mit Vorstellungsseiten; Vereinstermine stehen im gemeinsamen Kalender unter „Veranstaltungen“.',
            'color' => '#d4a017',
            'auto_add' => false,
            'modules' => ['calendar', 'polls'],
        ],
        [
            'name' => 'Bürgerbeteiligung',
            'description' => 'Ideen, Umfragen und Diskussionen zur Zukunft Uffenheims.',
            'color' => '#16a085',
            'auto_add' => false,
            'modules' => ['polls', 'questions'],
        ],
        [
            'name' => 'Abfall & Termine',
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
            if (Space::findOne(['name' => $def['name']])) {
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
            \humhub\libs\LogoImage::set(new \yii\web\UploadedFile(['tempName' => $logo]));
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
     * Abfallkalender im Space „Abfall & Termine“ anlegen/abgleichen und alle Nutzer aufnehmen:
     * setup/abfall  (die ICS-Datei erzeugt scripts/abfall-kalender.py)
     */
    public function actionAbfall($ownerUsername = 'admin')
    {
        $owner = User::findOne(['username' => $ownerUsername]);
        Yii::$app->user->switchIdentity($owner);
        $space = Space::findOne(['name' => 'Abfall & Termine']);
        if ($space === null) {
            $this->stderr("Space fehlt, zuerst setup/spaces ausführen\n");
            return ExitCode::DATAERR;
        }

        // Alle Bürger als Mitglieder (für die Abfuhr-Erinnerung), nicht die Import-Konten
        foreach (User::find()->active()->all() as $user) {
            $import = in_array($user->username, [self::RSS_USER, self::FB_USER], true);
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
        $n = \humhub\modules\content\models\Content::updateAll(['stream_channel' => null],
            ['object_model' => $entryClass, 'stream_channel' => 'default', 'contentcontainer_id' => $space->contentcontainer_id]);
        $a = 0;
        foreach (\humhub\modules\activity\models\Activity::find()->where(['object_model' => $entryClass])->all() as $activity) {
            $activity->hardDelete();
            $a++;
        }
        if ($n || $a) {
            $this->stdout("Aus dem Stream genommen: $n Termine, $a Aktivitäten\n");
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
        foreach (['Rathaus Uffenheim', 'Veranstaltungen'] as $name) {
            $s = Space::findOne(['name' => $name]);
            $spaceIds[] = $s->id;
            if (!$s->isMember($user->id)) {
                $s->addMember($user->id, 1, false);
                $this->stdout("+ Mitglied in {$s->name}\n");
            }
        }
        $space = Space::findOne(['name' => 'Rathaus Uffenheim']);
        // Neue Konten landen automatisch in mehreren Spaces; das Konto gehört nur ins Rathaus und
        // zu den Veranstaltungen. Mitgliedschaft direkt löschen und die „ist beigetreten“-Aktivitäten entfernen.
        foreach (\humhub\modules\space\models\Membership::find()->where(['user_id' => $user->id])
                     ->andWhere(['not in', 'space_id', $spaceIds])->all() as $membership) {
            $membership->delete();
            $this->stdout("- Mitglied in {$membership->space->name}\n");
        }
        foreach (\humhub\modules\activity\models\Activity::find()->joinWith('content')
                     ->where(['content.created_by' => $user->id])
                     ->andWhere(['activity.class' => \humhub\modules\space\activities\MemberAdded::class])->all() as $activity) {
            $activity->hardDelete();
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
        $space = Space::findOne(['name' => 'Rathaus Uffenheim']);
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
        $space = Space::findOne(['name' => 'Veranstaltungen']);
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
     * setup/ki "Wann kommt die Biotonne?"  stellt die Frage (zählt zum Monatslimit).
     * setup/ki "Frage" 1  zeigt nur, welche Seiten des Stadtwissens mitgeschickt würden.
     */
    public function actionKi($question = null, $dryRun = 0)
    {
        $module = Yii::$app->getModule('ki-assistent');
        $prompt = \humhub\modules\kiAssistent\controllers\ChatController::systemPrompt((string)$question);
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
            ->ask($prompt, [['role' => 'user', 'text' => $question]]);
        $module->addUsage($in, $out);
        $this->stdout($answer . "\n\n(" . round(microtime(true) - $t, 1) . " s, $in Eingabe- / $out Ausgabe-Tokens)\n");
        return ExitCode::OK;
    }

    /**
     * Altersbestätigung des KI-Assistenten anzeigen/setzen/zurücksetzen:
     * setup/ki-alter <benutzername> [zeigen|setzen|zuruecksetzen]
     */
    /**
     * Testfragen des KI-Assistenten prüfen: setup/ki-test [nur diese Nummer]
     * modules/ki-assistent/data/testfragen.json: je Frage „erwartet“ (Muster, die vorkommen müssen), „nicht“ (Muster, die
     * nicht vorkommen dürfen) und/oder „karte“ (erwartete Aktion: link, termin, download). Kostet je Frage 1–3 Anfragen.
     */
    public function actionKiTest($nr = null)
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
                $module->addUsage($in, $out);
            } catch (\Throwable $e) {
                $this->stdout(sprintf("%2d ✗ %s\n   FEHLER %s\n", $i + 1, $t['frage'], $e->getMessage()));
                continue;
            }
            $problems = [];
            // Antwort plus Text der Karten (z. B. Mitbring-Liste beim Rathaus-Termin)
            $haystack = $answer . "\n" . implode("\n", array_map(fn($c) => ($c['titel'] ?? '') . ' ' . implode(' ', $c['zeilen'] ?? []), $cards));
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
        $space = Space::findOne(['name' => 'Rathaus Uffenheim']);
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
        $space = Space::findOne(['name' => 'Rathaus Uffenheim']);
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
            $privat = $space->name === 'Mängelmelder';
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
     * Modul in einem Space ein-/ausschalten: setup/space-modul Veranstaltungen gallery 0
     * Ausschalten entfernt den Menüpunkt; Inhalte des Moduls im Space (z. B. die automatische
     * Galerie „Posted pictures“) löscht HumHub dabei mit, Bilder an Beiträgen/Terminen bleiben.
     */
    public function actionSpaceModul($spaceName, $modulId, $an = 0)
    {
        $space = Space::findOne(['name' => $spaceName]);
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
        $space = Space::findOne(['name' => 'Veranstaltungen']);
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
        $space = Space::findOne(['name' => 'Mängelmelder']);
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
    public function actionVereine()
    {
        $space = Space::findOne(['name' => 'Vereine & Ehrenamt']);
        $calendarSpace = Space::findOne(['name' => 'Veranstaltungen']);
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
}
