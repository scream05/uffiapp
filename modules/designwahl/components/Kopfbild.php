<?php

namespace humhub\modules\designwahl\components;

use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\widgets\stream\WallStreamModuleEntryWidget;
use humhub\modules\space\models\Space;

/**
 * Bild links im Beitragskopf: statt Typ-Symbol (z. B. rotes Kalenderblatt) oder Platzhalter-Profilbild das Bild des Spaces.
 * Der Space-Name in der Kopfzeile entfällt dann, das Bild steht für ihn (Name als Alt-Text und Tooltip).
 * Ausgabe über die eigene Ansicht views/content/stream/wallStreamEntryHeader.php.
 */
class Kopfbild
{
    /** Spaces, in denen jeder Beitrag mit dem Space-Bild erscheint */
    private const SPACES = ['rathaus-uffenheim', 'gewerbe', 'vorschlaege'];

    /** Import- und Systemkonten ohne eigenes Profilbild (Website der Stadt, Mitteilungsblatt, Gastmeldungen) */
    private const KONTEN = ['stadt-uffenheim', 'mitteilungsblatt', 'gastmeldung'];

    /** Space, dessen Bild den Kopf dieses Beitrags ersetzt, sonst null (dann bleibt alles wie im Kern) */
    public static function space(ContentActiveRecord $model, $widget): ?Space
    {
        $space = $model->content->container;
        if (!$space instanceof Space || !$space->image->exists()) {
            return null;
        }
        $autor = $model->content->createdBy;
        if ($widget instanceof WallStreamModuleEntryWidget // Termine, Umfragen …: Typ-Symbol statt Profilbild
            || in_array($space->url, self::SPACES, true)
            || ($autor !== null && in_array($autor->username, self::KONTEN, true))) {
            return $space;
        }
        return null;
    }
}
