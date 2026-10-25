<?php

use yii\db\Migration;

class m261025_000001_spiele extends Migration
{
    public function safeUp()
    {
        // Kennung des Vereins beim Bayerischen Fußball-Verband (bfv.de/vereine/<name>/<id>); gesetzt = Spiele werden abgerufen
        $this->addColumn('verein', 'bfv_id', $this->string(40)->null());
        // Vereinsname beim BFV (kürzer als im Verzeichnis, z. B. „1. FV Uffenheim“), für Menü und Überschrift
        $this->addColumn('verein', 'bfv_name', $this->string(150)->null());

        $this->createTable('verein_mannschaft', [
            'id' => $this->string(40)->notNull(), // permanentId beim BFV
            'verein_id' => $this->integer()->notNull(),
            'name' => $this->string(150)->notNull(),
            'typ' => $this->string(50)->notNull(), // Herren, A-Junioren, …
            'liga' => $this->string(150)->null(),
            'liga_id' => $this->string(60)->null(),
            'tabelle' => $this->text()->null(), // JSON [{platz,name,spiele,s,u,n,diff,punkte,eigen}]
            'aktualisiert_am' => $this->dateTime()->notNull(),
            'PRIMARY KEY(id)',
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addForeignKey('fk_verein_mannschaft_verein', 'verein_mannschaft', 'verein_id', 'verein', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('verein_spiel', [
            'id' => $this->primaryKey(),
            'mannschaft_id' => $this->string(40)->notNull(),
            'spiel_id' => $this->string(40)->notNull(), // matchId beim BFV
            'datum' => $this->date()->notNull(),
            'uhrzeit' => $this->string(5)->null(), // Ortszeit Europe/Berlin, leer = noch offen
            'wettbewerb' => $this->string(150)->null(),
            'wettbewerb_typ' => $this->string(60)->null(), // Meisterschaften, Pokale, Freundschaftsspiele, Turniere …
            'heim' => $this->string(150)->notNull(),
            'gast' => $this->string(150)->notNull(),
            'heim_eigen' => $this->boolean()->notNull(), // eigene Mannschaft spielt zu Hause
            'ergebnis' => $this->string(30)->null(),
            'ort' => $this->string(255)->null(),
            'aktualisiert_am' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ux_verein_spiel', 'verein_spiel', ['mannschaft_id', 'spiel_id'], true);
        $this->createIndex('ix_verein_spiel_datum', 'verein_spiel', ['datum']);
        $this->addForeignKey('fk_verein_spiel_mannschaft', 'verein_spiel', 'mannschaft_id', 'verein_mannschaft', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('verein_spiel');
        $this->dropTable('verein_mannschaft');
        $this->dropColumn('verein', 'bfv_name');
        $this->dropColumn('verein', 'bfv_id');
    }
}
