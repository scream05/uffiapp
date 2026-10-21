<?php

namespace humhub\modules\vorschlaege\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\vorschlaege\assets\VorschlagAsset;
use humhub\modules\vorschlaege\models\Stimme;
use humhub\modules\vorschlaege\models\Vorschlag;
use Yii;

/** Zeile unter einem Vorschlag: Stand, Abstimmung, für Bearbeiter das Formular zum Ändern des Stands */
class VorschlagAddon extends Widget
{
    public ?Vorschlag $vorschlag = null;

    public function run()
    {
        // Ein Fehler hier darf nie die Beitragsliste lahmlegen
        try {
            VorschlagAsset::register($this->view);
            $v = $this->vorschlag;
            $id = $v->content_id;
            $html = '<div class="vs-zeile">' . Vorschlag::etikett($v->status)
                . '<span class="vs-seit">' . Yii::t('VorschlaegeModule.base', 'seit {datum}',
                    ['datum' => Yii::$app->formatter->asDate($v->updated_at ?? $v->created_at, 'short')]) . '</span>'
                . self::abstimmung($v, Stimme::summen([$id])[$id] ?? null, Stimme::meine([$id])[$id] ?? 0);
            if (Vorschlag::canManage()) {
                $html .= $this->formular($v);
            }
            return $html . '</div>';
        } catch (\Throwable $e) {
            Yii::error($e, 'vorschlaege');
            return '';
        }
    }

    /**
     * Knöpfe „Dafür“/„Dagegen“ mit Anzahl (auch auf der Übersichtsseite). Nur Zahlen, nicht wer abgestimmt hat.
     * Abstimmen können angemeldete Personen außer dem Verfasser, solange der Vorschlag nicht abgeschlossen ist.
     */
    public static function abstimmung(Vorschlag $v, ?array $summe, int $mein): string
    {
        $summe ??= ['dafuer' => 0, 'dagegen' => 0];
        $eigener = (int)$v->content->created_by === (int)Yii::$app->user->id;
        $darf = !Yii::$app->user->isGuest && !$eigener && $v->istOffen();
        $knopf = function (int $wert, string $icon, string $text, int $anzahl) use ($v, $darf, $mein) {
            $an = $mein === $wert;
            $inhalt = '<i class="fa fa-' . $icon . '" aria-hidden="true"></i> ' . Html::encode($text)
                . ' <span class="vs-anzahl">' . $anzahl . '</span>';
            $klasse = 'btn btn-sm vs-knopf vs-knopf-' . ($wert > 0 ? 'dafuer' : 'dagegen') . ($an ? ' vs-an' : '');
            if (!$darf) {
                return Html::tag('span', $inhalt, ['class' => $klasse . ' vs-nur-zahl']);
            }
            return Html::beginForm($v->content->container->createUrl('/vorschlaege/vorschlag/stimme', ['id' => $v->content_id]), 'post', ['class' => 'vs-stimme-form'])
                . Html::hiddenInput('wert', $wert)
                . Html::submitButton($inhalt, ['class' => $klasse, 'aria-pressed' => $an ? 'true' : 'false',
                    'title' => $an ? Yii::t('VorschlaegeModule.base', 'Stimme zurücknehmen') : $text])
                . Html::endForm();
        };
        $html = '<span class="vs-stimmen" data-vs-id="' . $v->content_id . '">'
            . $knopf(1, 'thumbs-o-up', Yii::t('VorschlaegeModule.base', 'Dafür'), $summe['dafuer'])
            . $knopf(-1, 'thumbs-o-down', Yii::t('VorschlaegeModule.base', 'Dagegen'), $summe['dagegen']);
        if ($eigener && !Yii::$app->user->isGuest) {
            $html .= '<span class="vs-hinweis">' . Yii::t('VorschlaegeModule.base', 'Dein Vorschlag') . '</span>';
        } elseif (!$v->istOffen()) {
            $html .= '<span class="vs-hinweis">' . Yii::t('VorschlaegeModule.base', 'Abstimmung beendet') . '</span>';
        }
        return $html . '</span>';
    }

    private function formular(Vorschlag $v): string
    {
        $id = 'vs-formular-' . $v->content_id;
        $optionen = [];
        foreach (array_keys(Vorschlag::STATUSES) as $key) {
            $optionen[$key] = Vorschlag::anzeigeLabel($key);
        }
        return '<a href="#" class="vs-aendern" data-vs-toggle="' . $id . '"><i class="fa fa-pencil"></i> '
            . Yii::t('VorschlaegeModule.base', 'Stand ändern') . '</a>'
            . '<div id="' . $id . '" class="vs-formular">'
            . Html::beginForm($v->content->container->createUrl('/vorschlaege/vorschlag/status', ['id' => $v->content_id]), 'post')
            . Html::dropDownList('status', $v->status, $optionen, ['class' => 'form-select form-select-sm',
                'aria-label' => Yii::t('VorschlaegeModule.base', 'Neuer Stand')])
            . Html::textInput('note', '', ['class' => 'form-control form-control-sm', 'maxlength' => 500,
                'placeholder' => Yii::t('VorschlaegeModule.base', 'Hinweis (optional), z. B. „kommt mit dem nächsten Update“')])
            . Html::submitButton(Yii::t('VorschlaegeModule.base', 'Speichern'), ['class' => 'btn btn-primary btn-sm'])
            . '<span class="vs-formular-hinweis">' . Yii::t('VorschlaegeModule.base', 'Erscheint als Kommentar unter dem Vorschlag.') . '</span>'
            . Html::endForm() . '</div>';
    }
}
