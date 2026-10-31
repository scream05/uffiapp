<?php

use yii\db\Migration;

class m261101_000001_kurzfassung extends Migration
{
    public function safeUp()
    {
        // „Stadtrat kurz erklärt“: Kurzfassung einer Sitzung aus der Niederschrift (PDF und Audio unter /data/stadtrat)
        $this->createTable('stadtrat_kurzfassung', [
            'sitzung_id' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('entwurf'),
            'kurz' => $this->text()->null(),
            'lang' => $this->text()->null(),
            'vorlese' => $this->text()->null(),
            'audio_text' => $this->text()->null(),
            'audio_pruefung' => $this->text()->null(),
            'arbeit' => $this->string(20)->null(),
            'fehler' => $this->text()->null(),
            'geaendert_am' => $this->dateTime()->notNull(),
            'freigegeben_am' => $this->dateTime()->null(),
            'freigegeben_von' => $this->integer()->null(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addPrimaryKey('pk_stadtrat_kurzfassung', 'stadtrat_kurzfassung', 'sitzung_id');
        $this->addForeignKey('fk_stadtrat_kurzfassung_von', 'stadtrat_kurzfassung', 'freigegeben_von', 'user', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('stadtrat_kurzfassung');
    }
}
