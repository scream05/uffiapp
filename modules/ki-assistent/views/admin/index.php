<?php

use humhub\helpers\Html;
use humhub\modules\kiAssistent\Module;
use yii\helpers\Url;

/**
 * @var \humhub\modules\kiAssistent\Module $modul
 * @var \humhub\components\View $this
 */

$t = fn($s, $p = []) => Yii::t('KiAssistentModule.base', $s, $p);
$aktiv = $modul->getAnbieter();
$standard = $modul->getAnbieterStandard();
$gewaehlt = (string)$modul->settings->get('anbieter') !== '';
$bedrockModell = (string)$modul->settings->get('bedrock-modell');
$claudeStandard = getenv('CLAUDE_MODEL') ?: 'claude-haiku-5-5';

$this->registerJs('
$(".ki-testen").on("click", function () {
    var knopf = $(this), ziel = $("#ki-test-" + knopf.data("anbieter"));
    knopf.prop("disabled", true);
    ziel.removeClass("text-success text-danger").addClass("text-muted").text(' . json_encode($t('Teste …')) . ');
    var daten = {anbieter: knopf.data("anbieter")};
    daten[' . json_encode(Yii::$app->request->csrfParam) . '] = ' . json_encode(Yii::$app->request->csrfToken) . ';
    $.post(' . json_encode(Url::to(['/ki-assistent/admin/testen'])) . ', daten).done(function (r) {
        ziel.removeClass("text-muted").addClass(r.ok ? "text-success" : "text-danger").text(r.meldung);
    }).fail(function (x) {
        ziel.removeClass("text-muted").addClass("text-danger").text("HTTP " + x.status);
    }).always(function () { knopf.prop("disabled", false); });
});
');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= $t('KI-Anbieter') ?></strong></div>
    <div class="panel-body">
        <p><?= $t('Hier wählst du, welcher KI-Anbieter für die ganze Plattform arbeitet: Uffi, Übersetzen, WhatsApp-Auslesen, Stadtrat und Mitteilungsblatt. Die Änderung gilt sofort.') ?></p>
        <p class="help-block"><?= $t('Die API-Schlüssel liegen als Datei auf dem Server (Verzeichnis secrets/) und werden hier weder angezeigt noch eingegeben. Ein Anbieter ohne Schlüssel lässt sich nicht auswählen.') ?></p>

        <?= Html::beginForm(['/ki-assistent/admin/index'], 'post') ?>
        <table class="table" style="margin-top:15px">
            <thead>
            <tr>
                <th></th>
                <th><?= $t('Anbieter') ?></th>
                <th><?= $t('Schlüssel') ?></th>
                <th><?= $t('Modell') ?></th>
                <th><?= $t('Verbindung') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (Module::ANBIETER as $id => $name):
                $key = $modul->getApiKey($id) !== null; ?>
                <tr<?= $id === $aktiv ? ' class="success"' : '' ?>>
                    <td style="width:30px">
                        <input type="radio" name="anbieter" value="<?= $id ?>" id="ki-anbieter-<?= $id ?>"
                            <?= $id === $aktiv ? 'checked' : '' ?> <?= $key ? '' : 'disabled' ?>>
                    </td>
                    <td>
                        <label for="ki-anbieter-<?= $id ?>" style="font-weight:600;margin:0"><?= Html::encode($name) ?></label>
                        <?php if ($id === $aktiv): ?> <span class="label label-success"><?= $t('aktiv') ?></span><?php endif ?>
                        <?php if ($id === $standard): ?> <span class="label label-default" title="KI_ANBIETER"><?= $t('Standard aus .env') ?></span><?php endif ?>
                    </td>
                    <td>
                        <?php if ($key): ?>
                            <span class="text-success"><i class="fa fa-check"></i> <?= $t('vorhanden') ?></span>
                        <?php else: ?>
                            <span class="text-danger"><i class="fa fa-times"></i> <?= $t('fehlt') ?></span>
                            <br><code><?= Html::encode($modul->getApiKeyDatei($id)) ?></code>
                        <?php endif ?>
                    </td>
                    <td><code><?= Html::encode($modul->getModel('standard', null, $id)) ?></code>
                        <?php if ($id === 'bedrock'): ?><br><small class="text-muted"><?= Html::encode(Module::BEDROCK_REGIONEN[$modul->getBedrockRegion()] ?? $modul->getBedrockRegion()) ?></small><?php endif ?>
                    </td>
                    <td>
                        <?php if ($key): ?>
                            <button type="button" class="btn btn-default btn-sm ki-testen" data-anbieter="<?= $id ?>"><?= $t('Testen') ?></button>
                            <div id="ki-test-<?= $id ?>" class="small" style="margin-top:4px;max-width:360px"></div>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>

        <h4 style="margin-top:25px"><?= $t('Einstellungen für AWS Bedrock') ?></h4>
        <div class="row">
            <div class="col-md-4 form-group">
                <label for="bedrock_region"><?= $t('Region') ?></label>
                <?php $regionen = [];
                foreach (Module::BEDROCK_REGIONEN as $id => $ort) {
                    $regionen[$id] = "$ort ($id)";
                } ?>
                <?= Html::dropDownList('bedrock_region', $modul->getBedrockRegion(), $regionen,
                    ['class' => 'form-control', 'id' => 'bedrock_region']) ?>
            </div>
            <div class="col-md-5 form-group">
                <label for="bedrock_modell"><?= $t('Modell') ?></label>
                <?= Html::textInput('bedrock_modell', $bedrockModell, ['class' => 'form-control', 'id' => 'bedrock_modell',
                    'placeholder' => $claudeStandard, 'maxlength' => 80]) ?>
                <p class="help-block"><?= $t('Leer lassen für das Standardmodell ({modell}). Der Vorsatz „anthropic.“ wird automatisch ergänzt. Welche Modelle freigeschaltet sind, steht in der AWS-Konsole unter Bedrock → Model access.', ['modell' => $claudeStandard]) ?></p>
            </div>
        </div>
        <p class="help-block"><?= $t('Region und Modell bitte erst speichern und dann testen.') ?></p>

        <div class="alert alert-warning" style="margin-top:15px">
            <?= $t('Datenschutz: Bei einem Wechsel gehen die Anfragen an einen anderen Dienstleister. Datenschutzerklärung und Auftragsverarbeitungsvertrag müssen den gewählten Anbieter abdecken.') ?>
        </div>

        <?= Html::submitButton($t('Speichern'), ['class' => 'btn btn-primary']) ?>
        <?php if ($gewaehlt): ?>
            <span class="help-block" style="display:inline-block;margin-left:10px"><?= $t('In der Administration gewählt; KI_ANBIETER aus der .env ({standard}) gilt nicht mehr.', ['standard' => $standard]) ?></span>
        <?php endif ?>
        <?= Html::endForm() ?>
    </div>
</div>
