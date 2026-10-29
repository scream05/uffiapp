<?php

namespace humhub\modules\designwahl\components;

use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\file\models\File;
use Yii;

/**
 * Bildbeschreibungen (Alternativtext, WCAG 1.1.1) für Bilder in Beiträgen.
 * Gespeichert im Feld `title` der HumHub-Datei (vom Kern sonst ungenutzt); der Kern selbst nimmt als Alt-Text
 * den Dateinamen. Ausgabe über die eigene Ansicht views/file/widgets/showFiles.php.
 */
class Bildtext
{
    public const MAX = 250;

    /** Wörter in Dateinamen, die nichts über das Bild sagen (Kamera, Messenger, Bildschirmfoto, Importe) */
    private const NICHTSSAGEND = ['img', 'dsc', 'dscn', 'dcim', 'pxl', 'pict', 'photo', 'foto', 'fotos', 'bild', 'bilder', 'image', 'images',
        'whatsapp', 'screenshot', 'bildschirmfoto', 'scan', 'plakat', 'logo', 'datei', 'file', 'unbenannt', 'untitled', 'download',
        'signal', 'telegram', 'mvimg', 'vom', 'von', 'at', 'copy', 'kopie', 'edited', 'bearbeitet', 'jpeg', 'jpg', 'png', 'heic'];

    /** Alt-Text für ein Bild: gespeicherte Beschreibung, sonst lesbarer Dateiname, sonst Hinweis „ohne Beschreibung“ */
    public static function alt(File $file, int $nr = 1, int $anzahl = 1): string
    {
        $titel = trim((string)$file->title);
        if ($titel !== '') {
            return $titel;
        }
        $name = self::ausDateiname((string)$file->file_name);
        if ($name !== null) {
            return $name;
        }
        return $anzahl > 1
            ? Yii::t('DesignwahlModule.base', 'Bild {nr} von {anzahl}, ohne Beschreibung', ['nr' => $nr, 'anzahl' => $anzahl])
            : Yii::t('DesignwahlModule.base', 'Bild ohne Beschreibung');
    }

    /** Dateiname als Text („Demokratie-im-Dialog.jpg“ → „Demokratie im Dialog“), null wenn nichtssagend */
    public static function ausDateiname(string $dateiname): ?string
    {
        $name = trim(preg_replace('/\.[a-z0-9]{2,5}$/i', '', $dateiname));
        if ($name === '' || preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}/i', $name)) {
            return null;
        }
        // Typo3-Bilder der Stadt-Website („csm_Name_d082422772“), Größenangaben („-761x1024“)
        $name = preg_replace(['/^csm_/', '/_[0-9a-f]{8,}$/i', '/[-_]\d{2,4}x\d{2,4}$/'], '', $name);
        $text = trim(preg_replace('/\s+/', ' ', str_replace(['_', '-', '+'], ' ', $name)));
        $text = trim(preg_replace('/(\s\d{1,3}|\s?\(\d+\))$/', '', $text)); // „Igelhäuschen 1“, „Sommerfest (2)“
        // Es muss ein Wort mit Aussage übrig bleiben (mind. 3 Buchstaben, nicht nur Kamera-/Messenger-Kennung)
        preg_match_all('/\p{L}{3,}/u', $text, $woerter);
        $aussage = array_filter($woerter[0], fn($w) => !in_array(mb_strtolower($w), self::NICHTSSAGEND, true));
        return $aussage && mb_strlen(implode('', $aussage)) >= 6 ? $text : null;
    }

    /** @return File[] Bilder eines Beitrags (wie sie der Kern unter dem Beitrag zeigt) */
    public static function bilder(ContentActiveRecord $record): array
    {
        $dateien = File::find()->where(['object_model' => get_class($record), 'object_id' => $record->id])->orderBy('id')->all();
        return array_values(array_filter($dateien, fn(File $f) => str_starts_with((string)$f->mime_type, 'image/')));
    }

    /** Darf die angemeldete Person die Bildbeschreibungen dieses Beitrags ändern? (wer den Beitrag bearbeiten darf) */
    public static function darfBearbeiten(ContentActiveRecord $record): bool
    {
        return !Yii::$app->user->isGuest && $record->content !== null && $record->content->canEdit();
    }
}
