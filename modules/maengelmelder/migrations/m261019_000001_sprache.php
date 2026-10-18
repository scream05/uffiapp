<?php

use yii\db\Migration;

class m261019_000001_sprache extends Migration
{
    public function safeUp()
    {
        // Sprache der meldenden Person (Gäste: beim Melden eingestellte Sprache) für Bestätigungs- und Status-Mails
        $this->addColumn('maengel_kontakt', 'sprache', $this->string(8)->null()->after('telefon'));
    }

    public function safeDown()
    {
        $this->dropColumn('maengel_kontakt', 'sprache');
    }
}
