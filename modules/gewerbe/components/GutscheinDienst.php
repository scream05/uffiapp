<?php

namespace humhub\modules\gewerbe\components;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\Einloesung;
use humhub\modules\gewerbe\models\Gutschein;
use humhub\modules\gewerbe\Module;
use humhub\modules\gewerbe\notifications\GutscheinFuerAlle;
use humhub\modules\gewerbe\notifications\GutscheinNeu;
use humhub\modules\gewerbe\notifications\GutscheinPausiert;
use humhub\modules\gewerbe\permissions\ManageGewerbe;
use humhub\modules\user\components\PermissionManager;
use humhub\modules\user\models\User;
use Yii;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Gutscheine einlösen: Die Person scannt im Laden den Theken-QR des Betriebs (oder tippt dessen 4-stellige Nummer),
 * tippt „Jetzt einlösen“ und zeigt den Bestätigungsbildschirm (Live-Uhr, Kurzcode) dem Personal.
 */
class GutscheinDienst
{
    /** Für setup/gutschein-test: keine Glocken an echte Personen */
    public static bool $stumm = false;

    /** Theken-Token und -Nummer anlegen, falls noch keine da sind */
    public static function theke(Betrieb $betrieb): Betrieb
    {
        if ($betrieb->theke_token === null || $betrieb->theke_nummer === null) {
            self::thekeErneuern($betrieb);
        }
        return $betrieb;
    }

