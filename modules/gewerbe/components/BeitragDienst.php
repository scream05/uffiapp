<?php

namespace humhub\modules\gewerbe\components;

use humhub\helpers\Html;
use humhub\modules\content\models\Content;
use humhub\modules\post\models\Post;
use humhub\modules\user\models\User;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\modules\gewerbe\Module;
use humhub\modules\gewerbe\notifications\BeitragEingereicht;
use humhub\modules\gewerbe\notifications\BeitragFreigegeben;
use Yii;
use yii\base\Exception;

/** Einreichen, Freigeben, Ablehnen und Entfernen von Gewerbebeiträgen */
class BeitragDienst
{
    /** Glocke an Admins und alle mit „Gewerbe verwalten“ */
    public static function eingereicht(BetriebBeitrag $b): void
    {
        $empfaenger = [];
        foreach (User::find()->active()->all() as $user) {
            if ($user->id !== $b->user_id && ($user->isSystemAdmin()
                    || (new \humhub\modules\user\components\PermissionManager(['subject' => $user]))
                        ->can(\humhub\modules\gewerbe\permissions\ManageGewerbe::class))) {
                $empfaenger[] = $user;
            }
        }
        BeitragEingereicht::instance()->from($b->user)->about($b)->sendBulk($empfaenger);
    }

    /**
     * Freigeben: auf der Betriebsseite und/oder als HumHub-Beitrag (Verfasser: Betriebsverwalter) im Space des
     * Verzeichnisses, der in der Übersicht erscheint (Bereich „Gewerbe & Einkaufen“ in „Meine Übersicht“).
     */
    public static function freigeben(BetriebBeitrag $b, bool $betriebsseite, bool $uebersicht, bool $benachrichtigen = true): void
    {
        if (!$betriebsseite && !$uebersicht) {
            throw new Exception('Mindestens ein Ort muss gewählt sein.');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($uebersicht && !$b->content_id) {
                $space = Yii::$app->getModule('gewerbe')->getDirectorySpace();
                if ($space === null) {
                    throw new Exception('Space des Gewerbeverzeichnisses nicht gefunden.');
                }
                $post = new Post($space);
                $post->message = $b->text;
                $post->content->visibility = Content::VISIBILITY_PUBLIC;
                $post->content->created_by = $b->user_id;
                if (!$post->save()) {
                    throw new Exception('Beitrag konnte nicht angelegt werden: ' . json_encode($post->getErrors()));
                }
                // Bilder an den Beitrag hängen (sie wurden vom Verfasser hochgeladen, daher „steal“)
                $post->fileManager->attach($b->fileManager->findAll(), true);
                $b->content_id = $post->content->id;
            }
            $b->status = BetriebBeitrag::STATUS_VEROEFFENTLICHT;
            $b->auf_betriebsseite = $betriebsseite ? 1 : 0;
            $b->uebersicht = $uebersicht ? 1 : 0;
            $b->begruendung = null;
            $b->geprueft_von = Yii::$app->user->id;
            $b->geprueft_am = date('Y-m-d H:i:s');
            $b->save(false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        if ($benachrichtigen && $b->user_id !== Yii::$app->user->id) {
            BeitragFreigegeben::instance()->from(Yii::$app->user->getIdentity())->about($b)->send($b->user);
        }
    }

    /** Ablehnen mit Begründung, die per Mail an den Verfasser geht; Bilder werden gelöscht */
    public static function ablehnen(BetriebBeitrag $b, string $begruendung): bool
    {
        $b->status = BetriebBeitrag::STATUS_ABGELEHNT;
        $b->begruendung = mb_substr($begruendung, 0, 2000);
        $b->geprueft_von = Yii::$app->user->id;
        $b->geprueft_am = date('Y-m-d H:i:s');
        $b->save(false);
        foreach ($b->fileManager->findAll() as $file) {
            $file->delete();
        }
        return self::ablehnungsMail($b);
    }

    /**
     * Aus der Übersicht nehmen: HumHub-Beitrag löschen (samt Kommentaren), der Beitrag bleibt auf der Betriebsseite.
     * Die Bilder hängen vorher wieder am Gewerbebeitrag, sonst würden sie mit dem HumHub-Beitrag gelöscht.
     */
    public static function ausUebersicht(BetriebBeitrag $b): void
    {
        $post = $b->content?->getModel();
        if ($post !== null) {
            $b->fileManager->attach($post->fileManager->findAll(), true);
        }
        $b->updateAttributes(['content_id' => null, 'uebersicht' => 0, 'auf_betriebsseite' => 1]);
        $post?->hardDelete();
    }

    /** Veröffentlichten Beitrag ganz entfernen (samt HumHub-Beitrag) */
    public static function entfernen(BetriebBeitrag $b): void
    {
        $content = $b->content;
        $b->delete();
        if ($content) {
            $content->getModel()?->hardDelete();
        }
    }

    private static function ablehnungsMail(BetriebBeitrag $b): bool
    {
        $user = $b->user;
        if (!$user || !$user->email) {
            return false;
        }
        /** @var Module $module */
        $module = Yii::$app->getModule('gewerbe');
        $text = Yii::$app->view->renderFile('@gewerbe/views/mail/abgelehnt_text.php', [
            'beitrag' => $b, 'name' => $user->displayName, 'link' => $b->betrieb->getUrl(true), 'kontaktMail' => $module->kontaktMail,
        ]);
        $html = nl2br(Html::encode($text), false);
        $html = preg_replace('#https?://[^\s<]+#', '<a href="$0">$0</a>', $html);
        $mailer = Yii::$app->mailer;
        $html = $mailer->render('@humhub/views/mail/TextOnly', ['message' => $html], $mailer->htmlLayout);
        $mail = $mailer->compose()->setTo($user->email)->setReplyTo($module->kontaktMail)
            ->setSubject("Dein Beitrag für „{$b->betrieb->name}“ wurde nicht veröffentlicht");
        $mail->getSymfonyEmail()->text($text)->html($html);
        $ok = $mail->send();
        if (!$ok) {
            Yii::warning("Ablehnungs-Mail für Gewerbebeitrag {$b->id} nicht versendet", 'gewerbe');
        }
        return $ok;
    }
}
