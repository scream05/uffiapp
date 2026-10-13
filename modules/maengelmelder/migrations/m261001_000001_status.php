<?php

use yii\db\Migration;

class m261001_000001_status extends Migration
{
    public function safeUp()
    {
        $this->createTable('maengel_status', [
            'content_id' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('gemeldet'),
            'note' => $this->text()->null(),
            'updated_by' => $this->integer()->null(),
            'updated_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY(content_id)',
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        // Wird die Meldung gelöscht, verschwindet auch ihr Status
        $this->addForeignKey('fk_maengel_status_content', 'maengel_status', 'content_id', 'content', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('maengel_status');
    }
}
