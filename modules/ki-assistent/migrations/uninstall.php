<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('ki_feedback');
    }

    public function down()
    {
        return false;
    }
}
