<?php
/** Gemeinsame Gestaltung der Spielzeilen (Seite „Spiele & Ergebnisse“ und Kasten auf der Vereinsseite) */

use humhub\helpers\Html;
?>
<style <?= Html::nonce() ?>>
    .fvs-spiel { display: flex; align-items: center; gap: 12px; padding: 10px 4px; border-top: 1px solid var(--bs-border-color, #e5e5e5);
        color: inherit; text-decoration: none; }
    .fvs-spiel:hover, .fvs-spiel:focus-visible { background: var(--bs-tertiary-bg, #f6f6f6); color: inherit; text-decoration: none; }
    .fvs-zeit { flex: 0 0 auto; min-width: 46px; font-variant-numeric: tabular-nums; font-weight: 600; line-height: 1.25; }
    .fvs-tag { display: block; font-weight: 400; font-size: .85em; color: var(--bs-secondary-color, #777); white-space: nowrap; }
    .fvs-mitte { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .fvs-kopf { display: flex; flex-wrap: wrap; gap: 4px 8px; font-size: .8em; color: var(--bs-secondary-color, #777); }
    .fvs-team { font-weight: 600; color: var(--bs-primary, #446e88); }
    .fvs-wettbewerb { background: var(--bs-warning-bg-subtle, #fff3cd); color: var(--bs-warning-text-emphasis, #664d03); border-radius: 4px; padding: 0 5px; }
    .fvs-paarung { overflow-wrap: break-word; }
    .fvs-ort { font-size: .8em; color: var(--bs-secondary-color, #777); }
    .fvs-ergebnis { flex: 0 0 auto; min-width: 48px; text-align: center; font-weight: 700; font-variant-numeric: tabular-nums;
        border-radius: 6px; padding: 3px 8px; background: var(--bs-secondary-bg, #eee); }
    .fvs-s { background: var(--bs-success-bg-subtle, #d1e7dd); color: var(--bs-success-text-emphasis, #0a3622); }
    .fvs-n { background: var(--bs-danger-bg-subtle, #f8d7da); color: var(--bs-danger-text-emphasis, #58151c); }
    .fvs-zustand { font-weight: 400; font-size: .8em; color: var(--bs-secondary-color, #777); }
    .fvs-datum { margin: 16px 0 2px; font-size: .95em; font-weight: 600; }
    .fvs-datum:first-child { margin-top: 0; }
    .fvs-filter { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 4px; scrollbar-width: thin; }
    .fvs-filter .btn { white-space: nowrap; flex: 0 0 auto; }
    @media (min-width: 768px) { .fvs-filter { flex-wrap: wrap; overflow-x: visible; } }
    .fvs-tabelle { width: 100%; font-size: .9em; font-variant-numeric: tabular-nums; }
    .fvs-tabelle th, .fvs-tabelle td { padding: 4px 6px; border-top: 1px solid var(--bs-border-color, #e5e5e5); }
    .fvs-tabelle .zahl { text-align: right; width: 1%; white-space: nowrap; }
    .fvs-tabelle tr.eigen td { font-weight: 700; background: var(--bs-primary-bg-subtle, #e3ecf2); }
    @media (max-width: 575.98px) { .fvs-tabelle .breit { display: none; } }
    .fvs-live { background: #c62828; color: #fff; }
    .fvs-live-marke { background: #c62828; color: #fff; border-radius: 4px; padding: 0 5px; font-weight: 600; }
    .fvs-live-punkt { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: #c62828; margin-right: 2px;
        animation: fvs-puls 1.6s ease-in-out infinite; }
    @keyframes fvs-puls { 50% { opacity: .25; } }
    @media (prefers-reduced-motion: reduce) { .fvs-live-punkt { animation: none; } }
    .fvs-letztes { border-top: 1px solid var(--bs-border-color, #e5e5e5); padding-bottom: 8px; }
    .fvs-letztes:first-child { border-top: 0; }
    .fvs-letztes > .fvs-spiel { border-top: 0; }
    .fvs-mehr { display: inline-block; font-size: .85em; margin: 2px 0 0 62px; }
    .fvs-hinweis { font-size: .85em; margin: 0 0 8px 62px; }
    .fvs-bericht { margin: 0 0 4px 62px; font-size: .9em; }
    .fvs-bericht > summary { cursor: pointer; color: var(--bs-primary, #446e88); padding: 2px 0; }
    .fvs-bericht ol { list-style: none; padding: 0; margin: 6px 0; }
    .fvs-bericht li { display: flex; gap: 8px; padding: 4px 0; border-top: 1px dashed var(--bs-border-color, #e5e5e5); }
    .fvs-min { flex: 0 0 30px; text-align: right; font-variant-numeric: tabular-nums; color: var(--bs-secondary-color, #777); }
    .fvs-sym { flex: 0 0 16px; text-align: center; }
    .fvs-txt { flex: 1 1 auto; min-width: 0; }
    .fvs-e-tor .fvs-txt { font-weight: 600; }
    .fvs-e-phase .fvs-txt { color: var(--bs-secondary-color, #777); }
    .fvs-kommentar { display: block; font-weight: 400; color: var(--bs-secondary-color, #666); font-style: italic; }
    .fvs-karte { display: inline-block; width: 10px; height: 14px; border-radius: 2px; vertical-align: -2px; }
    .fvs-karte-gelb { background: #f9c80e; }
    .fvs-karte-rot { background: #d32f2f; }
    .fvs-karte-gelbrot { background: linear-gradient(135deg, #f9c80e 50%, #d32f2f 50%); }
    @media (max-width: 575.98px) { .fvs-bericht, .fvs-mehr, .fvs-hinweis { margin-left: 0; } }
</style>
