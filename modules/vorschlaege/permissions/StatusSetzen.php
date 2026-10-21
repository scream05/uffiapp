<?php

namespace humhub\modules\vorschlaege\permissions;

use humhub\libs\BasePermission;

/**
 * Stand von Vorschlägen setzen. Admins dürfen es immer; weitere Gruppen unter
 * Administration → Benutzer → Gruppen → Berechtigungen.
 */
class StatusSetzen extends BasePermission
{
    protected $moduleId = 'vorschlaege';
    protected $id = 'vorschlaege_status';
    public $title = 'Vorschläge bearbeiten';
    public $description = 'Darf den Stand von Ideen & Vorschlägen setzen (z. B. „Geplant“, „Umgesetzt“).';
    protected $defaultState = self::STATE_DENY;
}
