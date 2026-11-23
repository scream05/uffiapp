<?php

use yii\db\Migration;

/**
 * Feste Übersetzungen der Vereinsinhalte auf der Kursseite (Slogan, Vorstellung, Kursnamen …). Anders als der
 * KI-Zwischenspeicher ki_uebersetzung verfallen sie nicht nach 30 Tagen: einmal sorgfältig übersetzt, bleibt der Text,
 * bis sich das Deutsche ändert (neuer Hash). quelle = deutscher Text zum Gegenlesen.
 */
class m261123_000001_inhalt_uebersetzung extends Migration
{
    public function safeUp()
    {
        $this->createTable('kurs_inhalt_uebersetzung', [
            'id' => $this->primaryKey(),
            'hash' => $this->char(64)->notNull(),
            'sprache' => $this->string(8)->notNull(),
            'quelle' => 'MEDIUMTEXT NOT NULL',
            'text' => 'MEDIUMTEXT NOT NULL',
            'modell' => $this->string(60)->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->createIndex('ux_kurs_inhalt_uebersetzung', 'kurs_inhalt_uebersetzung', ['hash', 'sprache'], true);
    }

    public function safeDown()
    {
        $this->dropTable('kurs_inhalt_uebersetzung');
    }
}
