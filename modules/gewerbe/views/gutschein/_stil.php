<?php
/** Gemeinsame Gestaltung der Gutscheine (Ticket-Optik in Stadtfarben) */

use humhub\helpers\Html;

?>
<style <?= Html::nonce() ?>>
    .gs-liste { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .gs-ticket { position: relative; display: flex; gap: 12px; align-items: flex-start; padding: 14px 14px 14px 18px; border-radius: 14px;
        border: 2px dashed rgba(68, 110, 136, .45); background: rgba(68, 110, 136, .06); color: inherit; text-decoration: none !important; }
    a.gs-ticket:hover, a.gs-ticket:focus { background: rgba(68, 110, 136, .12); }
    .gs-ticket::before { content: ""; position: absolute; left: -2px; top: 14px; bottom: 14px; width: 6px; border-radius: 0 6px 6px 0; background: #91b80f; }
    .gs-ticket.zu::before { background: #8a8f94; }
    .gs-ticket .gs-titel { display: block; font-size: 1.12em; font-weight: 700; line-height: 1.25; overflow-wrap: anywhere; }
    .gs-ticket .gs-betrieb { display: block; font-size: .9em; }
    .gs-stand { display: inline-block; margin-top: 4px; font-size: .85em; font-weight: 600; color: #3d5a06; }
    .gs-ticket.zu .gs-stand { color: #6b6f73; }
    html[data-bs-theme="dark"] .gs-stand { color: #b5d94a; }
    html[data-bs-theme="dark"] .gs-ticket.zu .gs-stand { color: #b8bcc0; }
    .gs-gross { font-size: 1.5em; font-weight: 800; line-height: 1.2; margin: 6px 0; overflow-wrap: anywhere; }
    .gs-schritte { padding-left: 1.3em; margin: 8px 0 12px; }
    .gs-schritte li { margin-bottom: 6px; }
    .gs-nummer { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .gs-nummer input { max-width: 140px; font-size: 1.3em; letter-spacing: .3em; text-align: center; }
</style>
