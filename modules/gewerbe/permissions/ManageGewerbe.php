<?php

namespace humhub\modules\gewerbe\permissions;

use humhub\libs\BasePermission;

/**
 * Gewerbeverzeichnis verwalten: Anfragen von Betrieben bestätigen, neue Betriebe freigeben, alle Betriebsseiten
 * bearbeiten. Globales Recht, Vergabe unter Administration → Benutzer → Gruppen → Berechtigungen.
 */
class ManageGewerbe extends BasePermission
{
    protected $moduleId = 'gewerbe';
    protected $id = 'gewerbe_manage';
    public $title = 'Gewerbe verwalten';
    public $description = 'Darf Betriebsverwalter bestätigen, neue Betriebe freigeben und alle Betriebsseiten bearbeiten.';
    protected $defaultState = self::STATE_DENY;
}
