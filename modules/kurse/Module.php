<?php

namespace humhub\modules\kurse;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\models\KursVerein;
use humhub\modules\space\models\Space;
use humhub\modules\vereine\models\Verein;
use Yii;

/**
 * Kursverwaltung für Vereine (Beta: Schwimmkurse der SBU). Läuft im Space des Vereinsverzeichnisses.
 *
 * Wer darf was:
 * - Admins schalten einzelne Vereine für das Kursmodul frei (Tabelle kurs_verein).
 * - Bestätigte Verwalter eines freigeschalteten Vereins sind dessen Kursverwalter.
 * - Eltern melden sich ohne Konto an (E-Mail-Bestätigung, danach persönlicher Link mit Token).
 *
 * Solange die Einstellung „oeffentlich“ aus ist (Beta), sehen nur Admins und Kursverwalter die Kursseiten;
 * die Links aus den Mails (Bestätigen, Status, Abmelden) funktionieren immer.
 */
class Module extends ContentContainerModule
{
    /** Kinderdaten so viele Tage nach Kursende anonymisieren */
    public int $loeschenNachTagen = 183;
    /** Nicht bestätigte Anmeldungen nach so vielen Stunden löschen */
    public int $unbestaetigtStunden = 48;
    /** Erinnerungen nur zwischen diesen Stunden verschicken */
    public int $mailAbStunde = 8;
    public int $mailBisStunde = 20;

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentContainerName($container)
    {
        return 'Kurse';
    }

    public function getContentContainerDescription($container)
    {
        return 'Kurse freigeschalteter Vereine mit Online-Anmeldung, Warteliste und Bezahlstatus.';
    }

    /** Space, in dem die Kurse laufen (derselbe wie das Vereinsverzeichnis) */
    public function getSpace(): ?Space
    {
        $vereine = Yii::$app->getModule('vereine');
        return $vereine ? $vereine->getDirectorySpace() : null;
    }

    public function isOeffentlich(): bool
    {
        return (bool)$this->settings->get('oeffentlich', false);
    }

    public static function isAdmin(): bool
    {
        return !Yii::$app->user->isGuest && Yii::$app->user->isAdmin();
    }

    /** Vereine, deren Kurse der aktuelle Nutzer verwalten darf (Admins: alle freigeschalteten) @return Verein[] */
    public static function eigeneVereine(): array
    {
        if (Yii::$app->user->isGuest) {
            return [];
        }
        $q = Verein::find()->innerJoin('kurs_verein kv', 'kv.verein_id = verein.id')->orderBy(['verein.name' => SORT_ASC]);
        if (!self::isAdmin()) {
            $q->innerJoin('verein_verwalter vv', 'vv.verein_id = verein.id')
                ->andWhere(['vv.user_id' => Yii::$app->user->id, 'vv.status' => 'bestaetigt']);
        }
        return $q->all();
    }

    public static function istFreigeschaltet(int $vereinId): bool
    {
        return KursVerein::find()->where(['verein_id' => $vereinId])->exists();
    }

    /** Darf der aktuelle Nutzer die Kurse dieses Vereins verwalten? */
    public static function kannVerwalten(int $vereinId): bool
    {
        if (Yii::$app->user->isGuest || !self::istFreigeschaltet($vereinId)) {
            return false;
        }
        return self::isAdmin() || \humhub\modules\vereine\models\VereinVerwalter::find()
                ->where(['verein_id' => $vereinId, 'user_id' => Yii::$app->user->id, 'status' => 'bestaetigt'])->exists();
    }

    /** Sieht der aktuelle Nutzer die Kursseiten (Liste, Kursseite, Anmeldeformular)? */
    public function kannSehen(): bool
    {
        return $this->isOeffentlich() || self::isAdmin() || self::eigeneVereine() !== [];
    }

    /** Kurse, die in der Übersicht erscheinen: offen, nicht vorbei, Verein freigeschaltet @return Kurs[] */
    public static function sichtbareKurse(?int $vereinId = null): array
    {
        $q = Kurs::find()->innerJoin('kurs_verein kv', 'kv.verein_id = kurs.verein_id')
            ->where(['kurs.status' => [Kurs::STATUS_OFFEN, Kurs::STATUS_ABGESAGT]])
            ->andWhere(['>=', 'COALESCE(kurs.ende, kurs.beginn)', date('Y-m-d')])
            ->orderBy(['kurs.beginn' => SORT_ASC, 'kurs.zeit_von' => SORT_ASC]);
        if ($vereinId !== null) {
            $q->andWhere(['kurs.verein_id' => $vereinId]);
        }
        return $q->all();
    }
}
