<?php

namespace humhub\modules\vereine\search;

use humhub\interfaces\MetaSearchResultInterface;
use humhub\modules\vereine\models\Verein;
use Yii;

class SearchRecord implements MetaSearchResultInterface
{
    public function __construct(public Verein $verein, public ?string $keyword = null)
    {
    }

    public function getImage(): string
    {
        return Yii::$app->view->renderFile(Yii::$app->getModule('vereine')->getBasePath() . '/views/verein/_logo.php',
            ['verein' => $this->verein, 'size' => 36]);
    }

    public function getTitle(): string
    {
        return $this->verein->name;
    }

    public function getDescription(): string
    {
        $hint = $this->keyword ? $this->verein->searchHint($this->keyword) : null;
        $module = Yii::$app->getModule('vereine');
        return $hint ?? $module->kategorieLabel($this->verein->kategorie) . ' · ' . $module->ortLabel($this->verein->ortsteil);
    }

    public function getUrl(): string
    {
        return $this->verein->getUrl();
    }
}
