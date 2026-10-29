<?php

namespace humhub\modules\designwahl\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\designwahl\components\Bildtext;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Bildbeschreibungen eines Beitrags bearbeiten: /designwahl/bildtext?id=<content-id>
 * (Beitragsmenü ⋯ → „Bildbeschreibungen“, nur wer den Beitrag bearbeiten darf). Eigene Seite statt Fenster:
 * funktioniert ohne Skript, mit Tastatur und Screenreader.
 */
class BildtextController extends Controller
{
    protected function getAccessRules()
    {
        return [[ControllerAccess::RULE_LOGGED_IN_ONLY]];
    }

    public function actionIndex($id)
    {
        $content = Content::findOne(['id' => (int)$id]);
        $record = $content ? $content->getPolymorphicRelation() : null;
        if (!$record instanceof ContentActiveRecord) {
            throw new NotFoundHttpException();
        }
        if (!Bildtext::darfBearbeiten($record)) {
            throw new ForbiddenHttpException();
        }
        $bilder = Bildtext::bilder($record);
        $zurueck = $content->getUrl();

        if (Yii::$app->request->isPost) {
            $texte = (array)Yii::$app->request->post('bildtext', []);
            foreach ($bilder as $file) {
                if (!array_key_exists($file->guid, $texte)) {
                    continue;
                }
                $text = trim(preg_replace('/\s+/u', ' ', (string)$texte[$file->guid]));
                $text = mb_substr($text, 0, Bildtext::MAX);
                if ($text !== (string)$file->title) {
                    $file->updateAttributes(['title' => $text === '' ? null : $text]);
                }
            }
            $this->view->saved();
            return $this->redirect($zurueck);
        }

        $this->view->setPageTitle(Yii::t('DesignwahlModule.base', 'Bildbeschreibungen'));
        return $this->render('index', ['bilder' => $bilder, 'zurueck' => $zurueck, 'id' => $content->id]);
    }
}
