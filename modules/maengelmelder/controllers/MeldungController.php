<?php

namespace humhub\modules\maengelmelder\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\models\Content;
use humhub\modules\maengelmelder\components\Bauhof;
use humhub\modules\maengelmelder\components\Buergerinfo;
use humhub\modules\maengelmelder\components\GastFotos;
use humhub\modules\maengelmelder\components\Osm;
use humhub\modules\maengelmelder\models\MaengelAuch;
use humhub\modules\maengelmelder\models\MaengelKontakt;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\maengelmelder\models\Mangel;
use humhub\modules\maengelmelder\models\MeldeKontakt;
use humhub\modules\maengelmelder\Module;
use humhub\modules\post\models\Post;
use humhub\modules\maengelmelder\permissions\MangelMelden;
use Yii;
use yii\helpers\Url;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Mangel mit Ort melden (wird ein normaler Beitrag im Space) und alle Meldungen auf einer Karte zeigen.
 * Der Ort steht als Link auf die Karte im Beitragstext: …/maengelmelder/meldung/karte?lat=…&lng=…
 *
 * @property Module $module
 */
class MeldungController extends ContentContainerController
{
    protected function getAccessRules()
    {
        // Karte, „Schon gemeldet?“ und (falls eingeschaltet) das Melden auch für Gäste
        return [['login' => ['bearbeiten', 'auch']], ['post' => ['auch']]];
    }

