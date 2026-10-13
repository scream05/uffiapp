<?php

use yii\db\Migration;

class m261005_000001_feedback extends Migration
{
    public function safeUp()
    {
        // Bewertungen von Antworten; nur auf Klick des Nutzers, ohne Konto (anonym)
        $this->createTable('ki_feedback', [
            'id' => $this->primaryKey(),
            'bewertung' => $this->tinyInteger()->notNull(), // 1 = hilfreich, -1 = nicht hilfreich
            'frage' => $this->text()->notNull(),
            'antwort' => $this->text()->notNull(),
            'kommentar' => $this->string(500)->null(),
            'erledigt' => $this->boolean()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }

    public function safeDown()
    {
        $this->dropTable('ki_feedback');
    }
}
