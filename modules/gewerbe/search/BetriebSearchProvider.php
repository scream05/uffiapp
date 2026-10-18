<?php

namespace humhub\modules\gewerbe\search;

use humhub\interfaces\MetaSearchProviderInterface;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\Module;
use humhub\services\MetaSearchService;
use Yii;

/**
 * Gewerbeverzeichnis in der Suche der Kopfleiste (Name, Angebot, Beschreibung, Adresse, Branche)
 */
class BetriebSearchProvider implements MetaSearchProviderInterface
{
    private ?MetaSearchService $service = null;
    public ?string $keyword = null;
    public string|array|null $route = null;

    public function getName(): string
    {
        return Yii::t('GewerbeModule.base', 'Gewerbe & Einkaufen');
    }

    public function getSortOrder(): int
    {
        return 260; // nach den Vereinen, vor Spaces
    }

    public function getRoute(): string|array
    {
        $space = Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space ? ['/gewerbe/betrieb/index', 'cguid' => $space->guid] : '/dashboard';
    }

    public function getAllResultsText(): string
    {
        return $this->getService()->hasResults() ? Yii::t('base', 'Show all results') : Yii::t('GewerbeModule.base', 'Zum Gewerbeverzeichnis');
    }

    public function getIsHiddenWhenEmpty(): bool
    {
        return true;
    }

    public function getResults(int $maxResults): array
    {
        $query = Betrieb::find()->andWhere(Betrieb::searchCondition((string)$this->getKeyword()))->orderBy(['name' => SORT_ASC]);
        if (!Module::canManageAll()) {
            $query->andWhere(['!=', 'status', Betrieb::STATUS_VORGESCHLAGEN]);
        }
        $total = (int)(clone $query)->count();
        $results = [];
        foreach ($query->limit($maxResults)->all() as $betrieb) {
            $results[] = new SearchRecord($betrieb);
        }
        return ['totalCount' => $total, 'results' => $results];
    }

    public function getService(): MetaSearchService
    {
        if ($this->service === null) {
            $this->service = new MetaSearchService($this);
        }
        return $this->service;
    }

    public function getKeyword(): ?string
    {
        return $this->keyword;
    }
}
