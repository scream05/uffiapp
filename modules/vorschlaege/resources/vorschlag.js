/* Ideen & Vorschläge: abstimmen ohne Neuladen (ohne JavaScript schickt das Formular normal ab), Stand-Formular aufklappen */
(function ($) {
    $(document).off('submit.vorschlaege').on('submit.vorschlaege', 'form.vs-stimme-form', function (e) {
        e.preventDefault();
        var form = $(this);
        form.find('button').prop('disabled', true);
        $.post(form.attr('action'), form.serialize() + '&ajax=1').done(function (html) {
            form.closest('.vs-stimmen').replaceWith(html);
        }).fail(function () {
            form.off('submit.vorschlaege');
            form[0].submit();
        });
    });
    $(document).off('click.vorschlaege').on('click.vorschlaege', '[data-vs-toggle]', function (e) {
        e.preventDefault();
        $('#' + $(this).data('vs-toggle')).toggleClass('offen');
    });
})(jQuery);
