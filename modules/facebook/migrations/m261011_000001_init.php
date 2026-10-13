<?php

use yii\db\Migration;

class m261011_000001_init extends Migration
{
    public function safeUp()
    {
        // Welcher Facebook-Beitrag zu welchem HumHub-Beitrag gehört (für Aktualisierungen und gegen Doppelte)
        $this->createTable('fb_beitrag', [
            'id' => $this->primaryKey(),
            'fb_id' => $this->string(100)->notNull(),
            'post_id' => $this->integer()->null(),
            'fb_updated' => $this->string(40)->null(),
            // übernommen | doppelt (steht schon als Website-Artikel im Stream) | leer
            'status' => $this->string(20)->notNull()->defaultValue('übernommen'),
            'created_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ux_fb_beitrag_fb_id', 'fb_beitrag', 'fb_id', true);
    }

    public function safeDown()
    {
        $this->dropTable('fb_beitrag');
    }
}
