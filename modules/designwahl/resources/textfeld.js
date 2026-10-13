/**
 * Einfaches Textfeld (textarea.textfeld-einfach) als Ersatz für den Richtext-Editor in Nachrichten.
 * Das Mail-JS sucht den Editor über .ProsemirrorEditor und ruft focus() bzw. löst 'clear' aus.
 * Dient im Kommentarformular auch als unsichtbarer Ersatz für den Upload-Knopf (reset()).
 */
humhub.module('designwahl.textfeld', function (module, require, $) {
    var Widget = require('ui.widget').Widget;

    var Textfeld = Widget.extend();

    Textfeld.prototype.focus = function () {
        this.$.trigger('focus');
    };

    Textfeld.prototype.reset = function () {
    };

    $(document).on('clear', 'textarea.textfeld-einfach', function () {
        $(this).val('').trigger('input');
    });

    module.export = Textfeld;
});
