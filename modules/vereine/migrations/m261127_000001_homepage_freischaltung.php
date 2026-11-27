<?php

use yii\db\Migration;

/**
 * „Vereinsseite als Homepage gestalten“ nur nach Freischaltung durch einen Administrator.
 * Bestehende Inhalte bleiben erhalten; freigeschaltet wird, wer den Bereich schon nutzt (SBU Schwimmen).
 */
class m261127_000001_homepage_freischaltung extends Migration
{
    public function safeUp()
    {
        $this->addColumn('verein', 'homepage_aktiv', $this->boolean()->notNull()->defaultValue(0));
        $this->update('verein', ['homepage_aktiv' => 1], ['or',
            ['is not', 'titelbild_guid', null], ['is not', 'slogan', null], ['is not', 'untertitel', null],
            ['is not', 'stichwoerter', null], ['is not', 'knopf_text', null], ['is not', 'team_text', null],
            ['is not', 'team', null], ['is not', 'teambild_guid', null]]);
    }

    public function safeDown()
    {
        $this->dropColumn('verein', 'homepage_aktiv');
    }
}
