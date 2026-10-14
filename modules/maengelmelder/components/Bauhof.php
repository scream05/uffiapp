<?php

namespace humhub\modules\maengelmelder\components;

use humhub\modules\comment\models\Comment;
use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\maengelmelder\jobs\BauhofJob;
use humhub\modules\maengelmelder\models\Auftrag;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\maengelmelder\models\Mangel;
use humhub\modules\maengelmelder\models\Mitarbeiter;
use humhub\modules\maengelmelder\Module;
use humhub\modules\maengelmelder\notifications\BauhofMeldung;
use humhub\modules\post\models\Post;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use humhub\modules\whatsapp\components\Api;
use humhub\modules\whatsapp\events\EmpfangEvent;
use Yii;

/**
 * Bauhof per WhatsApp: Aufträge an Mitarbeiter schicken, Fotos/Texte/Statusänderungen der Mitarbeiter empfangen.
 *
 * Freie Nachrichten erlaubt WhatsApp nur bis 24 Std. nach der letzten Nachricht des Mitarbeiters. Außerhalb dieses
 * Fensters geht eine genehmigte Vorlage raus („Neuer Auftrag …“ bzw. „Neue Nachricht …“); tippt der Mitarbeiter
 * auf deren Knopf, ist das Fenster offen und die eigentlichen Inhalte (Text, Fotos, Standort, Knöpfe) folgen.
 *
 * Knopf-/Listen-IDs: d:<id> Auftrag anzeigen · s:<id>:<status> Status setzen · x:<id> weitere Status ·
 * l Auftragsliste · z:<id> Foto/Text diesem Auftrag zuordnen · n keinem Auftrag · m wartende Nachrichten zeigen
 */
class Bauhof
{
    /** Kurze Status-Beschriftungen für WhatsApp-Knöpfe/Listen (max. 20/24 Zeichen) */
    public const STATUS_KURZ = [
        'gemeldet' => 'Gemeldet',
        'pruefung' => 'In Prüfung',
        'arbeit' => 'In Arbeit',
        'erledigt' => 'Erledigt',
        'abgelehnt' => 'Nicht zuständig',
    ];

    public const HILFE = "So funktioniert's 👷\n\n"
        . "• Neue Aufträge kommen hier mit Beschreibung, Fotos und Ort an.\n"
        . "• Mit den Knöpfen unter dem Auftrag setzt du den Status („In Arbeit“, „Erledigt“ …).\n"
        . "• Fotos und Text, die du schickst, landen beim Auftrag – am sichersten, wenn du auf die Auftragsnachricht antwortest (wischen/„Antworten“).\n"
        . "• „Aufträge“ schreiben zeigt deine offenen Aufträge.\n\n"
        . "Bitte keine Personen oder Kennzeichen erkennbar fotografieren.";

    private static function module(): Module
    {
        return Yii::$app->getModule('maengelmelder');
    }

    // ------------------------------------------------------------------ Bauhof → Mitarbeiter

    /**
     * Mitarbeiter zuweisen (oder Zuweisung aufheben) und, wenn gewünscht, den Auftrag per WhatsApp schicken.
     * @return string|null Fehlermeldung beim Senden
     */
    public static function zuweisen(Auftrag $auftrag, ?int $mitarbeiterId, User $user, bool $senden = true): ?string
    {
        $alt = $auftrag->mitarbeiter_id;
        $auftrag->mitarbeiter_id = $mitarbeiterId;
        if ((int)$alt !== (int)$mitarbeiterId) {
            $auftrag->zugewiesen_at = $mitarbeiterId ? date('Y-m-d H:i:s') : null;
            $auftrag->zugewiesen_by = $mitarbeiterId ? $user->id : null;
            $auftrag->gesendet_at = null;
        }
        $auftrag->save(false);
        if ((int)$alt === (int)$mitarbeiterId) {
            return null;
        }
        $neu = $mitarbeiterId ? Mitarbeiter::findOne($mitarbeiterId) : null;
        self::vermerk($auftrag->content_id, $neu?->id ?? $alt, $user,
            $neu ? "Zugewiesen an {$neu->name}" : 'Zuweisung aufgehoben');
        if ($alt && ($vorher = Mitarbeiter::findOne($alt)) && $vorher->aktiv) {
            self::nachricht($vorher, $auftrag->content_id, "Auftrag Nr. {$auftrag->content_id} wurde dir wieder abgenommen – bitte nicht mehr bearbeiten.", $user, 'system');
        }
        return $neu && $senden ? self::sendeAuftrag($auftrag, $user) : null;
    }

