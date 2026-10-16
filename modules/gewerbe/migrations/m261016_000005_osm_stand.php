<?php

use yii\db\Migration;

class m261016_000005_osm_stand extends Migration
{
    public function safeUp()
    {
        // Zuletzt aus OSM gelesene Werte (JSON). Der Abgleich übernimmt eine OSM-Änderung nur, wenn das Feld noch
        // diesen Stand hat – von Hand oder per Web-Recherche geänderte bzw. geleerte Felder bleiben so.
        $this->addColumn('gewerbe_betrieb', 'osm_stand', $this->text()->null()->after('osm_ref'));
    }

    public function safeDown()
    {
        $this->dropColumn('gewerbe_betrieb', 'osm_stand');
    }
}
