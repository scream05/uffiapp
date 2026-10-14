<?php

namespace humhub\modules\whatsapp\components;

use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\whatsapp\events\EmpfangEvent;
use humhub\modules\whatsapp\jobs\VerarbeitenJob;
use humhub\modules\whatsapp\models\Entwurf;
use humhub\modules\whatsapp\models\Nachricht;
use humhub\modules\whatsapp\Module;
use humhub\modules\whatsapp\notifications\NeueEntwuerfe;
use Yii;
use yii\base\Event;

/**
 * Webhook-Inhalt speichern, Einreichungen (alle neuen Nachrichten eines Absenders, die zusammen kamen) auswerten,
 * Entwürfe anlegen, Absender und Admins informieren, Altes löschen.
 */
class Verarbeitung
{
    /** Hinweis an Absender, die etwas anderes als Bild, PDF oder Text schicken oder nur „Hallo“ schreiben */
    public const HILFE = "Hallo! Das ist die Termin-Nummer von rundumuffni.de 🗓️\n\n"
        . "Leite uns einfach den Beitrag mit der Veranstaltung aus der WhatsApp-Community weiter – am besten das Plakat "
        . "als Bild, gern mit Text. Wir lesen Datum, Uhrzeit und Ort aus und stellen den Termin nach kurzer Prüfung in den "
        . "Kalender auf https://community.rundumuffni.de.\n\nBilder und Texte, die du schickst, werden dafür von einer KI "
        . "ausgewertet. Datenschutz: https://community.rundumuffni.de/legal/page/view?pageKey=privacy";

    /** Vor dem Speichern im Termin-Eingang; wer die Nachricht übernimmt, setzt $handled (z. B. Bauhof-Mitarbeiter) */
    public const EVENT_EMPFANGEN = 'empfangen';
    /** Zustellstatus (sent/delivered/read/failed) unserer gesendeten Nachrichten */
    public const EVENT_STATUS = 'status';

    private static function module(): Module
    {
        return Yii::$app->getModule('whatsapp');
    }

