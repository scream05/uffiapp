<?php

use yii\db\Migration;

class m261026_000001_spielquellen extends Migration
{
    public function safeUp()
    {
        // Woher die Spiele kommen (components/Spielquellen.php): bfv | dbb (Basketball) | bvv (Volleyball) | bbv (Badminton).
        // bfv_id ist seitdem die Kennung bei dieser Quelle, bfv_name der Vereinsname dort.
        $this->addColumn('verein', 'spiele_quelle', $this->string(10)->notNull()->defaultValue('bfv'));
        // Unterscheidet Mannschaften mit gleicher Bezeichnung, z. B. „Landesliga“ bei zwei Frauen-Mannschaften
        $this->addColumn('verein_mannschaft', 'zusatz', $this->string(60)->null());
        // Link zum Spiel bei der Quelle (leer = BFV-Spielseite) und Satzergebnisse (Volleyball)
        $this->addColumn('verein_spiel', 'url', $this->string(255)->null());
        $this->addColumn('verein_spiel', 'details', $this->string(120)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('verein_spiel', 'details');
        $this->dropColumn('verein_spiel', 'url');
        $this->dropColumn('verein_mannschaft', 'zusatz');
        $this->dropColumn('verein', 'spiele_quelle');
    }
}
