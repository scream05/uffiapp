<?php
/**
 * @var bool $configured
 * @var bool $ageConfirmed
 * @var string $model
 * @var array $usage [Anfragen, Eingabe-Tokens, Ausgabe-Tokens]
 * @var int $limit
 */

use humhub\helpers\Html;
use humhub\modules\kiAssistent\assets\ChatAsset;
use yii\helpers\Url;
use yii\web\View;

ChatAsset::register($this);
// Links nur zu diesen Seiten klickbar (siehe Module::$linkDomains)
$this->registerJs('window.kiChatDomains = ' . json_encode(Yii::$app->getModule('ki-assistent')->getLinkDomains()) . ';', \yii\web\View::POS_HEAD);

?>
<div class="container ki-page">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Uffi</strong> <span class="text-muted">· KI-Assistent</span>
        </div>
        <div class="panel-body">
            <style <?= Html::nonce() ?>>
                #ki-info { margin-bottom: 10px; }
                #ki-info summary { cursor: pointer; color: var(--hh-text-color-soft, #777); font-size: 0.9em; }
                #ki-messages { max-height: 60vh; overflow-y: auto; margin-bottom: 15px; }
                /* Handy: kein eigener Scrollbereich, die Seite scrollt */
                @media (max-width: 767px) { #ki-messages { max-height: none; overflow: visible; } }
            </style>
            <details id="ki-info" open>
                <summary>KI-Antworten von Claude (Anthropic) – unverbindlich. Persönliche Daten nur für Formulare angeben. <u>Mehr</u></summary>
                <p class="help-block" style="margin-top:6px">
                    Beantwortet Fragen zu Abfuhrterminen, Veranstaltungen, Vereinen, Nachrichten und Informationen der Stadt,
                    bereitet Mängelmeldungen vor, trägt Termine in deinen Kalender ein und füllt ausgewählte Antragsformulare der Stadt mit dir aus.
                    Deine Nachrichten – bei Formularen auch deine Angaben wie Name und Anschrift – werden zur Beantwortung an Claude von Anthropic
                    (<?= Html::encode($model) ?>) übermittelt und hier nicht gespeichert; ein ausgefülltes Formular ist eine Stunde lang abrufbar und wird dann gelöscht.
                    Bewertest du eine Antwort mit 👍/👎, speichern wir Frage und Antwort ohne deinen Namen, um Uffi zu verbessern.
                    Antworten sind keine verbindliche Auskunft der Stadt Uffenheim.
                </p>
                <?php if (Yii::$app->user->isAdmin()): ?>
                <p class="help-block" id="ki-usage">
                    Diesen Monat: <?= $usage[0] ?> von <?= $limit ?> Anfragen (davon <?= $usage[3] ?? 0 ?> ohne Konto), <?= number_format($usage[1], 0, ',', '.') ?> Eingabe- / <?= number_format($usage[2], 0, ',', '.') ?> Ausgabe-Tokens
                </p>
                <?php endif ?>
            </details>
            <?php if (!$configured): ?>
                <div class="alert alert-danger">Kein Claude-Schlüssel hinterlegt (<code>secrets/anthropic_api_key</code>).</div>
            <?php endif ?>
            <?php if (!$ageConfirmed): ?>
                <div class="alert alert-info">
                    <?= Html::beginForm(['confirm-age'], 'post') ?>
                    <p>Uffi steht nur Personen ab 18 Jahren zur Verfügung.
                        Bitte bestätige <?= Yii::$app->user->isGuest ? 'dein Alter.' : 'einmalig dein Alter.' ?></p>
                    <div class="checkbox">
                        <label><input type="checkbox" name="adult" value="1" required> Ich bin mindestens 18 Jahre alt.</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Bestätigen</button>
                    <?= Html::endForm() ?>
                </div>
            <?php endif ?>
            <?php $enabled = $configured && $ageConfirmed ?>
            <div id="ki-messages" class="ki-messages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Gespräch mit Uffi"></div>
            <form id="ki-form" class="ki-form">
                <div class="input-group">
                    <input type="text" id="ki-input" class="form-control ki-input" maxlength="2000" autocomplete="off"
                           placeholder="z. B. Wann kommt die nächste Biotonne? · Laterne kaputt in der Hauptstraße · Saisonkarte beantragen" <?= $enabled ? '' : 'disabled' ?>>
                    <button class="btn btn-primary" type="submit" <?= $enabled ? '' : 'disabled' ?>>Fragen</button>
                </div>
            </form>
            <p style="margin-top:8px"><a href="#" id="ki-reset" class="ki-reset">Neues Gespräch</a>
                <?php if (Yii::$app->user->isAdmin()): ?> · <?= Html::a('Bewertungen', ['bewertungen']) ?><?php endif ?></p>
        </div>
    </div>
</div>

<?php ob_start() // nach ki-chat.js ausführen ?>
(function () {
    var info = document.getElementById('ki-info');
    var mobile = function () { return window.matchMedia('(max-width: 767px)').matches; };
    if (mobile()) info.removeAttribute('open');
    kiChat(document.querySelector('.ki-page'), {
        askUrl: <?= json_encode(Url::to(['ask'])) ?>,
        feedbackUrl: <?= json_encode(Url::to(['feedback'])) ?>,
        pageScroll: mobile,
        onAsk: function () { info.removeAttribute('open'); },
        onUsage: function (usage) {
            if (!document.getElementById('ki-usage')) return;
            document.getElementById('ki-usage').textContent = 'Diesen Monat: ' + usage[0] + ' von <?= $limit ?> Anfragen (davon ' + (usage[3] || 0) + ' ohne Konto), '
                + usage[1].toLocaleString('de-DE') + ' Eingabe- / ' + usage[2].toLocaleString('de-DE') + ' Ausgabe-Tokens';
        }
    });
})();
<?php $this->registerJs(ob_get_clean(), View::POS_END) ?>
