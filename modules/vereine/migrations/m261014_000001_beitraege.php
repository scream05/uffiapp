<?php

use yii\db\Migration;

class m261014_000001_beitraege extends Migration
{
    public function safeUp()
    {
        // Beiträge von Vereinsverwaltern; erscheinen erst nach Prüfung durch einen Admin
        $this->createTable('verein_beitrag', [
            'id' => $this->primaryKey(),
            'verein_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'text' => $this->text()->notNull(),
            // eingereicht | veroeffentlicht | abgelehnt
            'status' => $this->string(20)->notNull()->defaultValue('eingereicht'),
            // Wo der Admin ihn freigegeben hat: auf der Vereinsseite und/oder als Beitrag in der Übersicht
            'auf_vereinsseite' => $this->boolean()->notNull()->defaultValue(0),
            'uebersicht' => $this->boolean()->notNull()->defaultValue(0),
            // HumHub-Beitrag im Space des Verzeichnisses (nur bei „Übersicht“)
            'content_id' => $this->integer()->null(),
            'begruendung' => $this->text()->null(),
            'geprueft_von' => $this->integer()->null(),
            'geprueft_am' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ix_verein_beitrag_status', 'verein_beitrag', ['status', 'verein_id']);
        $this->addForeignKey('fk_verein_beitrag_verein', 'verein_beitrag', 'verein_id', 'verein', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_verein_beitrag_user', 'verein_beitrag', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_verein_beitrag_content', 'verein_beitrag', 'content_id', 'content', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('verein_beitrag');
    }
}
