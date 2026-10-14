<?php

namespace humhub\modules\uebersicht\commands;

use humhub\modules\content\models\Content;
use humhub\modules\uebersicht\Module;
use yii\console\Controller;

/** Angepinnte Beiträge der Übersicht: /app/yii uebersicht/liste | anpinnen <content-id> | loesen <content-id> */
class UebersichtController extends Controller
{
    public function actionListe()
    {
        /** @var Module $module */
        $module = \Yii::$app->getModule('uebersicht');
        foreach ($module->getIds() as $id) {
            $c = Content::findOne($id);
            $text = $c ? mb_substr(trim(strip_tags((string)($c->getModel()->getContentDescription() ?? ''))), 0, 60) : '(gelöscht)';
            $this->stdout("$id  " . ($c ? $c->container->getDisplayName() : '-') . "  $text\n");
        }
        if (!$module->getIds()) {
            $this->stdout("Keine angepinnten Beiträge.\n");
        }
    }

    public function actionAnpinnen($id)
    {
        \Yii::$app->getModule('uebersicht')->anpinnen((int)$id);
        $this->actionListe();
    }

    public function actionLoesen($id)
    {
        \Yii::$app->getModule('uebersicht')->loesen((int)$id);
        $this->actionListe();
    }
}