    /** Auftrag (erneut) an den zuständigen Mitarbeiter schicken; @return string|null Fehlermeldung */
    public static function sendeAuftrag(Auftrag $auftrag, User $user): ?string
    {
        $m = $auftrag->mitarbeiter;
        $mangel = ($c = Content::findOne($auftrag->content_id)) ? Mangel::fromContent($c) : null;
        if (!$m || !$mangel) {
            return 'Kein Mitarbeiter oder Meldung nicht gefunden.';
        }
        $log = null;
        try {
            if ($m->isFensterOffen()) {
                self::sendeDetails($m, $mangel, $auftrag);
            } else {
                $log = self::neueAusgehende($m, $mangel->getContentId(), 'auftrag', 'Auftrag per Vorlage angekündigt', $user);
                $log->wamid = Api::sendeVorlage($m->telefon, self::module()->vorlagen['auftrag'], self::module()->vorlagenSprache,
                    [(string)$mangel->getContentId(), $mangel->kategorie, mb_substr($mangel->getOrt(), 0, 120)],
                    ['d:' . $mangel->getContentId()]);
                $log->zustellung = 'gesendet';
                $log->save(false);
            }
            $auftrag->updateAttributes(['gesendet_at' => date('Y-m-d H:i:s')]);
            return null;
        } catch (\Throwable $e) {
            Yii::warning("Auftrag {$auftrag->content_id} an {$m->name}: {$e->getMessage()}", 'maengelmelder');
            $log?->updateAttributes(['zustellung' => 'fehler', 'fehler' => mb_substr(self::fehlertext($e), 0, 255)]);
            self::vermerk($auftrag->content_id, $m->id, $user, 'WhatsApp-Versand fehlgeschlagen: ' . self::fehlertext($e));
            return self::fehlertext($e);
        }
    }

    /**
     * Meldung wurde bearbeitet: Ist der Auftrag einem Mitarbeiter zugewiesen und noch offen, bekommt er die Änderung
     * (Ort, Kategorie, Beschreibung) per WhatsApp – im offenen Fenster zusätzlich den neuen Standort als Kartenpunkt,
     * sonst per Vorlage „Neue Nachricht“, der Text folgt beim Antippen (Kartenlink im Text).
     * @return string|null Fehlermeldung beim Senden
     */
    public static function sendeAenderung(Mangel $alt, Mangel $neu, User $user): ?string
    {
        $id = $neu->getContentId();
        $m = self::bearbeiter($id);
        if (!$m || ($aenderung = self::aenderung($alt, $neu)) === null) {
            return null;
        }
        [$teile, $was, $ortNeu] = $aenderung;
        try {
            if ($ortNeu && $m->isFensterOffen()) {
                $teil = self::neueAusgehende($m, $id, 'teil', null, null);
                $teil->wamid = Api::sendeStandort($m->telefon, $neu->lat, $neu->lng, "Auftrag Nr. $id – neuer Ort", $neu->adresse);
                $teil->zustellung = 'gesendet';
                $teil->save(false);
            }
        } catch (\Throwable $e) {
            Yii::warning("Standort Auftrag $id an {$m->name}: {$e->getMessage()}", 'maengelmelder');
        }
        return self::meldeAenderung($id, $teile, $was, $user);
    }

    /** Zuständiger, aktiver Mitarbeiter eines Auftrags (oder null) */
    public static function bearbeiter(int $contentId): ?Mitarbeiter
    {
        $m = Auftrag::findOne($contentId)?->mitarbeiter;
        return $m && $m->aktiv ? $m : null;
    }

    /**
     * Jede Änderung an einem zugewiesenen Auftrag per WhatsApp an den Mitarbeiter (Text; außerhalb des Fensters
     * Vorlage „Neue Nachricht“, Text beim Antippen) und Vermerk im Verlauf.
     * @param string[] $teile Zeilen der Änderung, z. B. „🔄 *Status:* Erledigt“
     * @return string|null Fehlermeldung beim Senden
     */
    public static function meldeAenderung(int $contentId, array $teile, string $was, User $user): ?string
    {
        $m = self::bearbeiter($contentId);
        if (!$m || !$teile) {
            return null;
        }
        $fehler = self::nachricht($m, $contentId, "✏️ Auftrag Nr. $contentId wurde geändert:\n\n" . implode("\n\n", $teile), $user, 'system');
        self::vermerk($contentId, $m->id, $user, $fehler ? "Änderung ($was) an {$m->name}: WhatsApp-Versand fehlgeschlagen: $fehler"
            : "Änderung ($was) an {$m->name} geschickt");
        return $fehler;
    }

