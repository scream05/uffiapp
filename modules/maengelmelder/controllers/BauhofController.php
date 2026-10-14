<?php

namespace humhub\modules\maengelmelder\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\models\Content;
use humhub\modules\maengelmelder\components\Bauhof;
use humhub\modules\maengelmelder\components\Buergerinfo;
use humhub\modules\maengelmelder\models\Auftrag;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\maengelmelder\models\MaengelAuch;
use humhub\modules\maengelmelder\models\MaengelKontakt;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\maengelmelder\models\Mangel;
use humhub\modules\maengelmelder\models\Mitarbeiter;
use humhub\modules\maengelmelder\Module;
use humhub\modules\post\models\Post;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Bauhof-Seite im Space Mängelmelder (nur Bauhofleitung: Recht „Mängelstatus bearbeiten“ oder Systemadmin):
 * Meldungen filtern, bearbeiten, Mitarbeitern zuweisen, per WhatsApp mit ihnen schreiben, Mitarbeiter verwalten.
 *
 * @property Module $module
 */
class BauhofController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login'], ['post' => ['mitarbeiter-loeschen', 'loeschen', 'senden', 'freigeben']]];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!MaengelStatus::canManage()) {
            throw new ForbiddenHttpException('Nur für den Bauhof.');
        }
        return true;
    }

    /** Liste bzw. Karte aller Meldungen mit Filtern */
    public function actionIndex($status = 'offen', $mitarbeiter = '', $kategorie = '', $q = '', $ansicht = 'liste')
    {
        // Entwürfe = Gastmeldungen, die noch auf Freigabe warten
        $posts = Mangel::find($this->contentContainer)->andWhere(['content.state' => [Content::STATE_PUBLISHED, Content::STATE_DRAFT]])
            ->orderBy(['content.created_at' => SORT_DESC])->limit(1000)->all();
        $ids = array_map(fn(Post $p) => (int)$p->content->id, $posts);
        $statuses = MaengelStatus::find()->where(['content_id' => $ids])->indexBy('content_id')->all();
        $auftraege = Auftrag::find()->where(['content_id' => $ids])->with('mitarbeiter')->indexBy('content_id')->all();
        $auch = MaengelAuch::counts($ids);
        $ungelesen = BauhofNachricht::find()->select(['n' => 'COUNT(*)', 'content_id'])
            ->where(['richtung' => BauhofNachricht::EIN, 'gelesen_at' => null, 'content_id' => $ids])
            ->groupBy('content_id')->indexBy('content_id')->column();
        $letzte = BauhofNachricht::find()->select(['m' => 'MAX(created_at)', 'content_id'])
            ->where(['content_id' => $ids])->andWhere(['not', ['typ' => 'teil']])
            ->groupBy('content_id')->indexBy('content_id')->column();

        $zeilen = [];
        $zaehler = ['pruefen' => 0, 'offen' => 0, 'alle' => 0] + array_fill_keys(array_keys(MaengelStatus::STATUSES), 0);
        foreach ($posts as $post) {
            $mangel = new Mangel($post);
            $id = $mangel->getContentId();
            $st = $statuses[$id] ?? new MaengelStatus(['status' => 'gemeldet']);
            $a = $auftraege[$id] ?? null;
            $entwurf = (int)$post->content->state === Content::STATE_DRAFT;
            $zaehler['alle']++;
            $istOffen = !in_array($st->status, MaengelStatus::CLOSED, true);
            if ($entwurf) {
                $zaehler['pruefen']++;
            } else {
                $zaehler[$st->status] = ($zaehler[$st->status] ?? 0) + 1;
            }
            if ($istOffen) {
                $zaehler['offen']++;
            }
            if (($status === 'offen' && !$istOffen) || ($status === 'pruefen' && !$entwurf)
                || (!in_array($status, ['offen', 'alle', 'pruefen'], true) && ($entwurf || $st->status !== $status))) {
                continue;
            }
            if ($mitarbeiter === '0' && $a?->mitarbeiter_id) {
                continue;
            }
            if ($mitarbeiter !== '' && $mitarbeiter !== '0' && (int)$a?->mitarbeiter_id !== (int)$mitarbeiter) {
                continue;
            }
            if ($kategorie !== '' && $mangel->kategorie !== $kategorie) {
                continue;
            }
            if ($q !== '' && !str_contains(mb_strtolower("$id {$mangel->kategorie} {$mangel->beschreibung} {$mangel->adresse}"), mb_strtolower(trim($q)))) {
                continue;
            }
            $zeilen[] = [
                'mangel' => $mangel,
                'status' => $st,
                'auftrag' => $a,
                'auch' => $auch[$id] ?? 0,
                'ungelesen' => (int)($ungelesen[$id] ?? 0),
                'aktivitaet' => max($letzte[$id] ?? '', $st->updated_at ?? '', $post->content->created_at),
                'bilder' => count($mangel->getBilder()),
                'entwurf' => $entwurf,
            ];
        }
        // Dringendes und Überfälliges nach oben, dann neueste Aktivität
        $rang = fn($z) => ($z['entwurf'] ? 5 : 0) + ($z['auftrag']?->prioritaet === 'dringend' ? 2 : ($z['auftrag']?->prioritaet === 'hoch' ? 1 : 0))
            + ($z['auftrag']?->faellig && $z['auftrag']->faellig < date('Y-m-d') && !in_array($z['status']->status, MaengelStatus::CLOSED, true) ? 3 : 0);
        usort($zeilen, fn($x, $y) => [$rang($y), $y['aktivitaet']] <=> [$rang($x), $x['aktivitaet']]);

        return $this->render('index', [
            'space' => $this->contentContainer,
            'zeilen' => $zeilen,
            'zaehler' => $zaehler,
            'filter' => compact('status', 'mitarbeiter', 'kategorie', 'q', 'ansicht'),
            'mitarbeiterListe' => Mitarbeiter::find()->orderBy('name')->all(),
            'module' => $this->module,
        ]);
    }

    /** Eine Meldung: Angaben, Zuweisung, Status, Verlauf und Chat */
    public function actionMangel($id)
    {
        [$content, $mangel] = $this->mangel($id);
        $auftrag = Auftrag::forContentId($content->id);
        $status = MaengelStatus::forContent($content);
        $request = Yii::$app->request;
        $user = Yii::$app->user->getIdentity();

        $entwurf = (int)$content->state === Content::STATE_DRAFT;

        if ($request->isPost && $entwurf && $request->post('aktion') !== 'buerger') {
            $this->view->warn('Bitte die Gastmeldung zuerst freigeben.');
            return $this->redirect($this->zu('mangel', ['id' => $content->id]));
        }
        if ($request->isPost) {
            $aktion = $request->post('aktion');
            if ($aktion === 'speichern') {
                $alterMitarbeiter = $auftrag->mitarbeiter_id;
                $vorher = ['prioritaet' => $auftrag->prioritaet, 'faellig' => $auftrag->faellig, 'hinweis' => $auftrag->hinweis];
                $auftrag->load($request->post());
                $neuerMitarbeiter = $auftrag->mitarbeiter_id ? (int)$auftrag->mitarbeiter_id : null;
                $auftrag->mitarbeiter_id = $alterMitarbeiter; // Wechsel macht Bauhof::zuweisen()
                if ($auftrag->validate()) {
                    $auftrag->save(false);
                    $fehler = Bauhof::zuweisen($auftrag, $neuerMitarbeiter, $user, (bool)$request->post('senden'));
                    $hinweisStatus = trim((string)$request->post('hinweis_status'));
                    $statusNeu = Bauhof::statusSetzen($content, (string)$request->post('status'), $hinweisStatus, $user, null, false);
                    // Gleicher Mitarbeiter: alle Änderungen in einer WhatsApp (ein neuer bekommt ohnehin den ganzen Auftrag)
                    if ($neuerMitarbeiter && (int)$alterMitarbeiter === $neuerMitarbeiter) {
                        $teile = [];
                        $was = [];
                        if ($statusNeu) {
                            $teile[] = Bauhof::statusZeile(MaengelStatus::forContent($content), $hinweisStatus);
                            $was[] = 'Status';
                        }
                        if ($auftrag->prioritaet !== $vorher['prioritaet']) {
                            $teile[] = '⚡ *Priorität:* ' . $auftrag->getPrioritaetLabel();
                            $was[] = 'Priorität';
                        }
                        if ((string)$auftrag->faellig !== (string)$vorher['faellig']) {
                            $teile[] = '📅 *Erledigen bis:* ' . ($auftrag->faellig ? date('d.m.Y', strtotime($auftrag->faellig)) : 'keine Frist mehr');
                            $was[] = 'Frist';
                        }
                        if ((string)$auftrag->hinweis !== (string)$vorher['hinweis']) {
                            $teile[] = '💬 *Hinweis:* ' . ($auftrag->hinweis ?: '(entfernt)');
                            $was[] = 'Hinweis';
                        }
                        $fehler = Bauhof::meldeAenderung($content->id, $teile, implode(', ', $was), $user) ?? $fehler;
                    }
                    $fehler ? $this->view->error('Gespeichert, aber WhatsApp-Versand fehlgeschlagen: ' . $fehler) : $this->view->saved();
                    return $this->redirect($this->zu('mangel', ['id' => $content->id]));
                }
            } elseif ($aktion === 'chat') {
                $text = trim(mb_substr((string)$request->post('text'), 0, 2000));
                $m = $auftrag->mitarbeiter;
                if ($text !== '' && $m) {
                    $fehler = Bauhof::nachricht($m, $content->id, $text, $user);
                    $fehler ? $this->view->error($fehler) : null;
                }
                if ($request->isAjax) {
                    return $this->renderVerlauf(['content_id' => $content->id]);
                }
                return $this->redirect($this->zu('mangel', ['id' => $content->id]) . '#verlauf');
            } elseif ($aktion === 'buerger') {
                $text = trim(mb_substr((string)$request->post('text'), 0, 1500));
                if ($text !== '') {
                    $fehler = Buergerinfo::nachricht($content, $text, $user);
                    $fehler ? $this->view->error($fehler) : null;
                }
                if ($request->isAjax) {
                    return $this->renderVerlauf(['content_id' => $content->id]);
                }
                return $this->redirect($this->zu('mangel', ['id' => $content->id]) . '#verlauf');
            } elseif ($aktion === 'senden' && $auftrag->mitarbeiter) {
                $fehler = Bauhof::sendeAuftrag($auftrag, $user);
                $fehler ? $this->view->error($fehler) : $this->view->success('Auftrag per WhatsApp geschickt.');
                return $this->redirect($this->zu('mangel', ['id' => $content->id]));
            }
        }

        BauhofNachricht::updateAll(['gelesen_at' => date('Y-m-d H:i:s')],
            ['content_id' => $content->id, 'richtung' => BauhofNachricht::EIN, 'gelesen_at' => null]);

        return $this->render('mangel', [
            'space' => $this->contentContainer,
            'mangel' => $mangel,
            'auftrag' => $auftrag,
            'status' => $status,
            'auch' => MaengelAuch::counts([$content->id])[$content->id] ?? 0,
            'mitarbeiterListe' => Mitarbeiter::aktive(),
            'verlauf' => $this->verlauf(['content_id' => $content->id]),
            'kontakt' => MaengelKontakt::findOne($content->id),
            'entwurf' => $entwurf,
            'buergerWeg' => Buergerinfo::weg($content),
        ]);
    }

    /** Gastmeldung freigeben: erscheint dann im Space und auf der Karte */
    public function actionFreigeben($id)
    {
        [$content] = $this->mangel($id);
        if ((int)$content->state === Content::STATE_DRAFT) {
            $content->getStateService()->publish();
            $this->gastHinweiseEntfernen($content->id);
            Buergerinfo::freigegeben($content);
            $this->view->success('Meldung freigegeben. Sie erscheint jetzt auf der Karte.');
        }
        return $this->redirect($this->zu('mangel', ['id' => $content->id]));
    }

    /** Foto einer Meldung ausliefern (auch bei Gastmeldungen, die noch nicht freigegeben sind) */
    public function actionFoto($id, $guid)
    {
        [, $mangel] = $this->mangel($id);
        foreach ($mangel->getBilder() as $file) {
            if ($file->guid === $guid && is_file($pfad = $file->getStore()->get())) {
                Yii::$app->response->headers->set('Cache-Control', 'private, max-age=86400');
                return \humhub\modules\maengelmelder\components\DateiAuslieferung::senden($pfad, $file->file_name);
            }
        }
        throw new NotFoundHttpException();
    }

    /** Verlauf neu laden (alle 20 s per JavaScript) */
    public function actionVerlauf($id = null, $mitarbeiter = null)
    {
        $where = $id ? ['content_id' => (int)$id] : ['mitarbeiter_id' => (int)$mitarbeiter];
        BauhofNachricht::updateAll(['gelesen_at' => date('Y-m-d H:i:s')], $where + ['richtung' => BauhofNachricht::EIN, 'gelesen_at' => null]);
        return $this->renderVerlauf($where);
    }

    /** Chat mit einem Mitarbeiter (alle Aufträge) */
    public function actionChat($id)
    {
        $m = Mitarbeiter::findOne((int)$id) ?? throw new NotFoundHttpException();
        $request = Yii::$app->request;
        if ($request->isPost) {
            $text = trim(mb_substr((string)$request->post('text'), 0, 2000));
            $contentId = (int)$request->post('content_id') ?: null;
            if ($text !== '') {
                $fehler = Bauhof::nachricht($m, $contentId, $text, Yii::$app->user->getIdentity());
                $fehler ? $this->view->error($fehler) : null;
            }
            if ($request->isAjax) {
                return $this->renderVerlauf(['mitarbeiter_id' => $m->id]);
            }
            return $this->redirect($this->zu('chat', ['id' => $m->id]));
        }
        BauhofNachricht::updateAll(['gelesen_at' => date('Y-m-d H:i:s')],
            ['mitarbeiter_id' => $m->id, 'richtung' => BauhofNachricht::EIN, 'gelesen_at' => null]);
        $offen = [];
        foreach ($m->getOffeneAuftraege() as $a) {
            $c = Content::findOne($a->content_id);
            if ($c && ($mg = Mangel::fromContent($c))) {
                $offen[$a->content_id] = "Nr. {$a->content_id} – {$mg->kategorie}, {$mg->getOrt()}";
            }
        }
        return $this->render('chat', [
            'space' => $this->contentContainer,
            'mitarbeiter' => $m,
            'offen' => $offen,
            'verlauf' => $this->verlauf(['mitarbeiter_id' => $m->id]),
        ]);
    }

    /** Mitarbeiter verwalten */
    public function actionMitarbeiter($id = null)
    {
        $model = $id ? (Mitarbeiter::findOne((int)$id) ?? throw new NotFoundHttpException()) : new Mitarbeiter(['aktiv' => 1]);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->view->saved();
            return $this->redirect($this->zu('mitarbeiter', []));
        }
        $offen = [];
        foreach (Auftrag::find()->alias('a')->select(['a.mitarbeiter_id', 'n' => 'COUNT(*)'])
                     ->leftJoin('maengel_status s', 's.content_id = a.content_id')
                     ->innerJoin('content c', 'c.id = a.content_id AND c.state = 1')
                     ->where(['not', ['a.mitarbeiter_id' => null]])
                     ->andWhere(['or', ['s.status' => null], ['not in', 's.status', MaengelStatus::CLOSED]])
                     ->groupBy('a.mitarbeiter_id')->asArray()->all() as $row) {
            $offen[$row['mitarbeiter_id']] = (int)$row['n'];
        }
        return $this->render('mitarbeiter', [
            'space' => $this->contentContainer,
            'model' => $model,
            'liste' => Mitarbeiter::find()->orderBy(['aktiv' => SORT_DESC, 'name' => SORT_ASC])->all(),
            'offen' => $offen,
            'ungelesen' => BauhofNachricht::find()->select(['n' => 'COUNT(*)', 'mitarbeiter_id'])
                ->where(['richtung' => BauhofNachricht::EIN, 'gelesen_at' => null])->groupBy('mitarbeiter_id')->indexBy('mitarbeiter_id')->column(),
            'module' => $this->module,
        ]);
    }

    /** Mitarbeiter löschen: Nummer weg, Verlauf und Fotos bleiben bei den Meldungen (ohne Namen) */
    public function actionMitarbeiterLoeschen($id)
    {
        $m = Mitarbeiter::findOne((int)$id) ?? throw new NotFoundHttpException();
        $m->delete();
        $this->view->success('Mitarbeiter gelöscht.');
        return $this->redirect($this->zu('mitarbeiter', []));
    }

    /** Meldung löschen (z. B. Spam oder doppelt); abgelehnte Gastmeldungen samt Fotos und Kontaktdaten endgültig */
    public function actionLoeschen($id)
    {
        [$content, $mangel] = $this->mangel($id);
        if ((int)$content->state === Content::STATE_DRAFT) {
            $mangel->post->hardDelete();
            $this->gastHinweiseEntfernen($content->id);
            $this->view->success('Gastmeldung abgelehnt und gelöscht.');
            return $this->redirect($this->zu('index', []));
        }
        $content->softDelete();
        MaengelKontakt::deleteAll(['content_id' => $content->id]);
        $this->view->success('Meldung gelöscht.');
        return $this->redirect($this->zu('index', []));
    }

    /** Foto/Datei aus WhatsApp ausliefern (nur Bauhof) */
    public function actionDatei($id)
    {
        $n = BauhofNachricht::findOne((int)$id);
        $file = $n?->file;
        if (!$file || $file->object_model !== BauhofNachricht::class || (int)$file->object_id !== (int)$n->id) {
            throw new NotFoundHttpException();
        }
        $pfad = $file->getStore()->get();
        if (!is_file($pfad)) {
            throw new NotFoundHttpException();
        }
        Yii::$app->response->headers->set('Cache-Control', 'private, max-age=86400');
        return \humhub\modules\maengelmelder\components\DateiAuslieferung::senden($pfad, $file->file_name);
    }

    /** Glocken-Hinweise „Gastmeldung wartet auf Freigabe“ bei allen Empfängern entfernen */
    private function gastHinweiseEntfernen(int $contentId): void
    {
        \humhub\modules\notification\models\Notification::deleteAll([
            'class' => \humhub\modules\maengelmelder\notifications\GastmeldungNeu::class,
            'source_class' => MaengelKontakt::class,
            'source_pk' => $contentId,
        ]);
    }

    private function zu(string $aktion, array $params = []): string
    {
        return $this->contentContainer->createUrl('/maengelmelder/bauhof/' . $aktion, $params);
    }

    /** @return array [Content, Mangel] */
    private function mangel($id): array
    {
        $content = Content::findOne(['id' => (int)$id, 'contentcontainer_id' => $this->contentContainer->contentcontainer_id,
            'object_model' => Post::class, 'state' => [Content::STATE_PUBLISHED, Content::STATE_DRAFT]]);
        $mangel = $content ? Mangel::fromContent($content) : null;
        if (!$mangel) {
            throw new NotFoundHttpException('Meldung nicht gefunden.');
        }
        return [$content, $mangel];
    }

    private function verlauf(array $where): array
    {
        return BauhofNachricht::find()->where($where)->andWhere(['not', ['typ' => ['teil', 'knopf']]])
            ->with(['mitarbeiter', 'user', 'file'])->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])->all();
    }

    private function renderVerlauf(array $where): string
    {
        return $this->renderPartial('_verlauf', ['verlauf' => $this->verlauf($where), 'space' => $this->contentContainer,
            'mitAuftrag' => isset($where['mitarbeiter_id'])]);
    }
}
