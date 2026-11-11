<?php

use humhub\helpers\Html;
use humhub\modules\stadtrat\components\Erklaert;
use humhub\modules\stadtrat\components\Ris;

/** @var \humhub\modules\stadtrat\models\Sitzung $s */
/** @var \humhub\modules\stadtrat\models\Kurzfassung $k */
/** @var bool $laeuft */
/** @var bool $hatPdf */
/** @var bool $hatAudio */
$t = fn($x, $p = []) => Yii::t('StadtratModule.base', $x, $p);
$hatText = $k->kurz !== null;
// data-params: Mit data-confirm schickt yii.js das Formular nach der Rückfrage selbst ab, ohne Namen/Wert des Knopfs –
// „tun“ kommt dann nur als verstecktes Feld aus data-params an
$knopf = fn($tun, $label, $klasse = 'btn-light btn-default', $extra = []) => Html::button($label, ['type' => 'submit', 'name' => 'tun', 'value' => $tun,
    'class' => "btn btn-sm $klasse", 'disabled' => $laeuft] + $extra + (isset($extra['data-confirm']) ? ['data-params' => json_encode(['tun' => $tun])] : []));
?>
<?php if ($laeuft): ?><meta http-equiv="refresh" content="10"><?php endif ?>
<style <?= Html::nonce() ?>>
    #sr-kf textarea { font-family: inherit; font-size: 0.95em; }
    #sr-kf .sr-vorschau { border: 1px solid var(--hh-border-color, #e5e5e5); border-radius: 6px; padding: 10px 14px; background: var(--hh-background-color-secondary, #fafafa); }
    #sr-kf .sr-vorschau h3 { font-size: 1.05em; margin: 4px 0 8px; }
    #sr-kf .sr-knoepfe { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
    #sr-kf .sr-pruefung { white-space: pre-wrap; font-size: 0.9em; }
    #sr-kf h2 { font-size: 1.05em; margin: 20px 0 6px; }
