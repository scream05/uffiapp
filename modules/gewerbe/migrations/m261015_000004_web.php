<?php

use yii\db\Migration;

class m261015_000004_web extends Migration
{
    public function safeUp()
    {
        // Einmalige Web-Recherche (Website/Impressum des Betriebs): Quellen als JSON-Liste und Datum.
        // Getrennt von „quelle“, weil der wöchentliche OSM-Abgleich diese Spalte überschreibt.
        $this->addColumn('gewerbe_betrieb', 'web_quellen', $this->text()->null()->after('abgleich_am'));
        $this->addColumn('gewerbe_betrieb', 'web_recherche_am', $this->date()->null()->after('web_quellen'));
    }

    public function safeDown()
    {
        $this->dropColumn('gewerbe_betrieb', 'web_quellen');
        $this->dropColumn('gewerbe_betrieb', 'web_recherche_am');
    }
}