    /**
     * Was hat sich an der Meldung geändert? @return array{0:string[],1:string,2:bool}|null [Zeilen, „Ort, Kategorie …“, Ort verschoben]
     */
    public static function aenderung(Mangel $alt, Mangel $neu): ?array
    {
        $ortNeu = $neu->lat && (abs($neu->lat - (float)$alt->lat) > 1e-6 || abs($neu->lng - (float)$alt->lng) > 1e-6);
        $katNeu = $neu->kategorie !== $alt->kategorie;
        $textNeu = trim($neu->beschreibung) !== trim($alt->beschreibung);
        $teile = [];
        if ($ortNeu) {
            $teile[] = "📍 *Neuer Ort:* " . $neu->getOrt()
                . sprintf("\nhttps://www.openstreetmap.org/?mlat=%.6f&mlon=%.6f#map=18/%.6f/%.6f", $neu->lat, $neu->lng, $neu->lat, $neu->lng);
        }
        if ($katNeu) {
            $teile[] = "🏷️ *Kategorie:* {$neu->kategorie} (vorher {$alt->kategorie})";
        }
        if ($textNeu) {
            $teile[] = "📝 *Neue Beschreibung:*\n" . mb_substr($neu->beschreibung, 0, 600);
        }
        if (!$teile) {
            return null;
        }
        $was = implode(', ', array_filter([$ortNeu ? 'Ort' : null, $katNeu ? 'Kategorie' : null, $textNeu ? 'Beschreibung' : null]));
        return [$teile, $was, $ortNeu];
    }

    /** Auftrag mit Fotos, Standort und Status-Knöpfen (nur im offenen Fenster) */
    public static function sendeDetails(Mitarbeiter $m, Mangel $mangel, ?Auftrag $auftrag = null): void
    {
        $id = $mangel->getContentId();
        $auftrag ??= Auftrag::forContentId($id);
        $status = MaengelStatus::forContent($mangel->post->content);
        foreach (array_slice($mangel->getBilder(), 0, 3) as $i => $file) {
            $data = $file->getStore()->getContent();
            if ($data === null || $data === false || strlen($data) > 5 * 1024 * 1024) {
                continue;
            }
            $teil = self::neueAusgehende($m, $id, 'teil', null, null);
            $teil->wamid = Api::sendeBild($m->telefon, Api::hochladen($data, $file->mime_type, $file->file_name),
                $i === 0 ? "Auftrag Nr. $id – Foto der Meldung" : '');
            $teil->zustellung = 'gesendet';
            $teil->save(false);
        }
        if ($mangel->lat) {
            $teil = self::neueAusgehende($m, $id, 'teil', null, null);
            $teil->wamid = Api::sendeStandort($m->telefon, $mangel->lat, $mangel->lng, "Auftrag Nr. $id", $mangel->adresse);
            $teil->zustellung = 'gesendet';
            $teil->save(false);
        }
        $text = "🔧 *Auftrag Nr. $id – {$mangel->kategorie}*\n\n" . mb_substr($mangel->beschreibung, 0, 600)
            . "\n\n📍 " . $mangel->getOrt()
            . "\nStatus: " . $status->getLabel()
            . ($auftrag->prioritaet !== 'normal' ? "\nPriorität: " . $auftrag->getPrioritaetLabel() : '')
            . ($auftrag->faellig ? "\nErledigen bis: " . date('d.m.Y', strtotime($auftrag->faellig)) : '')
            . ($auftrag->hinweis ? "\n\n💬 Hinweis: " . $auftrag->hinweis : '')
            . "\n\nFotos und Text zu diesem Auftrag am besten als Antwort auf diese Nachricht schicken.";
        $log = self::neueAusgehende($m, $id, 'auftrag', $text, null);
        $log->wamid = Api::sendeKnoepfe($m->telefon, $text, self::statusKnoepfe($id, $status->status));
        $log->zustellung = 'gesendet';
        $log->save(false);
        $m->updateAttributes(['aktuell_content_id' => $id]);
    }

    /**
     * Freie Nachricht des Bauhofs an einen Mitarbeiter (Chat). Außerhalb des Fensters wird sie vorgemerkt und eine
     * Vorlage „Neue Nachricht“ geschickt; die Nachricht folgt, sobald der Mitarbeiter darauf tippt.
     * @return string|null Fehlermeldung
     */
    public static function nachricht(Mitarbeiter $m, ?int $contentId, string $text, ?User $user, string $typ = 'text'): ?string
    {
        $log = self::neueAusgehende($m, $contentId, $typ, $text, $user);
        try {
            if ($m->isFensterOffen()) {
                $log->wamid = Api::sendeText($m->telefon, self::chatText($log));
                $log->zustellung = 'gesendet';
            } else {
                // Eine Vorlage genügt für mehrere wartende Nachrichten
                $wartet = BauhofNachricht::find()->where(['mitarbeiter_id' => $m->id, 'richtung' => BauhofNachricht::AUS,
                    'zustellung' => 'wartet'])->andWhere(['not', ['id' => $log->id]])->exists();
                if (!$wartet) {
                    Api::sendeVorlage($m->telefon, self::module()->vorlagen['nachricht'], self::module()->vorlagenSprache,
                        [$user ? $user->displayName : 'Bauhof'], ['m']);
                }
                $log->zustellung = 'wartet';
            }
            $log->save(false);
            return null;
        } catch (\Throwable $e) {
            $log->zustellung = 'fehler';
            $log->fehler = mb_substr(self::fehlertext($e), 0, 255);
            $log->save(false);
            return self::fehlertext($e);
        }
    }

