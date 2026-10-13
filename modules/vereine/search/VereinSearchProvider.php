<?php

namespace humhub\modules\vereine\search;

use humhub\interfaces\MetaSearchProviderInterface;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\Module;
use humhub\services\MetaSearchService;
use Yii;

/**
 * Vereinsverzeichnis in der Suche der Kopfleiste (Name, Beschreibung, Ansprechpartner, Treffpunkt …)
 */
class VereinSearchProvider implements MetaSearchProviderInterface
{
    private ?MetaSearchService $service = null;
    public ?string $keyword = null;
    public string|array|null $route = null;

    public function getName(): string
    {
        return 'Vereine';
    }

    public function getSortOrder(): int
    {
        return 250; // nach Inhalten, vor Spaces
    }

    public function getRoute(): string|array
    {
        $space = Yii::$app->getModule('vereine')->getDirectorySpace();
        return $space ? ['/vereine/verein/index', 'cguid' => $space->guid] : '/dashboard';
    }

    public function getAllResultsText(): string
    {
        return $this->getService()->hasResults() ? Yii::t('base', 'Show all results') : 'Zum Vereinsverzeichnis';
    }

    public function getIsHiddenWhenEmpty(): bool
    {
        return true;
    }

    public function getResults(int $maxResults): array
    {
        $query = Verein::find()->andWhere(Verein::searchCondition((string)$this->getKeyword()))->orderBy(['name' => SORT_ASC]);
        if (!Module::canManageAll()) {
            $query->andWhere(['!=', 'status', Verein::STATUS_VORGESCHLAGEN]);
        }
        $total = (int)(clone $query)->count();
        $results = [];
        foreach ($query->limit($maxResults)->all() as $verein) {
            $results[] = new SearchRecord($verein, $this->getKeyword());
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
