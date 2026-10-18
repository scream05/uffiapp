<?php

namespace humhub\modules\maengelmelder\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\maengelmelder\assets\StatusAsset;
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
        StatusAsset::register($this->view);
        $status = MaengelStatus::forContent($this->post->content);
        $since = $status->isNewRecord ? $this->post->content->created_at : $status->updated_at;
        $html = '<div class="mm-statuszeile">' . $status->getEtikett()
            . '<span class="mm-seit">' . Yii::t('MaengelmelderModule.base', 'seit {datum}', ['datum' => Yii::$app->formatter->asDate($since, 'short')]) . '</span>';

        $html .= $this->auch($status);

        if (MaengelStatus::canManage()) {
            $id = 'maengel-status-' . $this->post->content->id;
            $options = [];
            foreach (MaengelStatus::STATUSES as $key => [$label]) {
                $options[$key] = $label;
            }
            $html .= '<a href="#" class="mm-aendern" data-maengel-toggle="' . $id . '"><i class="fa fa-pencil"></i> Status ändern</a>'
                . '<div id="' . $id . '" class="mm-statusformular">'
                . Html::beginForm($this->post->content->container->createUrl('/maengelmelder/status/set', ['id' => $this->post->content->id]), 'post')
                . Html::dropDownList('status', $status->status, $options, ['class' => 'form-select form-select-sm', 'aria-label' => 'Neuer Status'])
                . Html::textInput('note', '', ['class' => 'form-control form-control-sm', 'maxlength' => 500,
                    'placeholder' => 'Hinweis für die meldende Person (optional), z. B. „Reparatur nächste Woche“'])
                . Html::submitButton('Speichern', ['class' => 'btn btn-primary btn-sm'])
                . Html::endForm() . '</div>';
            $this->view->registerJs(<<<JS
$(document).off('click.maengel').on('click.maengel', '[data-maengel-toggle]', function (e) {
    e.preventDefault();
    $('#' + $(this).data('maengel-toggle')).toggleClass('offen');
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
        $html = $count ? '<span class="mm-auch-zahl"><i class="fa fa-users"></i> ' . ($count === 1 ? Yii::t('MaengelmelderModule.base', '1 weitere Person betroffen') : Yii::t('MaengelmelderModule.base', '{n} weitere Personen betroffen', ['n' => $count]))
            . '</span>' : '';
        $own = (int)$content->created_by === (int)Yii::$app->user->id;
        if (!Yii::$app->user->isGuest && !$own && ($mine || !in_array($status->status, MaengelStatus::CLOSED, true))) {
            $html .= Html::beginForm($content->container->createUrl('/maengelmelder/meldung/auch', ['id' => $content->id]), 'post')
                . Html::submitButton(($mine ? '<i class="fa fa-check"></i> ' : '') . Yii::t('MaengelmelderModule.base', 'Betrifft mich auch'),
                    ['class' => 'btn btn-sm mm-auch-knopf ' . ($mine ? 'btn-primary' : 'btn-light'),
                        'title' => $mine ? Yii::t('MaengelmelderModule.base', 'Zurücknehmen') : Yii::t('MaengelmelderModule.base', 'Ich habe denselben Mangel bemerkt')]) . Html::endForm();
        }
        return $html;
    }
}
