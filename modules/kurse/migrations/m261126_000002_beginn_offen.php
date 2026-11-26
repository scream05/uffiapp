<?php

use yii\db\Migration;

/**
 * Kursbeginn steht noch nicht fest: Haken + Text (z. B. „voraussichtlich im Januar 2027“). Das Datum in `beginn` ist dann
 * nur ein ungefährer Wert für Sortierung und Jahresgruppe und wird nicht angezeigt; keine Erinnerungsmails.
 */
class m261126_000002_beginn_offen extends Migration
{
    public function safeUp()
    {
        $this->addColumn('kurs', 'beginn_offen', $this->boolean()->notNull()->defaultValue(0)->after('beginn'));
        $this->addColumn('kurs', 'beginn_text', $this->string(100)->null()->after('beginn_offen'));
    }

    public function safeDown()
    {
        $this->dropColumn('kurs', 'beginn_text');
        $this->dropColumn('kurs', 'beginn_offen');
    }
}
