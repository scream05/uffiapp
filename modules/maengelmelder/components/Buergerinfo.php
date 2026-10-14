<?php

namespace humhub\modules\maengelmelder\components;

use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\maengelmelder\jobs\BuergerJob;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\maengelmelder\models\MaengelKontakt;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\maengelmelder\models\Mangel;
use humhub\modules\maengelmelder\Module;
use humhub\modules\maengelmelder\notifications\BauhofMeldung;
use humhub\modules\whatsapp\components\Api;
use humhub\modules\whatsapp\events\EmpfangEvent;
use Yii;
use yii\helpers\Html;

/**
 * Infos an die meldende Person (wenn beim Melden gewünscht): bei Eingang (WhatsApp), Freigabe und jeder Statusänderung
 * per E-Mail und/oder WhatsApp. Antworten per WhatsApp landen im Verlauf der Meldung (bauhof_nachricht, buerger = 1),
 * die Bauhofleitung bekommt eine Glocke, der zugewiesene Mitarbeiter die Nachricht per WhatsApp.
 * Außerhalb des 24-Stunden-Fensters gehen WhatsApp-Infos als Vorlage (Module::$vorlagen buergerEingang/buergerStatus).
 * „STOPP“ beendet die WhatsApp-Infos für die Nummer.
 */
class Buergerinfo
{
    private const STOPP = ['stop', 'stopp', 'abmelden', 'abbestellen', 'keine nachrichten', 'ende'];

    private static function module(): Module
    {
        return Yii::$app->getModule('maengelmelder');
    }

    // ------------------------------------------------------------------ Bauhof → Bürger

    /** Nach dem Melden: WhatsApp-Bestätigung (E-Mail-Bestätigung für Gäste schickt der MeldungController) */
    public static function eingang(Content $content): void
    {
        $k = MaengelKontakt::findOne($content->id);
        $mangel = Mangel::fromContent($content);
        if (!$k || !$mangel || !$k->info_whatsapp || !$k->wa_id || !self::module()->buergerWhatsappAktiv()) {
            return;
        }
        $text = "Ihre Mängelmeldung Nr. {$content->id} ({$mangel->kategorie}) ist eingegangen. "
            . ($content->state == Content::STATE_DRAFT ? 'Der Bauhof prüft sie und schaltet sie dann frei. ' : '')
            . "Über Änderungen am Bearbeitungsstand informieren wir Sie hier. Sie können auf diese Nachricht antworten; "
            . "Ihre Antwort geht an den Bauhof. Keine Nachrichten mehr? Antworten Sie mit STOPP.";
        self::whatsapp($k, $content->id, $text, self::module()->vorlagen['buergerEingang'], [(string)$content->id, $mangel->kategorie], 'eingang');
    }

    /** Freigabe einer Gastmeldung durch den Bauhof */
    public static function freigegeben(Content $content): void
    {
        self::melden($content, 'Geprüft und freigegeben', 'Ihre Meldung ist jetzt auf der Karte zu sehen.');
    }

    /** Statusänderung (Bauhofseite oder Mitarbeiter per WhatsApp) */
    public static function statusGeaendert(Content $content, MaengelStatus $status, ?string $hinweis): void
    {
        self::melden($content, $status->getLabel(), $hinweis);
    }

    private static function melden(Content $content, string $stand, ?string $hinweis): void
    {
        try {
            $k = MaengelKontakt::findOne($content->id);
            $mangel = Mangel::fromContent($content);
            if (!$k || !$mangel || !self::module()->buergerInfoAktiv() || !($k->info_mail || $k->info_whatsapp)) {
                return;
            }
            if ($k->info_mail && ($mail = $k->getMail())) {
                self::mail($k, $mail, $mangel, $stand, $hinweis);
            }
            if ($k->info_whatsapp && $k->wa_id && self::module()->buergerWhatsappAktiv()) {
                $text = "Neuer Stand zu Ihrer Mängelmeldung Nr. {$content->id} ({$mangel->kategorie}): *$stand*"
                    . ($hinweis ? "\n💬 $hinweis" : '')
                    . "\n\nSie können auf diese Nachricht antworten; Ihre Antwort geht an den Bauhof. Keine Nachrichten mehr? Antworten Sie mit STOPP.";
                self::whatsapp($k, $content->id, $text, self::module()->vorlagen['buergerStatus'],
                    [(string)$content->id, $mangel->kategorie, $stand, $hinweis ?: '–'], 'status');
            }
        } catch (\Throwable $e) {
            Yii::error("Bürgerinfo {$content->id}: " . $e->getMessage(), 'maengelmelder');
        }
    }

