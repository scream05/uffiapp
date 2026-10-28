<?php

namespace humhub\modules\verzeichnis\components;

use humhub\helpers\Html;
use humhub\modules\content\models\Content;
use humhub\modules\post\models\Post;
use humhub\modules\user\components\PermissionManager;
use humhub\modules\user\models\User;
use humhub\modules\verzeichnis\models\BeitragBasis;
use Yii;
use yii\base\Exception;

/**
 * Einreichen, Freigeben, Ablehnen und Entfernen von Beiträgen eines Verzeichnisses. Je Modul eine Unterklasse
 * (gewerbe/vereine components\BeitragDienst), die Modul, Recht und Benachrichtigungen nennt.
 */
abstract class BeitragDienstBasis
{
    /** ID des Moduls, z. B. „gewerbe“ (getDirectorySpace, kontaktMail, Mail-Ansicht @<id>/views/mail/abgelehnt_text.php) */
    abstract protected static function modulId(): string;

    /** Recht „… verwalten“ (Klasse) */
    abstract protected static function verwaltenRecht(): string;

    /** Benachrichtigungen (Klassen) an Prüfer bzw. Verfasser */
    abstract protected static function eingereichtBenachrichtigung(): string;

    abstract protected static function freigegebenBenachrichtigung(): string;

    /** Glocke an Admins und alle mit dem Recht „… verwalten“ */
    public static function eingereicht(BeitragBasis $b): void
    {
        $empfaenger = [];
        foreach (User::find()->active()->all() as $user) {
            if ($user->id !== $b->user_id && ($user->isSystemAdmin()
                    || (new PermissionManager(['subject' => $user]))->can(static::verwaltenRecht()))) {
                $empfaenger[] = $user;
            }
        }
        static::eingereichtBenachrichtigung()::instance()->from($b->user)->about($b)->sendBulk($empfaenger);
    }

    /**
     * Freigeben: auf der Eintragsseite und/oder als HumHub-Beitrag (Verfasser: Verwalter) im Space des Verzeichnisses,
     * der in der Übersicht erscheint. $benachrichtigen: Glocke an den Verfasser; $still: ohne Benachrichtigungen an die
     * Mitglieder des Space (Übernahme älterer Beiträge, z. B. aus dem Mitteilungsblatt).
     */
    public static function freigeben(BeitragBasis $b, bool $seite, bool $uebersicht, bool $benachrichtigen = true, bool $still = false): void
    {
        if (!$seite && !$uebersicht) {
            throw new Exception('Mindestens ein Ort muss gewählt sein.');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($uebersicht && !$b->content_id) {
                $space = Yii::$app->getModule(static::modulId())->getDirectorySpace();
                if ($space === null) {
                    throw new Exception('Space des Verzeichnisses „' . static::modulId() . '“ nicht gefunden.');
                }
                $post = new Post($space);
                $post->message = $b->text;
                $post->content->visibility = Content::VISIBILITY_PUBLIC;
                $post->content->created_by = $b->user_id;
                $post->silentContentCreation = $still;
                if (!$post->save()) {
                    throw new Exception('Beitrag konnte nicht angelegt werden: ' . json_encode($post->getErrors()));
                }
                // Bilder an den Beitrag hängen (sie wurden vom Verfasser hochgeladen, daher „steal“)
                $post->fileManager->attach($b->fileManager->findAll(), true);
                $b->content_id = $post->content->id;
            }
            $b->status = BeitragBasis::STATUS_VEROEFFENTLICHT;
            $b->{$b::seiteSpalte()} = $seite ? 1 : 0;
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
            static::freigegebenBenachrichtigung()::instance()->from(Yii::$app->user->getIdentity())->about($b)->send($b->user);
        }
    }

    /** Ablehnen mit Begründung, die per Mail an den Verfasser geht; Bilder werden gelöscht */
    public static function ablehnen(BeitragBasis $b, string $begruendung): bool
    {
        $b->status = BeitragBasis::STATUS_ABGELEHNT;
        $b->begruendung = mb_substr($begruendung, 0, 2000);
        $b->geprueft_von = Yii::$app->user->id;
        $b->geprueft_am = date('Y-m-d H:i:s');
        $b->save(false);
        foreach ($b->fileManager->findAll() as $file) {
            $file->delete();
        }
        return static::ablehnungsMail($b);
    }

    /**
     * Aus der Übersicht nehmen: HumHub-Beitrag löschen (samt Kommentaren), der Beitrag bleibt auf der Eintragsseite.
     * Die Bilder hängen vorher wieder am Verzeichnisbeitrag, sonst würden sie mit dem HumHub-Beitrag gelöscht.
     */
    public static function ausUebersicht(BeitragBasis $b): void
    {
        $post = $b->content?->getModel();
        if ($post !== null) {
            $b->fileManager->attach($post->fileManager->findAll(), true);
        }
        $b->updateAttributes(['content_id' => null, 'uebersicht' => 0, $b::seiteSpalte() => 1]);
        $post?->hardDelete();
    }

    /** Veröffentlichten Beitrag ganz entfernen (samt HumHub-Beitrag) */
    public static function entfernen(BeitragBasis $b): void
    {
        $content = $b->content;
        $b->delete();
        if ($content) {
            $content->getModel()?->hardDelete();
        }
    }

    protected static function ablehnungsMail(BeitragBasis $b): bool
    {
        $user = $b->user;
        if (!$user || !$user->email) {
            return false;
        }
        $module = Yii::$app->getModule(static::modulId());
        // Mailtext und Betreff in der Sprache des Empfängers, danach die bisherige Sprache wiederherstellen
        $alt = [Yii::$app->language, Yii::$app->formatter->locale, Yii::$app->formatter->timeZone];
        Yii::$app->i18n->setUserLocale($user);
        try {
            $betreff = Yii::t($b::kategorie(), 'Dein Beitrag für „{name}“ wurde nicht veröffentlicht', ['name' => $b->eintrag->name]);
            $text = Yii::$app->view->renderFile('@' . static::modulId() . '/views/mail/abgelehnt_text.php', [
                'beitrag' => $b, 'name' => $user->displayName, 'link' => $b->eintrag->getUrl(true), 'kontaktMail' => $module->kontaktMail,
            ]);
        } finally {
            [Yii::$app->language, Yii::$app->formatter->locale, Yii::$app->formatter->timeZone] = $alt;
        }
        $html = nl2br(Html::encode($text), false);
        $html = preg_replace('#https?://[^\s<]+#', '<a href="$0">$0</a>', $html);
        $mailer = Yii::$app->mailer;
        $html = $mailer->render('@humhub/views/mail/TextOnly', ['message' => $html], $mailer->htmlLayout);
        $mail = $mailer->compose()->setTo($user->email)->setReplyTo($module->kontaktMail)
            ->setSubject($betreff);
        $mail->getSymfonyEmail()->text($text)->html($html);
        $ok = $mail->send();
        if (!$ok) {
            Yii::warning('Ablehnungs-Mail für Beitrag ' . $b->id . ' (' . static::modulId() . ') nicht versendet', static::modulId());
        }
        return $ok;
    }
}
