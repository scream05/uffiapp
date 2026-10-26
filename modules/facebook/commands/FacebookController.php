<?php

namespace humhub\modules\facebook\commands;

use humhub\modules\activity\models\Activity;
use humhub\modules\facebook\components\Api;
use humhub\modules\facebook\components\Import;
use humhub\modules\facebook\Module;
use humhub\modules\space\activities\MemberAddedActivity;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * /app/yii facebook/konto              Konto „Stadt Uffenheim (Facebook)“ anlegen (einmalig)
 * /app/yii facebook/token <token> [page_id]  dauerhaftes Seiten-Token aus kurzlebigem Nutzer-Token
 * /app/yii facebook/pruefen            Zugang testen (Token, Rechte, Seite, letzte Beiträge)
 * /app/yii facebook/abrufen [1]        Beiträge übernehmen (1 = Probelauf, nichts speichern) – per Crontab stündlich
 * /app/yii facebook/beispiel <datei> [1]  Beiträge aus einer JSON-Datei (Format der Graph-API) übernehmen, zum Testen
 */
class FacebookController extends Controller
{
    private function modul(): Module
    {
        return Yii::$app->getModule('facebook');
    }

    /** Verfasser-Konto wie „Stadt Uffenheim (Website)“: ohne Passwort, ohne Mails, nur im Space Rathaus */
    public function actionKonto()
    {
        $m = $this->modul();
        $user = User::findOne(['username' => $m->konto]);
        if ($user === null) {
            $user = new User(['scenario' => 'editAdmin']);
            $user->username = $m->konto;
            $user->email = 'stadt-uffenheim@facebook.invalid';
            $user->status = User::STATUS_ENABLED;
            if (!$user->save()) {
                $this->stderr('Konto: ' . json_encode($user->getErrors()) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("+ Konto {$m->konto}\n");
        }
        $user->profile->scenario = 'editAdmin';
        $user->profile->firstname = 'Stadt Uffenheim';
        $user->profile->lastname = '(Facebook)';
        $user->profile->about = 'Beiträge der Facebook-Seite der Stadt Uffenheim, mit Zustimmung der Stadt automatisch übernommen. '
            . 'Dieses Konto wird nicht betreut – Fragen bitte direkt an die Stadtverwaltung.';
        $user->profile->save(false);

        // Profilbild vom Website-Konto übernehmen
        $web = User::findOne(['username' => 'stadt-uffenheim']);
        $bild = $web?->getProfileImage();
        if ($bild && $bild->hasImage() && is_file($bild->getPath('_org'))) {
            $user->getProfileImage()->setNew($bild->getPath('_org'));
            $this->stdout("Profilbild vom Website-Konto übernommen\n");
        }

        // Nur Web-Benachrichtigungen, keine Mails/Push und keine Zusammenfassung
        $settings = Yii::$app->getModule('notification')->settings->user($user);
        foreach (Yii::$app->notification->getNotificationCategories($user) as $category) {
            foreach (Yii::$app->notification->getTargets($user) as $target) {
                if ($target->id !== 'web') {
                    $settings->set($target->getSettingKey($category), 0);
                }
            }
        }
        Yii::$app->getModule('activity')->settings->user($user)->set('mailSummaryInterval', 0);

        $space = Space::findOne(['url' => $m->space]);
        if (!$space->isMember($user->id)) {
            $space->addMember($user->id, 1, false);
            $this->stdout("+ Mitglied in {$space->name}\n");
        }
        // Neue Konten landen automatisch in weiteren Spaces – nur Rathaus behalten, „ist beigetreten“ entfernen
        foreach (Membership::find()->where(['user_id' => $user->id])->andWhere(['!=', 'space_id', $space->id])->all() as $ms) {
            $ms->delete();
            $this->stdout("- Mitglied in {$ms->space->name}\n");
        }
        foreach (Activity::find()->where(['created_by' => $user->id, 'class' => MemberAddedActivity::class])->all() as $activity) {
            $activity->delete();
        }
        return ExitCode::OK;
    }

    public function actionPruefen()
    {
        $m = $this->modul();
        $api = new Api($m);
        if ($m->zugang('page_id') === null || $m->zugang('token') === null) {
            $this->stderr("secrets/facebook.json: page_id und token eintragen, danach docker compose up -d\n");
            return ExitCode::CONFIG;
        }
        try {
            $info = $api->tokenInfo($m->zugang('token'));
            $this->stdout('Token: ' . (!empty($info['is_valid']) ? 'gültig' : 'UNGÜLTIG') . ', Typ ' . ($info['type'] ?? '?')
                . ', läuft ab: ' . (empty($info['expires_at']) ? 'nie' : date('d.m.Y H:i', $info['expires_at']))
                . "\nRechte: " . implode(', ', $info['scopes'] ?? []) . "\n");
            $seite = $api->seite();
            $this->stdout("Seite: {$seite['name']} ({$seite['link']}), " . ($seite['fan_count'] ?? '?') . " Fans\n");
            $beitraege = $api->beitraege(5);
            $this->stdout(count($beitraege) . " Beiträge gelesen, neuester vom "
                . (isset($beitraege[0]['created_time']) ? date('d.m.Y H:i', strtotime($beitraege[0]['created_time'])) : '–') . "\n");
            foreach ($beitraege as $b) {
                $this->stdout('  · ' . mb_substr(str_replace("\n", ' ', $b['message'] ?? '(ohne Text)'), 0, 80) . "\n");
            }
        } catch (\Throwable $e) {
            $this->stderr("Fehler: {$e->getMessage()}\n");
            return ExitCode::UNAVAILABLE;
        }
        return ExitCode::OK;
    }

    /**
     * Aus einem kurzlebigen Nutzer-Token (Graph API Explorer, Rechte pages_show_list, pages_read_engagement,
     * pages_read_user_content) ein dauerhaftes Seiten-Token machen: /app/yii facebook/token <token> [page_id]
     * App-Geheimcode kommt aus secrets/whatsapp.json (dieselbe Meta-App). Das Ergebnis gehört in secrets/facebook.json.
     */
    public function actionToken($nutzerToken, $pageId = null)
    {
        $m = $this->modul();
        $api = new Api($m);
        $pageId = $pageId ?: $m->zugang('page_id');
        $wa = json_decode((string)@file_get_contents('/run/secrets/whatsapp'), true);
        $secret = trim((string)($wa['app_secret'] ?? ''));
        try {
            $appId = $api->tokenInfo($nutzerToken)['app_id'] ?? null;
            if (!$appId || $secret === '') {
                throw new \RuntimeException('App-ID oder App-Geheimcode fehlt');
            }
            $lang = $api->get('oauth/access_token', ['grant_type' => 'fb_exchange_token', 'client_id' => $appId,
                'client_secret' => $secret, 'fb_exchange_token' => $nutzerToken], $nutzerToken)['access_token'];
            if (!$pageId) {
                // Seiten auflisten, auf die das Konto Zugriff hat
                foreach ($api->get('me/accounts', ['fields' => 'id,name'], $lang)['data'] ?? [] as $s) {
                    $this->stdout("{$s['id']}  {$s['name']}\n");
                }
                $this->stdout("Befehl mit der page_id der Stadt-Seite wiederholen.\n");
                return ExitCode::OK;
            }
            $seitenToken = $api->get($pageId, ['fields' => 'access_token,name'], $lang);
            $info = $api->tokenInfo($seitenToken['access_token']);
            $this->stdout("Seite: {$seitenToken['name']}\nSeiten-Token (läuft ab: "
                . (empty($info['expires_at']) ? 'nie' : date('d.m.Y', $info['expires_at'])) . ") – in secrets/facebook.json eintragen:\n\n"
                . json_encode(['page_id' => $pageId, 'token' => $seitenToken['access_token']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        } catch (\Throwable $e) {
            $this->stderr("Fehler: {$e->getMessage()}\n");
            return ExitCode::UNAVAILABLE;
        }
        return ExitCode::OK;
    }

    public function actionAbrufen($probelauf = 0)
    {
        $m = $this->modul();
        if ($m->zugang('page_id') === null || $m->zugang('token') === null) {
            // Noch nicht eingerichtet: still beenden, damit der Crontab-Eintrag schon stehen kann
            return ExitCode::OK;
        }
        try {
            $beitraege = (new Api($m))->beitraege(25);
        } catch (\Throwable $e) {
            $this->stderr(date('Y-m-d H:i:s') . ": {$e->getMessage()}\n");
            return ExitCode::UNAVAILABLE;
        }
        return $this->uebernehmen($beitraege, (bool)$probelauf);
    }

    public function actionBeispiel($datei, $probelauf = 1)
    {
        $json = json_decode((string)@file_get_contents($datei), true);
        if (!is_array($json)) {
            $this->stderr("Datei nicht lesbar oder kein JSON: $datei\n");
            return ExitCode::DATAERR;
        }
        return $this->uebernehmen($json['data'] ?? $json, (bool)$probelauf);
    }

    private function uebernehmen(array $beitraege, bool $probelauf)
    {
        try {
            [$neu, $geaendert, $uebersprungen, $fehler] = (new Import($this->modul(), fn($s) => $this->stdout($s)))
                ->uebernehmen($beitraege, $probelauf);
        } catch (\Throwable $e) {
            $this->stderr(date('Y-m-d H:i:s') . ": {$e->getMessage()}\n");
            return ExitCode::SOFTWARE;
        }
        if (!$probelauf) {
            $this->stdout(date('Y-m-d H:i:s') . ': ' . count($beitraege) . " Beiträge gelesen, $neu neu, $geaendert geändert, $uebersprungen übersprungen"
                . ($fehler ? ", $fehler fehlgeschlagen" : '') . "\n");
        }
        return $fehler ? ExitCode::SOFTWARE : ExitCode::OK;
    }
}
