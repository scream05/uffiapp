<?php

use yii\db\Migration;

class m261015_000003_osm extends Migration
{
    public function safeUp()
    {
        // Verknüpfung mit OpenStreetMap (z. B. „node/298388614“) für den wöchentlichen Abgleich
        $this->addColumn('gewerbe_betrieb', 'osm_ref', $this->string(40)->null()->after('recherchiert_am'));
        $this->createIndex('ix_gewerbe_betrieb_osm', 'gewerbe_betrieb', 'osm_ref');
        // Herkunft des Logos (Website des Betriebs)
        $this->addColumn('gewerbe_betrieb', 'logo_quelle', $this->string(500)->null()->after('logo_guid'));
        // Ergebnis des letzten Abgleichs: Hinweise für Verwalter/Admins (abweichende Angaben, Website nicht erreichbar …)
        $this->addColumn('gewerbe_betrieb', 'abgleich_hinweis', $this->text()->null()->after('osm_ref'));
        $this->addColumn('gewerbe_betrieb', 'abgleich_am', $this->dateTime()->null()->after('abgleich_hinweis'));
    }

    public function safeDown()
    {
        foreach (['osm_ref', 'logo_quelle', 'abgleich_hinweis', 'abgleich_am'] as $c) {
            $this->dropColumn('gewerbe_betrieb', $c);
        }
    }
}
