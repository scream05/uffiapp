<?php

namespace humhub\modules\facebook;

/**
 * Beiträge der Facebook-Seite der Stadt Uffenheim (Zustimmung der Stadt liegt vor) als Beiträge im Space
 * „Rathaus Uffenheim“, Verfasser „Stadt Uffenheim (Facebook)“. Abruf über die Graph-API von Meta mit einem
 * Seiten-Token (secrets/facebook.json), per Crontab stündlich: /app/yii facebook/abrufen
 */
class Module extends \humhub\components\Module
{
    /** Version der Graph-API von Meta (wie im WhatsApp-Modul) */
    public string $graphVersion = 'v23.0';

    /** Ziel-Space und Konto, unter dem die Beiträge erscheinen */
    public string $space = 'Rathaus Uffenheim';
    public string $konto = 'stadt-uffenheim-fb';

    /** Beim ersten Abruf nur Beiträge der letzten so vielen Tage übernehmen */
    public int $erstImportTage = 30;

    /** Höchstens so viele Bilder je Beitrag (Alben) */
    public int $maxBilder = 10;

    /**
     * Zugangsdaten als Docker-Secret (secrets/facebook.json auf dem Host):
     * page_id = ID der Facebook-Seite der Stadt, token = Seiten-Token oder Token eines Systemnutzers,
     * dem die Seite zugewiesen ist (daraus wird bei jedem Abruf das Seiten-Token geholt)
     */
    public function zugang(string $key): ?string
    {
        $json = json_decode((string)@file_get_contents('/run/secrets/facebook'), true);
        $value = is_array($json) ? trim((string)($json[$key] ?? '')) : '';
        return $value !== '' ? $value : null;
    }
}
