<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('gewerbe_beitrag');
        $this->dropTable('gewerbe_verwalter');
        $this->dropTable('gewerbe_betrieb');
    }

    public function down()
    {
        return false;
    }
}
