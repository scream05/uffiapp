<?php

namespace humhub\modules\vorschlaege;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

/**
 * Ideen & Vorschläge: Nutzer reichen Verbesserungsvorschläge für die Plattform ein (als Beitrag im Space
 * „Ideen & Vorschläge“, damit Kommentare, Benachrichtigungen und Melden wie gewohnt funktionieren).
 * Andere stimmen dafür oder dagegen, Admins (bzw. Recht „Vorschläge bearbeiten“) setzen den Stand.
 */
class Module extends ContentContainerModule
{
    public $resourcesPath = 'resources';

    /** Space der Vorschläge (URL) */
    public string $spaceUrl = 'vorschlaege';

    /** Gespeichert werden immer diese deutschen Werte; Anzeige über kategorieLabel() */
    public array $kategorien = [
        'Neue Funktion',
        'Verbesserung',
        'Fehler',
        'Inhalte & Informationen',
        'Sonstiges',
    ];

    /** Höchstens so viele neue Vorschläge je Person und Tag (gegen Versehen und Missbrauch) */
    public int $proTag = 5;

    /** Glocke an die Bearbeiter bei neuen Vorschlägen (Selbsttest schaltet sie aus) */
    public bool $benachrichtigen = true;

    public function getNotifications()
    {
        return [notifications\VorschlagNeu::class];
    }

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentContainerName($container)
    {
        return Yii::t('VorschlaegeModule.base', 'Ideen & Vorschläge');
    }

    public function getContentContainerDescription($container)
    {
        return Yii::t('VorschlaegeModule.base', 'Verbesserungsvorschläge einreichen, kommentieren und abstimmen.');
    }

    /** Globales Recht „Vorschläge bearbeiten“ (an Gruppen vergeben) */
    public function getPermissions($contentContainer = null)
    {
        return $contentContainer === null ? [new permissions\StatusSetzen()] : [];
    }

    public function kategorieLabel(string $kategorie): string
    {
        return Yii::t('VorschlaegeModule.base', $kategorie);
    }

    public function getSpace(): ?Space
    {
        return Space::findOne(['url' => $this->spaceUrl]);
    }

    /** Admins und alle mit „Vorschläge bearbeiten“ (Empfänger der Glocke bei neuen Vorschlägen) */
    public function bearbeiter(): array
    {
        $liste = [];
        foreach (User::find()->active()->all() as $user) {
            if ($user->isSystemAdmin() || (new \humhub\modules\user\components\PermissionManager(['subject' => $user]))
                    ->can(permissions\StatusSetzen::class)) {
                $liste[] = $user;
            }
        }
        return $liste;
    }
}
