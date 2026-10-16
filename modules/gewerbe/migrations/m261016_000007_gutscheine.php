<?php

use yii\db\Migration;

class m261016_000007_gutscheine extends Migration
{
    public function safeUp()
    {
        // Rabatt-Gutscheine der Betriebe (kein Geldwert); bestätigte Verwalter veröffentlichen direkt
        $this->createTable('gewerbe_gutschein', [
            'id' => $this->primaryKey(),
            'betrieb_id' => $this->integer()->notNull(),
            'titel' => $this->string(120)->notNull(),
            'bedingungen' => $this->text()->null(),
            'gueltig_ab' => $this->date()->notNull(),
            'gueltig_bis' => $this->date()->notNull(),
            // einmal | tag | woche | monat | unbegrenzt
            'pro_person' => $this->string(20)->notNull()->defaultValue('einmal'),
            'limit_gesamt' => $this->integer()->null(),
            // nur während der hinterlegten Öffnungszeiten einlösbar
            'nur_geoeffnet' => $this->boolean()->notNull()->defaultValue(0),
            // aktiv | pausiert (vom Betrieb oder automatisch) | beendet
            'status' => $this->string(20)->notNull()->defaultValue('aktiv'),
            'pause_grund' => $this->string(255)->null(),
            // Zähler bleibt, auch wenn alte Einlösungen gelöscht werden
            'eingeloest' => $this->integer()->notNull()->defaultValue(0),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->null(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ix_gewerbe_gutschein_betrieb', 'gewerbe_gutschein', ['betrieb_id', 'status']);
        $this->addForeignKey('fk_gewerbe_gutschein_betrieb', 'gewerbe_gutschein', 'betrieb_id', 'gewerbe_betrieb', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_gewerbe_gutschein_user', 'gewerbe_gutschein', 'created_by', 'user', 'id', 'SET NULL', 'CASCADE');

        // Eine Einlösung je Person und Vorgang; der Betrieb sieht nur Zeit und Kurzcode, nie die Person
        $this->createTable('gewerbe_einloesung', [
            'id' => $this->primaryKey(),
            'gutschein_id' => $this->integer()->notNull(),
            'betrieb_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'code' => $this->char(4)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'storniert_am' => $this->dateTime()->null(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ix_gewerbe_einloesung_person', 'gewerbe_einloesung', ['gutschein_id', 'user_id', 'created_at']);
        $this->createIndex('ix_gewerbe_einloesung_betrieb', 'gewerbe_einloesung', ['betrieb_id', 'created_at']);
        $this->addForeignKey('fk_gewerbe_einloesung_gutschein', 'gewerbe_einloesung', 'gutschein_id', 'gewerbe_gutschein', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_gewerbe_einloesung_betrieb', 'gewerbe_einloesung', 'betrieb_id', 'gewerbe_betrieb', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_gewerbe_einloesung_user', 'gewerbe_einloesung', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');

        // Theken-QR je Betrieb (Zufallstoken im Link) und 4-stellige Nummer für Einlösen ohne Kamera
        $this->addColumn('gewerbe_betrieb', 'theke_token', $this->string(40)->null());
        $this->addColumn('gewerbe_betrieb', 'theke_nummer', $this->char(4)->null());
        $this->createIndex('ux_gewerbe_betrieb_theke', 'gewerbe_betrieb', 'theke_token', true);
    }

    public function safeDown()
    {
        $this->dropIndex('ux_gewerbe_betrieb_theke', 'gewerbe_betrieb');
        $this->dropColumn('gewerbe_betrieb', 'theke_nummer');
        $this->dropColumn('gewerbe_betrieb', 'theke_token');
        $this->dropTable('gewerbe_einloesung');
        $this->dropTable('gewerbe_gutschein');
    }
}
