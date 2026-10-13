<?php

use yii\db\Migration;

class m261004_000001_init extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        $this->createTable('verein', [
            'id' => $this->primaryKey(),
            'name' => $this->string(150)->notNull(),
            'kategorie' => $this->string(50)->notNull(),
            'ortsteil' => $this->string(50)->notNull()->defaultValue('Uffenheim'),
            // offen = vorbefüllt, noch von niemandem gepflegt; vorgeschlagen = von Nutzer angelegt, wartet auf Freigabe
            'status' => $this->string(20)->notNull()->defaultValue('offen'),
            'beschreibung' => $this->text()->null(),
            'treffpunkt' => $this->string(255)->null(),
            'website' => $this->string(255)->null(),
            'email' => $this->string(150)->null(),
            'telefon' => $this->string(50)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'created_by' => $this->integer()->null(),
            'updated_at' => $this->dateTime()->null(),
            'updated_by' => $this->integer()->null(),
        ], $opts);
        $this->createIndex('ux_verein_name', 'verein', 'name', true);

        $this->createTable('verein_verwalter', [
            'verein_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            // angefragt | bestaetigt
            'status' => $this->string(20)->notNull()->defaultValue('angefragt'),
            'nachricht' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(verein_id, user_id)',
        ], $opts);
        $this->addForeignKey('fk_verein_verwalter_verein', 'verein_verwalter', 'verein_id', 'verein', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_verein_verwalter_user', 'verein_verwalter', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');

        // Termin im gemeinsamen Kalender ↔ Verein; wird der Termin gelöscht, verschwindet die Verknüpfung
        $this->createTable('verein_termin', [
            'calendar_entry_id' => $this->integer()->notNull(),
            'verein_id' => $this->integer()->notNull(),
            'PRIMARY KEY(calendar_entry_id)',
        ], $opts);
        $this->addForeignKey('fk_verein_termin_entry', 'verein_termin', 'calendar_entry_id', 'calendar_entry', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_verein_termin_verein', 'verein_termin', 'verein_id', 'verein', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('verein_termin');
        $this->dropTable('verein_verwalter');
        $this->dropTable('verein');
    }
}
