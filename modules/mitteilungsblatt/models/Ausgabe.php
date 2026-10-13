<?php

namespace humhub\modules\mitteilungsblatt\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $jahr
 * @property int $nr
 * @property int|null $nr_bis letzte Nummer bei Doppelausgaben (Nr. 1/2)
 * @property string $datum
 * @property string $quelle_url
 * @property int $seiten
 * @property int $groesse
 * @property int|null $post_id
 * @property string $created_at
 */
class Ausgabe extends ActiveRecord
{
    public static function tableName()
    {
        return 'mb_ausgabe';
    }

    public function getTitel(): string
    {
        return 'Nr. ' . $this->nr . ($this->nr_bis ? '–' . $this->nr_bis : '') . '/' . $this->jahr;
    }

    public function getDatumText(): string
    {
        return (int)substr($this->datum, 8, 2) . '. ' . self::MONATE[(int)substr($this->datum, 5, 2)] . ' ' . substr($this->datum, 0, 4);
    }

    /** Dateiname ohne Endung in der Ablage */
    public function getName(): string
    {
        return sprintf('%04d-%02d', $this->jahr, $this->nr);
    }

    public function getPdfPfad(): string
    {
        return Yii::$app->getModule('mitteilungsblatt')->ablage . '/' . $this->name . '.pdf';
    }

    public function getBildPfad(): string
    {
        return Yii::$app->getModule('mitteilungsblatt')->ablage . '/' . $this->name . '.jpg';
    }

    public const MONATE = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
}