    /**
     * Status setzen (Bauhofseite oder Mitarbeiter per WhatsApp). Öffentlich erscheint nur der neue Status als Kommentar
     * des Bauhof-Kontos, nicht der Name des Mitarbeiters.
     */
    public static function statusSetzen(Content $content, string $neu, ?string $hinweis, User $als, ?Mitarbeiter $von = null, bool $melden = true): bool
    {
        if (!isset(MaengelStatus::STATUSES[$neu])) {
            return false;
        }
        $status = MaengelStatus::forContent($content);
        $hinweis = $hinweis !== null && trim($hinweis) !== '' ? trim(mb_substr($hinweis, 0, 500)) : null;
        if ($status->status === $neu && $hinweis === null) {
            return false;
        }
        $status->status = $neu;
        $status->note = $hinweis;
        $status->updated_by = $als->id;
        $status->updated_at = date('Y-m-d H:i:s');
        $status->save(false);

        // Kommentar unter der Meldung (benachrichtigt die meldende Person); im Hintergrund als Bauhof-Konto
        $vorher = Yii::$app->user->getIdentity();
        $wechsel = !$vorher || (int)$vorher->id !== (int)$als->id;
        if ($wechsel) {
            Yii::$app->user->switchIdentity($als);
        }
        try {
            $comment = new Comment();
            $comment->message = '**Status: ' . $status->getLabel() . '**' . ($hinweis !== null ? " – $hinweis" : '');
            $comment->object_model = Post::class;
            $comment->object_id = $content->object_id;
            $comment->save();
        } finally {
            if ($wechsel) {
                Yii::$app->user->switchIdentity($vorher);
            }
        }
        $wer = $von ? $von->name . ' (WhatsApp)' : $als->displayName;
        self::vermerk($content->id, $von?->id, $von ? null : $als, "Status „{$status->getLabel()}“ – $wer"
            . ($hinweis !== null ? ": $hinweis" : ''), 'status');
        // Änderung im Backend: zuständigen Mitarbeiter informieren (nicht, wenn er den Status selbst gesetzt hat)
        if ($melden && $von === null) {
            self::meldeAenderung($content->id, [self::statusZeile($status, $hinweis)], 'Status', $als);
        }
        // Meldende Person, falls sie Infos per E-Mail/WhatsApp wünscht
        Buergerinfo::statusGeaendert($content, $status, $hinweis);
        return true;
    }

    /** Zeile „🔄 Status: …“ für Änderungsnachrichten */
    public static function statusZeile(MaengelStatus $status, ?string $hinweis): string
    {
        return "🔄 *Status:* " . $status->getLabel() . ($hinweis !== null && $hinweis !== '' ? "\n💬 $hinweis" : '');
    }

    // ------------------------------------------------------------------ Mitarbeiter → Bauhof

    /** Webhook (WhatsApp-Modul): Nachrichten von eingetragenen Mitarbeitern übernehmen */
    public static function onEmpfangen(EmpfangEvent $event): void
    {
        try {
            $d = $event->daten;
            $m = Mitarbeiter::findOne(['telefon' => preg_replace('/\D/', '', (string)($d['from'] ?? '')), 'aktiv' => 1]);
            if ($m === null) {
                return;
            }
            // Weitergeleitete Beiträge (Veranstaltungsplakate aus Gruppen) gehören in den Termin-Eingang –
            // außer es ist eine Antwort auf eine Bauhof-Nachricht. Eigene Fotos der Mitarbeiter sind nie weitergeleitet.
            $weitergeleitet = !empty($d['context']['forwarded']) || !empty($d['context']['frequently_forwarded']);
            $bezug = $d['context']['id'] ?? null;
            if ($weitergeleitet && !($bezug && BauhofNachricht::find()->where(['wamid' => $bezug])->exists())) {
                return;
            }
            $event->handled = true;
            if (BauhofNachricht::find()->where(['wamid' => $d['id']])->exists()) {
                return;
            }
            $typ = (string)($d['type'] ?? 'unbekannt');
            $text = match ($typ) {
                'text' => $d['text']['body'] ?? null,
                'image', 'document', 'video' => $d[$typ]['caption'] ?? null,
                'interactive' => $d['interactive']['button_reply']['id'] ?? $d['interactive']['list_reply']['id'] ?? null,
                'button' => $d['button']['payload'] ?? null,
                default => null,
            };
            $n = new BauhofNachricht([
                'mitarbeiter_id' => $m->id,
                'richtung' => BauhofNachricht::EIN,
                'typ' => in_array($typ, ['interactive', 'button'], true) ? 'knopf' : mb_substr($typ, 0, 20),
                'text' => $text,
                'media_id' => in_array($typ, ['image', 'document'], true) ? ($d[$typ]['id'] ?? null) : null,
                'wamid' => $d['id'],
                'bezug_wamid' => $d['context']['id'] ?? null,
                'created_at' => date('Y-m-d H:i:s', (int)($d['timestamp'] ?? time())),
            ]);
            $n->save(false);
            $m->updateAttributes(['letzte_eingang_at' => date('Y-m-d H:i:s')]);
            Yii::$app->queue->push(new BauhofJob(['id' => $n->id]));
        } catch (\Throwable $e) {
            // Lieber im Termin-Eingang landen lassen als verlieren
            Yii::error('Bauhof-Empfang: ' . $e->getMessage(), 'maengelmelder');
            $event->handled = false;
        }
    }