    /**
     * WhatsApp an die meldende Person: im offenen 24-Stunden-Fenster als Text, sonst als Vorlage. Wird im Verlauf
     * der Meldung gespeichert (Zustellstatus aktualisiert Bauhof::onStatus über die wamid).
     */
    private static function whatsapp(MaengelKontakt $k, int $contentId, string $text, string $vorlage, array $parameter, string $typ): void
    {
        $log = new BauhofNachricht(['content_id' => $contentId, 'richtung' => BauhofNachricht::AUS, 'buerger' => 1,
            'typ' => $typ, 'text' => $text, 'verarbeitet' => 1]);
        try {
            $log->wamid = self::fensterOffen($k->wa_id)
                ? Api::sendeText($k->wa_id, $text)
                : Api::sendeVorlage($k->wa_id, $vorlage, self::module()->vorlagenSprache, $parameter);
            $log->zustellung = 'gesendet';
        } catch (\Throwable $e) {
            $log->zustellung = 'fehler';
            $log->fehler = mb_substr($e->getMessage(), 0, 255);
            Yii::warning("Bürger-WhatsApp $contentId: " . $e->getMessage(), 'maengelmelder');
        }
        $log->save(false);
    }

    private static function fensterOffen(string $waId): bool
    {
        $letzte = BauhofNachricht::find()->alias('n')->innerJoin('maengel_kontakt k', 'k.content_id = n.content_id')
            ->where(['n.buerger' => 1, 'n.richtung' => BauhofNachricht::EIN, 'k.wa_id' => $waId])->max('n.created_at');
        return $letzte !== null && strtotime($letzte) > time() - 23 * 3600;
    }

    private static function mail(MaengelKontakt $k, string $an, Mangel $mangel, string $stand, ?string $hinweis): void
    {
        $content = $mangel->post->content;
        $link = $k->istGast() || $content->state != Content::STATE_PUBLISHED
            ? $content->container->createUrl('/maengelmelder/meldung/karte', ['lat' => $mangel->lat, 'lng' => $mangel->lng], true)
            : \yii\helpers\Url::to($content->getUrl(), true);
        $text = Yii::$app->view->renderFile('@maengelmelder/views/mail/status_text.php', [
            'name' => $k->getName(), 'mangel' => $mangel, 'stand' => $stand, 'hinweis' => $hinweis, 'link' => $link,
            'kontaktMail' => self::module()->kontaktMail, 'gast' => $k->istGast(),
        ]);
        self::mailSenden($an, "Neuer Stand zu Ihrer Mängelmeldung Nr. {$content->id}: $stand", $text);
    }

    /** Text-Mail im Layout der Plattform; Links ohne HumHubs Weiterleitungsdienst (wie die Eingangsbestätigung) */
    public static function mailSenden(string $an, string $betreff, string $text): bool
    {
        $html = nl2br(Html::encode($text), false);
        $html = preg_replace('#https?://[^\s<]+#', '<a href="$0">$0</a>', $html);
        $mailer = Yii::$app->mailer;
        $html = $mailer->render('@humhub/views/mail/TextOnly', ['message' => $html], $mailer->htmlLayout);
        $mail = $mailer->compose()->setTo($an)->setReplyTo(self::module()->kontaktMail)->setSubject($betreff);
        $mail->getSymfonyEmail()->text($text)->html($html);
        $ok = $mail->send();
        if (!$ok) {
            Yii::warning("Mail an Meldende(n) nicht versendet: $betreff", 'maengelmelder');
        }
        return $ok;
    }

    // ------------------------------------------------------------------ Bürger → Bauhof

