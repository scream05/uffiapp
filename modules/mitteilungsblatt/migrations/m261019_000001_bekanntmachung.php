<?php

use yii\db\Migration;

class m261019_000001_bekanntmachung extends Migration
{
    public function safeUp()
    {
        $opt = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        // Amtliche Bekanntmachungen aus dem Amtsblatt (nichtamtliches Verzeichnis, verlinkt auf die Originalseite)
        $this->createTable('mb_bekanntmachung', [
            'id' => $this->primaryKey(),
            'ausgabe_id' => $this->integer()->notNull(),
            'seite' => $this->smallInteger()->notNull(),
            'titel' => $this->string(300)->notNull(),
            'art' => $this->string(20)->notNull(),
            'stelle' => $this->string(150)->null(), // bekanntmachende Stelle, z. B. Landratsamt
            'frist_bis' => $this->date()->null(),
            'frist_text' => $this->string(200)->null(),
            // enthält Namen/Anschriften von Privatpersonen (z. B. öffentliche Zustellung): nur Titel + Link, nicht für Uffi
            'personenbezug' => $this->boolean()->notNull()->defaultValue(0),
            'ausgeblendet' => $this->boolean()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->createIndex('ix_mb_bekanntmachung_ausgabe', 'mb_bekanntmachung', ['ausgabe_id', 'seite']);
        $this->addForeignKey('fk_mb_bekanntmachung_ausgabe', 'mb_bekanntmachung', 'ausgabe_id', 'mb_ausgabe', 'id', 'CASCADE');
        // Wann die Ausgabe zuletzt auf Bekanntmachungen durchgesehen wurde
        $this->addColumn('mb_ausgabe', 'bm_geprueft', $this->dateTime()->null());
    }

    public function safeDown()
    {
        $this->dropColumn('mb_ausgabe', 'bm_geprueft');
        $this->dropTable('mb_bekanntmachung');
    }
}
