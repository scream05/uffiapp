<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\user\widgets\PeopleHeadingButtons;

/**
 * Mitgliederseite (/people) ohne „Neue Leute einladen“; übrige Knöpfe bleiben.
 * Ersetzt PeopleHeadingButtons per DI-Container (Events::onBeforeRequest), der Kern bleibt unverändert.
 */
class PersonenKnoepfe extends PeopleHeadingButtons
{
    public function init()
    {
        parent::init();
        foreach ($this->getEntries() as $entry) {
            if ($entry instanceof \humhub\modules\ui\menu\MenuLink && $entry->getUrl() === \yii\helpers\Url::to(['/user/invite'])) {
                $this->removeEntry($entry);
            }
        }
    }
}
