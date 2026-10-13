<?php

use yii\db\Migration;

class m261005_000001_details extends Migration
{
    public function safeUp()
    {
        // JSON-Listen: ansprechpartner [{funktion,name,email,telefon}], social [{typ,url}], quellen [url],
        // bildnachweise {file-guid: {beschreibung,urheber,lizenz,seite}}
        $this->addColumn('verein', 'ansprechpartner', $this->text()->null()->after('telefon'));
        $this->addColumn('verein', 'social', $this->text()->null()->after('ansprechpartner'));
        $this->addColumn('verein', 'logo_guid', $this->string(45)->null()->after('social'));
        $this->addColumn('verein', 'logo_quelle', $this->string(500)->null()->after('logo_guid'));
        $this->addColumn('verein', 'bildnachweise', $this->text()->null()->after('logo_quelle'));
        $this->addColumn('verein', 'quellen', $this->text()->null()->after('bildnachweise'));
        // Angaben aus öffentlichen Quellen zusammengestellt (nicht vom Verein selbst)
        $this->addColumn('verein', 'recherchiert_am', $this->date()->null()->after('quellen'));
    }

    public function safeDown()
    {
        foreach (['ansprechpartner', 'social', 'logo_guid', 'logo_quelle', 'bildnachweise', 'quellen', 'recherchiert_am'] as $c) {
            $this->dropColumn('verein', $c);
        }
    }
}
