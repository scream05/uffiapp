<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('bauhof_nachricht');
        $this->dropTable('maengel_auftrag');
        $this->dropTable('bauhof_mitarbeiter');
        $this->dropTable('maengel_auch');
        $this->dropTable('maengel_status');
    }

    public function down()
    {
        return false;
    }
}
