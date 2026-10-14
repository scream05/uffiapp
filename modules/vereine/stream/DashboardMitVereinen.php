<?php

namespace humhub\modules\vereine\stream;

use humhub\modules\dashboard\stream\filters\DashboardMemberStreamFilter;
use humhub\modules\vereine\models\VereinBeitrag;
use Yii;
use yii\db\Query;

/**
 * Übersicht angemeldeter Nutzer: zusätzlich zu Spaces, in denen man Mitglied ist bzw. denen man folgt, auch die
 * freigegebenen Vereinsbeiträge „für die Übersicht“ (der Space „Vereine & Ehrenamt“ hat nicht alle als Mitglieder).
 * Ergänzt die Bedingung „abonnierter Container“ des Kern-Filters um diese Content-IDs; findet sie die Bedingung
 * nicht (anderer HumHub-Stand), bleibt alles wie im Kern. Gäste sehen öffentliche Beiträge ohnehin.
 */
class DashboardMitVereinen extends DashboardMemberStreamFilter
{
    public function apply()
    {
        parent::apply();
        $ids = (new Query())->select('content_id')->from(VereinBeitrag::tableName())
            ->where(['status' => VereinBeitrag::STATUS_VEROEFFENTLICHT, 'uebersicht' => 1])
            ->andWhere(['not', ['content_id' => null]]);
        $where = $this->query->where;
        if (self::ergaenze($where, ['content.id' => $ids])) {
            $this->query->where = $where;
        } else {
            Yii::warning('DashboardMitVereinen: Container-Bedingung nicht gefunden', 'vereine');
        }
    }

    private static function ergaenze(&$bedingung, array $zusatz): bool
    {
        if (!is_array($bedingung)) {
            return false;
        }
        if (isset($bedingung[0]) && is_string($bedingung[0]) && strtoupper($bedingung[0]) === 'OR'
            && in_array('content.contentcontainer_id IS NULL', $bedingung, true)
            && in_array('space_membership.user_id IS NOT NULL', $bedingung, true)) {
            $bedingung[] = $zusatz;
            return true;
        }
        foreach ($bedingung as &$teil) {
            if (self::ergaenze($teil, $zusatz)) {
                return true;
            }
        }
        return false;
    }
}
