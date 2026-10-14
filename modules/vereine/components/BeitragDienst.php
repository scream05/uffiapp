<?php

namespace humhub\modules\vereine\components;

use humhub\helpers\Html;
use humhub\modules\content\models\Content;
use humhub\modules\post\models\Post;
use humhub\modules\user\models\User;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\Module;
use humhub\modules\vereine\notifications\BeitragEingereicht;
use humhub\modules\vereine\notifications\BeitragFreigegeben;
use Yii;
use yii\base\Exception;

/** Einreichen, Freigeben, Ablehnen und Entfernen von Vereinsbeiträgen */
class BeitragDienst
{
    /** Glocke an Admins und alle mit „Vereine verwalten“ */
    public static function eingereicht(VereinBeitrag $b): void
    {
        $empfaenger = [];
        foreach (User::find()->active()->all() as $user) {
            if ($user->id !== $b->user_id && ($user->isSystemAdmin()
                    || (new \humhub\modules\user\components\PermissionManager(['subject' => $user]))
                        ->can(\humhub\modules\vereine\permissions\ManageVereine::class))) {
                $empfaenger[] = $user;
            }
        }
        BeitragEingereicht::instance()->from($b->user)->about($b)->sendBulk($empfaenger);
    }

    /**
     * Freigeben: auf der Vereinsseite und/oder als HumHub-Beitrag (Verfasser: Vereinsverwalter) im Space des
     * Verzeichnisses, der in der Übersicht erscheint (Bereich „Vereine & Ehrenamt“ in „Meine Übersicht“, Standard an).
     */
    public static function freigeben(VereinBeitrag $b, bool $vereinsseite, bool $uebersicht): void
    {
        if (!$vereinsseite && !$uebersicht) {
            throw new Exception('Mindestens ein Ort muss gewählt sein.');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($uebersicht && !$b->content_id) {
                $space = Yii::$app->getModule('vereine')->getDirectorySpace();
                if ($space === null) {
                    throw new Exception('Space des Vereinsverzeichnisses nicht gefunden.');
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
            $b->status = VereinBeitrag::STATUS_VEROEFFENTLICHT;
            $b->auf_vereinsseite = $vereinsseite ? 1 : 0;
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
        if ($b->user_id !== Yii::$app->user->id) {
            BeitragFreigegeben::instance()->from(Yii::$app->user->getIdentity())->about($b)->send($b->user);
        }
    }

    /** Ablehnen mit Begründung, die per Mail an den Verfasser geht; Bilder werden gelöscht */
    public static function ablehnen(VereinBeitrag $b, string $begruendung): bool
    {
        $b->status = VereinBeitrag::STATUS_ABGELEHNT;
        $b->begruendung = mb_substr($begruendung, 0, 2000);
        $b->geprueft_von = Yii::$app->user->id;
        $b->geprueft_am = date('Y-m-d H:i:s');
        $b->save(false);
        foreach ($b->fileManager->findAll() as $file) {
            $file->delete();
        }
        return self::ablehnungsMail($b);
    }

    /** Veröffentlichten Beitrag ganz entfernen (samt HumHub-Beitrag) */
    public static function entfernen(VereinBeitrag $b): void
    {
        $content = $b->content;
        $b->delete();
        if ($content) {
            $content->getModel()?->hardDelete();
        }
    }

    private static function ablehnungsMail(VereinBeitrag $b): bool
    {
        $user = $b->user;
        if (!$user || !$user->email) {
            return false;
        }
        /** @var Module $module */
        $module = Yii::$app->getModule('vereine');
        $text = Yii::$app->view->renderFile('@vereine/views/mail/abgelehnt_text.php', [
            'beitrag' => $b, 'name' => $user->displayName, 'link' => $b->verein->getUrl(true), 'kontaktMail' => $module->kontaktMail,
        ]);
        $html = nl2br(Html::encode($text), false);
        $html = preg_replace('#https?://[^\s<]+#', '<a href="$0">$0</a>', $html);
        $mailer = Yii::$app->mailer;
        $html = $mailer->render('@humhub/views/mail/TextOnly', ['message' => $html], $mailer->htmlLayout);
        $mail = $mailer->compose()->setTo($user->email)->setReplyTo($module->kontaktMail)
            ->setSubject("Ihr Beitrag für „{$b->verein->name}“ wurde nicht veröffentlicht");
        $mail->getSymfonyEmail()->text($text)->html($html);
        $ok = $mail->send();
        if (!$ok) {
            Yii::warning("Ablehnungs-Mail für Vereinsbeitrag {$b->id} nicht versendet", 'vereine');
        }
        return $ok;
    }
}
