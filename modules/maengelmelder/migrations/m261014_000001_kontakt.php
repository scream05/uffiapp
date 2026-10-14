<?php

use yii\db\Migration;

class m261014_000001_kontakt extends Migration
{
    public function safeUp()
    {
        // Kontaktdaten der meldenden Person (Gäste: Name, E-Mail/Telefon; Konten: nur Telefon) – sieht nur der Bauhof
        $this->createTable('maengel_kontakt', [
            'content_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null(),
            'vorname' => $this->string(60)->null(),
            'nachname' => $this->string(60)->null(),
            'email' => $this->string(150)->null(),
            'telefon' => $this->string(30)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(content_id)',
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addForeignKey('fk_maengel_kontakt_content', 'maengel_kontakt', 'content_id', 'content', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_maengel_kontakt_user', 'maengel_kontakt', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('maengel_kontakt');
    }
}
