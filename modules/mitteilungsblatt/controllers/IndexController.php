<?php

namespace humhub\modules\mitteilungsblatt\controllers;

use humhub\components\access\ControllerAccess;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\mitteilungsblatt\components\Termine;
use humhub\modules\mitteilungsblatt\models\Ausgabe;
use humhub\modules\mitteilungsblatt\models\Bekanntmachung;
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
            throw new ForbiddenHttpException(Yii::t('MitteilungsblattModule.base', 'Das Mitteilungsblatt ist noch nicht freigegeben.'));
        }
        // Lesbar für alle, aber nicht in Suchmaschinen (Geburtstage, Jubilare, Anzeigen Dritter)
        Yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        if ($this->contentContainer !== null && $this->contentContainer->url !== $this->module->space) {
            throw new NotFoundHttpException();
        }
        if ($this->contentContainer === null && in_array($action->id, ['index', 'lesen', 'bekanntmachungen'], true)
            && ($space = Space::findOne(['url' => $this->module->space])) !== null) {
            $this->redirect($space->createUrl($action->id === 'index' ? '/mitteilungsblatt/index' : '/mitteilungsblatt/index/' . $action->id, Yii::$app->request->get()));
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
        $this->view->setPageTitle(Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt'));
        $q = trim(mb_substr((string)$q, 0, 100));
        return $this->render('index', [
            'ausgaben' => Ausgabe::find()->orderBy(['jahr' => SORT_DESC, 'nr' => SORT_DESC])->all(),
            'q' => $q,
            'treffer' => mb_strlen($q) >= 3 ? $this->suchen($q) : null,
            'hinweis' => Termine::hinweis($this->module, true) ?? $this->module->settings->get('hinweis'),
            'termineUrl' => $this->module->settings->get('termine_url'),
            'quelle' => $this->module->quelle,
        ]);
    }

    /**
     * Verzeichnis der amtlichen Bekanntmachungen (nichtamtlich, verlinkt auf die Originalseite im Amtsblatt).
     * Filter: ?art=…, ?jahr=…, ?frist=1 (nur laufende Fristen), ?q= (Titel), ?hinweise=1 (auch Hinweise der Verwaltung)
     */
    public function actionBekanntmachungen($art = '', $jahr = '', $frist = 0, $q = '', $hinweise = 0)
    {
        if (!$this->module->darfBekanntmachungenSehen()) {
            throw new ForbiddenHttpException(Yii::t('MitteilungsblattModule.base', 'Diese Seite ist noch nicht freigegeben.'));
        }
        $this->view->setPageTitle(Yii::t('MitteilungsblattModule.base', 'Amtliche Bekanntmachungen'));
        $admin = Yii::$app->user->isAdmin();
        $query = Bekanntmachung::find()->alias('b')->innerJoinWith('ausgabe a')
            ->orderBy(['a.datum' => SORT_DESC, 'b.seite' => SORT_ASC, 'b.id' => SORT_ASC]);
        if (!$admin) {
            $query->andWhere(['b.ausgeblendet' => 0]);
        }
        $art = in_array($art, Bekanntmachung::ARTEN, true) ? $art : '';
        if ($art !== '') {
            $query->andWhere(['b.art' => $art]);
        } elseif (!$hinweise) {
            $query->andWhere(['not in', 'b.art', Bekanntmachung::NUR_AUF_WUNSCH]);
        }
        $jahr = preg_match('/^\d{4}$/', (string)$jahr) ? (int)$jahr : '';
        if ($jahr !== '') {
            $query->andWhere(['a.jahr' => $jahr]);
        }
        if ($frist) {
            $query->andWhere(['>=', 'b.frist_bis', date('Y-m-d')]);
        }
        $q = trim(mb_substr((string)$q, 0, 100));
        foreach (array_slice(array_filter(preg_split('/\s+/u', $q), fn($w) => mb_strlen($w) >= 2), 0, 6) as $w) {
            $query->andWhere(['or', ['like', 'b.titel', $w], ['like', 'b.stelle', $w]]);
        }
        return $this->render('bekanntmachungen', [
            'liste' => $query->limit(300)->all(),
            'jahre' => Ausgabe::find()->alias('a')->select('a.jahr')->distinct()
                ->innerJoin(Bekanntmachung::tableName() . ' b', 'b.ausgabe_id = a.id')->orderBy(['a.jahr' => SORT_DESC])->column(),
            'laufend' => Bekanntmachung::find()->where(['>=', 'frist_bis', date('Y-m-d')])->andWhere($admin ? [] : ['ausgeblendet' => 0])
                ->andWhere($hinweise ? [] : ['not in', 'art', Bekanntmachung::NUR_AUF_WUNSCH])->count(),
            'art' => $art, 'jahr' => $jahr, 'frist' => (bool)$frist, 'q' => $q, 'hinweise' => (bool)$hinweise,
            'admin' => $admin,
            'frei' => $this->module->bekanntmachungenFrei(),
            'geprueft' => Ausgabe::find()->where(['not', ['bm_geprueft' => null]])->count(),
            'ausgaben' => Ausgabe::find()->count(),
        ]);
    }

    /** Admins: falsch erkannte Bekanntmachung aus- bzw. wieder einblenden */
    public function actionBekanntmachungUmschalten($id)
    {
        $this->forcePostRequest();
        if (!Yii::$app->user->isAdmin()) {
            throw new ForbiddenHttpException();
        }
        $b = Bekanntmachung::findOne((int)$id) ?? throw new NotFoundHttpException();
        $b->updateAttributes(['ausgeblendet' => $b->ausgeblendet ? 0 : 1]);
        return $this->redirect(Yii::$app->request->referrer ?: $this->url('/mitteilungsblatt/index/bekanntmachungen'));
    }

    public function actionLesen($id, $seite = null, $q = null)
    {
        $this->view->setPageTitle(Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt'));
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
            throw new NotFoundHttpException(Yii::t('MitteilungsblattModule.base', 'Diese Ausgabe ist nicht mehr im Archiv. Ältere Ausgaben gibt es im Rathaus.'));
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
