<?php

namespace humhub\modules\neuigkeiten;

use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

/**
 * Neuigkeiten: Einträge in data/eintraege.php (deutsch, im Repo gepflegt), Übersetzungen in data/uebersetzungen.json
 * (erzeugt mit `scripts/run-setup.sh neuigkeiten-uebersetzen`). Seite /neuigkeiten/index für alle, Popup einmalig
 * für Angemeldete; gesehene Einträge merkt sich die Benutzereinstellung „gesehen“ (Liste der IDs).
 */
class Module extends \humhub\components\Module
{
    /** Popup nur für Einträge, die höchstens so alt sind */
    public int $popupTage = 30;

    /** Höchstens so viele Einträge im Popup (die neuesten) */
    public int $popupMax = 3;

    public const ARTEN = ['neu' => ['Neu', 'star'], 'besser' => ['Verbessert', 'magic'], 'behoben' => ['Behoben', 'wrench']];

    private ?array $eintraege = null;

    /** @return array[] alle Einträge, neueste zuerst */
    public function eintraege(): array
    {
        if ($this->eintraege === null) {
            $liste = require $this->getBasePath() . '/data/eintraege.php';
            usort($liste, fn($a, $b) => strcmp($b['datum'], $a['datum']));
            $this->eintraege = $liste;
        }
        return $this->eintraege;
    }

    /** @return array id => [sprache => [titel, text]] */
    public function uebersetzungen(): array
    {
        $datei = $this->getBasePath() . '/data/uebersetzungen.json';
        $daten = is_file($datei) ? json_decode((string)file_get_contents($datei), true) : null;
        return is_array($daten) ? $daten : [];
    }

    /** Titel bzw. Text in der Sprache der Seite (sonst deutsch) */
    public function feld(array $eintrag, string $feld): string
    {
        static $tr = null;
        $tr ??= $this->uebersetzungen();
        $lang = substr((string)Yii::$app->language, 0, 2);
        return $lang !== 'de' && !empty($tr[$eintrag['id']][$lang][$feld]) ? $tr[$eintrag['id']][$lang][$feld] : $eintrag[$feld];
    }

    public static function artLabel(string $art): string
    {
        return Yii::t('NeuigkeitenModule.base', self::ARTEN[$art][0] ?? $art);
    }

    /** URL für den Knopf „Ansehen“ oder null (Space fehlt bzw. kein Link) */
    public function linkUrl(array $eintrag): ?string
    {
        if (empty($eintrag['link'])) {
            return null;
        }
        [$spaceUrl, $route] = $eintrag['link'];
        if ($spaceUrl === null) {
            return \yii\helpers\Url::to([$route]);
        }
        $space = Space::findOne(['url' => $spaceUrl]);
        return $space ? $space->createUrl($route) : null;
    }

    /** @return string[] IDs der Einträge, deren Popup die Person schon weggeklickt hat */
    public function gesehen(User $user): array
    {
        $ids = json_decode((string)$this->settings->user($user)->get('gesehen', '[]'), true);
        return is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }

    /** @return array[] Einträge fürs Popup: neue Funktionen der letzten Tage, noch nicht gesehen, nicht älter als das Konto */
    public function fuerPopup(User $user): array
    {
        $gesehen = $this->gesehen($user);
        $ab = max(date('Y-m-d', strtotime("-{$this->popupTage} days")), substr((string)$user->created_at, 0, 10));
        $liste = array_filter($this->eintraege(), fn($e) => !empty($e['popup']) && $e['datum'] >= $ab
            && $e['datum'] <= date('Y-m-d') && !in_array($e['id'], $gesehen, true));
        return array_slice(array_values($liste), 0, $this->popupMax);
    }

    /** Alle Popup-Einträge als gesehen merken (auch die, die wegen $popupMax nicht angezeigt wurden) */
    public function alleGesehen(User $user): void
    {
        $ids = array_column(array_filter($this->eintraege(), fn($e) => !empty($e['popup'])), 'id');
        $this->settings->user($user)->set('gesehen', json_encode(array_values(array_unique(array_merge($this->gesehen($user), $ids)))));
    }
}
