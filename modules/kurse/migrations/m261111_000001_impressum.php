<?php

use yii\db\Migration;

/**
 * Eigene Kursseite: Impressum des Vereins verlinken (leer = Impressum der Plattform)
 */
class m261111_000001_impressum extends Migration
{
    public function safeUp()
    {
        $this->addColumn('kurs_verein', 'impressum_url', $this->string(255)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('kurs_verein', 'impressum_url');
    }
}
