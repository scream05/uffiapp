<?php

namespace humhub\modules\eilmeldung\controllers;

use humhub\components\Controller;
use humhub\modules\eilmeldung\models\Eilmeldung;

/** /eilmeldung – laufende und Eilmeldungen der letzten 30 Tage, für alle (auch Gäste) */
class IndexController extends Controller
{
    protected function getAccessRules()
    {
        return [];
    }

    public function actionIndex()
    {
        $aktiv = Eilmeldung::aktiv()->all();
        $frueher = Eilmeldung::find()->where(['not in', 'id', array_map(fn($e) => $e->id, $aktiv) ?: [0]])
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-30 days'))])
            ->orderBy(['created_at' => SORT_DESC])->limit(50)->all();
        return $this->render('index', [
            'aktiv' => $aktiv,
            'frueher' => $frueher,
            'module' => $this->module,
            'darfSchreiben' => $this->module->darfSchreiben(\Yii::$app->user->getIdentity()),
        ]);
    }
}
