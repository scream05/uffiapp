<?php

use yii\db\Migration;

class m261101_000002_beitrag extends Migration
{
    public function safeUp()
    {
        // Kurzfassung zusätzlich als Beitrag in der Übersicht (Space „Rathaus Uffenheim“): id des Posts
        $this->addColumn('stadtrat_kurzfassung', 'beitrag_id', $this->integer()->null());
    }

    public function safeDown()
    {
        $this->dropColumn('stadtrat_kurzfassung', 'beitrag_id');
    }
}
