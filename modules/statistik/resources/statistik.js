// Statistik: Fadenkreuz + Tooltip im Verlauf, Tooltip über Säulen. Auch nach Pjax-Seitenwechsel.
humhub.module('statistik', function (module, require, $) {
    var init = function () {
        $('.st-diagramm').each(function () {
            var $d = $(this), $plot = $d.find('.st-plot');
            if ($d.data('st-bereit')) return;
            $d.data('st-bereit', 1);
            var daten = $d.data('verlauf'), text = $d.data('text'), max = $d.data('max');
            var $kreuz = $plot.find('.st-fadenkreuz'), $a = $plot.find('.st-marke-a'), $b = $plot.find('.st-marke-b'), $tip = $plot.find('.st-tip');
            var aus = function () { $kreuz.add($a).add($b).add($tip).prop('hidden', true); };
            $plot.on('mousemove touchstart', function (e) {
                var p = e.originalEvent.touches ? e.originalEvent.touches[0] : e;
                var r = this.getBoundingClientRect(), n = daten.length;
                var i = n > 1 ? Math.round((p.clientX - r.left) / r.width * (n - 1)) : 0;
                i = Math.max(0, Math.min(n - 1, i));
                var x = n > 1 ? i / (n - 1) * r.width : r.width / 2, z = daten[i];
                var y = function (v) { return (1 - v / max) * r.height; };
                $kreuz.css('left', x).prop('hidden', false);
                $a.css({left: x, top: y(z[3])}).prop('hidden', false);
                $b.css({left: x, top: y(z[4])}).prop('hidden', false);
                $tip.empty().append($('<b>').text(z[0]))
                    .append($('<div>').append('<i style="background:var(--st-a)"></i>').append(document.createTextNode(text[0] + ': ' + z[1])))
                    .append($('<div>').append('<i style="background:var(--st-b)"></i>').append(document.createTextNode(text[1] + ': ' + z[2])))
                    .prop('hidden', false);
                var w = $tip.outerWidth();
                $tip.css({left: x + 12 + w > r.width ? x - 12 - w : x + 12, top: 4});
            }).on('mouseleave', aus);
        });
        $('.st-saeulen').each(function () {
            var $s = $(this);
            if ($s.data('st-bereit')) return;
            $s.data('st-bereit', 1);
            var $tip = $('<div class="st-tip" hidden>').appendTo($s.css('position', 'relative'));
            $s.on('mouseenter touchstart', '.st-saeule', function () {
                $tip.text($(this).data('tip')).prop('hidden', false);
                var l = this.offsetLeft + this.offsetWidth / 2 - $tip.outerWidth() / 2;
                $tip.css({left: Math.max(0, Math.min(l, $s.width() - $tip.outerWidth())), top: -8});
            }).on('mouseleave', function () { $tip.prop('hidden', true); });
        });
    };
    module.export({init: init, initOnPjaxLoad: true});
});
