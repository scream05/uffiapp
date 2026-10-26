<?php

namespace humhub\modules\vorschlaege\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\models\Content;
use humhub\modules\vorschlaege\models\Stimme;
use humhub\modules\vorschlaege\models\Vorschlag;
use humhub\modules\vorschlaege\models\VorschlagForm;
use humhub\modules\vorschlaege\Module;
use humhub\modules\vorschlaege\widgets\VorschlagAddon;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Übersicht (auch für Gäste), Einreichen, Abstimmen und Stand setzen. Läuft nur im Space des Moduls.
 *
 * @property Module $module
 */
class VorschlagController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login' => ['create', 'stimme', 'status']], ['post' => ['stimme', 'status']]];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if ($this->contentContainer->url !== $this->module->spaceUrl) {
            throw new NotFoundHttpException();
        }
        return true;
    }

    /** Alle Vorschläge, nach Stimmen oder Datum, gefiltert nach Stand („offen“ = noch nicht abgeschlossen) */
    public function actionIndex($stand = 'offen', $sortierung = 'stimmen')
    {
        $stand = $stand === 'alle' || $stand === 'offen' || isset(Vorschlag::STATUSES[$stand]) ? $stand : 'offen';
        $sortierung = $sortierung === 'neu' ? 'neu' : 'stimmen';
        $query = Vorschlag::find()->alias('v')->innerJoinWith('content c')->with('content.createdBy')
            ->where(['c.contentcontainer_id' => $this->contentContainer->contentcontainer_id, 'c.state' => Content::STATE_PUBLISHED]);
        if (Yii::$app->user->isGuest) {
            $query->andWhere(['c.visibility' => Content::VISIBILITY_PUBLIC]);
        }
        $anzahl = (clone $query)->select(['v.status', 'n' => 'COUNT(*)'])->groupBy('v.status')->indexBy('status')->column();
        if ($stand === 'offen') {
            $query->andWhere(['not in', 'v.status', Vorschlag::CLOSED]);
        } elseif ($stand !== 'alle') {
            $query->andWhere(['v.status' => $stand]);
        }
        if ($sortierung === 'stimmen') {
            $query->leftJoin(['s' => Stimme::find()->select(['content_id', 'punkte' => 'SUM(wert)'])->groupBy('content_id')], 's.content_id = v.content_id')
                ->orderBy(['COALESCE(s.punkte, 0)' => SORT_DESC, 'v.created_at' => SORT_DESC]);
        } else {
            $query->orderBy(['v.created_at' => SORT_DESC]);
        }
        $vorschlaege = $query->limit(300)->all();
        $ids = array_map(fn($v) => (int)$v->content_id, $vorschlaege);
        $this->view->setPageTitle(Yii::t('VorschlaegeModule.base', 'Ideen & Vorschläge'));
        return $this->render('index', [
            'module' => $this->module,
            'space' => $this->contentContainer,
            'vorschlaege' => $vorschlaege,
            'summen' => Stimme::summen($ids),
            'meine' => Stimme::meine($ids),
            'kommentare' => $ids ? array_map('intval', (new \yii\db\Query())->select(['n' => 'COUNT(*)', 'content_id'])->from('comment')
                ->where(['content_id' => $ids, 'parent_comment_id' => null])
                ->groupBy('content_id')->indexBy('content_id')->column()) : [],
            'anzahl' => array_map('intval', $anzahl),
            'stand' => $stand,
            'sortierung' => $sortierung,
        ]);
    }

    public function actionCreate()
    {
        $form = new VorschlagForm();
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $heute = Vorschlag::find()->alias('v')->innerJoinWith('content c')
                ->where(['c.created_by' => Yii::$app->user->id])->andWhere(['>=', 'v.created_at', date('Y-m-d 00:00:00')])->count();
            if ($heute >= $this->module->proTag) {
                $this->view->error(Yii::t('VorschlaegeModule.base', 'Du hast heute schon {n} Vorschläge eingereicht. Bitte morgen weiter – danke für deinen Einsatz!', ['n' => $this->module->proTag]));
                return $this->render('create', ['form' => $form, 'space' => $this->contentContainer, 'module' => $this->module]);
            }
            try {
                $post = Vorschlag::einreichen($this->contentContainer, $form);
            } catch (\Throwable $e) {
                Yii::error($e, 'vorschlaege');
                $this->view->error(Yii::t('VorschlaegeModule.base', 'Das hat leider nicht geklappt. Bitte versuch es noch einmal.'));
                return $this->render('create', ['form' => $form, 'space' => $this->contentContainer, 'module' => $this->module]);
            }
            $this->view->success(Yii::t('VorschlaegeModule.base', 'Danke für deinen Vorschlag! Andere können ihn jetzt kommentieren und abstimmen.'));
            return $this->redirect($post->content->getUrl());
        }
        return $this->render('create', ['form' => $form, 'space' => $this->contentContainer, 'module' => $this->module]);
    }

    /** Dafür (wert=1) oder dagegen (wert=-1); dieselbe Stimme noch einmal nimmt sie zurück */
    public function actionStimme($id)
    {
        $v = $this->finde($id);
        if ((int)$v->content->created_by === (int)Yii::$app->user->id || !$v->istOffen()) {
            throw new ForbiddenHttpException(Yii::t('VorschlaegeModule.base', 'Hier kannst du nicht abstimmen.'));
        }
        $mein = Stimme::setzen($v->content_id, Yii::$app->user->id, (int)Yii::$app->request->post('wert'));
        if (Yii::$app->request->post('ajax')) {
            $this->view->registerAssetBundle(\humhub\modules\vorschlaege\assets\VorschlagAsset::class);
            return VorschlagAddon::abstimmung($v, Stimme::summen([$v->content_id])[$v->content_id] ?? null, $mein);
        }
        return $this->redirect(Yii::$app->request->referrer ?: $v->content->getUrl());
    }

    public function actionStatus($id)
    {
        if (!Vorschlag::canManage()) {
            throw new ForbiddenHttpException(Yii::t('VorschlaegeModule.base', 'Keine Berechtigung, den Stand zu ändern.'));
        }
        $v = $this->finde($id);
        $neu = (string)Yii::$app->request->post('status');
        if (!isset(Vorschlag::STATUSES[$neu])) {
            throw new NotFoundHttpException();
        }
        $v->statusSetzen($neu, (string)Yii::$app->request->post('note'))
            ? $this->view->success(Yii::t('VorschlaegeModule.base', 'Stand gespeichert.'))
            : $this->view->info(Yii::t('VorschlaegeModule.base', 'Keine Änderung.'));
        return $this->redirect($v->content->getUrl());
    }

    private function finde($id): Vorschlag
    {
        $v = Vorschlag::find()->alias('v')->innerJoinWith('content c')
            ->where(['v.content_id' => (int)$id, 'c.contentcontainer_id' => $this->contentContainer->contentcontainer_id])->one();
        if ($v === null || !$v->content->canView()) {
            throw new NotFoundHttpException();
        }
        return $v;
    }
}
