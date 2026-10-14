<?php

namespace humhub\modules\uebersicht\filters;

use humhub\modules\dashboard\stream\filters\DashboardMemberStreamFilter;
use humhub\modules\uebersicht\Module;
use Yii;

/**
 * Übersicht angemeldeter Nutzer nach „Meine Übersicht“: Beiträge aus allen eingeschalteten Bereichen (Spaces),
 * auch ohne Mitgliedschaft (Standard: alle an), keine aus ausgeschalteten – auch wenn man dort Mitglied ist.
 * Ergänzt die Container-Bedingung des Kern-Filters um die eingeschalteten Spaces; die Sichtbarkeitsprüfung des
 * Kerns bleibt (Nicht-Mitglieder sehen nur öffentliche Beiträge). Findet sie die Bedingung nicht (anderer
 * HumHub-Stand), gilt nur das Ausblenden, und im Log steht eine Warnung.
 */
class MeineUebersicht extends DashboardMemberStreamFilter
{
    public function apply()
    {
        parent::apply();
        /** @var Module $module */
        $module = Yii::$app->getModule('uebersicht');
        [$an, $aus] = $module->getContainerIds($this->user);
        if ($an) {
            $where = $this->query->where;
            if (self::ergaenze($where, ['content.contentcontainer_id' => $an])) {
                $this->query->where = $where;
            } else {
                Yii::warning('MeineUebersicht: Container-Bedingung nicht gefunden', 'uebersicht');
            }
        }
        if ($aus) {
            $this->query->andWhere(['or', ['content.contentcontainer_id' => null], ['not in', 'content.contentcontainer_id', $aus]]);
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
