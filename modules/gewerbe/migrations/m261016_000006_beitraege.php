<?php

use yii\db\Migration;

class m261016_000006_beitraege extends Migration
{
    public function safeUp()
    {
        // Beiträge von Betriebsverwaltern; erscheinen erst nach Prüfung durch einen Admin
        $this->createTable('gewerbe_beitrag', [
            'id' => $this->primaryKey(),
            'betrieb_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'text' => $this->text()->notNull(),
            // eingereicht | veroeffentlicht | abgelehnt
            'status' => $this->string(20)->notNull()->defaultValue('eingereicht'),
            // Wo der Admin ihn freigegeben hat: auf der Betriebsseite und/oder als Beitrag in der Übersicht
            'auf_betriebsseite' => $this->boolean()->notNull()->defaultValue(0),
            'uebersicht' => $this->boolean()->notNull()->defaultValue(0),
            // HumHub-Beitrag im Space des Verzeichnisses (nur bei „Übersicht“)
            'content_id' => $this->integer()->null(),
            'begruendung' => $this->text()->null(),
            'geprueft_von' => $this->integer()->null(),
            'geprueft_am' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ix_gewerbe_beitrag_status', 'gewerbe_beitrag', ['status', 'betrieb_id']);
        $this->addForeignKey('fk_gewerbe_beitrag_betrieb', 'gewerbe_beitrag', 'betrieb_id', 'gewerbe_betrieb', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_gewerbe_beitrag_user', 'gewerbe_beitrag', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_gewerbe_beitrag_content', 'gewerbe_beitrag', 'content_id', 'content', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('gewerbe_beitrag');
    }
}