    /** Zustellstatus unserer Nachrichten (gesendet → zugestellt → gelesen, oder fehler) */
    public static function onStatus(EmpfangEvent $event): void
    {
        try {
            $s = $event->daten;
            $n = isset($s['id']) ? BauhofNachricht::findOne(['wamid' => $s['id'], 'richtung' => BauhofNachricht::AUS]) : null;
            if ($n === null) {
                return;
            }
            $neu = ['sent' => 'gesendet', 'delivered' => 'zugestellt', 'read' => 'gelesen', 'failed' => 'fehler'][$s['status'] ?? ''] ?? null;
            $rang = ['wartet' => 0, 'gesendet' => 1, 'zugestellt' => 2, 'gelesen' => 3, 'fehler' => 4];
            if ($neu && ($rang[$neu] > ($rang[$n->zustellung] ?? -1))) {
                $n->zustellung = $neu;
                if ($neu === 'fehler') {
                    $n->fehler = mb_substr((string)($s['errors'][0]['title'] ?? $s['errors'][0]['message'] ?? 'Zustellung fehlgeschlagen'), 0, 255);
                }
                $n->save(false);
            }
        } catch (\Throwable $e) {
            Yii::warning('Bauhof-Zustellstatus: ' . $e->getMessage(), 'maengelmelder');
        }
    }

    /** Hintergrundjob: eingegangene Nachricht auswerten */
    public static function verarbeiten(int $id): void
    {
        $n = BauhofNachricht::findOne($id);
        if ($n === null || $n->verarbeitet || !($m = $n->mitarbeiter)) {
            return;
        }
        $n->updateAttributes(['verarbeitet' => 1]);
        try {
            Api::gelesen($n->wamid);
        } catch (\Throwable $e) {
        }
        try {
            if ($n->typ === 'knopf') {
                self::knopf($m, $n);
            } elseif ($n->typ === 'text') {
                self::text($m, $n);
            } elseif (in_array($n->typ, ['image', 'document'], true)) {
                self::datei($m, $n);
            } else {
                $n->updateAttributes(['gelesen_at' => date('Y-m-d H:i:s')]);
                self::antwort('sendeText', $m->telefon, 'Das kann ich leider nicht verarbeiten – bitte Text oder Foto schicken.');
            }
        } catch (\Throwable $e) {
            Yii::error("Bauhof-Nachricht $id: " . $e->getMessage(), 'maengelmelder');
        }
    }

