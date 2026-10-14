<?php

use yii\db\Migration;

/** Bestätigung von E-Mail-Adresse (Link) und WhatsApp-Nummer („START <Nr>“), bevor wir Meldende automatisch anschreiben */
class m261015_000001_bestaetigung extends Migration
{
    public function safeUp()
    {
        $this->addColumn('maengel_kontakt', 'mail_token', $this->string(64)->null()->after('info_whatsapp'));
        $this->addColumn('maengel_kontakt', 'mail_bestaetigt', $this->dateTime()->null()->after('mail_token'));
        $this->addColumn('maengel_kontakt', 'wa_bestaetigt', $this->dateTime()->null()->after('wa_id'));
        // Bestehende Kontakte gelten als bestätigt (sie bekamen schon Infos nach den alten Regeln)
        $this->update('maengel_kontakt', ['mail_bestaetigt' => new \yii\db\Expression('created_at')],
            ['or', ['not', ['email' => null]], ['not', ['user_id' => null]]]);
        $this->update('maengel_kontakt', ['wa_bestaetigt' => new \yii\db\Expression('created_at')], ['info_whatsapp' => 1]);
    }

    public function safeDown()
    {
        $this->dropColumn('maengel_kontakt', 'mail_token');
        $this->dropColumn('maengel_kontakt', 'mail_bestaetigt');
        $this->dropColumn('maengel_kontakt', 'wa_bestaetigt');
    }
}
