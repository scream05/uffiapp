<?php

use yii\db\Migration;

class m261020_000002_tanken extends Migration
{
    public function safeUp()
    {
        // Je Abruf der niedrigste Preis unter den geöffneten Tankstellen im Umkreis (für den Tagesverlauf)
        $this->createTable('tanken_verlauf', [
            'id' => $this->primaryKey(),
            'zeit' => $this->dateTime()->notNull(),
            'e5' => $this->decimal(4, 3)->null(),
            'e10' => $this->decimal(4, 3)->null(),
            'diesel' => $this->decimal(4, 3)->null(),
            'offen' => $this->smallInteger()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ix_tanken_verlauf_zeit', 'tanken_verlauf', 'zeit');
    }

    public function safeDown()
    {
        $this->dropTable('tanken_verlauf');
    }
}
