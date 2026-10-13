<?php

namespace humhub\modules\maengelmelder\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;

/**
 * Mängel über das Meldeformular erfassen. Space-Recht, unabhängig von „Beitrag erstellen“:
 * Mitglieder melden Mängel, schreiben aber keine freien Beiträge in den Stream.
 */
class MangelMelden extends BasePermission
{
    protected $moduleId = 'maengelmelder';
    protected $id = 'maengelmelder_melden';
    public $title = 'Mangel melden';
    public $description = 'Darf über das Formular „Mangel melden“ Meldungen erfassen.';

    public $defaultAllowedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_MODERATOR,
        Space::USERGROUP_MEMBER,
    ];

    protected $fixedGroups = [
        Space::USERGROUP_GUEST,
    ];
}
