<?php

use yii\db\Migration;

/**
 * Erster Kurstag wieder Pflicht (Nutzerentscheidung 26.11.2026, macht m261126_000004 rückgängig).
 */
class m261126_000005_beginn_wieder_pflicht extends Migration
{
    public function safeUp()
    {
        $this->update('kurs', ['beginn' => date('Y-m-d'), 'beginn_offen' => 1], ['beginn' => null]);
        $this->alterColumn('kurs', 'beginn', $this->date()->notNull());
    }

    public function safeDown()
    {
        $this->alterColumn('kurs', 'beginn', $this->date()->null());
    }
}
