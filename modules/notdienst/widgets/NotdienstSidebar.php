<?php

namespace humhub\modules\notdienst\widgets;

use humhub\components\Widget;
use humhub\modules\notdienst\components\Notdienst;
use Yii;

/** Kasten auf der Startseite: 112/116 117 und die nächstgelegene Notdienstapotheke */
class NotdienstSidebar extends Widget
{
    public function run()
    {
        $periods = Notdienst::periods(1);
        $now = time();
        return $this->render('sidebar', [
            'apotheke' => !Notdienst::isStale() && $periods && $periods[0]['von'] <= $now ? $periods[0]['apotheken'][0] : null,
            'bis' => $periods[0]['bis'] ?? null,
        ]);
    }
}
