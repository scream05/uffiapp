<?php

namespace humhub\modules\tanken;

use humhub\modules\tanken\jobs\Abruf;
use humhub\modules\tanken\widgets\SpritKasten;
use Yii;

class Events
{
    /** Startseite: Kasten mit den Preisen der Tankstellen in Uffenheim */
    public static function onDashboardSidebarInit($event)
    {
        try {
            if (Yii::$app->getModule('tanken')->aktiv()) {
                $event->sender->addWidget(SpritKasten::class, [], ['sortOrder' => 60]);
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'tanken');
        }
    }

    /** Im Space „Gewerbe & Einkaufen“: Menüpunkt „Spritpreise“ */
    public static function onSpaceMenuInit($event)
    {
        try {
            $modul = Yii::$app->getModule('tanken');
            if (!$modul->aktiv() || $event->sender->space->url !== $modul->spaceUrl) {
                return;
            }
            $event->sender->addItem([
                'label' => Yii::t('TankenModule.base', 'Spritpreise'),
                'group' => 'modules',
                'url' => $event->sender->space->createUrl('/tanken/index'),
                'icon' => '<i class="fa fa-car"></i>',
                'sortOrder' => 70,
                'isActive' => Yii::$app->controller?->module?->id === 'tanken',
            ]);
        } catch (\Throwable $e) {
            Yii::error($e, 'tanken');
        }
    }

    /** Stündlich ein Abruf (nur wenn die Daten älter sind), damit „Wann ist Tanken günstig?“ auch nachts Werte hat */
    public static function onCronHourly($event)
    {
        try {
            if (Yii::$app->getModule('tanken')->aktiv()) {
                // Zufällige Verzögerung: keine Lastspitzen zur vollen Stunde (Bitte von Tankerkönig)
                Yii::$app->queue->delay(random_int(60, 1500))->push(new Abruf());
                Yii::$app->db->createCommand()->delete('tanken_verlauf',
                    ['<', 'zeit', date('Y-m-d H:i:s', strtotime('-' . Yii::$app->getModule('tanken')->verlaufTage . ' days'))])->execute();
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'tanken');
        }
    }
}
