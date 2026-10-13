<?php

namespace humhub\modules\maengelmelder\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\maengelmelder\models\MaengelAuch;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\post\models\Post;
use Yii;

/** Status-Etikett unter einer Meldung, für Berechtigte mit Formular zum Ändern */
class StatusAddon extends Widget
{
    public ?Post $post = null;

    public function run()
    {
        // Ein Fehler hier darf nie die Beitragsliste lahmlegen
        try {
            return $this->render_();
        } catch (\Throwable $e) {
            Yii::error($e, 'maengelmelder');
            return '';
        }
    }

    private function render_(): string
    {
        $status = MaengelStatus::forContent($this->post->content);
        $since = $status->isNewRecord ? $this->post->content->created_at : $status->updated_at;
        $html = '<div class="maengel-status" style="margin:8px 0">'
            . '<span class="label" style="background:' . Html::encode($status->getColor()) . ';font-size:90%">'
            . '<i class="fa fa-wrench"></i> ' . Html::encode($status->getLabel()) . '</span>'
            . ' <small class="text-muted">seit ' . Yii::$app->formatter->asDate($since, 'short') . '</small>';

        $html .= $this->auch($status);

        if (MaengelStatus::canManage()) {
            $id = 'maengel-status-' . $this->post->content->id;
            $options = [];
            foreach (MaengelStatus::STATUSES as $key => [$label]) {
                $options[$key] = $label;
            }
            $html .= ' <a href="#" class="small" data-maengel-toggle="' . $id . '">Status ändern</a>'
                . '<div id="' . $id . '" style="display:none;margin-top:8px">'
                . Html::beginForm($this->post->content->container->createUrl('/maengelmelder/status/set', ['id' => $this->post->content->id]), 'post')
                . '<div class="form-group" style="margin-bottom:6px">'
                . Html::dropDownList('status', $status->status, $options, ['class' => 'form-control input-sm'])
                . '</div><div class="form-group" style="margin-bottom:6px">'
                . Html::textInput('note', '', ['class' => 'form-control input-sm', 'maxlength' => 500,
                    'placeholder' => 'Hinweis für die meldende Person (optional), z. B. „Reparatur nächste Woche“'])
                . '</div>' . Html::submitButton('Speichern', ['class' => 'btn btn-primary btn-sm'])
                . Html::endForm() . '</div>';
            $this->view->registerJs(<<<JS
$(document).off('click.maengel').on('click.maengel', '[data-maengel-toggle]', function (e) {
    e.preventDefault();
    $('#' + $(this).data('maengel-toggle')).toggle();
});
JS);
        }
        return $html . '</div>';
    }

    /** „X weitere betroffen“ und Knopf zum Anschließen (nicht bei eigenen und abgeschlossenen Meldungen) */
    private function auch(MaengelStatus $status): string
    {
        $content = $this->post->content;
        $count = MaengelAuch::counts([$content->id])[$content->id] ?? 0;
        $mine = in_array($content->id, MaengelAuch::mine([$content->id]), true);
        $html = $count ? ' <small class="text-muted"><i class="fa fa-users"></i> ' . ($count === 1 ? '1 weitere Person' : "$count weitere Personen")
            . ' betroffen</small>' : '';
        $own = (int)$content->created_by === (int)Yii::$app->user->id;
        if (!Yii::$app->user->isGuest && !$own && ($mine || !in_array($status->status, MaengelStatus::CLOSED, true))) {
            $html .= ' ' . Html::beginForm($content->container->createUrl('/maengelmelder/meldung/auch', ['id' => $content->id]), 'post',
                    ['style' => 'display:inline'])
                . Html::submitButton($mine ? '<i class="fa fa-check"></i> Betrifft mich auch' : 'Betrifft mich auch',
                    ['class' => 'btn btn-sm ' . ($mine ? 'btn-primary' : 'btn-light'), 'style' => 'padding:1px 8px;margin-left:4px',
                        'title' => $mine ? 'Zurücknehmen' : 'Ich habe denselben Mangel bemerkt']) . Html::endForm();
        }
        return $html;
    }
}