    /**
     * Webhook (nach Bauhof::onEmpfangen, das Mitarbeiter-Nummern übernimmt): Nachrichten von Nummern, die Infos zu
     * einer Meldung bekommen, gehören zur Meldung – bevorzugt zu der, auf deren Nachricht geantwortet wurde.
     */
    public static function onEmpfangen(EmpfangEvent $event): void
    {
        if ($event->handled) {
            return;
        }
        try {
            $d = $event->daten;
            $von = preg_replace('/\D/', '', (string)($d['from'] ?? ''));
            if ($von === '' || !self::module()->buergerWhatsappAktiv()) {
                return;
            }
            $bezug = $d['context']['id'] ?? null;
            $contentId = $bezug ? BauhofNachricht::find()->select('content_id')
                ->where(['wamid' => $bezug, 'buerger' => 1, 'richtung' => BauhofNachricht::AUS])->scalar() : null;
            $weitergeleitet = !empty($d['context']['forwarded']) || !empty($d['context']['frequently_forwarded']);
            if (!$contentId) {
                // Weitergeleitete Beiträge (z. B. Veranstaltungsplakate) ohne Bezug gehören in den Termin-Eingang
                if ($weitergeleitet) {
                    return;
                }
                $contentId = self::letzteMeldung($von);
            }
            if (!$contentId) {
                return;
            }
            $event->handled = true;
            if (BauhofNachricht::find()->where(['wamid' => $d['id']])->exists()) {
                return;
            }
            $typ = (string)($d['type'] ?? 'unbekannt');
            $n = new BauhofNachricht([
                'content_id' => (int)$contentId,
                'richtung' => BauhofNachricht::EIN,
                'buerger' => 1,
                'typ' => in_array($typ, ['interactive', 'button'], true) ? 'text' : mb_substr($typ, 0, 20),
                'text' => match ($typ) {
                    'text' => $d['text']['body'] ?? null,
                    'image', 'document', 'video' => $d[$typ]['caption'] ?? null,
                    'button' => $d['button']['text'] ?? null,
                    'interactive' => $d['interactive']['button_reply']['title'] ?? null,
                    default => null,
                },
                'media_id' => in_array($typ, ['image', 'document'], true) ? ($d[$typ]['id'] ?? null) : null,
                'wamid' => $d['id'],
                'bezug_wamid' => $bezug,
                'created_at' => date('Y-m-d H:i:s', (int)($d['timestamp'] ?? time())),
            ]);
            $n->save(false);
            Yii::$app->queue->push(new BuergerJob(['id' => $n->id]));
        } catch (\Throwable $e) {
            Yii::error('Bürger-Empfang: ' . $e->getMessage(), 'maengelmelder');
            $event->handled = false;
        }
    }

