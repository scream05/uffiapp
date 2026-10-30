<?php

namespace humhub\modules\maengelmelder;

use humhub\modules\maengelmelder\widgets\StatusAddon;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use Yii;

class Events
{
    public static function onUserLoeschen($event)
    {
        try {
            $user = $event->user ?? $event->sender;
            models\MaengelKontakt::deleteAll(['user_id' => $user->id]);
            \Yii::$app->db->createCommand()->delete('maengel_auch', ['user_id' => $user->id])->execute();
            models\BauhofNachricht::updateAll(['user_id' => null], ['user_id' => $user->id]);
        } catch (\Throwable $e) {
            \Yii::error($e);
        }
    }

    /** Status-Etikett unter jedem Beitrag in Spaces mit aktivem Mängelmelder */
    public static function onWallEntryAddonsInit($event)
    {
        try {
            $object = $event->sender->object;
            if (!$object instanceof Post) {
                return;
            }
            $container = $object->content->container;
            if (!$container instanceof Space || !$container->moduleManager->isEnabled('maengelmelder')) {
                return;
            }
            $event->sender->addWidget(StatusAddon::class, ['post' => $object], ['sortOrder' => 5]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Gäste sehen im Mängelmelder keinen Stream: Menüpunkt „Stream“ ausblenden */
    public static function onSpaceMenuRun($event)
    {
        try {
            $menu = $event->sender;
            if (!Yii::$app->user->isGuest || !$menu->space->moduleManager->isEnabled('maengelmelder')) {
                return;
            }
            $stream = $menu->space->createUrl('/space/space/home');
            foreach ($menu->getEntries() as $entry) {
                if ($entry instanceof \humhub\modules\ui\menu\MenuLink && $entry->getUrl() === $stream) {
                    $menu->removeEntry($entry);
                }
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Gäste: Stream des Mängelmelders (Seite, Nachladen, Einzelbeitrag) führt zur Karte */
    public static function onSpaceBeforeAction($event)
    {
        try {
            $id = $event->action->id;
            if (!Yii::$app->user->isGuest
                || !($id === 'home' || $id === 'stream' || ($id === 'index' && Yii::$app->request->get('contentId')))) {
                return;
            }
            $space = $event->action->controller->contentContainer ?? null;
            if (!$space instanceof Space || !$space->moduleManager->isEnabled('maengelmelder')) {
                return;
            }
            $event->isValid = false;
            if ($id === 'stream') {
                throw new \yii\web\ForbiddenHttpException();
            }
            $event->action->controller->redirect($space->createUrl('/maengelmelder/meldung/karte'));
        } catch (\yii\web\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /**
     * Mitgliedschaft im Mängelmelder speichern (neu per auto_add, Umschalten, Skripte): „In Übersicht anzeigen“ nur für
     * Bauhof und Admins, sonst immer aus – Mängel stehen dann nicht in der Übersicht normaler Konten.
     */
    public static function onMitgliedschaftSpeichern($event)
    {
        try {
            /** @var \humhub\modules\space\models\Membership $m */
            $m = $event->sender;
            if (!$m->show_at_dashboard || !$m->space || !$m->space->moduleManager->isEnabled('maengelmelder')) {
                return;
            }
            if (!Yii::$app->getModule('maengelmelder')->maengelInUebersicht($m->user)) {
                $m->show_at_dashboard = 0;
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Space-Menü (⋯): „Beiträge in der Übersicht anzeigen/verbergen“ im Mängelmelder nur für Bauhof und Admins */
    public static function onHeaderControlsRun($event)
    {
        try {
            $menu = $event->sender;
            if (!$menu->space || !$menu->space->moduleManager->isEnabled('maengelmelder')
                || Yii::$app->getModule('maengelmelder')->maengelInUebersicht(Yii::$app->user->getIdentity())) {
                return;
            }
            foreach ($menu->getEntries() as $entry) {
                if ($entry instanceof MenuLink && str_contains((string)$entry->getUrl(), 'switch-dashboard-display')) {
                    $menu->removeEntry($entry);
                }
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('maengelmelder')) {
                return;
            }
            $active = Yii::$app->controller->module && Yii::$app->controller->module->id === 'maengelmelder';
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('MaengelmelderModule.base', 'Mangel melden'),
                'url' => $space->createUrl('/maengelmelder/meldung/create'),
                'icon' => 'map-marker',
                'isActive' => $active && Yii::$app->controller->action->id === 'create',
                'sortOrder' => 150,
            ]));
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('MaengelmelderModule.base', 'Karte'),
                'url' => $space->createUrl('/maengelmelder/meldung/karte'),
                'icon' => 'map',
                'isActive' => $active && Yii::$app->controller->action->id === 'karte',
                'sortOrder' => 151,
            ]));
            if (models\MaengelStatus::canManage() && $space->url === Yii::$app->getModule('maengelmelder')->bauhofSpace) {
                // Ungelesene WhatsApp-Nachrichten und Gastmeldungen, die auf Freigabe warten
                $neu = models\BauhofNachricht::ungelesen() + (int)\humhub\modules\content\models\Content::find()
                    ->where(['state' => \humhub\modules\content\models\Content::STATE_DRAFT, 'contentcontainer_id' => $space->contentcontainer_id,
                        'created_by' => Yii::$app->getModule('maengelmelder')->getGastKonto()?->id ?? 0])->count();
                // HumHub 1.19: MenuLink::createByArray() gibt es nicht mehr, Konfiguration wie bei den anderen Einträgen
                $link = new MenuLink([
                    'label' => 'Bauhof' . ($neu ? ' <span class="badge bg-danger">' . $neu . '</span>' : ''),
                    'url' => $space->createUrl('/maengelmelder/bauhof/index'),
                    'icon' => 'truck',
                    'isActive' => $active && Yii::$app->controller->id === 'bauhof',
                    'sortOrder' => 152,
                ]);
                $link->setEncodeLabel(false);
                $event->sender->addEntry($link);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