    public function actionCreate($danke = null)
    {
        $space = $this->contentContainer;
        $gast = Yii::$app->user->isGuest;
        // Eigenes Recht statt „Beitrag erstellen“: Mitglieder melden, schreiben aber nicht frei in den Stream.
        // Gäste melden ohne Konto (mit Kontaktangaben), sofern eingeschaltet; die Meldung prüft zuerst der Bauhof.
        $canPost = $gast ? $this->module->gastMeldungenAktiv() : $space->permissionManager->can(MangelMelden::class);
        $data = ['kategorie' => '', 'beschreibung' => '', 'lat' => '', 'lng' => ''];
        $errors = [];
        $kontakt = new MeldeKontakt(['scenario' => $gast ? MeldeKontakt::SCENARIO_GAST : MeldeKontakt::SCENARIO_KONTO]);
        if (!$gast) {
            $kontakt->telefon = MeldeKontakt::telefonAusProfil(Yii::$app->user->getIdentity());
        }

        // Vorbelegung per Link (z. B. vom KI-Assistenten); abgeschickt wird erst mit „Meldung absenden“
        if (!Yii::$app->request->isPost) {
            $get = Yii::$app->request;
            if (in_array($get->get('kategorie'), $this->module->categories, true)) {
                $data['kategorie'] = $get->get('kategorie');
            }
            $data['beschreibung'] = mb_substr(trim((string)$get->get('beschreibung', '')), 0, 3000);
            $lat = (float)$get->get('lat');
            $lng = (float)$get->get('lng');
            if ($lat && $lng && $this->module->inBounds($lat, $lng)) {
                $data['lat'] = (string)round($lat, 6);
                $data['lng'] = (string)round($lng, 6);
            }
        }

        if (Yii::$app->request->isPost && $canPost) {
            $data = array_merge($data, array_map('trim', (array)Yii::$app->request->post('Meldung', [])));
            $lat = (float)str_replace(',', '.', $data['lat']);
            $lng = (float)str_replace(',', '.', $data['lng']);
            if (!in_array($data['kategorie'], $this->module->categories, true)) {
                $errors[] = Yii::t('MaengelmelderModule.base', 'Bitte eine Kategorie wählen.');
            }
            if (mb_strlen($data['beschreibung']) < 5) {
                $errors[] = Yii::t('MaengelmelderModule.base', 'Bitte kurz beschreiben, was kaputt ist.');
            }
            if ($data['lat'] === '' || $data['lng'] === '') {
                $errors[] = Yii::t('MaengelmelderModule.base', 'Bitte den Ort auf der Karte markieren.');
            } elseif (!$this->module->inBounds($lat, $lng)) {
                $errors[] = Yii::t('MaengelmelderModule.base', 'Der markierte Ort liegt nicht im Stadtgebiet von Uffenheim.');
            }
            $kontakt->load(Yii::$app->request->post());
            if (!$kontakt->validate()) {
                $errors = array_merge($errors, $kontakt->getErrorSummary(false));
            }
            if ($gast) {
                if ($kontakt->istBot()) {
                    Yii::warning('Gastmeldung verworfen (verstecktes Feld ausgefüllt)', 'maengelmelder');
                    return $this->redirect($space->createUrl('/maengelmelder/meldung/create', ['danke' => 0]));
                }
                [$fotos, $fotoFehler] = GastFotos::pruefen($this->module);
                $errors = array_merge($errors, $fotoFehler);
                if (!$errors && !$this->gastZaehlen(false)) {
                    $errors[] = Yii::t('MaengelmelderModule.base', 'Von deinem Anschluss kamen heute schon mehrere Meldungen. Bitte versuch es morgen wieder oder leg ein kostenloses Konto an.');
                }
                if (!$errors) {
                    return $this->gastMeldungSpeichern($space, $data, $lat, $lng, $kontakt, $fotos);
                }
            }
            if (!$errors) {
                $post = new Post($space);
                $post->message = Mangel::message($space, $data['kategorie'], mb_substr($data['beschreibung'], 0, 3000), $lat, $lng);
                $post->content->visibility = $space->getDefaultContentVisibility();
                if ($post->save()) {
                    $post->fileManager->attach(Yii::$app->request->post('fileList'));
                    $infos = $this->module->buergerInfoAktiv();
                    if ($kontakt->telefon !== '' || ($infos && ($kontakt->info_mail || $kontakt->info_whatsapp))) {
                        (new MaengelKontakt(['content_id' => $post->content->id, 'user_id' => Yii::$app->user->id,
                            'telefon' => $kontakt->telefon ?: null, 'created_at' => date('Y-m-d H:i:s'),
                            'info_mail' => $infos && $kontakt->info_mail ? 1 : 0,
                            'info_whatsapp' => $this->module->buergerWhatsappAktiv() && $kontakt->info_whatsapp ? 1 : 0,
                            'wa_id' => MeldeKontakt::waId($kontakt->telefon),
                            'mail_bestaetigt' => date('Y-m-d H:i:s')]))->save(false); // Konto-Adresse ist bestätigt
                    }
                    // Vom Bauhof angelegt: gleich zur Bearbeitung (Zuweisen usw.)
                    if (Yii::$app->request->get('bauhof') && MaengelStatus::canManage()) {
                        return $this->redirect($space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $post->content->id]));
                    }
                    // WhatsApp-Infos gewünscht: Danke-Seite mit Knopf „START“ (erst dann schreiben wir per WhatsApp)
                    if ($this->module->buergerWhatsappAktiv() && $kontakt->info_whatsapp && MeldeKontakt::waId($kontakt->telefon)) {
                        return $this->redirect($space->createUrl('/maengelmelder/meldung/create', ['danke' => $post->content->id, 'wa' => 1]));
                    }
                    return $this->redirect($post->content->getUrl());
                }
                $errors = array_merge(...array_values($post->getErrors()));
            }
        }

        return $this->render('create', [
            'space' => $space,
            'canPost' => $canPost,
            'data' => $data,
            'errors' => $errors,
            'module' => $this->module,
            'bearbeiten' => null,
            'kontakt' => $kontakt,
            'danke' => $danke,
        ]);
    }

    /**
     * Gastmeldung als Entwurf unter dem Systemkonto speichern (nur der Bauhof sieht sie, bis er sie freigibt).
     * Die Identität wird nur für diese Anfrage gesetzt, nicht in der Sitzung – der Gast bleibt abgemeldet.
     */
    private function gastMeldungSpeichern($space, array $data, float $lat, float $lng, MeldeKontakt $kontakt, array $fotos)
    {
        $user = Yii::$app->user;
        $user->setIdentity($this->module->getGastKonto());
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $post = new Post($space);
            $post->message = Mangel::message($space, $data['kategorie'], mb_substr($data['beschreibung'], 0, 3000), $lat, $lng);
            $post->content->visibility = Content::VISIBILITY_PRIVATE;
            $post->content->setState(Content::STATE_DRAFT);
            if (!$post->save()) {
                throw new \RuntimeException(json_encode($post->getErrors()));
            }
            [$files, $fotoFehler] = GastFotos::speichern($fotos);
            foreach ($files as $file) {
                $post->fileManager->attach($file);
            }
            $kontaktZeile = new MaengelKontakt([
                'content_id' => $post->content->id,
                'vorname' => $kontakt->vorname,
                'nachname' => $kontakt->nachname,
                'email' => $kontakt->email ?: null,
                'telefon' => $kontakt->telefon ?: null,
                'created_at' => date('Y-m-d H:i:s'),
                'info_mail' => $this->module->buergerInfoAktiv() && $kontakt->info_mail ? 1 : 0,
                'info_whatsapp' => $this->module->buergerWhatsappAktiv() && $kontakt->info_whatsapp ? 1 : 0,
                'wa_id' => MeldeKontakt::waId($kontakt->telefon),
            ]);
            $kontaktZeile->save(false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('Gastmeldung: ' . $e->getMessage(), 'maengelmelder');
            throw new \yii\web\ServerErrorHttpException(Yii::t('MaengelmelderModule.base', 'Die Meldung konnte nicht gespeichert werden. Bitte später noch einmal versuchen.'));
        } finally {
            $user->setIdentity(null);
        }
        $this->gastZaehlen(true);
        // Glocke an die Bauhofleitung (Fehler hier dürfen die Meldung nicht verhindern)
        try {
            if ($empfaenger = Bauhof::empfaenger()) {
                \humhub\modules\maengelmelder\notifications\GastmeldungNeu::instance()->about($kontaktZeile)->sendBulk($empfaenger);
            }
        } catch (\Throwable $e) {
            Yii::error('Gastmeldung-Benachrichtigung: ' . $e->getMessage(), 'maengelmelder');
        }
        if ($kontaktZeile->email) {
            $this->bestaetigungsMail($kontaktZeile);
        }
        if ($fotoFehler) {
            $this->view->warn(implode(' ', $fotoFehler) . ' ' . Yii::t('MaengelmelderModule.base', 'Die Meldung wurde ohne diese Fotos gespeichert.'));
        }
        return $this->redirect($space->createUrl('/maengelmelder/meldung/create', ['danke' => $post->content->id,
            'wa' => $kontaktZeile->info_whatsapp && $kontaktZeile->wa_id ? 1 : null, 'mail' => $kontaktZeile->email ? 1 : null]));
    }

    /**
     * Bestätigungsmail an die E-Mail-Adresse eines Gastes: nur ein Link, kein Text aus der Meldung (die Adresse
     * könnte fremd sein). Erst nach dem Klick schicken wir Infos und Nachrichten des Bauhofs per E-Mail.
     * Höchstens bestaetigungsMailsProTag Mails am Tag (für alle zusammen).
     */
    private function bestaetigungsMail(MaengelKontakt $k): void
    {
        try {
            $key = 'maengel-bestaetigungsmails-' . date('Ymd');
            $n = (int)Yii::$app->cache->get($key);
            if ($n >= $this->module->bestaetigungsMailsProTag) {
                Yii::warning("Bestätigungsmail für Meldung {$k->content_id} nicht gesendet: Tagesgrenze erreicht", 'maengelmelder');
                return;
            }
            Yii::$app->cache->set($key, $n + 1, 86400);
            $k->updateAttributes(['mail_token' => bin2hex(random_bytes(24))]);
            $link = $this->contentContainer->createUrl('/maengelmelder/meldung/bestaetigen', ['id' => $k->content_id, 't' => $k->mail_token], true);
            $text = $this->renderPartial('@maengelmelder/views/mail/bestaetigung_text', [
                'name' => $k->getName(), 'nr' => $k->content_id, 'link' => $link,
                'stunden' => $this->module->bestaetigungStunden, 'kontaktMail' => $this->module->kontaktMail,
            ]);
            $html = nl2br(\yii\helpers\Html::encode($text), false);
            $html = preg_replace('#https?://[^\s<]+#', '<a href="$0">$0</a>', $html);
            $mailer = Yii::$app->mailer;
            $html = $mailer->render('@humhub/views/mail/TextOnly', ['message' => $html], $mailer->htmlLayout);
            $mail = $mailer->compose()->setTo($k->email)->setReplyTo($this->module->kontaktMail)
                ->setSubject('Bitte bestätige deine E-Mail-Adresse (Mängelmeldung Nr. ' . $k->content_id . ')');
            // Text/HTML direkt setzen: HumHubs Link-Dienst (go.humhub.com) würde den Link sonst umleiten
            $mail->getSymfonyEmail()->text($text)->html($html);
            if (!$mail->send()) {
                Yii::warning("Bestätigungsmail Nr. {$k->content_id} nicht versendet", 'maengelmelder');
            }
        } catch (\Throwable $e) {
            Yii::error('Bestätigungsmail: ' . $e->getMessage(), 'maengelmelder');
        }
    }

    /** Link aus der Bestätigungsmail: E-Mail-Adresse einer Gastmeldung bestätigen */
    public function actionBestaetigen($id, $t)
    {
        $k = MaengelKontakt::findOne((int)$id);
        $ok = false;
        $abgelaufen = false;
        // Nur mit passendem Code – sonst verrät die Seite nichts über die Meldung (der Code bleibt nach der Bestätigung
        // gespeichert, damit ein zweites Öffnen des Links „bestätigt“ zeigt)
        if ($k && $k->mail_token && hash_equals($k->mail_token, (string)$t)) {
            if ($k->mail_bestaetigt !== null) {
                $ok = true;
            } elseif (strtotime($k->created_at) < time() - $this->module->bestaetigungStunden * 3600) {
                $abgelaufen = true;
            } else {
                $k->updateAttributes(['mail_bestaetigt' => date('Y-m-d H:i:s')]);
                Bauhof::vermerk($k->content_id, null, null, 'Meldende Person hat ihre E-Mail-Adresse bestätigt');
                $ok = true;
            }
        }
        return $this->render('bestaetigt', ['ok' => $ok, 'abgelaufen' => $abgelaufen, 'kontakt' => $ok ? $k : null,
            'space' => $this->contentContainer, 'infos' => $this->module->buergerInfoAktiv()]);
    }

    /** Gastmeldungen je IP-Adresse und Tag zählen (nur im Cache, gespeichert wird die Adresse nicht) */
    private function gastZaehlen(bool $erhoehen): bool
    {
        $key = 'maengel-gast-' . date('Ymd') . '-' . hash('sha256', (string)Yii::$app->request->userIP);
        $n = (int)Yii::$app->cache->get($key);
        if ($erhoehen) {
            Yii::$app->cache->set($key, $n + 1, 86400);
        }
        return $n < $this->module->gastMeldungenProTag;
    }

    /** Kategorie, Beschreibung, Ort und Fotos einer Meldung ändern (Bauhof) */
    public function actionBearbeiten($id)
    {
        if (!MaengelStatus::canManage()) {
            throw new ForbiddenHttpException();
        }
        $space = $this->contentContainer;
        $content = Content::findOne(['id' => (int)$id, 'contentcontainer_id' => $space->contentcontainer_id, 'object_model' => Post::class]);
        $mangel = $content ? Mangel::fromContent($content) : null;
        if ($mangel === null) {
            throw new NotFoundHttpException();
        }
        $data = ['kategorie' => $mangel->kategorie, 'beschreibung' => $mangel->beschreibung,
            'lat' => (string)$mangel->lat, 'lng' => (string)$mangel->lng];
        $errors = [];
        if (Yii::$app->request->isPost) {
            $data = array_merge($data, array_map('trim', (array)Yii::$app->request->post('Meldung', [])));
            $lat = (float)str_replace(',', '.', $data['lat']);
            $lng = (float)str_replace(',', '.', $data['lng']);
            if (!in_array($data['kategorie'], $this->module->categories, true)) {
                $errors[] = Yii::t('MaengelmelderModule.base', 'Bitte eine Kategorie wählen.');
            }
            if (mb_strlen($data['beschreibung']) < 5) {
                $errors[] = Yii::t('MaengelmelderModule.base', 'Bitte kurz beschreiben, was kaputt ist.');
            }
            if (!$this->module->inBounds($lat, $lng)) {
                $errors[] = Yii::t('MaengelmelderModule.base', 'Der markierte Ort liegt nicht im Stadtgebiet von Uffenheim.');
            }
            if (!$errors) {
                $post = $mangel->post;
                // Adresse nur neu ermitteln, wenn der Ort verschoben wurde
                $gleicherOrt = abs($lat - $mangel->lat) < 1e-6 && abs($lng - $mangel->lng) < 1e-6 && $mangel->adresse !== '';
                $post->message = Mangel::message($space, $data['kategorie'], mb_substr($data['beschreibung'], 0, 3000), $lat, $lng,
                    $gleicherOrt ? $mangel->adresse : null);
                if ($post->save()) {
                    $post->fileManager->attach(Yii::$app->request->post('fileList'));
                    // Zugewiesener Mitarbeiter erfährt Ort-, Kategorie- und Textänderungen per WhatsApp
                    $neu = Mangel::fromContent($content);
                    $fehler = $neu ? Bauhof::sendeAenderung($mangel, $neu, Yii::$app->user->getIdentity()) : null;
                    $fehler ? $this->view->error('Gespeichert, aber WhatsApp an den Mitarbeiter fehlgeschlagen: ' . $fehler) : $this->view->saved();
                    return $this->redirect($space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $content->id]));
                }
                $errors = array_merge(...array_values($post->getErrors()));
            }
        }
        return $this->render('create', [
            'space' => $space,
            'canPost' => true,
            'data' => $data,
            'errors' => $errors,
            'module' => $this->module,
            'bearbeiten' => $mangel,
            'kontakt' => null,
            'danke' => null,
        ]);
    }

    public function actionKarte($lat = null, $lng = null)
    {
        return $this->render('karte', [
            'markers' => $this->meldungen(),
            'focus' => $lat !== null && $lng !== null ? [(float)$lat, (float)$lng] : null,
            'module' => $this->module,
        ]);
    }

    /** Offene Meldungen im Umkreis (für „Schon gemeldet?“ beim Melden), als JSON, nächste zuerst */
    public function actionNahe($lat, $lng)
    {
        $lat = (float)$lat;
        $lng = (float)$lng;
        $near = [];
        foreach ($this->meldungen() as $m) {
            if (in_array($m['status'], MaengelStatus::CLOSED, true)) {
                continue;
            }
            $m['entfernung'] = (int)round(self::distance($lat, $lng, $m['lat'], $m['lng']));
            if ($m['entfernung'] <= $this->module->nearRadius) {
                $near[] = $m;
            }
        }
        usort($near, fn($a, $b) => $a['entfernung'] <=> $b['entfernung']);
        return $this->asJson(array_slice($near, 0, 5));
    }

    /**
     * Ort zu einer eingegebenen Adresse (Nominatim über unseren Server), damit der Ort auch ohne Karte und Ortung
     * gesetzt werden kann (Tastatur, Screenreader). Nur Stadtgebiet; höchstens 30 Suchen je Anschluss und Stunde.
     */
    public function actionSuche($q)
    {
        $q = trim((string)$q);
        if (mb_strlen($q) < 3) {
            return $this->asJson(['fehler' => Yii::t('MaengelmelderModule.base', 'Bitte mindestens 3 Zeichen eingeben.')]);
        }
        $ip = (string)Yii::$app->request->userIP;
        $key = 'maengel-suche-' . hash('sha256', strpos($ip, ':') !== false ? implode(':', array_slice(explode(':', $ip), 0, 4)) : $ip);
        $n = (int)Yii::$app->cache->get($key);
        if ($n >= 30) {
            return $this->asJson(['fehler' => Yii::t('MaengelmelderModule.base', 'Zu viele Suchen. Bitte später noch einmal oder den Ort in der Karte markieren.')]);
        }
        Yii::$app->cache->set($key, $n + 1, 3600);
        $hit = Osm::searchThrottled($q, Osm::NAH_BOUNDS);
        if ($hit === false) {
            return $this->asJson(['fehler' => Yii::t('MaengelmelderModule.base', 'Die Suche ist gerade belegt. Bitte gleich noch einmal versuchen.')]);
        }
        if ($hit === null) {
            return $this->asJson(['fehler' => Yii::t('MaengelmelderModule.base', 'Diese Adresse haben wir in Uffenheim nicht gefunden. Bitte anders schreiben oder den Ort in der Karte markieren.')]);
        }
        return $this->asJson(['lat' => $hit[0], 'lng' => $hit[1], 'name' => $hit[2]]);
    }

    /** „Betrifft mich auch“ setzen oder zurücknehmen, danach zur Meldung */
    public function actionAuch($id)
    {
        $content = Content::findOne(['id' => (int)$id, 'contentcontainer_id' => $this->contentContainer->contentcontainer_id,
            'object_model' => Post::class]);
        if ($content === null || !$content->canView()) {
            throw new NotFoundHttpException();
        }
        $on = MaengelAuch::toggle($content, Yii::$app->user->id);
        $this->view->success($on ? Yii::t('MaengelmelderModule.base', 'Danke! Wir haben vermerkt, dass dich das auch betrifft.') : Yii::t('MaengelmelderModule.base', 'Vermerk zurückgenommen.'));
        return $this->redirect($content->getUrl());
    }

    /**
     * Alle lesbaren Meldungen dieses Space mit Ort, Status und Zahl der Betroffenen.
     * Gäste sehen alle veröffentlichten Meldungen (sie sind privat), aber nur Ort, Titel, Datum und Status –
     * Text, Melder, Fotos und Kommentare erst nach Anmeldung.
     */
    private function meldungen(): array
    {
        $markers = [];
        $gast = Yii::$app->user->isGuest;
        $query = Post::find()->contentContainer($this->contentContainer);
        $gast ? $query->andWhere(['content.state' => \humhub\modules\content\models\Content::STATE_PUBLISHED]) : $query->readable();
        $posts = $query
            ->andWhere(['like', 'post.message', 'maengelmelder/meldung/karte?lat='])
            ->orderBy(['content.created_at' => SORT_DESC])->limit(500)->all();
        $ids = array_map(fn($p) => $p->content->id, $posts);
        $statuses = MaengelStatus::find()->where(['content_id' => $ids])->indexBy('content_id')->all();
        $auch = MaengelAuch::counts($ids);
        $mine = MaengelAuch::mine($ids);
        foreach ($posts as $post) {
            $id = $post->content->id;
            $status = $statuses[$id] ?? new MaengelStatus(['status' => 'gemeldet']);
            if (!preg_match('/karte\?lat=(-?[\d.]+)&(?:amp;)?lng=(-?[\d.]+)/', $post->message, $m)) {
                continue;
            }
            preg_match('/^\*\*Mängelmeldung: (.+?)\*\*\s*(.*?)\s*📍/su', $post->message, $k);
            preg_match('/📍 \*\*Ort:\*\* (.+?) – \[auf der Karte/u', $post->message, $o);
            $markers[] = [
                'id' => $id,
                'lat' => (float)$m[1],
                'lng' => (float)$m[2],
                // Kategorie aus dem (deutschen) Beitragstext, angezeigt übersetzt
                'titel' => isset($k[1]) ? $this->module->kategorieLabel($k[1]) : Yii::t('MaengelmelderModule.base', 'Meldung'),
                'adresse' => $o[1] ?? '',
                'auszug' => $gast ? '' : mb_strimwidth(preg_replace('/\s+/u', ' ', $k[2] ?? ''), 0, 140, '…'),
                'datum' => Yii::$app->formatter->asDate($post->content->created_at, 'short'),
                'url' => $gast ? '' : $post->content->getUrl(),
                'status' => $status->status,
                'statusLabel' => $status->getAnzeigeLabel(),
                'farbe' => $status->getColor(),
                'auch' => $auch[$id] ?? 0,
                'auchMeins' => in_array($id, $mine, true),
                'eigene' => !$gast && (int)$post->content->created_by === (int)Yii::$app->user->id,
                'auchUrl' => $this->contentContainer->createUrl('/maengelmelder/meldung/auch', ['id' => $id]),
            ];
        }
        return $markers;
    }

    /** Entfernung in Metern (Haversine) */
    private static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
