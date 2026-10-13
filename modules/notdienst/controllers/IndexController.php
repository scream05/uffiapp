<?php

namespace humhub\modules\notdienst\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\notdienst\components\Notdienst;

class IndexController extends Controller
{
    protected function getAccessRules()
    {
        return []; // auch für Gäste
    }

    public function actionIndex()
    {
        return $this->render('index', [
            'module' => $this->module,
            'periods' => Notdienst::periods(3),
            'stale' => Notdienst::isStale(),
            'data' => Notdienst::load(),
        ]);
    }
}