    /** Meldung mit WhatsApp-Infos an diese Nummer, zu der zuletzt geschrieben wurde (sonst die neueste) */
    private static function letzteMeldung(string $waId): ?int
    {
        $ids = MaengelKontakt::find()->alias('k')->innerJoin('content c', 'c.id = k.content_id')
            ->where(['k.wa_id' => $waId, 'k.info_whatsapp' => 1, 'c.state' => [Content::STATE_PUBLISHED, Content::STATE_DRAFT]])
            ->select('k.content_id')->column();
        if (!$ids) {
            return null;
        }
        $zuletzt = BauhofNachricht::find()->select('content_id')->where(['content_id' => $ids, 'buerger' => 1])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])->scalar();
        return (int)($zuletzt ?: max($ids));
    }

    /** Hintergrundjob: Foto laden, STOPP, Bestätigung an den Bürger, Glocke an den Bauhof, Weiterleitung an den Mitarbeiter */
    public static function verarbeiten(int $id): void
    {
        $n = BauhofNachricht::findOne($id);
        if ($n === null || $n->verarbeitet || !$n->buerger) {
            return;
        }
        $n->updateAttributes(['verarbeitet' => 1]);
        $k = MaengelKontakt::findOne($n->content_id);
        if (!$k || !$k->wa_id) {
            return;
        }
        try {
            Api::gelesen((string)$n->wamid);
        } catch (\Throwable $e) {
        }
        try {
            $t = mb_strtolower(trim((string)$n->text, " \t\n\r!.?"));
            if ($n->typ === 'text' && in_array($t, self::STOPP, true)) {
                MaengelKontakt::updateAll(['info_whatsapp' => 0], ['wa_id' => $k->wa_id]);
                $n->updateAttributes(['gelesen_at' => date('Y-m-d H:i:s')]);
                Bauhof::vermerk($n->content_id, null, null, 'Meldende Person hat WhatsApp-Infos abbestellt (STOPP)');
                $ok = new BauhofNachricht(['content_id' => $n->content_id, 'richtung' => BauhofNachricht::AUS, 'buerger' => 1,
                    'typ' => 'text', 'verarbeitet' => 1, 'text' => 'Sie bekommen von uns keine WhatsApp-Infos zu Ihren Mängelmeldungen mehr. Danke!']);
                try {
                    $ok->wamid = Api::sendeText($k->wa_id, $ok->text, $n->wamid);
                    $ok->zustellung = 'gesendet';
                } catch (\Throwable $e) {
                    $ok->zustellung = 'fehler';
                    $ok->fehler = mb_substr($e->getMessage(), 0, 255);
                }
                $ok->save(false);
                return;
            }
            if (in_array($n->typ, ['image', 'document'], true) && $n->media_id) {
                self::datei($n);
            }
            // Bestätigung höchstens einmal pro Stunde und Meldung
            $key = "maengel-buerger-ack-{$n->content_id}";
            if (!Yii::$app->cache->get($key)) {
                Yii::$app->cache->set($key, 1, 3600);
                $ack = new BauhofNachricht(['content_id' => $n->content_id, 'richtung' => BauhofNachricht::AUS, 'buerger' => 1,
                    'typ' => 'text', 'verarbeitet' => 1,
                    'text' => "Danke, Ihre Nachricht zu Meldung Nr. {$n->content_id} ist beim Bauhof angekommen."]);
                try {
                    $ack->wamid = Api::sendeText($k->wa_id, $ack->text, $n->wamid);
                    $ack->zustellung = 'gesendet';
                } catch (\Throwable $e) {
                    $ack->zustellung = 'fehler';
                    $ack->fehler = mb_substr($e->getMessage(), 0, 255);
                }
                $ack->save(false);
            }
            self::glocke($n);
            self::anMitarbeiter($n, $k);
        } catch (\Throwable $e) {
            Yii::error("Bürger-Nachricht $id: " . $e->getMessage(), 'maengelmelder');
        }
    }

    private static function datei(BauhofNachricht $n): void
    {
        [$data, $mime] = Api::medium((string)$n->media_id);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: $mime;
        $file = new File([
            'file_name' => ($n->typ === 'image' ? 'foto' : 'datei') . '-' . $n->id . '.' . (explode('/', $mime)[1] ?? 'bin'),
            'mime_type' => $mime,
            'size' => strlen($data),
        ]);
        $file->object_model = BauhofNachricht::class;
        $file->object_id = $n->id;
        $file->save();
        $file->setStoredFileContent($data);
        $n->updateAttributes(['file_id' => $file->id, 'media_id' => null]);
    }

    /** Glocke an die Bauhofleitung, höchstens alle 5 Minuten je Meldung */
    private static function glocke(BauhofNachricht $n): void
    {
        $key = "maengel-buerger-glocke-{$n->content_id}";
        if (Yii::$app->cache->get($key)) {
            return;
        }
        Yii::$app->cache->set($key, 1, 300);
        if ($users = Bauhof::empfaenger()) {
            BauhofMeldung::instance()->about($n)->sendBulk($users);
        }
    }

    /** Zugewiesener Mitarbeiter bekommt die Nachricht über die Uffi-Nummer (Fenster/Vorlage regelt Bauhof::nachricht) */
    private static function anMitarbeiter(BauhofNachricht $n, MaengelKontakt $k): void
    {
        $m = Bauhof::bearbeiter($n->content_id);
        if ($m === null) {
            return;
        }
        $inhalt = trim((string)$n->text);
        if ($n->file_id) {
            $inhalt = '📷 Foto' . ($inhalt !== '' ? ": $inhalt" : '') . ' (auf der Plattform beim Auftrag zu sehen)';
        }
        if ($inhalt === '') {
            return;
        }
        Bauhof::nachricht($m, $n->content_id, "Nachricht der meldenden Person:\n$inhalt", null);
    }
}
