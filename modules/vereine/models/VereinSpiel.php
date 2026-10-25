<?php

namespace humhub\modules\vereine\models;

use humhub\components\ActiveRecord;
use Yii;

/**
 * Spiel einer Mannschaft laut BFV bzw. einer anderen Spielquelle (components/Bfv.php, components/Spielquellen.php).
 * Datum und Uhrzeit sind Ortszeit (Europe/Berlin).
 *
 * @property int $id
 * @property string $mannschaft_id
 * @property string $spiel_id matchId beim BFV bzw. Kennung des Spiels bei der Quelle
 * @property string $datum
 * @property string|null $uhrzeit
 * @property string|null $wettbewerb
 * @property string|null $wettbewerb_typ
 * @property string $heim
 * @property string $gast
 * @property bool|int $heim_eigen
 * @property string|null $ergebnis
 * @property string|null $ort
 * @property string|null $url Spiel bei der Quelle (leer = BFV-Spielseite)
 * @property string|null $details Satzergebnisse (Volleyball), z. B. „25:20, 25:7, 25:20“
 * @property string $aktualisiert_am
 * @property string|null $ticker JSON [{m,s,t,k,art}] aus dem Liveticker, neueste zuerst
 * @property string|null $ticker_status live | beendet | keiner
 * @property string|null $ticker_stand Spielstand laut Liveticker
 * @property int|null $ticker_minute
 * @property string|null $ticker_am
 */
class VereinSpiel extends ActiveRecord
{
    /** Abkürzungen des BFV statt eines Ergebnisses */
    public const ZUSTAND = [
        'Abse.' => 'abgesetzt',
        'Ausf.' => 'ausgefallen',
        'Abbr.' => 'abgebrochen',
        'Annu.' => 'annulliert',
        'Nichtantr.' => 'Nichtantritt',
        'Wert.' => 'gewertet',
    ];

    public static function tableName()
    {
        return 'verein_spiel';
    }

    public function getMannschaft()
    {
        return $this->hasOne(VereinMannschaft::class, ['id' => 'mannschaft_id']);
    }

    public function getUrl(): string
    {
        return $this->url ?: 'https://www.bfv.de/ergebnisse/spiel/-/' . rawurlencode($this->spiel_id);
    }

    /** bfv, dbb, bvv oder bbv (aus der Kennung der Mannschaft, ohne Datenbankabfrage) */
    public function getQuelle(): string
    {
        return VereinMannschaft::quelleVon($this->mannschaft_id);
    }

    /**
     * Heimspiel? null, wenn die Quelle das nicht sagt: beim Volleyball heißt „zuerst genannt“ nicht „zu Hause“,
     * Spieltage richtet oft ein dritter Verein aus.
     */
    public function getHeimspiel(): ?bool
    {
        return $this->getQuelle() === 'bvv' ? null : (bool)$this->heim_eigen;
    }

    public function getGegner(): string
    {
        return $this->heim_eigen ? $this->gast : $this->heim;
    }

    /** Anstoß als Zeitstempel (ohne Uhrzeit: Tagesbeginn) */
    public function getZeitpunkt(): int
    {
        return (new \DateTime($this->datum . ' ' . ($this->uhrzeit ?: '00:00'), new \DateTimeZone('Europe/Berlin')))->getTimestamp();
    }

    /** [Tore Heim, Tore Gast] oder null, wenn (noch) kein Ergebnis */
    public function getTore(): ?array
    {
        return preg_match('/^(\d+)\s*:\s*(\d+)/', (string)$this->ergebnis, $m) ? [(int)$m[1], (int)$m[2]] : null;
    }

    /** s = gewonnen, u = unentschieden, n = verloren (aus Sicht der eigenen Mannschaft) */
    public function getWertung(): ?string
    {
        $t = $this->getTore();
        if ($t === null && $this->ticker_status === 'beendet' && preg_match('/^(\d+):(\d+)$/', (string)$this->ticker_stand, $m)) {
            $t = [(int)$m[1], (int)$m[2]];
        }
        if ($t === null) {
            return null;
        }
        [$eigen, $fremd] = $this->heim_eigen ? $t : [$t[1], $t[0]];
        return $eigen > $fremd ? 's' : ($eigen === $fremd ? 'u' : 'n');
    }

    /** Text statt eines Ergebnisses (abgesetzt, …), null bei Ergebnis oder ohne Angabe */
    public function getZustand(): ?string
    {
        $e = trim((string)$this->ergebnis);
        if ($e === '' || $this->getTore() !== null) {
            return null;
        }
        return isset(self::ZUSTAND[$e]) ? Yii::t('VereineModule.base', self::ZUSTAND[$e]) : $e;
    }

    public function istVorbei(?int $jetzt = null): bool
    {
        if ($this->ticker_status === 'beendet') {
            return true;
        }
        if ($this->ticker_status === 'live') {
            return false;
        }
        // Sonst gilt ein Spiel zwei Stunden nach Anstoß als vorbei (ohne Uhrzeit: am Folgetag)
        $jetzt ??= time();
        return $this->uhrzeit ? $this->getZeitpunkt() + 7200 < $jetzt : $this->datum < date('Y-m-d', $jetzt);
    }

    /** Angepfiffen und noch nicht vorbei (abgesetzte Spiele nie; nur Fußball, die anderen Quellen haben keinen Liveticker) */
    public function laeuft(?int $jetzt = null): bool
    {
        if ($this->getQuelle() !== 'bfv') {
            return false;
        }
        $jetzt ??= time();
        return $this->getZustand() === null && $this->uhrzeit !== null && $this->getZeitpunkt() <= $jetzt && !$this->istVorbei($jetzt);
    }

    /** Einträge des Livetickers in zeitlicher Reihenfolge (älteste zuerst) */
    public function getTickerList(): array
    {
        return array_reverse(json_decode((string)$this->ticker, true) ?: []);
    }

    /** Ergebnis bzw. laufender Spielstand für die Anzeige */
    public function getAnzeigeStand(): ?string
    {
        if ($this->getTore() !== null) {
            return $this->ergebnis;
        }
        return $this->ticker_stand && preg_match('/^\d+:\d+$/', $this->ticker_stand) ? $this->ticker_stand : null;
    }
}
