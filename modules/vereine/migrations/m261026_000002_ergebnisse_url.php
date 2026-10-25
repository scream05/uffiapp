<?php

use yii\db\Migration;

class m261026_000002_ergebnisse_url extends Migration
{
    public function safeUp()
    {
        // Link zu Ergebnissen/Tabellen bei einer Quelle, die wir nicht abrufen dürfen (z. B. RWK-Onlinemelder der Schützen)
        $this->addColumn('verein', 'ergebnisse_url', $this->string(255)->null());
        $this->addColumn('verein', 'ergebnisse_text', $this->string(100)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('verein', 'ergebnisse_text');
        $this->dropColumn('verein', 'ergebnisse_url');
    }
}
