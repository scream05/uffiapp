<?php

namespace humhub\modules\maengelmelder\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\models\Content;
use humhub\modules\maengelmelder\models\MaengelAuch;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\maengelmelder\models\Mangel;
use humhub\modules\maengelmelder\Module;
use humhub\modules\post\models\Post;
use humhub\modules\post\permissions\CreatePost;
use Yii;
use yii\helpers\Url;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Mangel mit Ort melden (wird ein normaler Beitrag im Space) und alle Meldungen auf einer Karte zeigen.
 * Der Ort steht als Link auf die Karte im Beitragstext: …/maengelmelder/meldung/karte?lat=…&lng=…
 *
 * @property Module $module
 */
class MeldungController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login' => ['create', 'bearbeiten', 'nahe', 'auch']], ['post' => ['auch']]]; // Karte auch für Gäste
    }

    public function actionCreate()
    {
        $space = $this->contentContainer;
        $canPost = $space->permissionManager->can(CreatePost::class);
        $data = ['kategorie' => '', 'beschreibung' => '', 'lat' => '', 'lng' => ''];
        $errors = [];

        // Vorbelegung per Link (z. B. vom KI-Assistenten); abgeschickt wird erst mit „Meldung absenden“
        if (!Yii::$app->request->isPost) {
            $get = Yii::$app->request;
            if (in_array($get->get('kategorie'), $this->module->categories, true)) {
                $data['kategorie'] = $get->get('kategorie');
            }
            $data['beschreibung'] = mb_substr(trim((string)$get->get('beschreibung', '')), 0, 3000);
            $lat = (float)$get->get('lat');
            $lng = (float)$get->get('lng');
            if ($lat && $lng && $this->module->inBounds($lat, $lng)) {
                $data['lat'] = (string)round($lat, 6);
                $data['lng'] = (string)round($lng, 6);
            }
        }

        if (Yii::$app->request->isPost && $canPost) {
            $data = array_merge($data, array_map('trim', (array)Yii::$app->request->post('Meldung', [])));
            $lat = (float)str_replace(',', '.', $data['lat']);
            $lng = (float)str_replace(',', '.', $data['lng']);
            if (!in_array($data['kategorie'], $this->module->categories, true)) {
                $errors[] = 'Bitte eine Kategorie wählen.';
            }
            if (mb_strlen($data['beschreibung']) < 5) {
                $errors[] = 'Bitte kurz beschreiben, was kaputt ist.';
            }
            if ($data['lat'] === '' || $data['lng'] === '') {
                $errors[] = 'Bitte den Ort auf der Karte markieren.';
            } elseif (!$this->module->inBounds($lat, $lng)) {
                $errors[] = 'Der markierte Ort liegt nicht im Stadtgebiet von Uffenheim.';
            }
            if (!$errors) {
                $post = new Post($space);
                $post->message = Mangel::message($space, $data['kategorie'], mb_substr($data['beschreibung'], 0, 3000), $lat, $lng);
                $post->content->visibility = $space->getDefaultContentVisibility();
                if ($post->save()) {
                    $post->fileManager->attach(Yii::$app->request->post('fileList'));
                    // Vom Bauhof angelegt: gleich zur Bearbeitung (Zuweisen usw.)
                    if (Yii::$app->request->get('bauhof') && MaengelStatus::canManage()) {
                        return $this->redirect($space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $post->content->id]));
                    }
                    return $this->redirect($post->content->getUrl());
                }
                $errors = array_merge(...array_values($post->getErrors()));
            }
        }

        return $this->render('create', [
            'space' => $space,
            'canPost' => $canPost,
            'data' => $data,
            'errors' => $errors,
            'module' => $this->module,
            'bearbeiten' => null,
        ]);
    }

    /** Kategorie, Beschreibung, Ort und Fotos einer Meldung ändern (Bauhof) */
    public function actionBearbeiten($id)
    {
        if (!MaengelStatus::canManage()) {
            throw new ForbiddenHttpException();
        }
        $space = $this->contentContainer;
        $content = Content::findOne(['id' => (int)$id, 'contentcontainer_id' => $space->contentcontainer_id, 'object_model' => Post::class]);
        $mangel = $content ? Mangel::fromContent($content) : null;
        if ($mangel === null) {
            throw new NotFoundHttpException();
        }
        $data = ['kategorie' => $mangel->kategorie, 'beschreibung' => $mangel->beschreibung,
            'lat' => (string)$mangel->lat, 'lng' => (string)$mangel->lng];
        $errors = [];
        if (Yii::$app->request->isPost) {
            $data = array_merge($data, array_map('trim', (array)Yii::$app->request->post('Meldung', [])));
            $lat = (float)str_replace(',', '.', $data['lat']);
            $lng = (float)str_replace(',', '.', $data['lng']);
            if (!in_array($data['kategorie'], $this->module->categories, true)) {
                $errors[] = 'Bitte eine Kategorie wählen.';
            }
            if (mb_strlen($data['beschreibung']) < 5) {
                $errors[] = 'Bitte kurz beschreiben, was kaputt ist.';
            }
            if (!$this->module->inBounds($lat, $lng)) {
                $errors[] = 'Der markierte Ort liegt nicht im Stadtgebiet von Uffenheim.';
            }
            if (!$errors) {
                $post = $mangel->post;
                // Adresse nur neu ermitteln, wenn der Ort verschoben wurde
                $gleicherOrt = abs($lat - $mangel->lat) < 1e-6 && abs($lng - $mangel->lng) < 1e-6 && $mangel->adresse !== '';
                $post->message = Mangel::message($space, $data['kategorie'], mb_substr($data['beschreibung'], 0, 3000), $lat, $lng,
                    $gleicherOrt ? $mangel->adresse : null);
                if ($post->save()) {
                    $post->fileManager->attach(Yii::$app->request->post('fileList'));
                    $this->view->saved();
                    return $this->redirect($space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $content->id]));
                }
                $errors = array_merge(...array_values($post->getErrors()));
            }
        }
        return $this->render('create', [
            'space' => $space,
            'canPost' => true,
            'data' => $data,
            'errors' => $errors,
            'module' => $this->module,
            'bearbeiten' => $mangel,
        ]);
    }

    public function actionKarte($lat = null, $lng = null)
    {
        return $this->render('karte', [
            'markers' => $this->meldungen(),
            'focus' => $lat !== null && $lng !== null ? [(float)$lat, (float)$lng] : null,
            'module' => $this->module,
        ]);
    }

    /** Offene Meldungen im Umkreis (für „Schon gemeldet?“ beim Melden), als JSON, nächste zuerst */
    public function actionNahe($lat, $lng)
    {
        $lat = (float)$lat;
        $lng = (float)$lng;
        $near = [];
        foreach ($this->meldungen() as $m) {
            if (in_array($m['status'], MaengelStatus::CLOSED, true)) {
                continue;
            }
            $m['entfernung'] = (int)round(self::distance($lat, $lng, $m['lat'], $m['lng']));
            if ($m['entfernung'] <= $this->module->nearRadius) {
                $near[] = $m;
            }
        }
        usort($near, fn($a, $b) => $a['entfernung'] <=> $b['entfernung']);
        return $this->asJson(array_slice($near, 0, 5));
    }

    /** „Betrifft mich auch“ setzen oder zurücknehmen, danach zur Meldung */
    public function actionAuch($id)
    {
        $content = Content::findOne(['id' => (int)$id, 'contentcontainer_id' => $this->contentContainer->contentcontainer_id,
            'object_model' => Post::class]);
        if ($content === null || !$content->canView()) {
            throw new NotFoundHttpException();
        }
        $on = MaengelAuch::toggle($content, Yii::$app->user->id);
        $this->view->success($on ? 'Danke! Wir haben vermerkt, dass Sie das auch betrifft.' : 'Vermerk zurückgenommen.');
        return $this->redirect($content->getUrl());
    }

    /**
     * Alle lesbaren Meldungen dieses Space mit Ort, Status und Zahl der Betroffenen.
     * Gäste sehen alle veröffentlichten Meldungen (sie sind privat), aber nur Ort, Titel, Datum und Status –
     * Text, Melder, Fotos und Kommentare erst nach Anmeldung.
     */
    private function meldungen(): array
    {
        $markers = [];
        $gast = Yii::$app->user->isGuest;
        $query = Post::find()->contentContainer($this->contentContainer);
        $gast ? $query->andWhere(['content.state' => \humhub\modules\content\models\Content::STATE_PUBLISHED]) : $query->readable();
        $posts = $query
            ->andWhere(['like', 'post.message', 'maengelmelder/meldung/karte?lat='])
            ->orderBy(['content.created_at' => SORT_DESC])->limit(500)->all();
        $ids = array_map(fn($p) => $p->content->id, $posts);
        $statuses = MaengelStatus::find()->where(['content_id' => $ids])->indexBy('content_id')->all();
        $auch = MaengelAuch::counts($ids);
        $mine = MaengelAuch::mine($ids);
        foreach ($posts as $post) {
            $id = $post->content->id;
            $status = $statuses[$id] ?? new MaengelStatus(['status' => 'gemeldet']);
            if (!preg_match('/karte\?lat=(-?[\d.]+)&(?:amp;)?lng=(-?[\d.]+)/', $post->message, $m)) {
                continue;
            }
            preg_match('/^\*\*Mängelmeldung: (.+?)\*\*\s*(.*?)\s*📍/su', $post->message, $k);
            $markers[] = [
                'id' => $id,
                'lat' => (float)$m[1],
                'lng' => (float)$m[2],
                'titel' => $k[1] ?? 'Meldung',
                'auszug' => $gast ? '' : mb_strimwidth(preg_replace('/\s+/u', ' ', $k[2] ?? ''), 0, 140, '…'),
                'datum' => Yii::$app->formatter->asDate($post->content->created_at, 'short'),
                'url' => $gast ? '' : $post->content->getUrl(),
                'status' => $status->status,
                'statusLabel' => $status->getLabel(),
                'farbe' => $status->getColor(),
                'auch' => $auch[$id] ?? 0,
                'auchMeins' => in_array($id, $mine, true),
                'eigene' => !$gast && (int)$post->content->created_by === (int)Yii::$app->user->id,
                'auchUrl' => $this->contentContainer->createUrl('/maengelmelder/meldung/auch', ['id' => $id]),
            ];
        }
        return $markers;
    }

    /** Entfernung in Metern (Haversine) */
    private static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
