<?php

namespace humhub\modules\vorschlaege\models;

use humhub\components\ActiveRecord;
use humhub\helpers\Html;
use humhub\modules\comment\models\Comment;
use humhub\modules\content\models\Content;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\vorschlaege\notifications\VorschlagNeu;
use humhub\modules\vorschlaege\permissions\StatusSetzen;
use Yii;

/**
 * Titel, Kategorie und Stand eines Vorschlags (je Beitrag)
 *
 * @property int $content_id
 * @property string $titel
 * @property string $kategorie
 * @property string $status
 * @property string|null $note
 * @property int|null $updated_by
 * @property string|null $updated_at
 * @property string $created_at
 * @property Content $content
 */
class Vorschlag extends ActiveRecord
{
    /** Schlüssel => [Bezeichnung, Symbol]; Farben in resources/vorschlag.css */
    public const STATUSES = [
        'neu' => ['Neu', 'lightbulb-o'],
        'pruefung' => ['Wird geprüft', 'search'],
        'geplant' => ['Geplant', 'calendar-check-o'],
        'arbeit' => ['In Arbeit', 'wrench'],
        'umgesetzt' => ['Umgesetzt', 'check'],
        'abgelehnt' => ['Abgelehnt', 'ban'],
    ];

    /** Abgeschlossen: keine Abstimmung mehr */
    public const CLOSED = ['umgesetzt', 'abgelehnt'];

    public static function tableName()
    {
        return 'vorschlag';
    }

    public function getContent()
    {
        return $this->hasOne(Content::class, ['id' => 'content_id']);
    }

    public function getEditor()
    {
        return $this->hasOne(User::class, ['id' => 'updated_by']);
    }

    public static function canManage(): bool
    {
        return !Yii::$app->user->isGuest
            && (Yii::$app->user->isAdmin() || Yii::$app->user->can(StatusSetzen::class));
    }

    public function istOffen(): bool
    {
        return !in_array($this->status, self::CLOSED, true);
    }

    /** Text des Beitrags: Titel fett, darunter die Beschreibung, am Ende die Kategorie */
    public static function message(string $titel, string $kategorie, string $text): string
    {
        return '**' . str_replace('*', '', $titel) . "**\n\n" . $text . "\n\n_" . $kategorie . '_';
    }

    /**
     * Neuer Vorschlag als Beitrag des angemeldeten Nutzers im Space (öffentlich), dazu Titel/Kategorie/Stand;
     * Glocke an Admins und „Vorschläge bearbeiten“.
     */
    public static function einreichen(Space $space, VorschlagForm $form): Post
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $post = new Post($space);
            $post->message = self::message($form->titel, $form->kategorie, $form->text);
            $post->content->visibility = Content::VISIBILITY_PUBLIC;
            if (!$post->save()) {
                throw new \RuntimeException(json_encode($post->getErrors(), JSON_UNESCAPED_UNICODE));
            }
            (new static(['content_id' => $post->content->id, 'titel' => $form->titel, 'kategorie' => $form->kategorie,
                'status' => 'neu', 'created_at' => date('Y-m-d H:i:s')]))->save(false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        $ich = Yii::$app->user->getIdentity();
        $empfaenger = array_values(array_filter(Yii::$app->getModule('vorschlaege')->bearbeiter(), fn($u) => (int)$u->id !== (int)$ich->id));
        if ($empfaenger && Yii::$app->getModule('vorschlaege')->benachrichtigen) {
            VorschlagNeu::instance()->from($ich)->about($post)->sendBulk($empfaenger);
        }
        return $post;
    }

    /** Beschreibung aus dem Beitragstext (ohne fetten Titel und Kategorie am Ende) */
    public static function beschreibung(string $message): string
    {
        return trim(preg_replace(['/^\*\*.*?\*\*\s*/us', '/\s*_[^_\n]+_\s*$/u'], '', $message));
    }

    /** Deutsche Bezeichnung (für den Kommentar bei Änderungen – gespeicherte Texte bleiben deutsch) */
    public function getLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public static function anzeigeLabel(string $status): string
    {
        return isset(self::STATUSES[$status]) ? Yii::t('VorschlaegeModule.base', self::STATUSES[$status][0]) : $status;
    }

    public static function etikett(string $status): string
    {
        return '<span class="vs-status vs-status-' . Html::encode($status) . '">'
            . '<i class="fa fa-' . (self::STATUSES[$status][1] ?? 'circle') . '" aria-hidden="true"></i>'
            . Html::encode(self::anzeigeLabel($status)) . '</span>';
    }

    /**
     * Stand setzen und als Kommentar unter dem Vorschlag vermerken (benachrichtigt Verfasser und alle,
     * die dem Beitrag folgen, z. B. weil sie kommentiert haben). Gibt false zurück, wenn sich nichts ändert.
     */
    public function statusSetzen(string $neu, ?string $hinweis): bool
    {
        if (!isset(self::STATUSES[$neu])) {
            return false;
        }
        $hinweis = $hinweis !== null && trim($hinweis) !== '' ? trim(mb_substr($hinweis, 0, 500)) : null;
        if ($this->status === $neu && $hinweis === null) {
            return false;
        }
        $this->status = $neu;
        $this->note = $hinweis;
        $this->updated_by = Yii::$app->user->id;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save(false);

        $comment = new Comment();
        $comment->message = '**Stand: ' . $this->getLabel() . '**' . ($hinweis !== null ? " – $hinweis" : '');
        $comment->object_model = Post::class;
        $comment->object_id = $this->content->object_id;
        $comment->save();
        return true;
    }
}