    /** Neuer Theken-QR (alter Aufsteller gilt danach nicht mehr) */
    public static function thekeErneuern(Betrieb $betrieb): void
    {
        $betrieb->updateAttributes([
            'theke_token' => Yii::$app->security->generateRandomString(24),
            'theke_nummer' => str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public static function betriebZuToken(string $token): ?Betrieb
    {
        return preg_match('/^[A-Za-z0-9_-]{24}$/', $token) ? Betrieb::findOne(['theke_token' => $token]) : null;
    }

    public static function thekeUrl(Betrieb $betrieb, bool $scheme = true): string
    {
        $space = Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space->createUrl('/gewerbe/gutschein/theke', ['t' => self::theke($betrieb)->theke_token], $scheme);
    }

    /** Nummer vom Aufsteller prüfen (für Handys ohne Kamera); höchstens 8 Fehlversuche je Person und Stunde */
    public static function nummerPruefen(Betrieb $betrieb, string $nummer, int $userId): ?string
    {
        $key = "gewerbe-nummer-fehler-$userId";
        $fehler = (int)Yii::$app->cache->get($key);
        if ($fehler >= 8) {
            return 'Zu viele falsche Nummern. Bitte scanne den QR-Code an der Kasse oder versuch es in einer Stunde noch einmal.';
        }
        $betrieb = self::theke($betrieb);
        if (!hash_equals((string)$betrieb->theke_nummer, preg_replace('/\D/', '', $nummer))) {
            Yii::$app->cache->set($key, $fehler + 1, 3600);
            return 'Die Nummer passt nicht. Sie steht auf dem Aufsteller an der Kasse unter dem QR-Code.';
        }
        return null;
    }

    /** null = jetzt einlösbar, sonst der Grund (Du-Form) */
    public static function grund(Gutschein $g, ?int $userId): ?string
    {
        if (($grund = self::grundAllgemein($g)) !== null) {
            return $grund;
        }
        if ($userId === null) {
            return 'Zum Einlösen musst du angemeldet sein.';
        }
        if (($ab = self::wiederAb($g, $userId)) !== null) {
            return $ab === 'nie' ? 'Du hast diesen Gutschein schon eingelöst.'
                : 'Du hast diesen Gutschein schon eingelöst. Wieder möglich ab ' . Yii::$app->formatter->asDatetime($ab, 'php:j.n. \\u\\m H:i') . ' Uhr.';
        }
        return null;
    }

    /** Wie grund(), aber ohne die Person: Zeitraum, Status, Menge, Öffnungszeiten */
    public static function grundAllgemein(Gutschein $g): ?string
    {
        $betrieb = $g->betrieb;
        if ($g->status === Gutschein::STATUS_BEENDET || $g->istAbgelaufen()) {
            return 'Dieser Gutschein ist beendet.';
        }
        if ($g->status === Gutschein::STATUS_PAUSIERT) {
            return 'Der Betrieb hat diesen Gutschein gerade pausiert.';
        }
        if ($g->gueltig_ab > date('Y-m-d')) {
            return 'Der Gutschein gilt erst ab ' . Yii::$app->formatter->asDate($g->gueltig_ab, 'medium') . '.';
        }
        if ($g->istAufgebraucht()) {
            return 'Alle Gutscheine sind schon eingelöst.';
        }
        if ($g->nur_geoeffnet && ($stand = $betrieb->getStand()) !== null && !$stand['offen']) {
            return 'Einlösen geht nur während der Öffnungszeiten (' . $stand['text'] . ').';
        }
        return null;
    }

    /** Gesperrt für diese Person? null = nein, 'nie' = einmal-Gutschein schon genutzt, sonst Zeitpunkt (Unix) */
    public static function wiederAb(Gutschein $g, int $userId): int|string|null
    {
        if ($g->pro_person === 'unbegrenzt') {
            return null;
        }
        $q = Einloesung::find()->where(['gutschein_id' => $g->id, 'user_id' => $userId, 'storniert_am' => null]);
        if ($g->pro_person === 'einmal') {
            return $q->exists() ? 'nie' : null;
        }
        $seit = match ($g->pro_person) {
            'tag' => strtotime('today'),
            'woche' => time() - 7 * 86400,
            default => time() - 30 * 86400,
        };
        $letzte = $q->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', $seit)])->max('created_at');
        if ($letzte === null) {
            return null;
        }
        return match ($g->pro_person) {
            'tag' => strtotime('tomorrow'),
            'woche' => strtotime($letzte) + 7 * 86400,
            default => strtotime($letzte) + 30 * 86400,
        };
    }

    /**
     * Einlösen (mit Zeilensperre gegen doppeltes Tippen und das letzte Stück eines begrenzten Gutscheins).
     * @return Einloesung|string die Einlösung oder der Grund, warum es nicht geht
     */
    public static function einloesen(Gutschein $g, int $userId): Einloesung|string
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('gewerbe');
        $tx = Yii::$app->db->beginTransaction();
        try {
            $g = Gutschein::findBySql('SELECT * FROM ' . Gutschein::tableName() . ' WHERE id = :id FOR UPDATE', [':id' => $g->id])->one();
            if (($grund = self::grund($g, $userId)) !== null) {
                $tx->rollBack();
                return $grund;
            }
            $e = new Einloesung([
                'gutschein_id' => $g->id,
                'betrieb_id' => $g->betrieb_id,
                'user_id' => $userId,
                'code' => Einloesung::neuerCode(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $e->save(false);
            $g->updateCounters(['eingeloest' => 1]);
            $tx->commit();
        } catch (\Throwable $ex) {
            $tx->rollBack();
            throw $ex;
        }
        // Auffällig viele Einlösungen in kurzer Zeit (z. B. Theken-QR im Netz geteilt): pausieren und Bescheid geben
        $kurz = Einloesung::find()->where(['betrieb_id' => $g->betrieb_id, 'storniert_am' => null])
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', time() - 60 * $module->sperreMinuten)])->count();
        if ($kurz > $module->sperreAnzahl) {
            foreach (Gutschein::find()->where(['betrieb_id' => $g->betrieb_id, 'status' => Gutschein::STATUS_AKTIV])->all() as $aktiv) {
                self::pausieren($aktiv, "Automatisch pausiert: mehr als {$module->sperreAnzahl} Einlösungen in {$module->sperreMinuten} Minuten. "
                    . 'Falls der Theken-QR weitergegeben wurde, erneuere ihn bitte, bevor du den Gutschein wieder startest.', true);
            }
        }
        return $e;
    }

    /** Rücknahme durch den Betrieb (z. B. aus Versehen getippt), nur kurz nach dem Einlösen */
    public static function stornieren(Einloesung $e): bool
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('gewerbe');
        if ($e->storniert_am !== null || $e->alter() > 60 * $module->stornoMinuten) {
            return false;
        }
        $e->updateAttributes(['storniert_am' => date('Y-m-d H:i:s')]);
        Gutschein::updateAllCounters(['eingeloest' => -1], ['and', ['id' => $e->gutschein_id], ['>', 'eingeloest', 0]]);
        return true;
    }

    public static function pausieren(Gutschein $g, ?string $grund, bool $benachrichtigen = false): void
    {
        $g->updateAttributes(['status' => Gutschein::STATUS_PAUSIERT, 'pause_grund' => $grund ? mb_substr($grund, 0, 255) : null]);
        if ($benachrichtigen && !self::$stumm) {
            $empfaenger = [];
            foreach (array_merge($g->betrieb->getConfirmedUsers(), self::pruefer()) as $u) {
                $empfaenger[$u->id] = $u;
            }
            GutscheinPausiert::instance()->about($g)->sendBulk(array_values($empfaenger));
        }
    }

    /**
     * Neuer Gutschein: Glocke an Admins / „Gewerbe verwalten“ (zur Kenntnis), alle übrigen Konten
     * bekommen „Neue Gutscheine“ (Glocke + Push, je Person abschaltbar; per Queue)
     */
    public static function neu(Gutschein $g): void
    {
        if (self::$stumm) {
            return;
        }
        $von = $g->ersteller;
        $pruefer = array_values(array_filter(self::pruefer(), fn(User $u) => $u->id !== $g->created_by));
        $n = GutscheinNeu::instance()->about($g);
        if ($von) {
            $n->from($von);
        }
        $n->sendBulk($pruefer);

        $ohne = array_merge(array_map(fn(User $u) => $u->id, self::pruefer()), [(int)$g->created_by]);
        GutscheinFuerAlle::instance()->about($g)->sendBulk(
            User::find()->active()->andWhere(['not in', 'user.id', $ohne])->andWhere(['!=', 'user.username', 'gastmeldung'])
        );
    }

    /** @return User[] */
    private static function pruefer(): array
    {
        $liste = [];
        foreach (User::find()->active()->all() as $user) {
            if ($user->isSystemAdmin() || (new PermissionManager(['subject' => $user]))->can(ManageGewerbe::class)) {
                $liste[] = $user;
            }
        }
        return $liste;
    }

    /** QR-Code als SVG (schwarz auf weiß, mit Ruhezone) */
    public static function qrSvg(string $text, int $groesse = 320): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($groesse, 2), new SvgImageBackEnd()));
        return $writer->writeString($text, 'UTF-8');
    }
}
