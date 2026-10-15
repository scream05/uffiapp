<?php

use yii\db\Migration;

class m261015_000002_karte_zeiten extends Migration
{
    public function safeUp()
    {
        // Ort für die Karte (aus der Adresse über Nominatim ermittelt)
        $this->addColumn('gewerbe_betrieb', 'lat', $this->decimal(9, 6)->null()->after('adresse'));
        $this->addColumn('gewerbe_betrieb', 'lng', $this->decimal(9, 6)->null()->after('lat'));
        // Öffnungszeiten je Wochentag als JSON {"1": [["08:00","12:00"], …], …} (1 = Montag); oeffnungszeiten = Hinweise
        $this->addColumn('gewerbe_betrieb', 'zeiten', $this->text()->null()->after('oeffnungszeiten'));
        // Startliste aus öffentlichen Quellen (Status „offen“, noch nicht vom Betrieb gepflegt)
        $this->addColumn('gewerbe_betrieb', 'quelle', $this->string(500)->null()->after('logo_guid'));
        $this->addColumn('gewerbe_betrieb', 'recherchiert_am', $this->date()->null()->after('quelle'));
    }

    public function safeDown()
    {
        foreach (['lat', 'lng', 'zeiten', 'quelle', 'recherchiert_am'] as $c) {
            $this->dropColumn('gewerbe_betrieb', $c);
        }
    }
}
