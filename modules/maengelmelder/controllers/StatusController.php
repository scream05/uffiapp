<?php

namespace humhub\modules\maengelmelder\controllers;

use humhub\modules\maengelmelder\components\Bauhof;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\models\Content;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\post\models\Post;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/** Status einer Meldung setzen (nur mit Recht „Mängelstatus bearbeiten“); die Änderung wird als Kommentar vermerkt */
class StatusController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login'], ['post' => ['set']]];
    }

    public function actionSet($id)
    {
        if (!MaengelStatus::canManage()) {
            throw new ForbiddenHttpException('Keine Berechtigung, den Status zu ändern.');
        }
        $content = Content::findOne(['id' => (int)$id, 'contentcontainer_id' => $this->contentContainer->contentcontainer_id,
            'object_model' => Post::class]);
        if ($content === null) {
            throw new NotFoundHttpException();
        }
        $new = (string)Yii::$app->request->post('status');
        $note = trim(mb_substr((string)Yii::$app->request->post('note'), 0, 500));
        if (!isset(MaengelStatus::STATUSES[$new])) {
            throw new NotFoundHttpException('Unbekannter Status');
        }

        // Status, öffentlicher Kommentar (benachrichtigt die meldende Person) und Vermerk im Bauhof-Verlauf
        Bauhof::statusSetzen($content, $new, $note, Yii::$app->user->getIdentity());
        return $this->redirect($content->getUrl());
    }
}
