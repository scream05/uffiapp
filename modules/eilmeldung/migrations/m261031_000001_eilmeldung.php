<?php

use yii\db\Migration;

class m261031_000001_eilmeldung extends Migration
{
    public function safeUp()
    {
        // Eine Eilmeldung für ganz Uffenheim: von Hand (Admins, Bauhof) oder aus NINA (quelle_id = Kennung der Warnung)
        $this->createTable('eilmeldung', [
            'id' => $this->primaryKey(),
            'art' => $this->string(20)->notNull(),
            'titel' => $this->string(150)->notNull(),
            'text' => $this->text()->null(),
            'gueltig_bis' => $this->dateTime()->notNull(),
            'beendet_am' => $this->dateTime()->null(),
            'quelle' => $this->string(10)->notNull()->defaultValue('hand'),
            'quelle_id' => $this->string(150)->null(),
            'schwere' => $this->string(20)->null(),
            'empfaenger' => $this->integer()->notNull()->defaultValue(0),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('idx_eilmeldung_quelle_id', 'eilmeldung', 'quelle_id', true);
        $this->createIndex('idx_eilmeldung_gueltig', 'eilmeldung', ['beendet_am', 'gueltig_bis']);
        $this->addForeignKey('fk_eilmeldung_user', 'eilmeldung', 'created_by', 'user', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('eilmeldung');
    }
}
