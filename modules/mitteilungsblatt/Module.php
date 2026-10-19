<?php

namespace humhub\modules\mitteilungsblatt;

use Yii;

/**
 * Mitteilungsblatt der Stadt Uffenheim: holt neue Ausgaben von uffenheim.de (Crontab: /app/yii mitteilungsblatt/abrufen),
 * speichert PDF, Titelbild und Text je Seite (Volltextsuche) und zeigt sie im Reader (pdf.js, modules/mitteilungsblatt/pdfjs).
 * Solange nicht freigegeben (/app/yii mitteilungsblatt/freigeben 1), sehen nur Admins Seite und Menüpunkt
 * und es gibt keine Beiträge im Stream.
 */
class Module extends \humhub\components\Module
{
    /** Seite der Stadt mit der Liste der Ausgaben */
    public string $quelle = 'https://www.uffenheim.de/rathaus-buergerservice/service/mitteilungsblatt';

    /** Space mit Menüpunkt und Beitrag zu jeder neuen Ausgabe, Verfasser wie beim Website-Import */
    public string $space = 'rathaus-uffenheim'; // URL des Space, bleibt beim Umbenennen gleich
    public string $konto = 'stadt-uffenheim';

    /** Ablage für PDF und Titelbild (humhub-data/mitteilungsblatt; nicht in der täglichen Sicherung, die nimmt nur config/modules/themes/uploads) */
    public string $ablage = '/data/mitteilungsblatt';

    /** So viele Ausgaben bleiben im Archiv (14-tägig → 26 im Jahr), ältere werden gelöscht; 0 = alle behalten */
    public int $behalten = 0;

    public function istFreigegeben(): bool
    {
        return (bool)$this->settings->get('freigegeben');
    }

    /** Seite „Bekanntmachungen“ freigegeben (/app/yii mitteilungsblatt/bekanntmachungen-freigeben 1)? Sonst nur Admins. */
    public function bekanntmachungenFrei(): bool
    {
        return (bool)$this->settings->get('bekanntmachungen_frei');
    }

    public function darfBekanntmachungenSehen(): bool
    {
        if (!$this->darfLesen()) {
            return false;
        }
        return $this->bekanntmachungenFrei() || (!Yii::$app->user->isGuest && Yii::$app->user->isAdmin());
    }

    /** Darf das aktuelle Konto das Mitteilungsblatt sehen? */
    public function darfLesen(): bool
    {
        if (Yii::$app->user->isGuest) {
            return $this->istFreigegeben();
        }
        return $this->istFreigegeben() || Yii::$app->user->isAdmin();
    }
}
