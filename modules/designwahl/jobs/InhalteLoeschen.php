<?php

namespace humhub\modules\designwahl\jobs;

use humhub\modules\comment\models\Comment;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\like\models\Like;
use humhub\modules\queue\LongRunningActiveJob;
use Yii;

/**
 * Löscht beim Löschen des eigenen Kontos auf Wunsch (Häkchen im Löschformular, seit 23.11.2026) alle Inhalte,
 * die das Konto erstellt hat: Beiträge, Termine, Mängelmeldungen, Umfragen, Fragen usw. samt Dateien und den
 * Kommentaren anderer darunter, außerdem eigene Kommentare und „Gefällt mir“ bei fremden Inhalten.
 * Sofort und endgültig (hardDelete), nicht über den Papierkorb. Private Nachrichten bleiben bei den Empfängern.
 * Hintergrund: Apple-Richtlinie 5.1.1(v) (Kontolöschung inkl. nutzergenerierter Inhalte).
 */
class InhalteLoeschen extends LongRunningActiveJob
{
    public $user_id;

    public function run()
    {
        self::ausfuehren((int)$this->user_id);
    }

    /** @return int[] [Inhalte, Kommentare, Gefällt mir] */
    public static function ausfuehren(int $userId): array
    {
        $zahl = [0, 0, 0];
        foreach (Content::find()->where(['created_by' => $userId])->each(50) as $content) {
            try {
                $record = $content->getPolymorphicRelation();
                $erledigt = $record instanceof ContentActiveRecord ? $record->hardDelete() : $content->hardDeleteInternal();
                $erledigt && $zahl[0]++;
            } catch (\Throwable $e) {
                Yii::error("Inhalt {$content->id} von Konto $userId nicht gelöscht: " . $e->getMessage(), 'designwahl');
            }
        }
        foreach (Comment::find()->where(['created_by' => $userId])->each(50) as $comment) {
            try {
                $comment->delete() && $zahl[1]++;
            } catch (\Throwable $e) {
                Yii::error("Kommentar {$comment->id} von Konto $userId nicht gelöscht: " . $e->getMessage(), 'designwahl');
            }
        }
        foreach (Like::find()->where(['created_by' => $userId])->each(50) as $like) {
            try {
                $like->delete() && $zahl[2]++;
            } catch (\Throwable $e) {
                Yii::error("Gefällt mir {$like->id} von Konto $userId nicht gelöscht: " . $e->getMessage(), 'designwahl');
            }
        }
        Yii::info("Konto $userId: {$zahl[0]} Inhalte, {$zahl[1]} Kommentare, {$zahl[2]} Gefällt mir gelöscht", 'designwahl');
        return $zahl;
    }
}
