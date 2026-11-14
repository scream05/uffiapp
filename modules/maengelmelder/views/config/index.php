<?php

use humhub\helpers\Html;

/**
 * @var \humhub\components\View $this
 * @var string $kanal 'whatsapp'|'telegram'
 * @var bool $telegramAktiv
 * @var string|null $botName
 * @var array|null $webhook getWebhookInfo
 * @var string $erwarteteUrl
 * @var string|null $fehler
 */

$webhookGesetzt = $webhook && !empty($webhook['url']);
$webhookPasst = $webhookGesetzt && rtrim((string)$webhook['url'], '/') === rtrim($erwarteteUrl, '/');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Mängelmelder – Bauhof-Kanal</strong></div>
    <div class="panel-body">
        <p>Über welchen Messenger der Bauhof seine Mitarbeiter mit Aufträgen, Fotos und Status erreicht.</p>

        <?= Html::beginForm(['/maengelmelder/config/index'], 'post') ?>
        <?= Html::hiddenInput('aktion', 'kanal') ?>
        <div class="radio">
            <label>
                <?= Html::radio('bauhofKanal', $kanal === 'whatsapp', ['value' => 'whatsapp']) ?>
                <strong>WhatsApp</strong> – wie bisher über die WhatsApp Cloud API (Meta), Nummer der Mitarbeiter.
            </label>
        </div>
        <div class="radio">
            <label>
                <?= Html::radio('bauhofKanal', $kanal === 'telegram', ['value' => 'telegram', 'disabled' => !$telegramAktiv]) ?>
                <strong>Telegram</strong> – über den Telegram-Bot. Verbundene Mitarbeiter bekommen Telegram,
                noch nicht verbundene weiter WhatsApp (sanfter Übergang).
                <?php if (!$telegramAktiv): ?>
                    <br><span class="text-danger">Noch nicht einsatzbereit: Bot-Token fehlt (secrets/telegram.json).</span>
                <?php endif; ?>
            </label>
        </div>
        <?= Html::submitButton('Kanal speichern', ['class' => 'btn btn-primary']) ?>
        <?= Html::endForm() ?>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Telegram einrichten</strong></div>
    <div class="panel-body">
        <?php if ($fehler): ?>
            <div class="alert alert-warning">Telegram-Status nicht abrufbar: <?= Html::encode($fehler) ?></div>
        <?php endif; ?>

        <table class="table">
            <tr>
                <td style="width:40%">Bot-Token (secrets/telegram.json)</td>
                <td>
                    <?php if ($telegramAktiv): ?>
                        <span class="text-success"><i class="fa fa-check"></i> hinterlegt</span>
                        <?php if ($botName): ?> – Bot: <code>@<?= Html::encode($botName) ?></code><?php endif; ?>
                    <?php else: ?>
                        <span class="text-danger"><i class="fa fa-times"></i> fehlt</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Webhook</td>
                <td>
                    <?php if ($webhookPasst): ?>
                        <span class="text-success"><i class="fa fa-check"></i> eingerichtet</span>
                        <?php if (!empty($webhook['pending_update_count'])): ?>
                            <span class="text-muted">(<?= (int)$webhook['pending_update_count'] ?> wartende Updates)</span>
                        <?php endif; ?>
                    <?php elseif ($webhookGesetzt): ?>
                        <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> zeigt woanders hin:</span>
                        <code><?= Html::encode($webhook['url']) ?></code>
                    <?php else: ?>
                        <span class="text-muted">nicht eingerichtet</span>
                    <?php endif; ?>
                    <?php if (!empty($webhook['last_error_message'])): ?>
                        <br><span class="text-danger">Letzter Fehler: <?= Html::encode($webhook['last_error_message']) ?></span>
                    <?php endif; ?>
                    <div class="help-block">Erwartet: <code><?= Html::encode($erwarteteUrl) ?></code></div>
                </td>
            </tr>
        </table>

        <?php if ($telegramAktiv): ?>
            <?= Html::beginForm(['/maengelmelder/config/index'], 'post', ['style' => 'display:inline']) ?>
            <?= Html::hiddenInput('aktion', 'webhook-an') ?>
            <?= Html::submitButton($webhookPasst ? 'Webhook neu setzen' : 'Webhook einrichten', ['class' => 'btn btn-default']) ?>
            <?= Html::endForm() ?>
            <?php if ($webhookGesetzt): ?>
                <?= Html::beginForm(['/maengelmelder/config/index'], 'post', ['style' => 'display:inline']) ?>
                <?= Html::hiddenInput('aktion', 'webhook-aus') ?>
                <?= Html::submitButton('Webhook entfernen', ['class' => 'btn btn-link text-danger',
                    'data-confirm' => 'Webhook wirklich entfernen? Dann kommen keine Telegram-Nachrichten mehr an.']) ?>
                <?= Html::endForm() ?>
            <?php endif; ?>
        <?php endif; ?>

        <hr>
        <p class="help-block">
            So richtest du Telegram ein: 1. Bei <strong>@BotFather</strong> einen Bot anlegen und den Token in
            <code>secrets/telegram.json</code> eintragen. 2. Hier den <em>Webhook einrichten</em>. 3. Jeden Mitarbeiter
            in der Mitarbeiterliste über seinen persönlichen Link verbinden lassen. 4. Oben auf <strong>Telegram</strong>
            umschalten.
        </p>
    </div>
</div>
