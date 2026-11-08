<?php

namespace humhub\modules\kurse\widgets;

use humhub\components\Widget;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\Module;
use humhub\modules\vereine\models\Verein;
use Yii;

/**
 * Kasten „Kurse“ auf der Vereinsseite: aktuelle Kurse, für Kursverwalter „Kurse verwalten“,
 * für Admins der Schalter zum Freischalten des Vereins.
 */
class VereinKurse extends Widget
{
    public Verein $verein;

    public function run()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('kurse');
        $space = $module->getSpace();
        if ($space === null || !$space->moduleManager->isEnabled('kurse')) {
            return '';
        }
        $freigeschaltet = Module::istFreigeschaltet($this->verein->id);
        $verwalten = Module::kannVerwalten($this->verein->id);
        $admin = Module::isAdmin();
        $kurse = $freigeschaltet && ($module->kannSehen() || $verwalten) ? Module::sichtbareKurse($this->verein->id) : [];
        if (!$admin && !$verwalten && !$kurse) {
            return '';
        }
        return $this->render('vereinKurse', [
            'verein' => $this->verein,
            'space' => $space,
            'kurse' => $kurse,
            'freigeschaltet' => $freigeschaltet,
            'verwalten' => $verwalten,
            'admin' => $admin,
            'entwuerfe' => $verwalten ? (int)Kurs::find()->where(['verein_id' => $this->verein->id, 'status' => Kurs::STATUS_ENTWURF])->count() : 0,
        ]);
    }
}
