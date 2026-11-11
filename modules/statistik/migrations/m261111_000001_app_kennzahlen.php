<?php

use yii\db\Migration;

/**
 * App-Kennzahlen wie im App Store:
 * - statistik_konto_tag: an welchen Tagen ein Konto aktiv war (nur Konto-Nr. + Datum) → täglich/wöchentlich/monatlich aktive Konten
 * - statistik_aufruf.start: Aufruf kam vom Startbildschirm (PWA-Start, markierte start_url im Manifest)
 */
class m261111_000001_app_kennzahlen extends Migration
{
    public function safeUp()
    {
        $this->createTable('statistik_konto_tag', [
            'user_id' => $this->integer()->notNull(),
            'tag' => $this->date()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addPrimaryKey('pk_statistik_konto_tag', 'statistik_konto_tag', ['tag', 'user_id']);
        $this->addForeignKey('fk_statistik_konto_tag_user', 'statistik_konto_tag', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addColumn('statistik_aufruf', 'start', $this->boolean()->notNull()->defaultValue(0));
    }

    public function safeDown()
    {
        $this->dropColumn('statistik_aufruf', 'start');
        $this->dropTable('statistik_konto_tag');
    }
}
