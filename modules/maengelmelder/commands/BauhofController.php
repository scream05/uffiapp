<?php

namespace humhub\modules\maengelmelder\commands;

use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\maengelmelder\models\MaengelKontakt;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\maengelmelder\Module;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\whatsapp\components\Api;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Bauhof (Mängelmelder per WhatsApp):
 *   /app/yii bauhof/vorlagen            Status der WhatsApp-Vorlagen bei Meta
 *   /app/yii bauhof/vorlagen 1          fehlende Vorlagen zur Prüfung einreichen
 *   /app/yii bauhof/aufraeumen          Verlauf/Fotos/Kontaktdaten lange abgeschlossener Meldungen löschen (täglich per Crontab)
 *   /app/yii bauhof/gastmeldungen       Meldungen ohne Konto: Stand anzeigen
 *   /app/yii bauhof/gastmeldungen an    einschalten (legt das Systemkonto an), „aus“ schaltet ab
 *   /app/yii bauhof/buergerinfo an|aus  Infos an Meldende bei Statusänderung (E-Mail/WhatsApp)
 */
class BauhofController extends Controller
{
    private function module(): Module
    {
        return Yii::$app->getModule('maengelmelder');
    }

    /** Vorlagen für den Erstkontakt (Kategorie „Utility“, Deutsch) */
    private function definitionen(): array
    {
        $mod = $this->module();
        return [
            $mod->vorlagen['auftrag'] => [
                'name' => $mod->vorlagen['auftrag'],
                'language' => $mod->vorlagenSprache,
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY',
                        'text' => "Neuer Auftrag vom Bauhof Uffenheim: Nr. {{1}} – {{2}}, Ort: {{3}}.\n\nTippe auf „Auftrag anzeigen“, um Beschreibung, Fotos und Karte zu bekommen.",
                        'example' => ['body_text' => [['12', 'Straße / Gehweg', 'Marktplatz 1, Uffenheim']]]],
                    ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Auftrag anzeigen']]],
                ],
            ],
            $mod->vorlagen['buergerEingang'] => [
                'name' => $mod->vorlagen['buergerEingang'],
                'language' => $mod->vorlagenSprache,
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY',
                        'text' => "Ihre Mängelmeldung Nr. {{1}} ({{2}}) bei Rund um Uffni ist eingegangen. Über Änderungen am Bearbeitungsstand informieren wir Sie hier.\n\nSie können auf diese Nachricht antworten; Ihre Antwort geht an den Bauhof. Keine Nachrichten mehr? Antworten Sie mit STOPP.",
                        'example' => ['body_text' => [['12', 'Straßenbeleuchtung']]]],
                ],
            ],
            $mod->vorlagen['buergerStatus'] => [
                'name' => $mod->vorlagen['buergerStatus'],
                'language' => $mod->vorlagenSprache,
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY',
                        'text' => "Neuer Stand zu Ihrer Mängelmeldung Nr. {{1}} ({{2}}): {{3}}\nHinweis des Bauhofs: {{4}}\n\nSie können auf diese Nachricht antworten; Ihre Antwort geht an den Bauhof. Keine Nachrichten mehr? Antworten Sie mit STOPP.",
                        'example' => ['body_text' => [['12', 'Straßenbeleuchtung', 'In Arbeit', 'Lampe wird am Freitag getauscht']]]],
                ],
            ],
            $mod->vorlagen['nachricht'] => [
                'name' => $mod->vorlagen['nachricht'],
                'language' => $mod->vorlagenSprache,
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY',
                        'text' => "Du hast eine neue Nachricht vom Bauhof Uffenheim (von {{1}}).\n\nTippe auf „Nachricht lesen“, um sie anzuzeigen.",
                        'example' => ['body_text' => [['Bauhofleitung']]]],
                    ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Nachricht lesen']]],
                ],
            ],
        ];
    }

    public function actionVorlagen($anlegen = 0)
    {
        $vorhanden = [];
        foreach (Api::vorlagen() as $v) {
            $vorhanden[$v['name']] = $v;
        }
        foreach ($this->definitionen() as $name => $def) {
            if (isset($vorhanden[$name])) {
                $v = $vorhanden[$name];
                $this->stdout("$name ({$v['language']}): {$v['status']}" . (!empty($v['rejected_reason']) && $v['rejected_reason'] !== 'NONE' ? " – {$v['rejected_reason']}" : '') . "\n");
            } elseif ($anlegen) {
                $res = Api::vorlageAnlegen($def);
                $this->stdout("$name: eingereicht, Status " . ($res['status'] ?? '?') . "\n");
            } else {
                $this->stdout("$name: fehlt (mit „bauhof/vorlagen 1“ einreichen)\n");
            }
        }
        return ExitCode::OK;
    }

    /**
     * Verlauf und WhatsApp-Fotos von Meldungen löschen, die seit $bauhofAufbewahrungTage abgeschlossen sind,
     * dazu Nachrichten ohne Meldung nach derselben Frist und verwaiste Dateien.
     */
    public function actionAufraeumen()
    {
        $frist = date('Y-m-d H:i:s', time() - $this->module()->bauhofAufbewahrungTage * 86400);
        $alt = MaengelStatus::find()->select('content_id')->where(['status' => MaengelStatus::CLOSED])
            ->andWhere(['<', 'updated_at', $frist])->column();
        $n = 0;
        foreach (BauhofNachricht::find()->where(['content_id' => $alt])
                     ->orWhere(['and', ['content_id' => null], ['<', 'created_at', $frist]])->each() as $msg) {
            $msg->delete();
            $n++;
        }
        // Dateien, deren Nachricht schon weg ist (z. B. Meldung gelöscht)
        $f = 0;
        foreach (File::find()->where(['object_model' => BauhofNachricht::class])
                     ->andWhere(['not exists', BauhofNachricht::find()->where('bauhof_nachricht.id = file.object_id')])->each() as $file) {
            $file->delete();
            $f++;
        }
        // Kontaktdaten: nach $kontaktAufbewahrungTage ab Abschluss, bei gelöschten Meldungen sofort
        $kontaktFrist = date('Y-m-d H:i:s', time() - $this->module()->kontaktAufbewahrungTage * 86400);
        // Nachrichten mit der meldenden Person: mit derselben Frist wie die Kontaktdaten
        foreach (BauhofNachricht::find()->where(['buerger' => 1, 'content_id' => MaengelStatus::find()->select('content_id')
            ->where(['status' => MaengelStatus::CLOSED])->andWhere(['<', 'updated_at', $kontaktFrist])])->each() as $msg) {
            $msg->delete();
            $n++;
        }
        $k = MaengelKontakt::deleteAll(['or',
            ['content_id' => MaengelStatus::find()->select('content_id')->where(['status' => MaengelStatus::CLOSED])
                ->andWhere(['<', 'updated_at', $kontaktFrist])],
            ['content_id' => Content::find()->select('id')->where(['state' => Content::STATE_DELETED])],
        ]);
        if ($n || $f || $k) {
            $this->stdout(date('Y-m-d H:i:s') . ": $n Nachrichten, $f verwaiste Dateien, $k Kontaktdaten gelöscht\n");
        }
        return ExitCode::OK;
    }

    /** Infos an Meldende (E-Mail/WhatsApp bei Statusänderung) ein- oder ausschalten: bauhof/buergerinfo an|aus */
    public function actionBuergerinfo($schalter = null)
    {
        $mod = $this->module();
        if (in_array($schalter, ['an', 'aus'], true)) {
            $mod->settings->set('buergerInfo', $schalter === 'an' ? 1 : 0);
        }
        $this->stdout('Infos an Meldende: E-Mail ' . ($mod->buergerInfoAktiv() ? 'an' : 'aus')
            . ', WhatsApp ' . ($mod->buergerWhatsappAktiv() ? 'an' : 'aus')
            . ', mit E-Mail: ' . MaengelKontakt::find()->where(['info_mail' => 1])->count()
            . ', mit WhatsApp: ' . MaengelKontakt::find()->where(['info_whatsapp' => 1])->count() . "\n");
        return ExitCode::OK;
    }

    /**
     * WhatsApp-Infos an Meldende: an|aus, oder „auto“ (per Crontab): schaltet ein, sobald Meta beide Vorlagen
     * genehmigt hat, und tut danach nichts mehr.
     */
    public function actionBuergerinfoWhatsapp($schalter = null)
    {
        $mod = $this->module();
        if ($schalter === 'auto') {
            if ($mod->settings->get('buergerInfoWhatsapp') !== null) {
                return ExitCode::OK; // schon entschieden (an oder bewusst aus)
            }
            $status = [];
            foreach (Api::vorlagen() as $v) {
                $status[$v['name']] = $v['status'] ?? '';
            }
            $noetig = [$mod->vorlagen['buergerEingang'], $mod->vorlagen['buergerStatus']];
            if (array_filter($noetig, fn($n) => ($status[$n] ?? '') !== 'APPROVED')) {
                return ExitCode::OK;
            }
            $mod->settings->set('buergerInfoWhatsapp', 1);
            $this->stdout(date('Y-m-d H:i:s') . ": Vorlagen genehmigt – WhatsApp-Infos an Meldende eingeschaltet\n");
        } elseif (in_array($schalter, ['an', 'aus'], true)) {
            $mod->settings->set('buergerInfoWhatsapp', $schalter === 'an' ? 1 : 0);
        }
        return $this->actionBuergerinfo();
    }

    /**
     * Meldungen ohne Konto ein- oder ausschalten. Beim Einschalten wird das Systemkonto angelegt, unter dem
     * Gastmeldungen erscheinen (ohne Passwort, Adresse .invalid: keine Anmeldung, keine Mails), Mitglied nur im Mängelmelder.
     */
    public function actionGastmeldungen($schalter = null)
    {
        $mod = $this->module();
        if ($schalter === 'an') {
            $user = User::findOne(['username' => $mod->gastKonto]);
            if ($user === null) {
                $user = new User(['scenario' => 'editAdmin']);
                $user->username = $mod->gastKonto;
                $user->email = $mod->gastKonto . '@website.invalid';
                $user->status = User::STATUS_ENABLED;
                if (!$user->save()) {
                    $this->stderr('Konto: ' . json_encode($user->getErrors()) . "\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }
                $user->profile->scenario = 'editAdmin';
                $user->profile->firstname = 'Gastmeldung';
                $user->profile->lastname = '(ohne Konto)';
                $user->profile->about = 'Mängelmeldungen von Bürgerinnen und Bürgern ohne Konto, vom Bauhof geprüft und freigegeben.';
                $user->profile->save();
                $this->stdout("+ Konto {$mod->gastKonto}\n");
                // Keine Mails/Push, keine Zusammenfassung
                $settings = Yii::$app->getModule('notification')->settings->user($user);
                foreach (Yii::$app->notification->getNotificationCategories($user) as $category) {
                    foreach (Yii::$app->notification->getTargets($user) as $target) {
                        if ($target->id !== 'web') {
                            $settings->set($target->getSettingKey($category), 0);
                        }
                    }
                }
                Yii::$app->getModule('activity')->settings->user($user)->set('mailSummaryInterval', 0);
            }
            // Nur im Space des Mängelmelders Mitglied (neue Konten landen sonst automatisch in mehreren Spaces)
            $space = Space::findOne(['url' => $mod->bauhofSpace]);
            if ($space && !$space->isMember($user->id)) {
                $space->addMember($user->id, 1, false);
            }
            foreach (\humhub\modules\space\models\Membership::find()->where(['user_id' => $user->id])
                         ->andWhere(['not', ['space_id' => $space?->id]])->all() as $membership) {
                $membership->delete();
            }
            foreach (\humhub\modules\activity\models\Activity::find()->joinWith('content')
                         ->where(['content.created_by' => $user->id])
                         ->andWhere(['activity.class' => \humhub\modules\space\activities\MemberAdded::class])->all() as $activity) {
                $activity->hardDelete();
            }
            $mod->settings->set('gastMeldungen', 1);
        } elseif ($schalter === 'aus') {
            $mod->settings->set('gastMeldungen', 0);
        }
        $offen = Content::find()->where(['state' => Content::STATE_DRAFT, 'created_by' => $mod->getGastKonto()?->id ?? 0])->count();
        $this->stdout('Gastmeldungen: ' . ($mod->gastMeldungenAktiv() ? 'an' : 'aus') . ", zu prüfen: $offen\n");
        return ExitCode::OK;
    }
}