    private static function knopf(Mitarbeiter $m, BauhofNachricht $n): void
    {
        $n->updateAttributes(['gelesen_at' => date('Y-m-d H:i:s')]); // Knöpfe sind keine Nachrichten an den Bauhof
        $teile = explode(':', (string)$n->text);
        $id = (int)($teile[1] ?? 0);
        $mangel = $id ? self::auftragDesMitarbeiters($m, $id) : null;
        switch ($teile[0]) {
            case 'd':
                if ($mangel) {
                    self::sendeDetails($m, $mangel);
                    self::wartendeSenden($m);
                } else {
                    self::antwort('sendeText', $m->telefon, "Auftrag Nr. $id ist dir nicht (mehr) zugewiesen.");
                }
                return;
            case 'm':
                if (!self::wartendeSenden($m)) {
                    self::antwort('sendeText', $m->telefon, 'Keine neuen Nachrichten.');
                }
                return;
            case 'l':
                self::sendeListe($m);
                return;
            case 's':
                $neu = (string)($teile[2] ?? '');
                if (!$mangel || !isset(MaengelStatus::STATUSES[$neu])) {
                    self::antwort('sendeText', $m->telefon, "Auftrag Nr. $id ist dir nicht (mehr) zugewiesen.");
                    return;
                }
                $n->updateAttributes(['content_id' => $id]);
                $m->updateAttributes(['aktuell_content_id' => $id]);
                if (self::statusSetzen($mangel->post->content, $neu, null, self::bauhofKonto($id), $m)) {
                    self::benachrichtigen($m, $n, "hat Auftrag Nr. $id auf „" . self::STATUS_KURZ[$neu] . '“ gesetzt');
                }
                $text = "✅ Auftrag Nr. $id: Status „" . self::STATUS_KURZ[$neu] . '“ gespeichert.';
                if ($neu === 'erledigt') {
                    self::antwort('sendeText', $m->telefon, "$text Danke! Schick gern noch ein Foto vom Ergebnis.");
                } else {
                    self::antwort('sendeKnoepfe', $m->telefon, $text, self::statusKnoepfe($id, $neu));
                }
                return;
            case 'x':
                if ($mangel) {
                    $aktuell = MaengelStatus::forContent($mangel->post->content)->status;
                    $zeilen = [];
                    foreach (self::STATUS_KURZ as $key => $label) {
                        if ($key !== $aktuell) {
                            $zeilen["s:$id:$key"] = [$label, ''];
                        }
                    }
                    self::antwort('sendeListe', $m->telefon, "Neuer Status für Auftrag Nr. $id:", 'Status wählen', $zeilen);
                }
                return;
            case 'z':
            case 'n':
                $offen = BauhofNachricht::find()->where(['mitarbeiter_id' => $m->id, 'richtung' => BauhofNachricht::EIN,
                    'content_id' => null])->andWhere(['typ' => ['image', 'document', 'text']])
                    ->andWhere(['>', 'created_at', date('Y-m-d H:i:s', time() - 6 * 3600)])->all();
                if ($teile[0] === 'z' && $mangel) {
                    foreach ($offen as $o) {
                        $o->updateAttributes(['content_id' => $id]);
                    }
                    $m->updateAttributes(['aktuell_content_id' => $id]);
                    self::antwort('sendeText', $m->telefon, '📎 ' . (count($offen) === 1 ? 'Gespeichert' : count($offen) . ' Nachrichten gespeichert') . " bei Auftrag Nr. $id.");
                } else {
                    self::antwort('sendeText', $m->telefon, '📎 Gespeichert (ohne Auftrag). Der Bauhof sieht es trotzdem.');
                }
                return;
        }
    }

    private static function text(Mitarbeiter $m, BauhofNachricht $n): void
    {
        $t = mb_strtolower(trim((string)$n->text, " \t\n\r!.?"));
        $gruss = in_array($t, ['hilfe', '', 'help', 'info', 'hallo', 'hi', 'moin', 'servus', 'start'], true);
        $erste = !BauhofNachricht::find()->where(['mitarbeiter_id' => $m->id, 'richtung' => BauhofNachricht::EIN])
            ->andWhere(['<', 'id', $n->id])->exists();
        if ($erste || $gruss) {
            self::antwort('sendeText', $m->telefon, ($erste ? "Hallo {$m->name}! Diese Nummer ist jetzt mit dem Bauhof verbunden. 👷\n\n" : '') . self::HILFE);
            self::wartendeSenden($m);
            if ($gruss) {
                $n->updateAttributes(['gelesen_at' => date('Y-m-d H:i:s')]);
                return;
            }
        }
        if (in_array($t, ['aufträge', 'auftraege', 'auftrag', 'liste', 'meine aufträge'], true)) {
            $n->updateAttributes(['gelesen_at' => date('Y-m-d H:i:s')]);
            self::sendeListe($m);
            return;
        }
        $id = self::zuordnen($m, $n);
        if ($id) {
            $n->updateAttributes(['content_id' => $id]);
        }
        self::wartendeSenden($m);
        self::benachrichtigen($m, $n, $id ? "hat zu Auftrag Nr. $id geschrieben" : 'hat geschrieben');
    }

    private static function datei(Mitarbeiter $m, BauhofNachricht $n): void
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

