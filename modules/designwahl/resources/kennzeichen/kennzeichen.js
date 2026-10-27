/**
 * Zahl am Symbol der Web-App (PWA auf dem Startbildschirm): ungesehene Meldungen + ungelesene Nachrichten,
 * so wie HumHub sie auch im Fenstertitel zählt. Hier nur bei geöffneter Seite (Lesen senkt die Zahl);
 * bei geschlossener Web-App setzt der Service Worker die Zahl mit jedem Push (Events::onServiceWorkerInit).
 */
(function () {
    if (!navigator.setAppBadge || !window.humhub || !window.jQuery) {
        return;
    }

    function zaehlen() {
        var anzahl = parseInt(jQuery('#notification_widget').data('notification-count'), 10) || 0;
        var mail = humhub.modules.mail && humhub.modules.mail.notification;
        if (mail && mail.getNewMessageCount) {
            anzahl += parseInt(mail.getNewMessageCount(), 10) || 0;
        }
        return anzahl;
    }

    function setzen() {
        var anzahl = zaehlen();
        (anzahl > 0 ? navigator.setAppBadge(anzahl) : navigator.clearAppBadge()).catch(function () {});
    }

    // Meldungen ändern die Zahl über updateCount, das Nachrichten-Modul meldet sich über UpdateTitleNotificationCount
    var ereignis = humhub.require('event');
    ereignis.on('humhub:ready', setzen);
    ereignis.on('humhub:notification:updateCount', setzen);
    ereignis.on('humhub:modules:notification:UpdateTitleNotificationCount', setzen);
})();
