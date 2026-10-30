<?php
/**
 * Ersatz für die Kategorie-Legende des Kalenders: Kategorien als Knöpfe über dem Kalender (Events::onCalendarTypeLegend).
 * In Kalendern mit eigener Leiste (resources/kalender/kalender-liste.js) wandern die Knöpfe in die Leiste, der Kasten bleibt leer und unsichtbar.
 * Namen sind die sechs Gruppen (setup/kalender-gruppen), übersetzt über DesignwahlModule.base.
 * Ohne Filterleiste (seit 30.10.2026 für alle aus, Events::onCalendarFilterBar) filtern sie direkt im Kalender (options.types).
 * Am Handy eine waagrecht wischbare Zeile.
 *
 * @var \humhub\modules\calendar\models\CalendarEntryType[] $types
 */

use humhub\helpers\Html;

usort($types, fn($a, $b) => ((int)$a->sort_order <=> (int)$b->sort_order) ?: strcmp($a->name, $b->name));
?>
<div class="panel panel-default calendar-type-legend kal-legende">
    <div class="panel-heading">
        <strong><?= Yii::t('DesignwahlModule.base', 'Kategorien') ?></strong> <small class="text-muted"><?= Yii::t('DesignwahlModule.base', '– antippen zum Filtern') ?></small>
    </div>
    <div class="panel-body">
        <?php foreach ($types as $type) : ?>
            <button type="button" class="calendar-type-item kal-legende-item" data-type-id="<?= (int)$type->id ?>"
                    data-color="<?= Html::encode($type->color) ?>" aria-pressed="false">
                <span class="calendar-type-color" style="background:<?= Html::encode($type->color) ?>"></span>
                <?= Html::encode(Yii::t('DesignwahlModule.base', $type->name)) ?>
            </button>
        <?php endforeach ?>
    </div>
</div>
<?php $this->registerCss(<<<'CSS'
.kal-legende .kal-legende-item { border: 2px solid transparent; cursor: pointer; text-align: left; transition: opacity .15s; }
.kal-legende .kal-legende-item:hover { border-color: var(--text-color-soft3, #ccc); }
.kal-legende .kal-legende-item.active { border-color: currentColor; font-weight: 600; }
.kal-legende.kal-legende-gefiltert .kal-legende-item:not(.active) { opacity: .55; }
.kal-legende.kal-legende-oben .panel-heading { padding-bottom: 0; }
.kal-legende.dn-kal-legende-leer { display: none; }
@media (max-width: 767px) {
    /* eine wischbare Zeile statt zwölf Zeilen untereinander */
    .kal-legende .panel-body { display: flex; flex-wrap: nowrap; gap: 6px; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
    .kal-legende .panel-body::-webkit-scrollbar { display: none; }
    .kal-legende .kal-legende-item { flex: none; white-space: nowrap; margin: 0; }
}
CSS, [], 'kal-legende') ?>
<?php ob_start() ?>
(function () {
    var select = function () { return $('select[name="filterType[]"]'); };
    var kalender = function () {
        var el = document.getElementById('calendar');
        return el ? humhub.require('ui.widget').Widget.instance(el) : null;
    };

    // Über den Kalender stellen (der Kalender rendert die Legende darunter)
    var $legende = $('.kal-legende').last();
    var $panel = $('#calendar').closest('.panel');
    if ($legende.length && $panel.length && !$legende.hasClass('kal-legende-oben')) {
        $legende.addClass('kal-legende-oben').insertBefore($panel);
    }

    // Gäste: gewählte Kategorie merkt sich der Kalender selbst (options.types)
    function aktiveIds() {
        if (select().length) return (select().val() || []).map(String);
        var w = kalender();
        return w && w.options.types ? w.options.types.map(String) : [];
    }

    // Knöpfe zeigen, welche Kategorie gerade gefiltert ist (auch nach Änderung in der Filterleiste oder „x“)
    function markieren() {
        var aktiv = aktiveIds();
        $('.kal-legende-item').each(function () {
            var an = aktiv.indexOf(String($(this).data('type-id'))) !== -1;
            $(this).toggleClass('active', an).attr('aria-pressed', an ? 'true' : 'false');
        });
        $('.kal-legende').toggleClass('kal-legende-gefiltert', aktiv.length > 0);
        // Am Handy (wischbare Zeile): gewählte Kategorie ins Bild holen
        var an = $('.kal-legende-item.active')[0];
        if (an && an.parentNode.scrollWidth > an.parentNode.clientWidth) {
            an.parentNode.scrollLeft = an.offsetLeft - an.parentNode.offsetLeft - 16;
        }
    }

    if (!window.kalLegende) {
        window.kalLegende = true; // Seitenwechsel per PJAX: Handler nur einmal binden
        $(document).on('click', '.kal-legende-item', function () {
            var id = String($(this).data('type-id'));
            var aktiv = aktiveIds();
            var aus = aktiv.length === 1 && aktiv[0] === id;
            var $sel = select();
            if (!$sel.length) {
                var w = kalender();
                if (!w || !w.fullCalendar) return;
                w.options.types = aus ? [] : [id];
                w.initFullCalendar(true);
            } else if (aus) {
                $sel.val(null).trigger('change');
            } else {
                $sel.find('option').remove();
                $('<option>').val(id).text($.trim($(this).text()))
                    .attr({'data-id': id, 'data-text': $.trim($(this).text()), 'data-image': $(this).data('color')})
                    .prop('selected', true).appendTo($sel);
                $sel.trigger('change');
            }
            markieren();
        });
        $(document).on('change', 'select[name="filterType[]"]', markieren);
    }
    markieren();
})();
<?php $this->registerJs(ob_get_clean()) ?>
