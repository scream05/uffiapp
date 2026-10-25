<?php

use yii\db\Migration;

class m261025_000002_ticker extends Migration
{
    public function safeUp()
    {
        // Liveticker des BFV: nur für laufende Spiele und das letzte Spiel je Mannschaft (ältere werden geleert)
        $this->addColumn('verein_spiel', 'ticker', $this->text()->null()); // JSON [{m,s,t,k,art}], neueste zuerst
        $this->addColumn('verein_spiel', 'ticker_status', $this->string(10)->null()); // live | beendet | keiner
        $this->addColumn('verein_spiel', 'ticker_stand', $this->string(20)->null());
        $this->addColumn('verein_spiel', 'ticker_minute', $this->smallInteger()->null());
        $this->addColumn('verein_spiel', 'ticker_am', $this->dateTime()->null());
    }

    public function safeDown()
    {
        foreach (['ticker', 'ticker_status', 'ticker_stand', 'ticker_minute', 'ticker_am'] as $c) {
            $this->dropColumn('verein_spiel', $c);
        }
    }
}
