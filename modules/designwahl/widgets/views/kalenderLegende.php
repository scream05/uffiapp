<?php
/**
 * Ersatz für die Kategorie-Legende des Kalenders: Kategorien als Knöpfe, die den Kategorie-Filter
 * der Filterleiste setzen (Events::onCalendarTypeLegend).
 *
 * @var \humhub\modules\calendar\models\CalendarEntryType[] $types
 */

use humhub\helpers\Html;

?>
<div class="panel panel-default calendar-type-legend kal-legende">
    <div class="panel-heading">
        <strong>Kategorien</strong> <small class="text-muted">– antippen zum Filtern</small>
    </div>
    <div class="panel-body">
        <?php foreach ($types as $type) : ?>
            <button type="button" class="calendar-type-item kal-legende-item" data-type-id="<?= (int)$type->id ?>"
                    data-color="<?= Html::encode($type->color) ?>" aria-pressed="false">
                <span class="calendar-type-color" style="background:<?= Html::encode($type->color) ?>"></span>
                <?= Html::encode($type->name) ?>
            </button>
        <?php endforeach ?>
    </div>
</div>
<?php $this->registerCss(<<<'CSS'
.kal-legende .kal-legende-item { border: 2px solid transparent; cursor: pointer; text-align: left; transition: opacity .15s; }
.kal-legende .kal-legende-item:hover { border-color: var(--text-color-soft3, #ccc); }
.kal-legende .kal-legende-item.active { border-color: currentColor; font-weight: 600; }
.kal-legende.kal-legende-gefiltert .kal-legende-item:not(.active) { opacity: .55; }
CSS, [], 'kal-legende') ?>
<?php ob_start() ?>
(function () {
    var select = function () { return $('select[name="filterType[]"]'); };

    // Knöpfe zeigen, welche Kategorie gerade gefiltert ist (auch nach Änderung in der Filterleiste oder „x“)
    function markieren() {
        var aktiv = (select().val() || []).map(String);
        $('.kal-legende-item').each(function () {
            var an = aktiv.indexOf(String($(this).data('type-id'))) !== -1;
            $(this).toggleClass('active', an).attr('aria-pressed', an ? 'true' : 'false');
        });
        $('.kal-legende').toggleClass('kal-legende-gefiltert', aktiv.length > 0);
    }

    if (!window.kalLegende) {
        window.kalLegende = true; // Seitenwechsel per PJAX: Handler nur einmal binden
        $(document).on('click', '.kal-legende-item', function () {
            var $sel = select();
            if (!$sel.length) return;
            var id = String($(this).data('type-id'));
            var aktiv = ($sel.val() || []).map(String);
            if (aktiv.length === 1 && aktiv[0] === id) {
                $sel.val(null).trigger('change');
            } else {
                $sel.find('option').remove();
                $('<option>').val(id).text($.trim($(this).text()))
                    .attr({'data-id': id, 'data-text': $.trim($(this).text()), 'data-image': $(this).data('color')})
                    .prop('selected', true).appendTo($sel);
                $sel.trigger('change');
                // Legende steht unter dem Kalender: zum Ergebnis hochscrollen
                var cal = document.querySelector('.calendar-filter-bar') || document.querySelector('.fc');
                if (cal) cal.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
            markieren();
        });
        $(document).on('change', 'select[name="filterType[]"]', markieren);
    }
    markieren();
})();
<?php $this->registerJs(ob_get_clean()) ?>
