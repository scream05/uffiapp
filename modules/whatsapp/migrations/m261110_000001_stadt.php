<?php

use yii\db\Migration;

/** Termin-Eingang auch für den Veranstaltungskalender von uffenheim.de (Freigabe vor Veröffentlichung, oft Doppler) */
class m261110_000001_stadt extends Migration
{
    public function safeUp()
    {
        // whatsapp | stadt
        $this->addColumn('wa_entwurf', 'quelle', $this->string(20)->notNull()->defaultValue('whatsapp')->after('id'));
        // UID des Termins auf uffenheim.de (wie calendar_entry.uid), damit Änderungen der Stadt nachgezogen werden
        $this->addColumn('wa_entwurf', 'uid', $this->string(64)->null()->after('quelle'));
        $this->addColumn('wa_entwurf', 'link', $this->string(500)->null()->after('uid'));
        // Bilder der Detailseite: JSON [[url, Bildtext], …], werden erst beim Übernehmen geladen
        $this->addColumn('wa_entwurf', 'bilder', $this->text()->null()->after('bild'));
        // Prüfsumme der Angaben der Stadt beim letzten Abgleich: nur echte Änderungen überschreiben Korrekturen
        $this->addColumn('wa_entwurf', 'stand', $this->string(40)->null()->after('bilder'));
        $this->createIndex('ux_wa_entwurf_uid', 'wa_entwurf', 'uid', true);
    }

    public function safeDown()
    {
        $this->dropIndex('ux_wa_entwurf_uid', 'wa_entwurf');
        foreach (['stand', 'bilder', 'link', 'uid', 'quelle'] as $c) {
            $this->dropColumn('wa_entwurf', $c);
        }
    }
}
