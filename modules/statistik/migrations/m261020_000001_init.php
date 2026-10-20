<?php

use yii\db\Migration;

class m261020_000001_init extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        // Ein Eintrag je Seitenaufruf. Keine IP, keine Konto-ID, kein Cookie:
        // „besucher“ ist ein Hash mit täglich neuem Zufallssalz, das nach dem Tag verworfen wird.
        $this->createTable('statistik_aufruf', [
            'id' => $this->bigPrimaryKey(),
            'zeit' => $this->dateTime()->notNull(),
            'tag' => $this->date()->notNull(),
            'pfad' => $this->string(255)->notNull(),
            'besucher' => $this->char(16)->notNull(),
            'besuch' => $this->char(16)->notNull(),
            'neuer_besuch' => $this->boolean()->notNull()->defaultValue(0),
            'angemeldet' => $this->boolean()->notNull()->defaultValue(0),
            'geraet' => $this->string(10)->notNull(),   // Handy | Tablet | PC | App
            'browser' => $this->string(20)->notNull(),
            'system' => $this->string(20)->notNull(),
            'herkunft' => $this->string(100)->null(),   // nur Domain einer fremden Seite
        ], $opts);
        $this->createIndex('ix_statistik_aufruf_tag', 'statistik_aufruf', 'tag');
        $this->createIndex('ix_statistik_aufruf_besucher', 'statistik_aufruf', ['besucher', 'tag']);
    }

    public function safeDown()
    {
        $this->dropTable('statistik_aufruf');
    }
}
