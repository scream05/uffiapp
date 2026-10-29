<?php

namespace humhub\modules\neuigkeiten;

use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

/**
 * Neuigkeiten: Einträge in data/eintraege.php (deutsch, im Repo gepflegt), Übersetzungen in data/uebersetzungen.json
 * (erzeugt mit `scripts/run-setup.sh neuigkeiten-uebersetzen`). Seite /neuigkeiten/index für alle, Popup einmalig
 * für Angemeldete; gesehene Einträge merkt sich die Benutzereinstellung „gesehen“ (Liste der IDs).
 * Dazu der Willkommensrundgang (widgets/Rundgang): einmal je Konto statt des Popups, erledigt = Einstellung „rundgang“.
 */
class Module extends \humhub\components\Module
{
    /** Popup nur für Einträge, die höchstens so alt sind */
    public int $popupTage = 30;

    /** Höchstens so viele Einträge im Popup (die neuesten) */
    public int $popupMax = 3;

    public const ARTEN = ['neu' => ['Neu', 'star'], 'besser' => ['Verbessert', 'magic'], 'behoben' => ['Behoben', 'wrench']];

    /** Hilfe-Seiten: Schlüssel => deutscher Titel (Inhalt in hilfe/<sprache>/<schlüssel>.html) */
    public const HILFE = ['app' => 'App-Anleitung', 'regeln' => 'Community-Regeln', 'barrierefreiheit' => 'Barrierefreiheit',
        'leichte-sprache' => 'Leichte Sprache'];

    /** Hilfe-Seiten nur auf Deutsch (Leichte Sprache gibt es nur auf Deutsch): nicht übersetzen, in allen Sprachen die deutsche Fassung */
    public const NUR_DEUTSCH = ['leichte-sprache'];

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

    /** Willkommensrundgang noch nicht gesehen (einmal für jedes Konto, auch für bestehende) */
    public function rundgangOffen(User $user): bool
    {
        return !$this->settings->user($user)->get('rundgang');
    }

    /** Rundgang beendet oder übersprungen: nicht wieder von selbst zeigen */
    public function rundgangGesehen(User $user): void
    {
        $this->settings->user($user)->set('rundgang', date('Y-m-d'));
    }

    /** Alle Popup-Einträge als gesehen merken (auch die, die wegen $popupMax nicht angezeigt wurden) */
    public function alleGesehen(User $user): void
    {
        $ids = array_column(array_filter($this->eintraege(), fn($e) => !empty($e['popup'])), 'id');
        $this->settings->user($user)->set('gesehen', json_encode(array_values(array_unique(array_merge($this->gesehen($user), $ids)))));
    }

    public static function hilfeTitel(string $seite): string
    {
        return Yii::t('NeuigkeitenModule.base', self::HILFE[$seite] ?? $seite);
    }

    /**
     * Inhalt einer Hilfe-Seite in der Sprache der Seite (sonst deutsch). Platzhalter im HTML:
     * {{url:/route?a=b}} und {{space:<space-url>:/route}} (fehlt der Space, wird es die Startseite).
     */
    public function hilfeHtml(string $seite, ?string $lang = null): string
    {
        $lang ??= substr((string)Yii::$app->language, 0, 2);
        $datei = $this->getBasePath() . "/hilfe/$lang/$seite.html";
        if (!is_file($datei) || in_array($seite, self::NUR_DEUTSCH, true)) {
            $datei = $this->getBasePath() . "/hilfe/de/$seite.html";
        }
        $route = function (string $r): array {
            $teile = explode('?', $r, 2);
            parse_str($teile[1] ?? '', $params);
            return [$teile[0]] + $params;
        };
        return preg_replace_callback('/\{\{(url|space):([^}]+)\}\}/', function ($m) use ($route) {
            if ($m[1] === 'url') {
                return \yii\helpers\Html::encode(\yii\helpers\Url::to($route($m[2])));
            }
            [$spaceUrl, $r] = explode(':', $m[2], 2);
            $space = Space::findOne(['url' => $spaceUrl]);
            return \yii\helpers\Html::encode($space ? $space->createUrl($r) : \yii\helpers\Url::home());
        }, (string)file_get_contents($datei));
    }
}