        $id = self::zuordnen($m, $n);
        if ($id) {
            $n->updateAttributes(['content_id' => $id]);
            // Bei mehreren Fotos hintereinander nur einmal bestätigen
            $kurzVorher = BauhofNachricht::find()->where(['mitarbeiter_id' => $m->id, 'richtung' => BauhofNachricht::EIN,
                'content_id' => $id, 'typ' => ['image', 'document']])->andWhere(['<', 'id', $n->id])
                ->andWhere(['>', 'created_at', date('Y-m-d H:i:s', strtotime($n->created_at) - 120)])->exists();
            if (!$kurzVorher) {
                self::antwort('sendeText', $m->telefon, "📷 Gespeichert bei Auftrag Nr. $id.");
            }
            self::benachrichtigen($m, $n, "hat ein Foto zu Auftrag Nr. $id geschickt");
            return;
        }
        $offen = $m->getOffeneAuftraege();
        if (count($offen) > 1) {
            $zeilen = [];
            foreach (array_slice($offen, 0, 9) as $a) {
                if ($mg = self::mangelZu($a->content_id)) {
                    $zeilen['z:' . $a->content_id] = ["Nr. {$a->content_id} {$mg->kategorie}", $mg->getOrt()];
                }
            }
            $zeilen['n'] = ['Keinem Auftrag', 'Nur an den Bauhof'];
            $schonGefragt = BauhofNachricht::find()->where(['mitarbeiter_id' => $m->id, 'richtung' => BauhofNachricht::EIN, 'content_id' => null])
                ->andWhere(['typ' => ['image', 'document']])->andWhere(['<', 'id', $n->id])
                ->andWhere(['>', 'created_at', date('Y-m-d H:i:s', strtotime($n->created_at) - 120)])->exists();
            if (!$schonGefragt) {
                self::antwort('sendeListe', $m->telefon, 'Zu welchem Auftrag gehört das Foto?', 'Auftrag wählen', $zeilen);
            }
        } else {
            self::antwort('sendeText', $m->telefon, '📷 Gespeichert (ohne Auftrag). Der Bauhof sieht es trotzdem.');
        }
        self::benachrichtigen($m, $n, 'hat ein Foto geschickt');
    }

    /**
     * Auftrag einer eingehenden Nachricht: 1. Antwort auf eine unserer Nachrichten, 2. zuletzt angezeigter Auftrag
     * des Mitarbeiters (wenn noch offen und ihm zugewiesen), 3. sein einziger offener Auftrag.
     */
    private static function zuordnen(Mitarbeiter $m, BauhofNachricht $n): ?int
    {
        if ($n->bezug_wamid && ($bezug = BauhofNachricht::findOne(['wamid' => $n->bezug_wamid])) && $bezug->content_id) {
            return (int)$bezug->content_id;
        }
        $offen = $m->getOffeneAuftraege();
        $ids = array_map(fn(Auftrag $a) => (int)$a->content_id, $offen);
        if ($m->aktuell_content_id && in_array((int)$m->aktuell_content_id, $ids, true)) {
            return (int)$m->aktuell_content_id;
        }
        return count($ids) === 1 ? $ids[0] : null;
    }

    private static function sendeListe(Mitarbeiter $m): void
    {
        $offen = $m->getOffeneAuftraege();
        if ($offen === []) {
            self::antwort('sendeText', $m->telefon, 'Du hast gerade keine offenen Aufträge. 👍');
            return;
        }
        $zeilen = [];
        foreach (array_slice($offen, 0, 10) as $a) {
            if ($mg = self::mangelZu($a->content_id)) {
                $zeilen['d:' . $a->content_id] = ["Nr. {$a->content_id} {$mg->kategorie}", $mg->getOrt()];
            }
        }
        self::antwort('sendeListe', $m->telefon, 'Deine offenen Aufträge (' . count($offen) . '):', 'Auftrag wählen', $zeilen);
    }

    /** Vorgemerkte Chat-Nachrichten schicken, sobald das Fenster offen ist; @return int Anzahl */
    private static function wartendeSenden(Mitarbeiter $m): int
    {
        $wartend = BauhofNachricht::find()->where(['mitarbeiter_id' => $m->id, 'richtung' => BauhofNachricht::AUS, 'zustellung' => 'wartet'])
            ->orderBy('id')->all();
        foreach ($wartend as $w) {
            try {
                $w->wamid = Api::sendeText($m->telefon, self::chatText($w));
                $w->zustellung = 'gesendet';
            } catch (\Throwable $e) {
                $w->zustellung = 'fehler';
                $w->fehler = mb_substr(self::fehlertext($e), 0, 255);
            }
            $w->save(false);
        }
        return count($wartend);
    }

    // ------------------------------------------------------------------ Hilfsfunktionen

    /** Antwort an den Mitarbeiter; ein Sendefehler darf Zuordnung und Benachrichtigung nicht verhindern */
    private static function antwort(string $methode, ...$args): ?string
    {
        try {
            return Api::$methode(...$args);
        } catch (\Throwable $e) {
            Yii::warning("Bauhof-Antwort ($methode): " . $e->getMessage(), 'maengelmelder');
            return null;
        }
    }

    /** Chat-Text mit Absender und Auftragsbezug */
    private static function chatText(BauhofNachricht $n): string
    {
        $von = $n->user ? $n->user->displayName : 'Bauhof';
        return "💬 *$von*" . ($n->content_id ? " (Auftrag Nr. {$n->content_id})" : '') . ":\n" . $n->text;
    }

    private static function statusKnoepfe(int $id, string $aktuell): array
    {
        $k = [];
        if ($aktuell !== 'arbeit') {
            $k["s:$id:arbeit"] = '🔧 In Arbeit';
        }
        if ($aktuell !== 'erledigt') {
            $k["s:$id:erledigt"] = '✅ Erledigt';
        }
        $k["x:$id"] = 'Anderer Status';
        return $k;
    }

    /** Meldung, wenn sie diesem Mitarbeiter zugewiesen ist */
    private static function auftragDesMitarbeiters(Mitarbeiter $m, int $id): ?Mangel
    {
        $a = Auftrag::findOne($id);
        return $a && (int)$a->mitarbeiter_id === (int)$m->id ? self::mangelZu($id) : null;
    }

    private static function mangelZu(int $contentId): ?Mangel
    {
        $c = Content::findOne(['id' => $contentId, 'state' => Content::STATE_PUBLISHED]);
        return $c ? Mangel::fromContent($c) : null;
    }

    private static function neueAusgehende(Mitarbeiter $m, ?int $contentId, string $typ, ?string $text, ?User $user): BauhofNachricht
    {
        $n = new BauhofNachricht(['mitarbeiter_id' => $m->id, 'content_id' => $contentId, 'richtung' => BauhofNachricht::AUS,
            'typ' => $typ, 'text' => $text, 'user_id' => $user?->id, 'verarbeitet' => 1]);
        $n->save(false);
        return $n;
    }

    /** Interner Vermerk im Verlauf (Zuweisung, Statusänderung, Versandfehler) */
    public static function vermerk(int $contentId, ?int $mitarbeiterId, ?User $user, string $text, string $typ = 'system'): void
    {
        (new BauhofNachricht(['content_id' => $contentId, 'mitarbeiter_id' => $mitarbeiterId, 'richtung' => BauhofNachricht::INTERN,
            'typ' => $typ, 'text' => $text, 'user_id' => $user?->id, 'verarbeitet' => 1]))->save(false);
    }

    /** Konto, unter dem Änderungen per WhatsApp öffentlich erscheinen: wer zugewiesen hat, sonst Bauhof-Gruppe, sonst Admin */
    private static function bauhofKonto(int $contentId): User
    {
        $a = Auftrag::findOne($contentId);
        if ($a && $a->zugewiesen_by && ($u = User::findOne(['id' => $a->zugewiesen_by, 'status' => User::STATUS_ENABLED]))) {
            return $u;
        }
        return self::empfaenger()[0] ?? throw new \RuntimeException('Kein Bauhof- oder Admin-Konto gefunden');
    }

    /** Bauhofleitung: Mitglieder der Gruppe „Bauhof“, sonst System-Admins */
    public static function empfaenger(): array
    {
        $group = Group::findOne(['name' => self::module()->bauhofGruppe]);
        $users = $group ? User::find()->joinWith('groups')->where(['group.id' => $group->id, 'user.status' => User::STATUS_ENABLED])->all() : [];
        return $users ?: User::find()->joinWith('groups')->where(['group.is_admin_group' => 1, 'user.status' => User::STATUS_ENABLED])->all();
    }

    /** Glocke/Push an die Bauhofleitung, höchstens alle 5 Minuten je Mitarbeiter (Text: BauhofMeldung::text()) */
    private static function benachrichtigen(Mitarbeiter $m, BauhofNachricht $n, string $was = ''): void
    {
        if ($m->benachrichtigt_at && strtotime($m->benachrichtigt_at) > time() - 300) {
            return;
        }
        $m->updateAttributes(['benachrichtigt_at' => date('Y-m-d H:i:s')]);
        $users = self::empfaenger();
        $a = $n->content_id ? Auftrag::findOne($n->content_id) : null;
        if ($a && $a->zugewiesen_by && !in_array((int)$a->zugewiesen_by, array_map(fn($u) => (int)$u->id, $users), true)
            && ($u = User::findOne(['id' => $a->zugewiesen_by, 'status' => User::STATUS_ENABLED]))) {
            $users[] = $u;
        }
        if ($users) {
            BauhofMeldung::instance()->about($n)->sendBulk($users);
        }
    }

    private static function fehlertext(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '132001') || str_contains($msg, 'template name does not exist')) {
            return 'Die WhatsApp-Vorlage ist noch nicht genehmigt. Bis dahin klappt der Versand nur, wenn der Mitarbeiter in den letzten 24 Std. selbst an die Nummer geschrieben hat.';
        }
        if (str_contains($msg, '131047') || str_contains($msg, 're-engagement')) {
            return 'Der Mitarbeiter hat seit über 24 Std. nicht geschrieben – Versand nur per Vorlage möglich.';
        }
        if (str_contains($msg, '131026') || str_contains($msg, 'undeliverable')) {
            return 'Die Nummer ist nicht bei WhatsApp erreichbar.';
        }
        return $msg;
    }
}
