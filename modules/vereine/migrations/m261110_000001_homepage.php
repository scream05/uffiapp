<?php

use yii\db\Migration;

/** Vereinsseite als kleine Homepage: Titelbereich (Bild, Slogan, Stichwörter, Knopf) und Teamübersicht */
class m261110_000001_homepage extends Migration
{
    public function safeUp()
    {
        $this->addColumn('verein', 'titelbild_guid', $this->string(45)->null());
        $this->addColumn('verein', 'slogan', $this->string(150)->null());
        $this->addColumn('verein', 'untertitel', $this->string(255)->null());
        // Komma-getrennt, als kleine Schilder unter dem Slogan
        $this->addColumn('verein', 'stichwoerter', $this->string(255)->null());
        // Großer Knopf im Titelbereich: springt zu den Kursen (falls sichtbar), sonst zum Kontakt
        $this->addColumn('verein', 'knopf_text', $this->string(60)->null());
        $this->addColumn('verein', 'knopf_untertext', $this->string(150)->null());
        $this->addColumn('verein', 'team_text', $this->text()->null());
        // JSON [{titel, mitglieder: [{name, funktion}]}]
        $this->addColumn('verein', 'team', $this->text()->null());
        $this->addColumn('verein', 'teambild_guid', $this->string(45)->null());
    }

    public function safeDown()
    {
        foreach (['teambild_guid', 'team', 'team_text', 'knopf_untertext', 'knopf_text', 'stichwoerter', 'untertitel', 'slogan', 'titelbild_guid'] as $c) {
            $this->dropColumn('verein', $c);
        }
    }
}
