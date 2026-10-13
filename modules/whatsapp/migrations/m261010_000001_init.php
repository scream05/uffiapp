<?php

use yii\db\Migration;

class m261010_000001_init extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        // Eingegangene WhatsApp-Nachrichten (Rohdaten, nach der Bearbeitung befristet aufbewahrt)
        $this->createTable('wa_nachricht', [
            'id' => $this->primaryKey(),
            'wamid' => $this->string(190)->notNull(),
            // Telefonnummer des Absenders (wa_id), für die Antwort; wird nach Ablauf der Frist entfernt
            'wa_id' => $this->string(32)->null(),
            'absender_name' => $this->string(150)->null(),
            'typ' => $this->string(20)->notNull(),
            'text' => $this->text()->null(),
            'media_id' => $this->string(100)->null(),
            'mime' => $this->string(100)->null(),
            'datei' => $this->string(255)->null(),
            'weitergeleitet' => $this->boolean()->notNull()->defaultValue(0),
            'empfangen_at' => $this->dateTime()->notNull(),
            // neu | verarbeitet | ignoriert | fehler
            'status' => $this->string(20)->notNull()->defaultValue('neu'),
            'fehler' => $this->string(255)->null(),
            // Ergebnis der Auswertung, z. B. Grund, wenn kein Termin erkannt wurde
            'ergebnis' => $this->string(500)->null(),
            // Nachrichten, die zusammen ausgewertet wurden (Bild + Text desselben Absenders)
            'gruppe' => $this->string(40)->null(),
        ], $opts);
        $this->createIndex('ux_wa_nachricht_wamid', 'wa_nachricht', 'wamid', true);
        $this->createIndex('ix_wa_nachricht_status', 'wa_nachricht', 'status');
        $this->createIndex('ix_wa_nachricht_gruppe', 'wa_nachricht', 'gruppe');

        // Von Claude erkannte Termine, warten auf Freigabe
        $this->createTable('wa_entwurf', [
            'id' => $this->primaryKey(),
            'gruppe' => $this->string(40)->notNull(),
            'wa_id' => $this->string(32)->null(),
            'absender_name' => $this->string(150)->null(),
            'titel' => $this->string(200)->notNull(),
            'start_datum' => $this->date()->null(),
            'start_zeit' => $this->string(5)->null(),
            'ende_datum' => $this->date()->null(),
            'ende_zeit' => $this->string(5)->null(),
            'ganztaegig' => $this->boolean()->notNull()->defaultValue(0),
            'ort' => $this->string(255)->null(),
            'veranstalter' => $this->string(200)->null(),
            'beschreibung' => $this->text()->null(),
            'kategorie' => $this->string(100)->null(),
            // Nummer des Bildes der Einreichung (0-basiert), das zum Termin gehört
            'bild' => $this->integer()->null(),
            'hinweis' => $this->string(500)->null(),
            // offen | uebernommen | abgelehnt
            'status' => $this->string(20)->notNull()->defaultValue('offen'),
            'calendar_entry_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'bearbeitet_at' => $this->dateTime()->null(),
            'bearbeitet_by' => $this->integer()->null(),
        ], $opts);
        $this->createIndex('ix_wa_entwurf_status', 'wa_entwurf', 'status');
        $this->addForeignKey('fk_wa_entwurf_entry', 'wa_entwurf', 'calendar_entry_id', 'calendar_entry', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('wa_entwurf');
        $this->dropTable('wa_nachricht');
    }
}
