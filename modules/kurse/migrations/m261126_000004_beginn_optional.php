<?php

use yii\db\Migration;

/**
 * Erster Kurstag ist kein Pflichtfeld mehr: leer = Beginn steht noch nicht fest (beginn_offen = 1).
 */
class m261126_000004_beginn_optional extends Migration
{
    public function safeUp()
    {
        $this->alterColumn('kurs', 'beginn', $this->date()->null());
    }

    public function safeDown()
    {
        $this->update('kurs', ['beginn' => date('Y-m-d')], ['beginn' => null]);
        $this->alterColumn('kurs', 'beginn', $this->date()->notNull());
    }
}
