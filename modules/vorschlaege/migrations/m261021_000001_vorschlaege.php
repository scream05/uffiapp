<?php

use yii\db\Migration;

class m261021_000001_vorschlaege extends Migration
{
    public function safeUp()
    {
        // Ein Vorschlag = ein Beitrag im Space; hier Titel, Kategorie und Stand
        $this->createTable('vorschlag', [
            'content_id' => $this->integer()->notNull(),
            'titel' => $this->string(150)->notNull(),
            'kategorie' => $this->string(50)->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('neu'),
            'note' => $this->text()->null(),
            'updated_by' => $this->integer()->null(),
            'updated_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(content_id)',
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addForeignKey('fk_vorschlag_content', 'vorschlag', 'content_id', 'content', 'id', 'CASCADE', 'CASCADE');
        $this->createIndex('idx_vorschlag_status', 'vorschlag', 'status');

        // Stimmen: +1 dafür, -1 dagegen; sichtbar ist nur die Summe, nicht wer abgestimmt hat
        $this->createTable('vorschlag_stimme', [
            'content_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'wert' => $this->tinyInteger()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(content_id, user_id)',
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addForeignKey('fk_vorschlag_stimme_content', 'vorschlag_stimme', 'content_id', 'content', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_vorschlag_stimme_user', 'vorschlag_stimme', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('vorschlag_stimme');
        $this->dropTable('vorschlag');
    }
}
