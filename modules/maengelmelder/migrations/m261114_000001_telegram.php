<?php

use yii\db\Migration;

/**
 * Zweiter Nachrichtenkanal für Bauhof-Mitarbeiter: Telegram. Je Mitarbeiter die chat_id (erst nach dem
 * Verbinden gesetzt) und ein Einmal-Code für den persönlichen Verbinden-Link (t.me/Bot?start=<Code>).
 */
class m261114_000001_telegram extends Migration
{
    public function safeUp()
    {
        $this->addColumn('bauhof_mitarbeiter', 'telegram_chat_id', $this->string(32)->null());
        $this->addColumn('bauhof_mitarbeiter', 'telegram_link_token', $this->string(40)->null());
        $this->createIndex('ix_bauhof_mitarbeiter_tg', 'bauhof_mitarbeiter', 'telegram_chat_id');
        $this->createIndex('ix_bauhof_mitarbeiter_tglink', 'bauhof_mitarbeiter', 'telegram_link_token');
    }

    public function safeDown()
    {
        $this->dropIndex('ix_bauhof_mitarbeiter_tglink', 'bauhof_mitarbeiter');
        $this->dropIndex('ix_bauhof_mitarbeiter_tg', 'bauhof_mitarbeiter');
        $this->dropColumn('bauhof_mitarbeiter', 'telegram_link_token');
        $this->dropColumn('bauhof_mitarbeiter', 'telegram_chat_id');
    }
}