</style>
<div class="panel panel-default" id="sr-kf">
    <div class="panel-heading"><strong><?= $t('Stadtrat kurz erklärt') ?>: <?= Html::encode($s->gremium . ', ' . $s->tag()) ?></strong>
        · <?= Html::a($t('alle Sitzungen'), ['index']) ?></div>
    <div class="panel-body">
        <p><?= $t('Stand:') ?> <strong><?= $k->istFreigegeben() ? $t('veröffentlicht am {zeit}', ['zeit' => date('j.n.Y, G:i', strtotime($k->freigegeben_am))]) : ($hatText ? $t('Entwurf – noch nicht öffentlich') : $t('noch kein Entwurf')) ?></strong></p>
        <?php if ($laeuft): ?>
            <div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> <?= $k->arbeit === 'entwurf' ? $t('Claude schreibt den Entwurf (etwa 1–2 Minuten) …') : $t('Audio wird erzeugt und geprüft (etwa 1–3 Minuten) …') ?> <?= $t('Die Seite lädt sich selbst neu.') ?></div>
        <?php endif ?>
        <?php if ($k->fehler): ?><div class="alert alert-danger"><?= Html::encode($k->fehler) ?></div><?php endif ?>

        <h2>1. <?= $t('Niederschrift') ?></h2>
        <?= Html::beginForm(['bearbeiten', 'id' => $s->id], 'post', ['enctype' => 'multipart/form-data', 'id' => 'sr-pdf-form']) ?>
        <p class="text-muted small"><?= $hatPdf ? $t('Niederschrift ist hochgeladen (nicht öffentlich).') : $t('Niederschrift der öffentlichen Sitzung als PDF. Sie bleibt intern und wird nur an Claude geschickt.') ?></p>
        <?php if (!$k->istFreigegeben()): ?>
            <div class="sr-knoepfe">
                <input type="file" name="pdf" accept="application/pdf" <?= $laeuft ? 'disabled' : '' ?>>
                <?= $knopf('hochladen', $hatPdf ? $t('Neue Niederschrift hochladen und Entwurf erstellen') : $t('Hochladen und Entwurf erstellen'), 'btn-primary') ?>
                <?php if ($hatPdf): ?><?= $knopf('entwurf', $hatText ? $t('Entwurf neu erstellen (überschreibt die Texte)') : $t('Entwurf erstellen'), 'btn-light btn-default',
                    $hatText ? ['data-confirm' => $t('Deine Änderungen an den Texten gehen verloren. Fortfahren?')] : []) ?><?php endif ?>
            </div>
        <?php endif ?>
        <?= Html::endForm() ?>
        <script <?= Html::nonce() ?>>
            // Hochladen per fetch statt Formular-Navigation: Die HumHub-App (WebView) schickt
            // multipart-Formulare nicht ab – Klick auf „Hochladen“ blieb dort ohne Reaktion
            (function () {
                var form = document.getElementById('sr-pdf-form');
                var knopf = form && form.querySelector('button[value="hochladen"]');
                if (!knopf || !window.fetch || !window.FormData) return;
                knopf.addEventListener('click', function (e) {
                    e.preventDefault();
                    var feld = form.querySelector('input[type="file"]');
                    if (!feld.files || !feld.files.length) {
                        alert(<?= json_encode($t('Bitte zuerst die PDF-Datei auswählen.')) ?>);
                        return;
                    }
                    var daten = new FormData(form);
                    daten.set('tun', 'hochladen');
                    form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                    knopf.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + <?= json_encode($t('Wird hochgeladen …')) ?>;
                    fetch(form.action, {method: 'POST', body: daten, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j.fehler) alert(j.fehler);
                            window.location.reload();
                        })
                        .catch(function () {
                            alert(<?= json_encode($t('Hochladen fehlgeschlagen. Bitte Verbindung prüfen und noch einmal versuchen.')) ?>);
                            window.location.reload();
                        });
                });
            })();
        </script>

        <?php if ($hatText): ?>
            <?= Html::beginForm(['bearbeiten', 'id' => $s->id], 'post') ?>
            <h2>2. <?= $t('Texte prüfen') ?></h2>
            <p class="text-muted small"><?= $t('Bitte mit der Niederschrift vergleichen: Stimmen Beschlüsse und Zahlen? Keine Namen, keine Wertungen? Format: „## Überschrift“, „- Liste“, „**fett**“.') ?></p>
            <label class="control-label"><?= $t('Kurzfassung (Seite und Push)') ?></label>
            <?= Html::textarea('kurz', $k->kurz, ['class' => 'form-control', 'rows' => 14, 'disabled' => $laeuft]) ?>
            <details style="margin:6px 0 14px"><summary><?= $t('Vorschau') ?></summary><div class="sr-vorschau"><?= Erklaert::html((string)$k->kurz) ?></div></details>
            <label class="control-label"><?= $t('Ausführliche Fassung') ?></label>
            <?= Html::textarea('lang', $k->lang, ['class' => 'form-control', 'rows' => 22, 'disabled' => $laeuft]) ?>
            <details style="margin:6px 0 14px"><summary><?= $t('Vorschau') ?></summary><div class="sr-vorschau"><?= Erklaert::html((string)$k->lang) ?></div></details>
            <label class="control-label"><?= $t('Vorlesetext (Zahlen bitte als Wörter, sonst liest die Stimme sie manchmal falsch)') ?></label>
            <?= Html::textarea('vorlese', $k->vorlese, ['class' => 'form-control', 'rows' => 12, 'disabled' => $laeuft]) ?>
            <div class="sr-knoepfe"><?= $knopf('speichern', $t('Speichern'), 'btn-primary') ?></div>

            <h2>3. <?= $t('Audio') ?></h2>
            <?php if ($hatAudio): ?>
                <audio controls preload="none" src="<?= Html::encode(\yii\helpers\Url::to(['/stadtrat/kurzfassung/hoeren', 'id' => $s->id, 'v' => strtotime($k->geaendert_am)])) ?>" style="width:100%;max-width:480px"></audio>
                <p class="small" style="margin-top:6px"><?= Erklaert::audioOk($k)
                        ? '<span class="text-success"><i class="fa fa-check"></i> ' . $t('Automatische Prüfung: Die Abschrift stimmt mit dem Vorlesetext überein. Bitte trotzdem anhören.') . '</span>'
                        : '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> ' . $t('Automatische Prüfung hat Abweichungen gefunden – bitte anhören und ggf. neu erzeugen:') . '</span>' ?></p>
                <?php if ($k->audio_pruefung): ?><div class="sr-pruefung text-muted"><?= Html::encode($k->audio_pruefung) ?></div><?php endif ?>
                <?php if ($k->audio_text): ?><details><summary class="small"><?= $t('Abschrift der Aufnahme') ?></summary><div class="sr-pruefung"><?= Html::encode($k->audio_text) ?></div></details><?php endif ?>
            <?php else: ?>
                <p class="text-muted small"><?= $t('Noch keine Aufnahme. Der Vorlesetext geht dafür an Google (Gemini). Ohne Aufnahme wird nur der Text veröffentlicht.') ?></p>
            <?php endif ?>
            <div class="sr-knoepfe"><?= $knopf('audio', $hatAudio ? $t('Speichern und Audio neu erzeugen') : $t('Speichern und Audio erzeugen')) ?></div>

            <h2>4. <?= $t('Veröffentlichen') ?></h2>
            <p class="text-muted small"><?= $k->istFreigegeben()
                    ? $t('Änderungen sind nach „Speichern“ sofort öffentlich. Zurückziehen nimmt die Kurzfassung von der Seite.')
                    : $t('Danach erscheint die Kurzfassung bei der Sitzung auf der Seite „Stadtrat“ (mit Audio, falls vorhanden), und Uffi kennt sie.') ?></p>
            <div class="sr-knoepfe">
                <?php if (!$k->istFreigegeben()): ?>
                    <?= $knopf('freigeben', $t('Geprüft – speichern und veröffentlichen'), 'btn-success', ['data-confirm' => $t('Texte geprüft und Audio angehört? Dann jetzt veröffentlichen.')]) ?>
                <?php else: ?>
                    <?= $knopf('zurueckziehen', $t('Zurückziehen'), 'btn-danger', ['data-confirm' => $k->beitrag_id
                        ? $t('Kurzfassung von der Seite nehmen und den Beitrag in der Übersicht löschen?') : $t('Kurzfassung von der Seite nehmen?')]) ?>
                    <?= Html::a($t('Ansehen'), Ris::url($s)) ?>
                <?php endif ?>
            </div>

            <?php if ($k->istFreigegeben()): ?>
                <h2>5. <?= $t('Beitrag in der Übersicht') ?></h2>
                <?php if (($beitragUrl = Erklaert::beitragUrl($k)) !== null): ?>
                    <p class="small"><i class="fa fa-check text-success"></i> <?= $t('Die Kurzfassung steht als Beitrag im Space „Rathaus Uffenheim“ und damit in der Übersicht.') ?>
                        <?= Html::a($t('Beitrag ansehen'), $beitragUrl) ?></p>
                    <p class="text-muted small"><?= $t('Spätere Änderungen an der Kurzfassung ändern den Beitrag nicht – ihn bei Bedarf direkt bearbeiten.') ?></p>
                <?php else: ?>
                    <p class="text-muted small"><?= $t('Teilt die Kurzfassung als öffentlichen Beitrag im Space „Rathaus Uffenheim“ unter deinem Namen, mit Hinweis auf die Niederschrift. So sehen alle sie in ihrer Übersicht.') ?></p>
                    <details style="margin:6px 0"><summary class="small"><?= $t('Vorschau des Beitragstexts') ?></summary><div class="sr-pruefung"><?= Html::encode(Erklaert::beitragText($k)) ?></div></details>
                    <div class="sr-knoepfe"><?= $knopf('beitrag', $t('Als Beitrag in der Übersicht teilen'), 'btn-primary', ['data-confirm' => $t('Kurzfassung jetzt als öffentlichen Beitrag in der Übersicht teilen?')]) ?></div>
                <?php endif ?>
            <?php endif ?>
            <?= Html::endForm() ?>
        <?php endif ?>
    </div>
</div>
