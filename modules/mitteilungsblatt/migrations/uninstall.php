<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('mb_seite');
        $this->dropTable('mb_ausgabe');
    }
}
