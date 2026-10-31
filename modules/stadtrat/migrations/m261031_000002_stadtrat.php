<?php

use yii\db\Migration;

class m261031_000002_stadtrat extends Migration
{
    public function safeUp()
    {
        // Eine Sitzung aus dem Ratsinformationssystem; tagesordnung = JSON [[nr, titel], …] (nur öffentlicher Teil)
        $this->createTable('stadtrat_sitzung', [
            'id' => $this->integer()->notNull(),
            'gremium' => $this->string(150)->notNull(),
            'titel' => $this->string(255)->notNull(),
            'datum' => $this->date()->notNull(),
            'beginn' => $this->string(5)->null(),
            'ort' => $this->string(255)->null(),
            'nichtoeffentlich' => $this->boolean()->notNull()->defaultValue(0),
            'tagesordnung' => $this->text()->null(),
            'hinweise' => $this->text()->null(),
            'ris_stand' => $this->string(20)->null(),
            'gemeldet_am' => $this->dateTime()->null(),
            'abgerufen_am' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addPrimaryKey('pk_stadtrat_sitzung', 'stadtrat_sitzung', 'id');
        $this->createIndex('idx_stadtrat_sitzung_datum', 'stadtrat_sitzung', 'datum');
    }

    public function safeDown()
    {
        $this->dropTable('stadtrat_sitzung');
    }
}
