<?php

use yii\db\Migration;

class m261108_000001_init extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        // Welche Vereine das Kursmodul nutzen dürfen (Schalter nur für Admins)
        $this->createTable('kurs_verein', [
            'verein_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'created_by' => $this->integer()->null(),
            'PRIMARY KEY(verein_id)',
        ], $opts);
        $this->addForeignKey('fk_kurs_verein_verein', 'kurs_verein', 'verein_id', 'verein', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('kurs', [
            'id' => $this->primaryKey(),
            'verein_id' => $this->integer()->notNull(),
            // entwurf | offen | abgesagt
            'status' => $this->string(20)->notNull()->defaultValue('entwurf'),
            'titel' => $this->string(150)->notNull(),
            'niveau' => $this->string(100)->null(),
            'beschreibung' => $this->text()->null(),
            'ort' => $this->string(255)->notNull(),
            'beginn' => $this->date()->notNull(),
            'ende' => $this->date()->null(),
            'zeit_von' => $this->time()->null(),
            'zeit_bis' => $this->time()->null(),
            'termine_hinweis' => $this->string(255)->null(),
            'einheiten' => $this->smallInteger()->null(),
            'alter_min' => $this->decimal(4, 1)->null(),
            'alter_max' => $this->decimal(4, 1)->null(),
            'plaetze' => $this->smallInteger()->notNull(),
            'warteliste_max' => $this->smallInteger()->null(),
            'preis_mitglied' => $this->decimal(7, 2)->null(),
            'preis_nichtmitglied' => $this->decimal(7, 2)->null(),
            'nur_mitglieder' => $this->boolean()->notNull()->defaultValue(false),
            'zahlart_bar' => $this->boolean()->notNull()->defaultValue(true),
            'zahlart_ueberweisung' => $this->boolean()->notNull()->defaultValue(true),
            'kontoinhaber' => $this->string(150)->null(),
            'iban' => $this->string(40)->null(),
            'zahlungsfrist_tage' => $this->smallInteger()->null(),
            'anmeldung_ab' => $this->dateTime()->null(),
            'anmeldung_bis' => $this->dateTime()->null(),
            'erinnerung_tage' => $this->smallInteger()->null(),
            'kontakt_email' => $this->string(150)->notNull(),
            'bedingungen' => $this->text()->null(),
            'absage_grund' => $this->text()->null(),
            'erinnerung_am' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'created_by' => $this->integer()->null(),
            'updated_at' => $this->dateTime()->null(),
            'updated_by' => $this->integer()->null(),
        ], $opts);
        $this->addForeignKey('fk_kurs_verein', 'kurs', 'verein_id', 'verein', 'id', 'CASCADE', 'CASCADE');
        $this->createIndex('ix_kurs_beginn', 'kurs', ['status', 'beginn']);

        $this->createTable('kurs_anmeldung', [
            'id' => $this->primaryKey(),
            'kurs_id' => $this->integer()->notNull(),
            'token' => $this->string(64)->notNull(),
            // unbestaetigt | platz | warteliste | storniert
            'status' => $this->string(20)->notNull()->defaultValue('unbestaetigt'),
            'kind_vorname' => $this->string(100)->notNull(),
            'kind_nachname' => $this->string(100)->notNull(),
            'kind_geburtsdatum' => $this->date()->null(),
            'schwimmstand' => $this->text()->null(),
            'eltern_name' => $this->string(150)->notNull(),
            'email' => $this->string(150)->null(),
            'telefon' => $this->string(50)->null(),
            'notfall_telefon' => $this->string(50)->null(),
            'mitglied' => $this->boolean()->notNull()->defaultValue(false),
            'zahlart' => $this->string(20)->null(),
            'betrag' => $this->decimal(7, 2)->null(),
            'bezahlt_am' => $this->date()->null(),
            'notiz' => $this->text()->null(),
            'von_hand' => $this->boolean()->notNull()->defaultValue(false),
            'user_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'bestaetigt_am' => $this->dateTime()->null(),
            'platz_am' => $this->dateTime()->null(),
            'storniert_am' => $this->dateTime()->null(),
            'storniert_von' => $this->string(20)->null(),
            'erinnerung_am' => $this->dateTime()->null(),
            'zahlungserinnerung_am' => $this->dateTime()->null(),
            'anonymisiert_am' => $this->dateTime()->null(),
        ], $opts);
        $this->createIndex('ux_kurs_anmeldung_token', 'kurs_anmeldung', 'token', true);
        $this->createIndex('ix_kurs_anmeldung_kurs', 'kurs_anmeldung', ['kurs_id', 'status']);
        $this->addForeignKey('fk_kurs_anmeldung_kurs', 'kurs_anmeldung', 'kurs_id', 'kurs', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_kurs_anmeldung_user', 'kurs_anmeldung', 'user_id', 'user', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('kurs_anmeldung');
        $this->dropTable('kurs');
        $this->dropTable('kurs_verein');
    }
}
