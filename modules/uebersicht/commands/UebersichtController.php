<?php

namespace humhub\modules\uebersicht\commands;

use humhub\modules\content\models\Content;
use humhub\modules\uebersicht\Module;
use yii\console\Controller;

/**
 * Angepinnte Beiträge der Übersicht: /app/yii uebersicht/liste | anpinnen <content-id> | loesen <content-id>
 * „Meine Übersicht“: uebersicht/uebernehmen (HumHubs „nicht in der Übersicht“ je Mitgliedschaft übernehmen),
 * uebersicht/pruefen <benutzername> (Herkunft der ersten 40 Beiträge der Übersicht dieser Person)
 */
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

    public function actionUebernehmen()
    {
        $module = \Yii::$app->getModule('uebersicht');
        foreach (\humhub\modules\space\models\Membership::find()->where(['show_at_dashboard' => 0])->all() as $m) {
            if (!$m->user || !$m->space || in_array($m->space->url, $module->ohneSchalter, true)) {
                continue;
            }
            $aus = $module->getAusgeblendet($m->user);
            if (!in_array($m->space->guid, $aus, true)) {
                $aus[] = $m->space->guid;
                $module->setAusgeblendet($m->user, $aus);
                $this->stdout("{$m->user->username}: {$m->space->name} ausgeblendet\n");
            }
        }
    }

    public function actionPruefen($username)
    {
        $user = \humhub\modules\user\models\User::findOne(['username' => $username]);
        if (!$user) {
            $this->stderr("Benutzer nicht gefunden\n");
            return 1;
        }
        \Yii::$app->user->switchIdentity($user);
        $module = \Yii::$app->getModule('uebersicht');
        \Yii::$app->getModule('dashboard')->memberFilterClass = \humhub\modules\uebersicht\filters\MeineUebersicht::class;
        $this->stdout('Ausgeblendet: ' . implode(', ', array_map(fn($g) => \humhub\modules\space\models\Space::findOne(['guid' => $g])?->name ?? $g,
                $module->getAusgeblendet($user))) . "\n");
        $q = new \humhub\modules\dashboard\stream\DashboardStreamQuery(['user' => $user, 'limit' => 40, 'activity' => false]);
        $zaehler = [];
        foreach ($q->all() as $c) {
            $name = $c->container ? $c->container->getDisplayName() : '(global)';
            $zaehler[$name] = ($zaehler[$name] ?? 0) + 1;
        }
        foreach ($zaehler as $name => $n) {
            $this->stdout("  $name: $n\n");
        }
        return 0;
    }
}
