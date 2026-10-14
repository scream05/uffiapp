<?php

namespace humhub\modules\uebersicht;

use humhub\modules\content\models\Content;

/**
 * Angepinnte Beiträge in der Übersicht (Dashboard). Der HumHub-Kern kann nur innerhalb eines Space anpinnen;
 * hier wählt ein Admin Beiträge aus beliebigen Spaces, die für alle oben in der Übersicht stehen
 * (jeweils nur für Personen, die den Beitrag ohnehin sehen dürfen). Liste als Moduleinstellung „ids“, neueste zuerst.
 */
class Module extends \humhub\components\Module
{
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
