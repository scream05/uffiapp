<?php

use yii\db\Migration;

/** Bauhof: Mitarbeiter (nur Name + WhatsApp-Nummer), Zuweisung je Meldung, Nachrichtenverlauf per WhatsApp */
class m261011_000001_bauhof extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        $this->createTable('bauhof_mitarbeiter', [
            'id' => $this->primaryKey(),
            'name' => $this->string(100)->notNull(),
            // WhatsApp-Nummer international ohne + (z. B. 4915112345678)
            'telefon' => $this->string(32)->notNull(),
            'aktiv' => $this->boolean()->notNull()->defaultValue(1),
            // Einwilligung zur Nutzung der Nummer dokumentiert (wann, von wem eingetragen)
            'einwilligung_at' => $this->dateTime()->notNull(),
            'einwilligung_by' => $this->integer()->null(),
            // Meldung, auf die sich Fotos/Texte ohne eindeutigen Bezug beziehen (zuletzt angezeigt)
            'aktuell_content_id' => $this->integer()->null(),
            // letzte Nachricht vom Mitarbeiter: 24-Stunden-Fenster für freie Nachrichten
            'letzte_eingang_at' => $this->dateTime()->null(),
            'benachrichtigt_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], $opts);
        $this->createIndex('ux_bauhof_mitarbeiter_telefon', 'bauhof_mitarbeiter', 'telefon', true);
        $this->addForeignKey('fk_bauhof_mitarbeiter_aktuell', 'bauhof_mitarbeiter', 'aktuell_content_id', 'content', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('maengel_auftrag', [
            'content_id' => $this->integer()->notNull(),
            'mitarbeiter_id' => $this->integer()->null(),
            // normal | hoch | dringend
            'prioritaet' => $this->string(10)->notNull()->defaultValue('normal'),
            'faellig' => $this->date()->null(),
            // geht mit dem Auftrag an den Mitarbeiter
            'hinweis' => $this->text()->null(),
            // nur für den Bauhof sichtbar
            'notiz' => $this->text()->null(),
            'zugewiesen_at' => $this->dateTime()->null(),
            'zugewiesen_by' => $this->integer()->null(),
            'gesendet_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(content_id)',
        ], $opts);
        $this->addForeignKey('fk_maengel_auftrag_content', 'maengel_auftrag', 'content_id', 'content', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_maengel_auftrag_mitarbeiter', 'maengel_auftrag', 'mitarbeiter_id', 'bauhof_mitarbeiter', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('bauhof_nachricht', [
            'id' => $this->primaryKey(),
            'mitarbeiter_id' => $this->integer()->null(),
            'content_id' => $this->integer()->null(),
            // ein | aus | intern (Statusänderung, Zuweisung)
            'richtung' => $this->string(10)->notNull(),
            // text | image | document | status | auftrag | knopf | system
            'typ' => $this->string(20)->notNull(),
            'text' => $this->text()->null(),
            'file_id' => $this->integer()->null(),
            'media_id' => $this->string(100)->null(),
            'wamid' => $this->string(190)->null(),
            // eingehend: Antwort auf diese unserer Nachrichten
            'bezug_wamid' => $this->string(190)->null(),
            // ausgehend: wartet (auf Antippen der Vorlage) | gesendet | zugestellt | gelesen | fehler
            'zustellung' => $this->string(20)->null(),
            'fehler' => $this->string(255)->null(),
            'user_id' => $this->integer()->null(),
            'verarbeitet' => $this->boolean()->notNull()->defaultValue(0),
            'gelesen_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], $opts);
        $this->createIndex('ux_bauhof_nachricht_wamid', 'bauhof_nachricht', 'wamid', true);
        $this->createIndex('ix_bauhof_nachricht_content', 'bauhof_nachricht', 'content_id');
        $this->createIndex('ix_bauhof_nachricht_mitarbeiter', 'bauhof_nachricht', ['mitarbeiter_id', 'created_at']);
        $this->addForeignKey('fk_bauhof_nachricht_content', 'bauhof_nachricht', 'content_id', 'content', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_bauhof_nachricht_mitarbeiter', 'bauhof_nachricht', 'mitarbeiter_id', 'bauhof_mitarbeiter', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('bauhof_nachricht');
        $this->dropTable('maengel_auftrag');
        $this->dropTable('bauhof_mitarbeiter');
    }
}
