<?php

use yii\db\Migration;

/**
 * Link zum Aufnahmeantrag / Mitglied werden (je Verein, im Web änderbar)
 */
class m261126_000006_mitglied_url extends Migration
{
    public function safeUp()
    {
        $this->addColumn('kurs_verein', 'mitglied_url', $this->string(500)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('kurs_verein', 'mitglied_url');
    }
}
