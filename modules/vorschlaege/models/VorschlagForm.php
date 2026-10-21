<?php

namespace humhub\modules\vorschlaege\models;

use yii\base\Model;
use Yii;

/** Eingabe beim Einreichen */
class VorschlagForm extends Model
{
    public $titel = '';
    public $kategorie = 'Verbesserung';
    public $text = '';

    public function rules()
    {
        return [
            [['titel', 'text'], 'trim'],
            [['titel', 'kategorie', 'text'], 'required'],
            ['titel', 'string', 'min' => 5, 'max' => 150],
            ['text', 'string', 'min' => 10, 'max' => 5000],
            ['kategorie', 'in', 'range' => Yii::$app->getModule('vorschlaege')->kategorien],
        ];
    }

    public function attributeLabels()
    {
        return [
            'titel' => Yii::t('VorschlaegeModule.base', 'Worum geht es?'),
            'kategorie' => Yii::t('VorschlaegeModule.base', 'Art'),
            'text' => Yii::t('VorschlaegeModule.base', 'Beschreibung'),
        ];
    }
}
