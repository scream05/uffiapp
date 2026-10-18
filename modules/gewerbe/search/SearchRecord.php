<?php

namespace humhub\modules\gewerbe\search;

use humhub\interfaces\MetaSearchResultInterface;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\Module;
use Yii;

class SearchRecord implements MetaSearchResultInterface
{
    public function __construct(public Betrieb $betrieb)
    {
    }

    public function getImage(): string
    {
        return Yii::$app->view->renderFile(Yii::$app->getModule('gewerbe')->getBasePath() . '/views/betrieb/_logo.php',
            ['betrieb' => $this->betrieb, 'size' => 36]);
    }

    public function getTitle(): string
    {
        return $this->betrieb->name;
    }

    public function getDescription(): string
    {
        $stand = $this->betrieb->getStand(true);
        return implode(' · ', array_filter([Module::kategorieLabel($this->betrieb->kategorie), Module::ortLabel($this->betrieb->ortsteil), $stand['text'] ?? null]));
    }

    public function getUrl(): string
    {
        return $this->betrieb->getUrl();
    }
}
