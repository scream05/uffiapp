<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\space\models\Space;
use humhub\modules\space\widgets\InviteButton;
use Yii;

/**
 * Knopf „Einladen“ im Space-Kopf ausblenden – außer in geschlossenen Spaces (Sichtbarkeit „nur Mitglieder“,
 * z. B. Vereins-Arbeitsbereich /s/buergerverein): dort zeigt der Kern-Knopf ihn wie gewohnt, aber nur wer das
 * Recht „Mitglieder einladen“ hat (Space-Admins/-Moderatoren). Ersetzt InviteButton per DI-Container
 * (Events::onBeforeRequest), der Kern bleibt unverändert.
 */
class OhneEinladen extends InviteButton
{
    public function run()
    {
        if ($this->space instanceof Space && (int)$this->space->visibility === Space::VISIBILITY_NONE) {
            return parent::run();
        }
        return '';
    }

    /**
     * Vorlage inviteButton.php des Kerns verwenden (Yii sucht sonst neben der Unterklasse → Fehlerseite).
     */
    public function getViewPath()
    {
        return Yii::getAlias('@humhub/modules/space/widgets/views');
    }
}
