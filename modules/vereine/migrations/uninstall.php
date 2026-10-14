<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('verein_beitrag');
        $this->dropTable('verein_termin');
        $this->dropTable('verein_verwalter');
        $this->dropTable('verein');
    }

    public function down()
    {
        return false;
    }
}
