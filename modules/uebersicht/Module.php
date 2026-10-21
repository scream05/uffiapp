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
 */
class Module extends \humhub\components\Module
{
    /** Spaces ohne Beiträge im Stream (nur Kalender) – erscheinen nicht in „Meine Übersicht“ */
    public array $ohneSchalter = ['abfall-and-termine'];

    /** Bereiche, die in der Übersicht standardmäßig aus sind (auch für Gäste); jede Person kann sie einschalten */
    public array $standardAus = ['vorschlaege'];

    /** @return Space[] Bereiche, die die Person sehen und für die Übersicht wählen kann */
    public function getBereiche(User $user): array
    {
        return Space::find()->visible($user)->andWhere(['space.status' => Space::STATUS_ENABLED])
            ->andWhere(['not in', 'space.url', $this->ohneSchalter])->orderBy(['space.name' => SORT_ASC])->all();
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
        foreach (Membership::findAll(['user_id' => $user->id]) as $m) {
            $space = $m->space;
            if ($space && !in_array($space->url, $this->ohneSchalter, true)) {
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