    /**
     * Inhalt eines Webhook-Aufrufs speichern (nur Nachrichten an unsere Nummer; Zustellstatus wird ignoriert).
     * @return int Anzahl neu gespeicherter Nachrichten
     */
    public static function empfangen(array $payload): int
    {
        $phoneId = self::module()->zugang('phone_number_id');
        $neu = 0;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if (($change['field'] ?? '') !== 'messages' || ($value['metadata']['phone_number_id'] ?? null) !== $phoneId) {
                    continue;
                }
                $namen = [];
                foreach ($value['contacts'] ?? [] as $c) {
                    $namen[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? null;
                }
                foreach ($value['statuses'] ?? [] as $st) {
                    Event::trigger(self::class, self::EVENT_STATUS, new EmpfangEvent(['daten' => $st]));
                }
                foreach ($value['messages'] ?? [] as $m) {
                    if (empty($m['id']) || empty($m['from']) || Nachricht::find()->where(['wamid' => $m['id']])->exists()) {
                        continue;
                    }
                    $ev = new EmpfangEvent(['daten' => $m, 'profilname' => $namen[$m['from']] ?? null]);
                    Event::trigger(self::class, self::EVENT_EMPFANGEN, $ev);
                    if ($ev->handled) {
                        continue;
                    }
                    $typ = (string)($m['type'] ?? 'unbekannt');
                    $media = $m[$typ] ?? [];
                    $n = new Nachricht([
                        'wamid' => $m['id'],
                        'wa_id' => preg_replace('/\D/', '', $m['from']),
                        'absender_name' => isset($namen[$m['from']]) ? mb_substr($namen[$m['from']], 0, 150) : null,
                        'typ' => mb_substr($typ, 0, 20),
                        'text' => match ($typ) {
                            'text' => $m['text']['body'] ?? null,
                            'image', 'document' => $media['caption'] ?? null,
                            default => null,
                        },
                        'media_id' => in_array($typ, ['image', 'document'], true) ? ($media['id'] ?? null) : null,
                        'mime' => $media['mime_type'] ?? null,
                        'weitergeleitet' => !empty($m['context']['forwarded']) || !empty($m['context']['frequently_forwarded']) ? 1 : 0,
                        'empfangen_at' => date('Y-m-d H:i:s', (int)($m['timestamp'] ?? time())),
                        'status' => Nachricht::STATUS_NEU,
                    ]);
                    if ($n->save()) {
                        $neu++;
                    }
                }
            }
        }
        if ($neu > 0) {
            // Kurz warten, bis zusammen weitergeleitete Nachrichten (Bild, Text) alle da sind
            Yii::$app->queue->delay(self::module()->sammelSekunden + 5)->push(new VerarbeitenJob());
        }
        return $neu;
    }

    /**
     * Alle fälligen Einreichungen auswerten (Hintergrundjob nach dem Webhook, zusätzlich per Crontab als Absicherung).
     * @param callable|null $log function(string $zeile)
     */
    public static function lauf(?callable $log = null): void
    {
        $log ??= fn($s) => str_starts_with($s, '!') ? Yii::warning($s, 'whatsapp') : Yii::info($s, 'whatsapp');
        if (!Yii::$app->mutex->acquire('whatsapp-verarbeiten', 0)) {
            return;
        }
        try {
            self::medienLaden($log);
            $grenze = date('Y-m-d H:i:s', time() - self::module()->sammelSekunden);
            $absender = Nachricht::find()->select('wa_id')->distinct()->where(['status' => Nachricht::STATUS_NEU])
                ->andWhere(['not', ['wa_id' => null]])->column();
            foreach ($absender as $waId) {
                $nachrichten = Nachricht::find()->where(['status' => Nachricht::STATUS_NEU, 'wa_id' => $waId])
                    ->orderBy(['empfangen_at' => SORT_ASC, 'id' => SORT_ASC])->all();
                if (end($nachrichten)->empfangen_at > $grenze) {
                    continue; // es kommt vielleicht noch etwas
                }
                foreach (self::aufteilen($nachrichten) as $teil) {
                    self::einreichung($teil, $log);
                }
            }
            self::aufraeumen($log);
        } finally {
            Yii::$app->mutex->release('whatsapp-verarbeiten');
        }
    }

    /**
     * Jedes Bild/PDF ist eine eigene Einreichung. Textnachrichten gehören zum Bild davor (weitergeleiteter Beitrag
     * aus Bild + Text); Text vor dem ersten Bild zum ersten Bild; Text ohne Bild bildet eine Einreichung für sich.
     * @param Nachricht[] $nachrichten in Eingangsreihenfolge
     * @return Nachricht[][]
     */
    private static function aufteilen(array $nachrichten): array
    {
        $teile = [];
        $vorab = [];
        foreach ($nachrichten as $n) {
            if ($n->media_id !== null) {
                $teile[] = [...$vorab, $n];
                $vorab = [];
            } elseif ($teile !== []) {
                $teile[count($teile) - 1][] = $n;
            } else {
                $vorab[] = $n;
            }
        }
        if ($vorab !== []) {
            $teile[] = $vorab;
        }
        return $teile;
    }

    /** Bilder/PDFs herunterladen (Medien-IDs bleiben bei Meta 30 Tage gültig) */
    private static function medienLaden(callable $log): void
    {
        foreach (Nachricht::find()->where(['status' => Nachricht::STATUS_NEU, 'datei' => null])
                     ->andWhere(['not', ['media_id' => null]])->all() as $n) {
            try {
                [$data, $mime] = Api::medium($n->media_id);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: $mime;
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif', 'application/pdf'], true)) {
                    $n->updateAttributes(['status' => Nachricht::STATUS_IGNORIERT, 'mime' => $mime, 'ergebnis' => 'Dateityp wird nicht ausgewertet']);
                    continue;
                }
                $datei = sha1($n->wamid) . ($mime === 'application/pdf' ? '.pdf' : '.' . (explode('/', $mime)[1] ?: 'jpg'));
                file_put_contents(Nachricht::dir() . '/' . $datei, $data);
                $n->updateAttributes(['datei' => $datei, 'mime' => $mime]);
            } catch (\Throwable $e) {
                $log("! Medium von Nachricht {$n->id}: {$e->getMessage()}");
                // Nach einer Stunde aufgeben, damit der Rest der Einreichung trotzdem ausgewertet wird
                if (strtotime($n->empfangen_at) < time() - 3600) {
                    $n->updateAttributes(['status' => Nachricht::STATUS_FEHLER, 'fehler' => mb_substr($e->getMessage(), 0, 255)]);
                }
            }
        }
    }

    /** @param Nachricht[] $nachrichten */
    private static function einreichung(array $nachrichten, callable $log): void
    {
        $module = self::module();
        $first = $nachrichten[0];
        $gruppe = date('Ymd-His') . '-' . substr(sha1($first->wamid), 0, 8);
        foreach ($nachrichten as $n) {
            if ($n->media_id !== null && $n->datei === null) {
                return; // Download noch nicht gelungen, wird im nächsten Lauf wiederholt
            }
        }
        $nutzbar = array_filter($nachrichten, fn(Nachricht $n) => in_array($n->typ, Nachricht::TYPEN, true));
        $text = trim(implode("\n\n", array_filter(array_map(fn(Nachricht $n) => $n->text, $nutzbar))));
        $medien = array_values(array_map(fn(Nachricht $n) => [$n->getPfad(), $n->mime],
            array_filter($nutzbar, fn(Nachricht $n) => $n->datei !== null)));

        $markieren = function (string $status, ?string $ergebnis) use ($nachrichten, $gruppe) {
            foreach ($nachrichten as $n) {
                $n->updateAttributes(['status' => $n->status === Nachricht::STATUS_NEU ? $status : $n->status,
                    'gruppe' => $gruppe, 'ergebnis' => $ergebnis !== null ? mb_substr($ergebnis, 0, 500) : null]);
            }
        };

        // Nur „Hallo“, Sticker, Sprachnachricht o. Ä.: Hilfe statt KI-Auswertung
        if ($medien === [] && (mb_strlen($text) < 40 || !preg_match('/\d/', $text))) {
            $markieren(Nachricht::STATUS_IGNORIERT, 'Kein Plakat und kein Text mit Datum');
            self::antworten($first->wa_id, self::HILFE, $log);
            $log("· Gruppe {$first->gruppe}: kein Plakat/Datum, Hilfe gesendet");
            return;
        }
        [$anzahl] = $module->getUsage();
        if ($anzahl >= $module->tagesLimit) {
            $log("! Tageslimit ({$module->tagesLimit}) erreicht, Einreichung wartet bis morgen");
            return;
        }
        $space = $module->getCalendarSpace();
        $kategorien = $space ? array_map(fn($t) => $t->name, Entwurf::typen($space)) : [];
        try {
            [$termine, $keinTermin, $in, $out] = Auslesen::termine($text, $medien, $kategorien);
            $module->addUsage($in, $out);
        } catch (\Throwable $e) {
            $log("! Auswertung Gruppe {$first->gruppe}: {$e->getMessage()}");
            // Bei Ausfall der KI später erneut versuchen; nach 6 Stunden als Fehler im Eingang zeigen
            if (strtotime($first->empfangen_at) < time() - 6 * 3600) {
                $markieren(Nachricht::STATUS_FEHLER, 'Auswertung fehlgeschlagen: ' . $e->getMessage());
            }
            return;
        }

        $entwuerfe = [];
        foreach ($termine as $t) {
            $e = new Entwurf($t + [
                'gruppe' => $gruppe,
                'wa_id' => $first->wa_id,
                'absender_name' => $first->absender_name,
                'status' => Entwurf::STATUS_OFFEN,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($e->save(false)) {
                $entwuerfe[] = $e;
            }
        }
        $markieren(Nachricht::STATUS_VERARBEITET, $entwuerfe ? count($entwuerfe) . ' Termin(e) erkannt' : 'Kein Termin: ' . ($keinTermin ?: 'nicht erkannt'));
        $log("+ Gruppe {$first->gruppe}: " . count($medien) . ' Datei(en), ' . count($entwuerfe) . ' Termin(e)' . ($entwuerfe ? '' : " – $keinTermin"));

        if ($entwuerfe) {
            $liste = implode("\n", array_map(fn(Entwurf $e) => '• ' . $e->titel . ' – ' . self::wann($e), $entwuerfe));
            self::antworten($first->wa_id, "Danke! Erkannt:\n$liste\n\nWir prüfen kurz und stellen "
                . (count($entwuerfe) > 1 ? 'die Termine' : 'den Termin') . " dann in den Kalender von rundumuffni.de. "
                . "Du bekommst eine Nachricht, sobald es so weit ist.", $log);
            self::benachrichtigen($entwuerfe[0], $space);
        } else {
            self::antworten($first->wa_id, "Danke! Darin konnten wir leider keine kommende Veranstaltung mit Datum erkennen"
                . ($keinTermin ? " ($keinTermin)" : '') . ". Wir schauen es uns trotzdem an. Am besten das Plakat als Bild schicken.", $log);
            self::benachrichtigen(null, $space, $gruppe);
        }
    }

    /** „Sa, 18.10.2026, 19:00 Uhr“ */
    public static function wann(Entwurf $e): string
    {
        $tage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $ts = strtotime($e->start_datum);
        $s = $tage[(int)date('w', $ts)] . ', ' . date('d.m.Y', $ts);
        if ($e->ende_datum) {
            $s .= ' bis ' . date('d.m.Y', strtotime($e->ende_datum));
        }
        return $s . ($e->start_zeit && !$e->ganztaegig ? ", {$e->start_zeit} Uhr" : '');
    }

    /** Antwort nur innerhalb von 24 Std. nach der letzten Nachricht dieser Person (sonst bräuchte es eine Vorlage) */
    public static function antworten(?string $waId, string $text, ?callable $log = null): bool
    {
        if (!$waId) {
            return false;
        }
        $letzte = Nachricht::find()->where(['wa_id' => $waId])->max('empfangen_at');
        if (!$letzte || strtotime($letzte) < time() - 23 * 3600) {
            return false;
        }
        try {
            Api::sendeText($waId, $text);
            return true;
        } catch (\Throwable $e) {
            ($log ?? fn($s) => Yii::warning($s, 'whatsapp'))("! Antwort an …" . substr($waId, -3) . ": {$e->getMessage()}");
            return false;
        }
    }

    /** System-Admins und Admins des Space „Veranstaltungen“ */
    private static function benachrichtigen(?Entwurf $entwurf, ?Space $space, ?string $gruppe = null): void
    {
        $ids = User::find()->select('user.id')->joinWith('groups')->where(['group.is_admin_group' => 1])
            ->andWhere(['user.status' => User::STATUS_ENABLED])->column();
        if ($space) {
            $ids = array_merge($ids, Membership::find()->select('user_id')
                ->where(['space_id' => $space->id, 'group_id' => Space::USERGROUP_ADMIN])->column());
        }
        $users = User::find()->where(['id' => array_unique($ids), 'status' => User::STATUS_ENABLED])->all();
        if ($users === []) {
            return;
        }
        // Quelle: erster erkannter Termin, sonst die Nachricht (Hinweis „ohne erkannten Termin“)
        $source = $entwurf ?? ($gruppe ? Nachricht::findOne(['gruppe' => $gruppe]) : null);
        if ($source) {
            NeueEntwuerfe::instance()->about($source)->sendBulk($users);
        }
    }

    /** Rohnachrichten, Dateien und Telefonnummern nach Ablauf der Frist löschen */
    private static function aufraeumen(callable $log): void
    {
        $frist = date('Y-m-d H:i:s', time() - self::module()->aufbewahrungTage * 86400);
        $offen = Entwurf::find()->select('gruppe')->where(['status' => Entwurf::STATUS_OFFEN])->column();
        // Auch nie verarbeitete Nachrichten (Status „neu“) fallen nach der Frist weg – Telefonnummern nicht länger als 30 Tage
        $alt = Nachricht::find()->where(['<', 'empfangen_at', $frist])
            ->andWhere(['or', ['gruppe' => null], ['not in', 'gruppe', $offen ?: ['']]])->all();
        foreach ($alt as $n) {
            $n->delete();
        }
        $n = Entwurf::updateAll(['wa_id' => null, 'absender_name' => null],
            ['and', ['<', 'created_at', $frist], ['not', ['status' => Entwurf::STATUS_OFFEN]], ['not', ['wa_id' => null]]]);
        Entwurf::deleteAll(['and', ['<', 'created_at', $frist], ['status' => Entwurf::STATUS_ABGELEHNT]]);
        if ($alt || $n) {
            $log('- ' . count($alt) . " alte Nachrichten gelöscht, $n Entwürfe anonymisiert");
        }
    }
}
