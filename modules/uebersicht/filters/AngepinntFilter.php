<?php

namespace humhub\modules\uebersicht\filters;

use humhub\modules\content\models\Content;
use humhub\modules\stream\models\filters\StreamQueryFilter;
use humhub\modules\uebersicht\Module;
use Yii;

/**
 * Wie PinnedContentStreamFilter des Kerns, aber für die Übersicht: angepinnte Beiträge kommen im ersten
 * Abruf nach vorne (sofern die Person sie sehen darf) und werden aus dem übrigen Stream herausgenommen.
 * Bei aktiven Stream-Filtern (z. B. „Nur meine Beiträge“) bleibt der Stream unverändert.
 */
class AngepinntFilter extends StreamQueryFilter
{
    /** @var Content[] */
    private array $angepinnt = [];

    public function apply()
    {
        $sq = $this->streamQuery;
        if ($sq->isSingleContentQuery() || !empty($sq->filters) || !empty($sq->includes)) {
            return;
        }
        /** @var Module $module */
        $module = Yii::$app->getModule('uebersicht');
        $ids = $module->getIds();
        if (!$ids) {
            return;
        }
        $this->query->andWhere(['NOT IN', 'content.id', $ids]);
        if (!$sq->isInitialQuery()) {
            return;
        }
        $inhalte = Content::find()->where(['content.id' => $ids, 'content.state' => Content::STATE_PUBLISHED])
            ->indexBy('id')->all();
        foreach ($ids as $id) {
            $c = $inhalte[$id] ?? null;
            if ($c && !$c->isArchived() && $c->canView($sq->user)) {
                $this->angepinnt[] = $c;
            }
        }
    }

    public function postProcessStreamResult(array &$results): void
    {
        if ($this->angepinnt) {
            $results = array_merge($this->angepinnt, $results);
        }
    }
}
