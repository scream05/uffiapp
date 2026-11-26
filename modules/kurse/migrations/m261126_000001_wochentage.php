<?php

use yii\db\Migration;

/**
 * Kurstage je Woche (ISO-Wochentage 1 = Montag … 7 = Sonntag, kommagetrennt, z. B. „1,6“ für Montag und Samstag).
 * Leer = wie bisher (ein Termin pro Woche, Wochentag ergibt sich aus dem Beginn).
 */
class m261126_000001_wochentage extends Migration
{
    public function safeUp()
    {
        $this->addColumn('kurs', 'wochentage', $this->string(20)->null()->after('ende'));
    }

    public function safeDown()
    {
        $this->dropColumn('kurs', 'wochentage');
    }
}
