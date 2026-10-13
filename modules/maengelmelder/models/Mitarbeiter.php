<?php

namespace humhub\modules\maengelmelder\models;

use humhub\components\ActiveRecord;

/**
 * Bauhof-Mitarbeiter: nur Name und WhatsApp-Nummer, kein Konto auf der Plattform.
 *
 * @property int $id
 * @property string $name
 * @property string $telefon
 * @property int $aktiv
 * @property string $einwilligung_at
 * @property int|null $einwilligung_by
 * @property int|null $aktuell_content_id
 * @property string|null $letzte_eingang_at
 * @property string|null $benachrichtigt_at
 * @property string $created_at
 */
class Mitarbeiter extends ActiveRecord
{
    /** Formularfeld: Einwilligung liegt vor */
    public $einwilligung = false;

    public static function tableName()
    {
        return 'bauhof_mitarbeiter';
    }

    public function rules()
    {
        return [
            [['name', 'telefon'], 'trim'],
            [['name', 'telefon'], 'required'],
            ['name', 'string', 'max' => 100],
            ['telefon', 'filter', 'filter' => [self::class, 'normalisieren']],
            ['telefon', 'match', 'pattern' => '/^[1-9]\d{8,14}$/', 'message' => 'Bitte eine gültige Handynummer eingeben, z. B. 0151 23456789.'],
            ['telefon', 'unique', 'message' => 'Diese Nummer ist schon eingetragen.'],
            ['aktiv', 'boolean'],
            ['einwilligung', 'boolean'],
            ['einwilligung', 'required', 'requiredValue' => 1, 'when' => fn() => $this->isNewRecord,
                'message' => 'Bitte bestätigen, dass der Mitarbeiter eingewilligt hat.'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => 'Name',
            'telefon' => 'Handynummer (WhatsApp)',
            'aktiv' => 'Aktiv',
            'einwilligung' => 'Der Mitarbeiter ist einverstanden, dass seine Nummer hier gespeichert wird und er Aufträge per WhatsApp bekommt.',
        ];
    }

    /** „0151 234 567-89“, „+49 151 …“, „0049 151 …“ → 4915123456789 */
    public static function normalisieren($nummer): string
    {
        $n = preg_replace('/\D/', '', (string)$nummer);
        if (str_starts_with($n, '00')) {
            return substr($n, 2);
        }
        if (str_starts_with($n, '0')) {
            return '49' . substr($n, 1);
        }
        return $n;
    }

    /** „+49 151 23456789“ */
    public function getTelefonAnzeige(): string
    {
        return str_starts_with($this->telefon, '49') ? '+49 ' . substr($this->telefon, 2, 3) . ' ' . substr($this->telefon, 5) : '+' . $this->telefon;
    }

    /** Freie Nachrichten nur innerhalb von 24 Std. nach der letzten Nachricht des Mitarbeiters (Puffer 1 Std.) */
    public function isFensterOffen(): bool
    {
        return $this->letzte_eingang_at !== null && strtotime($this->letzte_eingang_at) > time() - 23 * 3600;
    }

    public function getFensterBis(): ?int
    {
        return $this->isFensterOffen() ? strtotime($this->letzte_eingang_at) + 24 * 3600 : null;
    }

    /** Offene, diesem Mitarbeiter zugewiesene Aufträge (neueste zuerst) */
    public function getOffeneAuftraege(): array
    {
        return Auftrag::find()->alias('a')
            ->leftJoin('maengel_status s', 's.content_id = a.content_id')
            ->innerJoin('content c', 'c.id = a.content_id AND c.state = 1')
            ->where(['a.mitarbeiter_id' => $this->id])
            ->andWhere(['or', ['s.status' => null], ['not in', 's.status', MaengelStatus::CLOSED]])
            ->orderBy(['a.zugewiesen_at' => SORT_DESC])->all();
    }

    public static function aktive(): array
    {
        return static::find()->where(['aktiv' => 1])->orderBy('name')->all();
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
            $this->einwilligung_at = date('Y-m-d H:i:s');
            $this->einwilligung_by = \Yii::$app->user->id ?? null;
        }
        return parent::beforeSave($insert);
    }
}
