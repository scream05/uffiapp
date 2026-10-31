<?php

namespace humhub\modules\eilmeldung\components;

use humhub\modules\eilmeldung\models\Eilmeldung;
use humhub\modules\eilmeldung\Module;
use humhub\modules\eilmeldung\notifications\EilmeldungNeu;
use humhub\modules\user\models\User;
use Yii;

/** Push-Nachricht zu einer Eilmeldung an alle Empfänger (Module::$empfaenger), Zwischenspeicher leeren */
class Versand
{
    /** Import- und Systemkonten bekommen keine Push-Nachricht */
    private const OHNE = ['stadt-uffenheim', 'mitteilungsblatt', 'gastmeldung', 'facebook'];

    public static function module(): Module
    {
        return Yii::$app->getModule('eilmeldung');
    }

    public static function empfaenger(): \humhub\modules\user\components\ActiveQueryUser
    {
        $q = User::find()->active()->andWhere(['not in', 'user.username', self::OHNE]);
        $liste = self::module()->empfaenger;
        return $liste === null ? $q : $q->andWhere(['user.username' => $liste]);
    }

    /** @return int Anzahl Empfänger */
    public static function senden(Eilmeldung $e): int
    {
        $n = (int)self::empfaenger()->count();
        EilmeldungNeu::instance()->payload(['id' => $e->id, 'art' => $e->art, 'titel' => $e->titel])->sendBulk(self::empfaenger());
        $e->updateAttributes(['empfaenger' => $n]);
        Yii::info("Eilmeldung {$e->id} an $n Konten", 'eilmeldung');
        return $n;
    }

    /** Nach jeder Änderung: Hinweis auf der Startseite und Uffi sofort aktuell */
    public static function geaendert(): void
    {
        Yii::$app->cache->delete('eilmeldung-aktiv');
        Yii::$app->cache->delete('ki-assistent-context-aktuell');
    }

    /** Laufende Eilmeldungen, 1 Minute zwischengespeichert (Startseite für alle, auch Gäste) */
    public static function aktive(): array
    {
        return Yii::$app->cache->getOrSet('eilmeldung-aktiv', fn() => Eilmeldung::aktiv()->all(), 60);
    }

    /** Für Uffi: laufende Eilmeldungen als Text */
    public static function forAssistant(): string
    {
        $lines = [];
        foreach (Eilmeldung::aktiv()->all() as $e) {
            $lines[] = '- ' . self::module()->artName($e->art) . ': ' . $e->titel
                . ' (seit ' . date('d.m.Y, G:i', strtotime($e->created_at)) . ' Uhr, gilt bis ' . date('d.m.Y, G:i', strtotime($e->gueltig_bis)) . ' Uhr'
                . ($e->quelle === 'nina' ? ', amtliche Warnung über NINA' : '') . ')'
                . ($e->text ? "\n  " . mb_substr(trim(preg_replace('/\s+/u', ' ', $e->text)), 0, 500) : '')
                . ' – ' . $e->getUrl(true);
        }
        return "## Eilmeldungen für Uffenheim (Sperrungen, Wasser, Strom, Unwetter; Seite: " . \yii\helpers\Url::to(['/eilmeldung/index'], true) . ")\n"
            . ($lines ? implode("\n", $lines) : '- Derzeit keine Eilmeldungen.');
    }
}
