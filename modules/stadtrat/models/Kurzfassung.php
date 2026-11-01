<?php

namespace humhub\modules\stadtrat\models;

use yii\db\ActiveRecord;

/**
 * Kurzfassung einer Sitzung in einfacher Sprache. Erst nach Prüfung und Freigabe durch einen Admin öffentlich.
 * @property int $sitzung_id
 * @property string $status entwurf | freigegeben
 * @property string|null $kurz Kurzfassung (einfaches Markdown: ## Überschrift, - Liste, **fett**)
 * @property string|null $lang ausführliche Fassung (dasselbe Format)
 * @property string|null $vorlese Vorlesetext fürs Audio (Zahlen als Wörter)
 * @property string|null $audio_text Abschrift der erzeugten Aufnahme (Gemini)
 * @property string|null $audio_pruefung Ergebnis des Abgleichs Abschrift ↔ Vorlesetext (Claude)
 * @property string|null $arbeit läuft gerade: entwurf | audio
 * @property string|null $fehler letzter Fehler
 * @property string $geaendert_am
 * @property string|null $freigegeben_am
 * @property int|null $freigegeben_von
 * @property int|null $beitrag_id Post in der Übersicht (Space „Rathaus Uffenheim“), falls geteilt
 * @property-read Sitzung $sitzung
 */
class Kurzfassung extends ActiveRecord
{
    public static function tableName()
    {
        return 'stadtrat_kurzfassung';
    }

    public function getSitzung()
    {
        return $this->hasOne(Sitzung::class, ['id' => 'sitzung_id']);
    }

    public function istFreigegeben(): bool
    {
        return $this->status === 'freigegeben';
    }

    public function beforeSave($insert)
    {
        $this->geaendert_am = date('Y-m-d H:i:s');
        return parent::beforeSave($insert);
    }
}
