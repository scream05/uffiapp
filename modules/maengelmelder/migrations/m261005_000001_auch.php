<?php

use yii\db\Migration;

class m261005_000001_auch extends Migration
{
    public function safeUp()
    {
        // „Betrifft mich auch“: weitere Personen schließen sich einer Meldung an, statt sie erneut zu melden
        $this->createTable('maengel_auch', [
            'content_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(content_id, user_id)',
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addForeignKey('fk_maengel_auch_content', 'maengel_auch', 'content_id', 'content', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_maengel_auch_user', 'maengel_auch', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('maengel_auch');
    }
}
