<?php

use yii\db\Migration;

class m261011_200001_init extends Migration
{
    public function safeUp()
    {
        $opt = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        $this->createTable('mb_ausgabe', [
            'id' => $this->primaryKey(),
            'jahr' => $this->smallInteger()->notNull(),
            'nr' => $this->smallInteger()->notNull(),
            // Erscheinungstag laut Titelseite, sonst Datum auf uffenheim.de
            'datum' => $this->date()->notNull(),
            'quelle_url' => $this->string(500)->notNull(),
            'seiten' => $this->smallInteger()->notNull()->defaultValue(0),
            'groesse' => $this->integer()->notNull()->defaultValue(0),
            'post_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->createIndex('ux_mb_ausgabe_jahr_nr', 'mb_ausgabe', ['jahr', 'nr'], true);

        // Text je Seite für die Suche
        $this->createTable('mb_seite', [
            'id' => $this->primaryKey(),
            'ausgabe_id' => $this->integer()->notNull(),
            'seite' => $this->smallInteger()->notNull(),
            'text' => 'MEDIUMTEXT NOT NULL',
        ], $opt);
        $this->createIndex('ix_mb_seite_ausgabe', 'mb_seite', ['ausgabe_id', 'seite']);
        $this->addForeignKey('fk_mb_seite_ausgabe', 'mb_seite', 'ausgabe_id', 'mb_ausgabe', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('mb_seite');
        $this->dropTable('mb_ausgabe');
    }
}
