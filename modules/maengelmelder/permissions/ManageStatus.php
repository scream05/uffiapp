<?php

namespace humhub\modules\maengelmelder\permissions;

use humhub\libs\BasePermission;

/**
 * Status von Mängelmeldungen ändern. Globales Recht, wird unter
 * Administration → Benutzer → Gruppen → Berechtigungen einer Gruppe (z. B. „Bauhof“) gegeben.
 */
class ManageStatus extends BasePermission
{
    protected $moduleId = 'maengelmelder';
    protected $id = 'maengelmelder_manage_status';
    public $title = 'Mängelstatus bearbeiten';
    public $description = 'Darf den Status von Meldungen im Mängelmelder ändern (z. B. Bauhof).';
    protected $defaultState = self::STATE_DENY;
}
