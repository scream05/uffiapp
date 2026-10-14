<?php

namespace humhub\modules\mitteilungsblatt\controllers;

use humhub\components\access\ControllerAccess;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\mitteilungsblatt\components\Termine;
use humhub\modules\mitteilungsblatt\models\Ausgabe;
use humhub\modules\mitteilungsblatt\models\Seite;
use humhub\modules\space\models\Space;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;

/**
 * Läuft im Space „Rathaus Uffenheim“ (/s/rathaus-uffenheim/mitteilungsblatt/…), damit Space-Kopf und -Menü
 * sichtbar bleiben. Alte Adressen ohne Space (/mitteilungsblatt/…) werden dorthin umgeleitet.
 */
class IndexController extends ContentContainerController
{
    public $requireContainer = false;

    protected function getAccessRules()
    {
        return []; // auch für Gäste
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!$this->module->darfLesen()) {
            throw new ForbiddenHttpException('Das Mitteilungsblatt ist noch nicht freigegeben.');
        }
        // Lesbar für alle, aber nicht in Suchmaschinen (Geburtstage, Jubilare, Anzeigen Dritter)
        Yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        if ($this->contentContainer !== null && $this->contentContainer->url !== $this->module->space) {
            throw new NotFoundHttpException();
        }
        if ($this->contentContainer === null && in_array($action->id, ['index', 'lesen'], true)
            && ($space = Space::findOne(['url' => $this->module->space])) !== null) {
            $this->redirect($space->createUrl($action->id === 'index' ? '/mitteilungsblatt/index' : '/mitteilungsblatt/index/lesen', Yii::$app->request->get()));
            return false;
        }
        return true;
    }

    /** Adresse innerhalb des Space (falls vorhanden) – für die Ansichten */
    public function url(string $route, array $params = []): string
    {
        return $this->contentContainer !== null
            ? $this->contentContainer->createUrl($route, $params)
            : Url::to(array_merge([$route], $params));
    }

    /** Archiv aller Ausgaben, mit ?q= Volltextsuche */
    public function actionIndex($q = '')
    {
        $this->view->setPageTitle('Mitteilungsblatt');
        $q = trim(mb_substr((string)$q, 0, 100));
        return $this->render('index', [
            'ausgaben' => Ausgabe::find()->orderBy(['jahr' => SORT_DESC, 'nr' => SORT_DESC])->all(),
            'q' => $q,
            'treffer' => mb_strlen($q) >= 3 ? $this->suchen($q) : null,
            'hinweis' => Termine::hinweis($this->module) ?? $this->module->settings->get('hinweis'),
            'termineUrl' => $this->module->settings->get('termine_url'),
            'quelle' => $this->module->quelle,
        ]);
    }

    public function actionLesen($id, $seite = null, $q = null)
    {
        $this->view->setPageTitle('Mitteilungsblatt');
        return $this->render('lesen', [
            'ausgabe' => $this->ausgabe($id),
            'seite' => $seite !== null ? max(1, (int)$seite) : null,
            'q' => $q !== null ? mb_substr(trim((string)$q), 0, 100) : null,
        ]);
    }

    public function actionPdf($id, $download = 0)
    {
        $a = $this->ausgabe($id);
        return Yii::$app->response->sendFile($a->pdfPfad, "Mitteilungsblatt-Uffenheim-{$a->name}.pdf", [
            'mimeType' => 'application/pdf',
            'inline' => !$download,
        ]);
    }

    public function actionBild($id)
    {
        $a = $this->ausgabe($id);
        if (!is_file($a->bildPfad)) {
            throw new NotFoundHttpException();
        }
        Yii::$app->response->headers->set('Cache-Control', 'private, max-age=86400');
        return Yii::$app->response->sendFile($a->bildPfad, "{$a->name}.jpg", ['mimeType' => 'image/jpeg', 'inline' => true]);
    }

    private function ausgabe($id): Ausgabe
    {
        $a = Ausgabe::findOne((int)$id);
        if ($a === null || !is_file($a->pdfPfad)) {
            throw new NotFoundHttpException('Diese Ausgabe ist nicht mehr im Archiv. Ältere Ausgaben gibt es im Rathaus.');
        }
        return $a;
    }

    /**
     * Seiten, auf denen alle Wörter vorkommen (Sortierung der Datenbank: Groß/klein und ä/a egal), neueste Ausgabe zuerst
     * @return array [['ausgabe' => Ausgabe, 'seite' => int, 'auszug' => string], …]
     */
    private function suchen(string $q): array
    {
        $woerter = array_slice(array_filter(preg_split('/\s+/u', $q), fn($w) => mb_strlen($w) >= 2), 0, 6);
        if (!$woerter) {
            return [];
        }
        $query = Seite::find()->alias('s')->innerJoin(Ausgabe::tableName() . ' a', 'a.id = s.ausgabe_id')
            ->orderBy(['a.jahr' => SORT_DESC, 'a.nr' => SORT_DESC, 's.seite' => SORT_ASC])->limit(60);
        foreach ($woerter as $w) {
            $query->andWhere(['like', 's.text', $w]);
        }
        $ausgaben = [];
        $treffer = [];
        foreach ($query->all() as $s) {
            $ausgaben[$s->ausgabe_id] ??= Ausgabe::findOne($s->ausgabe_id);
            $treffer[] = ['ausgabe' => $ausgaben[$s->ausgabe_id], 'seite' => $s->seite, 'auszug' => $this->auszug($s->text, $woerter)];
        }
        return $treffer;
    }

    /** Textstelle rund um das erste gefundene Wort */
    private function auszug(string $text, array $woerter): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        $pos = false;
        foreach ($woerter as $w) {
            if (($pos = mb_stripos($text, $w)) !== false) {
                break;
            }
        }
        $start = max(0, (int)$pos - 90);
        $auszug = mb_substr($text, $start, 220);
        return ($start > 0 ? '… ' : '') . $auszug . (mb_strlen($text) > $start + 220 ? ' …' : '');
    }
}
