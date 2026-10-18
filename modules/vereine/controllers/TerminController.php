<?php

namespace humhub\modules\vereine\controllers;

use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\calendar\models\CalendarEntryType;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\space\models\Space;
use humhub\modules\vereine\models\TerminForm;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\Module;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Vereinstermine im gemeinsamen Kalender anlegen/bearbeiten/löschen (nur Verwalter des Vereins).
 *
 * @property Module $module
 */
class TerminController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login']];
    }

    public function actionCreate($verein)
    {
        $verein = $this->manageable(Verein::findOne((int)$verein));
        $space = $this->calendarSpace();
        $form = new TerminForm(new CalendarEntry($space));
        return $this->handle($form, $verein, $space);
    }

    public function actionEdit($id)
    {
        [$entry, $verein] = $this->findEntry($id);
        return $this->handle(new TerminForm($entry), $verein, $entry->content->container);
    }

    public function actionDelete($id)
    {
        $this->forcePostRequest();
        [$entry, $verein] = $this->findEntry($id);
        $entry->delete();
        $this->view->success(Yii::t('VereineModule.base', 'Termin gelöscht.'));
        return $this->redirect($verein->getUrl());
    }

    private function handle(TerminForm $form, Verein $verein, Space $space)
    {
        $similar = [];
        if ($form->load(Yii::$app->request->post())) {
            $similar = $form->entry->isNewRecord && !$form->trotzdem ? $form->similarCityEntries($space) : [];
            if ($similar === [] && $form->save($verein, $this->type($space))) {
                $this->view->saved();
                return $this->redirect($form->entry->content->getUrl());
            }
        }
        return $this->render('edit', ['form' => $form, 'verein' => $verein, 'similar' => $similar]);
    }

    private function type(Space $space): ?CalendarEntryType
    {
        return CalendarEntryType::find()->where(['content_tag.module_id' => 'calendar', 'content_tag.name' => $this->module->calendarType,
            'content_tag.contentcontainer_id' => $space->contentcontainer_id])->one();
    }

    private function calendarSpace(): Space
    {
        $space = $this->module->getCalendarSpace();
        if ($space === null || !$space->moduleManager->isEnabled('calendar')) {
            throw new NotFoundHttpException(Yii::t('VereineModule.base', 'Der gemeinsame Kalender ist nicht eingerichtet.'));
        }
        return $space;
    }

    /** @return array [CalendarEntry, Verein] */
    private function findEntry($id): array
    {
        $entry = CalendarEntry::findOne((int)$id);
        $verein = $entry && $entry->content->state === \humhub\modules\content\models\Content::STATE_PUBLISHED ? Verein::forEntry($entry) : null;
        if ($verein === null) {
            throw new NotFoundHttpException(Yii::t('VereineModule.base', 'Termin nicht gefunden.'));
        }
        return [$entry, $this->manageable($verein)];
    }

    private function manageable(?Verein $verein): Verein
    {
        if ($verein === null || $verein->status === Verein::STATUS_VORGESCHLAGEN) {
            throw new NotFoundHttpException(Yii::t('VereineModule.base', 'Verein nicht gefunden.'));
        }
        if (!$verein->canManage()) {
            throw new ForbiddenHttpException(Yii::t('VereineModule.base', 'Nur Verwalter dieses Vereins können Termine eintragen.'));
        }
        return $verein;
    }
}
