<style <?= \humhub\helpers\Html::nonce() ?>>
    .kurs-karte { display: block; padding: 12px 14px; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; margin-bottom: 10px;
        color: inherit; text-decoration: none; background: var(--bs-body-bg, #fff); }
    .kurs-karte:hover { border-color: var(--bs-primary, #446e88); text-decoration: none; color: inherit; }
    .kurs-karte .fa { width: 14px; text-align: center; opacity: .7; }
    .kurs-fakten { display: grid; grid-template-columns: max-content 1fr; gap: 4px 14px; margin: 12px 0; }
    .kurs-fakten dt { font-weight: 600; } .kurs-fakten dd { margin: 0; }
    @media (max-width: 480px) { .kurs-fakten { grid-template-columns: 1fr; } .kurs-fakten dd { margin-bottom: 6px; } }
    .kurs-zeile { padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,.06); }
    .kurs-zeile form { display: inline; }
    .kurs-belegung { height: 8px; border-radius: 4px; background: rgba(0,0,0,.08); overflow: hidden; margin: 6px 0 2px; }
    .kurs-belegung > div { height: 100%; background: var(--bs-primary, #446e88); }
    .kurs-jahr { margin: 0 0 10px; }
    .kurs-jahr > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 10px; padding: 9px 14px;
        border-radius: 10px; background: color-mix(in srgb, var(--bs-primary, #446e88) 10%, transparent); font-weight: 700; user-select: none; }
    .kurs-jahr > summary::-webkit-details-marker { display: none; }
    .kurs-jahr > summary::after { content: ''; margin-inline-start: auto; width: 9px; height: 9px; border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor; transform: rotate(45deg); transition: transform .2s; margin-top: -4px; }
    .kurs-jahr[open] > summary::after { transform: rotate(-135deg); margin-top: 4px; }
    .kurs-jahr > summary:hover { background: color-mix(in srgb, var(--bs-primary, #446e88) 18%, transparent); }
    .kurs-jahr-zahl { font-size: 1.1em; }
    .kurs-jahr-anzahl { font-weight: 400; opacity: .7; font-size: .95em; }
    .kurs-jahr-liste { margin-top: 10px; }
</style>
