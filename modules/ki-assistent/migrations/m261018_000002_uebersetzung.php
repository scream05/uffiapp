<?php

use yii\db\Migration;

class m261018_000002_uebersetzung extends Migration
{
    public function safeUp()
    {
        // Zwischenspeicher für Übersetzungen auf Klick: je Ausgangstext (SHA-256) und Zielsprache einmal, 30 Tage
        $this->createTable('ki_uebersetzung', [
            'id' => $this->primaryKey(),
            'hash' => $this->char(64)->notNull(),
            'sprache' => $this->string(8)->notNull(),
            'text' => 'MEDIUMTEXT NOT NULL',
            'created_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ux_ki_uebersetzung', 'ki_uebersetzung', ['hash', 'sprache'], true);
        $this->createIndex('ix_ki_uebersetzung_created', 'ki_uebersetzung', 'created_at');
    }

    public function safeDown()
    {
        $this->dropTable('ki_uebersetzung');
    }
}
