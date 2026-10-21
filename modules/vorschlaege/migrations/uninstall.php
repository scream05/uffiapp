<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('vorschlag_stimme');
        $this->dropTable('vorschlag');
    }

    public function down()
    {
        return false;
    }
}
