<?php

use yii\db\Migration;

class m261015_000001_init extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        $this->createTable('gewerbe_betrieb', [
            'id' => $this->primaryKey(),
            'name' => $this->string(150)->notNull(),
            'kategorie' => $this->string(50)->notNull(),
            'ortsteil' => $this->string(50)->notNull()->defaultValue('Uffenheim'),
            // vorgeschlagen = wartet auf Freigabe; aktiv = im Verzeichnis
            'status' => $this->string(20)->notNull()->defaultValue('vorgeschlagen'),
            'angebot' => $this->string(500)->null(), // Stichworte für Suche und Liste
            'beschreibung' => $this->text()->null(),
            'adresse' => $this->string(255)->null(),
            'oeffnungszeiten' => $this->text()->null(),
            'website' => $this->string(255)->null(),
            'email' => $this->string(150)->null(),
            'telefon' => $this->string(50)->null(),
            'social' => $this->text()->null(), // JSON [{typ,url}]
            'logo_guid' => $this->string(45)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'created_by' => $this->integer()->null(),
            'updated_at' => $this->dateTime()->null(),
            'updated_by' => $this->integer()->null(),
        ], $opts);
        $this->createIndex('ux_gewerbe_betrieb_name', 'gewerbe_betrieb', 'name', true);

        $this->createTable('gewerbe_verwalter', [
            'betrieb_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            // angefragt | bestaetigt
            'status' => $this->string(20)->notNull()->defaultValue('angefragt'),
            'nachricht' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(betrieb_id, user_id)',
        ], $opts);
        $this->addForeignKey('fk_gewerbe_verwalter_betrieb', 'gewerbe_verwalter', 'betrieb_id', 'gewerbe_betrieb', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_gewerbe_verwalter_user', 'gewerbe_verwalter', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('gewerbe_verwalter');
        $this->dropTable('gewerbe_betrieb');
    }
}
