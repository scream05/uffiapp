<?php

namespace humhub\modules\uebersicht;

use humhub\modules\content\models\Content;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;

/**
 * Angepinnte Beiträge in der Übersicht (Dashboard). Der HumHub-Kern kann nur innerhalb eines Space anpinnen;
 * hier wählt ein Admin Beiträge aus beliebigen Spaces, die für alle oben in der Übersicht stehen
 * (jeweils nur für Personen, die den Beitrag ohnehin sehen dürfen). Liste als Moduleinstellung „ids“, neueste zuerst.
 *
 * „Meine Übersicht“: jede angemeldete Person schaltet Bereiche (Spaces) für ihre Übersicht an oder aus, unabhängig
 * von der Mitgliedschaft. Standard: alles an außer $standardAus. Gespeichert wird nur, was ausgeschaltet ist (Benutzereinstellung
 * „ausgeblendet“, Space-GUIDs), damit neue Spaces automatisch an sind; eingeschaltete Bereiche aus $standardAus
 * stehen in „eingeblendet“.
 *
 * Reihenfolge der Kästen in der Seitenleiste der Übersicht (am Handy im Fach): je Person unter „Meine Übersicht“
 * (Benutzereinstellung „kaesten“, Schlüssel aus KAESTEN), ausgeblendete in „kaesten_aus“; Events::onDashboardSidebarRun
 * setzt die sortOrder und entfernt ausgeblendete Kästen.
 */
class Module extends \humhub\components\Module
{
    /** Spaces ohne Beiträge im Stream (nur Kalender) – erscheinen nicht in „Meine Übersicht“ */
    public array $ohneSchalter = ['abfall-and-termine'];

    /** Bereiche, die in der Übersicht standardmäßig aus sind (auch für Gäste); jede Person kann sie einschalten */
    public array $standardAus = ['vorschlaege'];

    /** Sortierbare Kästen der Seitenleiste: Schlüssel => [Widget-Klasse, Bezeichnung], in der Standardreihenfolge */
    public const KAESTEN = [
        'notdienste' => ['humhub\modules\notdienst\widgets\NotdienstSidebar', 'Notdienste'],
        'sport' => ['humhub\modules\vereine\widgets\SpieltagKasten', 'Sport heute (nur an Spieltagen)'],
        'oeffnungszeiten' => ['humhub\modules\oeffnungszeiten\widgets\Kasten', 'Öffnungszeiten'],
        'sprit' => ['humhub\modules\tanken\widgets\SpritKasten', 'Spritpreise'],
        'aktivitaeten' => ['humhub\modules\activity\widgets\ActivityStreamViewer', 'Letzte Aktivitäten'],
        'veranstaltungen' => ['humhub\modules\calendar\widgets\UpcomingEvents', 'Anstehende Veranstaltungen'],
    ];

    /** @return string[] Schlüssel der vorhandenen Kästen in der Reihenfolge der Person (neue Kästen hinten an) */
    public function getKaesten(User $user): array
    {
        $vorhanden = array_keys(array_filter(self::KAESTEN, fn($k) => class_exists($k[0])));
        $eigen = json_decode((string)$this->settings->user($user)->get('kaesten', '[]'), true) ?: [];
        $eigen = array_values(array_intersect($eigen, $vorhanden));
        return array_merge($eigen, array_values(array_diff($vorhanden, $eigen)));
    }

    public function setKaesten(User $user, array $schluessel): void
    {
        $gueltig = array_values(array_unique(array_intersect($schluessel, array_keys(self::KAESTEN))));
        $standard = array_values(array_intersect(array_keys(self::KAESTEN), $gueltig));
        // Unverändert = keine Einstellung (dann gelten künftige Standardänderungen auch für diese Person)
        $gueltig === $standard ? $this->settings->user($user)->delete('kaesten')
            : $this->settings->user($user)->set('kaesten', json_encode($gueltig));
    }

    /** @return string[] Schlüssel der Kästen, die die Person ausgeblendet hat */
    public function getKaestenAus(User $user): array
    {
        $aus = json_decode((string)$this->settings->user($user)->get('kaesten_aus', '[]'), true) ?: [];
        return array_values(array_intersect($aus, array_keys(self::KAESTEN)));
    }

    public function setKaestenAus(User $user, array $schluessel): void
    {
        $aus = array_values(array_unique(array_intersect($schluessel, array_keys(self::KAESTEN))));
        $aus ? $this->settings->user($user)->set('kaesten_aus', json_encode($aus)) : $this->settings->user($user)->delete('kaesten_aus');
    }

    /** @return Space[] Bereiche, die die Person sehen und für die Übersicht wählen kann */
    public function getBereiche(User $user): array
    {
        return Space::find()->visible($user)->andWhere(['space.status' => Space::STATUS_ENABLED])
            ->andWhere(['not in', 'space.url', array_merge($this->ohneSchalter, $this->gesperrt($user))])
            ->orderBy(['space.name' => SORT_ASC])->all();
    }

