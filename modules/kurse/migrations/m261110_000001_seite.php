<?php

use yii\db\Migration;

/**
 * Eigene Kursseite je Verein (z. B. /kurse/sbu-schwimmen): ohne Menüs der Plattform, im Design des Vereins,
 * zum Verlinken von der Vereins-Homepage.
 */
class m261110_000001_seite extends Migration
{
    public function safeUp()
    {
        // Adresse der Seite (/kurse/<slug>), leer = keine eigene Seite
        $this->addColumn('kurs_verein', 'slug', $this->string(60)->null());
        // 0 = Vorschau (nur Admins/Kursverwalter), 1 = für alle
        $this->addColumn('kurs_verein', 'seite_aktiv', $this->boolean()->notNull()->defaultValue(false));
        // Hauptfarbe der Seite (#rrggbb), leer = Plattformblau
        $this->addColumn('kurs_verein', 'farbe', $this->string(7)->null());
        $this->createIndex('ux_kurs_verein_slug', 'kurs_verein', 'slug', true);
    }

    public function safeDown()
    {
        $this->dropIndex('ux_kurs_verein_slug', 'kurs_verein');
        $this->dropColumn('kurs_verein', 'farbe');
        $this->dropColumn('kurs_verein', 'seite_aktiv');
        $this->dropColumn('kurs_verein', 'slug');
    }
}
