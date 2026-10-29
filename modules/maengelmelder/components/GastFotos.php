<?php

namespace humhub\modules\maengelmelder\components;

use humhub\modules\file\models\File;
use humhub\modules\maengelmelder\Module;
use Yii;
use yii\web\UploadedFile;

/**
 * Fotos aus dem Meldeformular für Gäste (HumHubs Upload ist nur mit Konto möglich).
 * Jedes Bild wird mit Imagick geöffnet, gedreht, auf max. 2000 px verkleinert und als JPEG neu gespeichert.
 * Dabei fallen alle Metadaten weg (z. B. GPS-Position, Kamera). Was sich nicht als Bild öffnen lässt, wird abgelehnt.
 */
class GastFotos
{
    private const FORMATE = ['JPEG', 'PNG', 'WEBP', 'HEIC', 'HEIF', 'GIF'];
    private const MAX_PX = 2000;

    /** @return array{0: UploadedFile[], 1: string[]} Dateien und Fehlermeldungen */
    public static function pruefen(Module $module): array
    {
        $dateien = array_values(array_filter(UploadedFile::getInstancesByName('fotos'),
            fn(UploadedFile $f) => $f->error !== UPLOAD_ERR_NO_FILE));
        $fehler = [];
        if (count($dateien) > $module->gastFotos) {
            $fehler[] = Yii::t('MaengelmelderModule.base', 'Bitte höchstens {max} Fotos anhängen.', ['max' => $module->gastFotos]);
        }
        foreach ($dateien as $f) {
            if ($f->error === UPLOAD_ERR_INI_SIZE || $f->error === UPLOAD_ERR_FORM_SIZE || $f->size > $module->gastFotoMaxMb * 1024 * 1024) {
                $fehler[] = Yii::t('MaengelmelderModule.base', 'Das Foto „{name}“ ist zu groß (höchstens {mb} MB).', ['name' => $f->name, 'mb' => $module->gastFotoMaxMb]);
            } elseif ($f->error !== UPLOAD_ERR_OK) {
                $fehler[] = Yii::t('MaengelmelderModule.base', 'Das Foto „{name}“ konnte nicht hochgeladen werden.', ['name' => $f->name]);
            }
        }
        return [$dateien, $fehler];
    }

    /**
     * Bilder umwandeln und als HumHub-Dateien anlegen (noch keinem Beitrag zugeordnet).
     *
     * @param UploadedFile[] $dateien
     * @return array{0: File[], 1: string[]}
     */
    public static function speichern(array $dateien): array
    {
        $files = [];
        $fehler = [];
        foreach ($dateien as $i => $f) {
            try {
                // Typ nach Inhalt prüfen, bevor ImageMagick die Datei überhaupt öffnet (keine SVG/PS/PDF o. Ä.)
                $typ = (new \finfo(FILEINFO_MIME_TYPE))->file($f->tempName);
                if (!in_array($typ, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/gif', 'image/avif'], true)) {
                    throw new \RuntimeException("Typ $typ");
                }
                $im = new \Imagick();
                $im->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
                $im->pingImage($f->tempName);
                if (!in_array(strtoupper($im->getImageFormat()), self::FORMATE, true)) {
                    throw new \RuntimeException('Format ' . $im->getImageFormat());
                }
                $im->clear();
                $im->readImage($f->tempName . '[0]'); // bei GIF/HEIC-Serien nur das erste Bild
                $im->autoOrient();
                if ($im->getImageWidth() > self::MAX_PX || $im->getImageHeight() > self::MAX_PX) {
                    $im->thumbnailImage(self::MAX_PX, self::MAX_PX, true);
                }
                $im->setImageBackgroundColor('white');
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $im->stripImage();
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(85);
                $blob = $im->getImageBlob();
                $im->clear();

                $file = new File();
                $file->file_name = 'Foto-' . ($i + 1) . '.jpg';
                $file->title = 'Foto zur Mängelmeldung'; // Bildbeschreibung (Alt-Text), siehe designwahl Bildtext
                $file->mime_type = 'image/jpeg';
                $file->size = strlen($blob);
                if (!$file->save()) {
                    throw new \RuntimeException(json_encode($file->getErrors()));
                }
                $file->setStoredFileContent($blob);
                $files[] = $file;
            } catch (\Throwable $e) {
                Yii::warning("Gastfoto {$f->name}: " . $e->getMessage(), 'maengelmelder');
                $fehler[] = Yii::t('MaengelmelderModule.base', '„{name}“ ist kein unterstütztes Foto (JPEG, PNG, WebP oder HEIC).', ['name' => $f->name]);
            }
        }
        return [$files, $fehler];
    }
}
