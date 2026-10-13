<?php

use yii\db\Migration;

class m261012_000001_doppelausgabe extends Migration
{
    public function safeUp()
    {
        // Doppelausgaben („Nr 1/2“ zum Jahresanfang): nr = erste, nr_bis = letzte Nummer
        $this->addColumn('mb_ausgabe', 'nr_bis', $this->smallInteger()->null()->after('nr'));
        $this->update('mb_ausgabe', ['nr_bis' => 2], ['nr' => 1, 'jahr' => [2025, 2026]]);
    }

    public function safeDown()
    {
        $this->dropColumn('mb_ausgabe', 'nr_bis');
    }
}
