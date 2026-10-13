<?php

namespace humhub\modules\vereine\permissions;

use humhub\libs\BasePermission;

/**
 * Vereinsverzeichnis verwalten: Anfragen von Vereinsverwaltern bestätigen, neue Vereine freigeben, alle Vereinsseiten
 * und Vereinstermine bearbeiten. Globales Recht, Vergabe unter Administration → Benutzer → Gruppen → Berechtigungen.
 */
class ManageVereine extends BasePermission
{
    protected $moduleId = 'vereine';
    protected $id = 'vereine_manage';
    public $title = 'Vereine verwalten';
    public $description = 'Darf Vereinsverwalter bestätigen, neue Vereine freigeben und alle Vereinsseiten bearbeiten.';
    protected $defaultState = self::STATE_DENY;
}
