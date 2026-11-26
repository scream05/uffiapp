<?php

use yii\db\Migration;

/**
 * Abweichende Uhrzeit je Kurstag als JSON, z. B. {"1":{"von":"16:00","bis":"16:45"},"6":{"von":"11:15","bis":"12:00"}}.
 * Tage ohne Eintrag nutzen zeit_von/zeit_bis des Kurses.
 */
class m261126_000003_tageszeiten extends Migration
{
    public function safeUp()
    {
        $this->addColumn('kurs', 'tageszeiten', $this->text()->null()->after('wochentage'));
    }

    public function safeDown()
    {
        $this->dropColumn('kurs', 'tageszeiten');
    }
}
