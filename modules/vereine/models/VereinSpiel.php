<?php

namespace humhub\modules\vereine\models;

use humhub\components\ActiveRecord;
use Yii;

/**
 * Spiel einer Mannschaft laut BFV (abgerufen von components/Bfv.php). Datum und Uhrzeit sind Ortszeit (Europe/Berlin).
 *
 * @property int $id
 * @property string $mannschaft_id
 * @property string $spiel_id matchId beim BFV
 * @property string $datum
 * @property string|null $uhrzeit
 * @property string|null $wettbewerb
 * @property string|null $wettbewerb_typ
 * @property string $heim
 * @property string $gast
 * @property bool|int $heim_eigen
 * @property string|null $ergebnis
 * @property string|null $ort
 * @property string $aktualisiert_am
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
        return 'https://www.bfv.de/ergebnisse/spiel/-/' . rawurlencode($this->spiel_id);
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
        if (($t = $this->getTore()) === null) {
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
        // Ein Spiel gilt zwei Stunden nach Anstoß als vorbei (ohne Uhrzeit: am Folgetag)
        $jetzt ??= time();
        return $this->uhrzeit ? $this->getZeitpunkt() + 7200 < $jetzt : $this->datum < date('Y-m-d', $jetzt);
    }
}
