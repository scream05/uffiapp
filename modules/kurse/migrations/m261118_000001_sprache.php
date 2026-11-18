<?php

use yii\db\Migration;

/**
 * Sprache der Eltern bei der Anmeldung (Yii::$app->language), damit alle Mails – auch aus dem Cron – in dieser Sprache
 * kommen. Leer (alte und von Hand eingetragene Anmeldungen) = Deutsch.
 */
class m261118_000001_sprache extends Migration
{
    public function safeUp()
    {
        $this->addColumn('kurs_anmeldung', 'sprache', $this->string(10)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('kurs_anmeldung', 'sprache');
    }
}
