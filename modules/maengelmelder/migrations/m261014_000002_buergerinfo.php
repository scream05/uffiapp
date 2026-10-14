<?php

use yii\db\Migration;

class m261014_000002_buergerinfo extends Migration
{
    public function safeUp()
    {
        // Meldende Person möchte über Statusänderungen informiert werden (E-Mail und/oder WhatsApp an wa_id)
        $this->addColumn('maengel_kontakt', 'info_mail', $this->boolean()->notNull()->defaultValue(0));
        $this->addColumn('maengel_kontakt', 'info_whatsapp', $this->boolean()->notNull()->defaultValue(0));
        $this->addColumn('maengel_kontakt', 'wa_id', $this->string(20)->null());
        $this->createIndex('ix_maengel_kontakt_wa', 'maengel_kontakt', 'wa_id');
        // Nachricht an/von der meldenden Person (statt an/von einem Mitarbeiter)
        $this->addColumn('bauhof_nachricht', 'buerger', $this->boolean()->notNull()->defaultValue(0));
    }

    public function safeDown()
    {
        $this->dropColumn('bauhof_nachricht', 'buerger');
        $this->dropIndex('ix_maengel_kontakt_wa', 'maengel_kontakt');
        $this->dropColumn('maengel_kontakt', 'wa_id');
        $this->dropColumn('maengel_kontakt', 'info_whatsapp');
        $this->dropColumn('maengel_kontakt', 'info_mail');
    }
}