    /**
     * @return string[] URLs der Spaces, die die Person gar nicht in der Übersicht haben darf (nicht wählbar, immer aus):
     * der Mängelmelder außer für Bauhof und Admins (maengelmelder\Module::maengelInUebersicht)
     */
    public function gesperrt(User $user): array
    {
        $maengel = \Yii::$app->getModule('maengelmelder');
        return $maengel && method_exists($maengel, 'maengelInUebersicht') && !$maengel->maengelInUebersicht($user) ? [$maengel->bauhofSpace] : [];
    }

    /** @return string[] GUIDs der ausgeschalteten Spaces (eigene Wahl plus nicht eingeschaltete aus $standardAus) */
    public function getAusgeblendet(User $user): array
    {
        $guids = $this->leseListe($user, 'ausgeblendet');
        $an = $this->leseListe($user, 'eingeblendet');
        foreach ($this->standardAusGuids() as $guid) {
            if (!in_array($guid, $an, true)) {
                $guids[] = $guid;
            }
        }
        return array_values(array_unique($guids));
    }

    /** Speichert die Auswahl und gleicht „Beiträge in der Übersicht“ der Mitgliedschaften an */
    public function setAusgeblendet(User $user, array $guids): void
    {
        $guids = array_values(array_unique(array_filter($guids, 'is_string')));
        $standard = $this->standardAusGuids();
        $this->settings->user($user)->set('ausgeblendet', json_encode(array_values(array_diff($guids, $standard))));
        $this->settings->user($user)->set('eingeblendet', json_encode(array_values(array_diff($standard, $guids))));
        $gesperrt = $this->gesperrt($user);
        foreach (Membership::findAll(['user_id' => $user->id]) as $m) {
            $space = $m->space;
            if ($space && in_array($space->url, $gesperrt, true)) {
                $m->updateAttributes(['show_at_dashboard' => 0]);
            } elseif ($space && !in_array($space->url, $this->ohneSchalter, true)) {
                $m->updateAttributes(['show_at_dashboard' => in_array($space->guid, $guids, true) ? 0 : 1]);
            }
        }
    }

    /** @return string[] */
    private function standardAusGuids(): array
    {
        return $this->standardAus ? Space::find()->select('guid')->where(['url' => $this->standardAus])->column() : [];
    }

    /** @return int[] Content-Container-IDs von $standardAus (Übersicht der Gäste) */
    public function standardAusContainerIds(): array
    {
        return $this->standardAus ? array_map('intval', Space::find()->select('contentcontainer_id')
            ->where(['url' => $this->standardAus])->column()) : [];
    }

    /** @return string[] */
    private function leseListe(User $user, string $name): array
    {
        $guids = json_decode((string)$this->settings->user($user)->get($name, '[]'), true);
        return is_array($guids) ? array_values(array_filter($guids, 'is_string')) : [];
    }

    /** @return array{0: int[], 1: int[]} Content-Container-IDs der ein- und der ausgeschalteten Bereiche */
    public function getContainerIds(User $user): array
    {
        $aus = $this->getAusgeblendet($user);
        $an = $weg = [];
        foreach ($this->getBereiche($user) as $space) {
            if (in_array($space->guid, $aus, true)) {
                $weg[] = (int)$space->contentcontainer_id;
            } else {
                $an[] = (int)$space->contentcontainer_id;
            }
        }
        if ($gesperrt = $this->gesperrt($user)) {
            $weg = array_merge($weg, array_map('intval', Space::find()->select('contentcontainer_id')->where(['url' => $gesperrt])->column()));
        }
        return [$an, $weg];
    }

    /** @return int[] Content-IDs, neueste Anpinnung zuerst */
    public function getIds(): array
    {
        $ids = json_decode((string)$this->settings->get('ids', '[]'), true);
        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public function istAngepinnt(int $contentId): bool
    {
        return in_array($contentId, $this->getIds(), true);
    }

    public function anpinnen(int $contentId): void
    {
        $ids = array_diff($this->getIds(), [$contentId]);
        array_unshift($ids, $contentId);
        $this->speichern($ids);
    }

    public function loesen(int $contentId): void
    {
        $this->speichern(array_diff($this->getIds(), [$contentId]));
    }

    /** Gelöschte Inhalte fallen dabei heraus */
    private function speichern(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $vorhanden = $ids ? Content::find()->select('id')->where(['id' => $ids])->column() : [];
        $ids = array_values(array_filter($ids, fn($id) => in_array($id, array_map('intval', $vorhanden), true)));
        $this->settings->set('ids', json_encode($ids));
    }
}
