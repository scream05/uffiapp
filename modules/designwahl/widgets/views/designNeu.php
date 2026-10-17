<?php
/**
 * @var string|null $vorname null bei Gästen
 * @var array $mehr [text, icon (HTML), url, farbe, uffi]
 */

use humhub\helpers\Html;
use yii\helpers\Url;

?>
<template id="dn-hero-vorlage" data-wetter-url="<?= Html::encode(Url::to(['/designwahl/wetter'])) ?>"
          data-vorhersage-url="<?= Html::encode(Url::to(['/designwahl/wetter/vorhersage'])) ?>">
    <section class="dn-hero" aria-label="Begrüßung">
        <div class="dn-hero-text">
            <div class="dn-hero-zeile">
                <p class="dn-hero-datum"></p>
                <button type="button" class="dn-wetter" aria-haspopup="dialog" aria-expanded="false" aria-controls="dn-wetterfach" hidden></button>
            </div>
            <?php if ($vorname !== null): ?>
                <h1 class="dn-hero-titel"><span class="dn-hero-gruss">Hallo</span> <strong><?= Html::encode($vorname) ?></strong></h1>
            <?php else: ?>
                <div class="dn-hero-kopf">
                    <h1 class="dn-hero-titel">Willkommen in <strong>Uffenheim</strong></h1>
                    <button type="button" class="dn-hero-klapp" aria-expanded="true" aria-controls="dn-hero-mehr"
                            aria-label="Hinweis einklappen" title="Hinweis einklappen"><i class="fa fa-chevron-up" aria-hidden="true"></i></button>
                </div>
                <div class="dn-hero-mehr" id="dn-hero-mehr">
                <p class="dn-hero-gast">Termine, Abfuhr, Notdienste und Neues aus der Stadt – ohne Anmeldung.
                    Mit kostenlosem Konto zusätzlich: Mängel melden, Abfuhr-Erinnerung aufs Handy und mitreden.</p>
                <p class="dn-hero-knoepfe">
                    <a class="dn-hero-knopf" href="<?= Html::encode(Url::to(['/user/auth/login'])) ?>" data-pjax-prevent>Konto anlegen</a>
                    <a class="dn-hero-knopf dn-hero-knopf-hell" href="<?= Html::encode(Url::to(['/user/auth/login'])) ?>" data-pjax-prevent>Anmelden</a>
                </p>
                <p class="dn-hero-klein">Bürgerplattform der Bürgerliste Uffenheim e. V. – kein Angebot der Stadt Uffenheim.</p>
                </div>
            <?php endif ?>
        </div>
        <svg class="dn-hero-welle" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 38 C60 18 110 52 175 36 S300 8 400 30 V60 H0 Z" fill="currentColor" opacity=".18"/>
            <path d="M0 48 C80 30 140 60 220 44 S340 26 400 42 V60 H0 Z" fill="currentColor" opacity=".28"/>
        </svg>
    </section>
</template>

<div class="dn-mehr" id="dn-mehr" role="dialog" aria-modal="true" aria-label="Mehr" hidden>
    <div class="dn-mehr-hinter" data-dn-zu></div>
    <div class="dn-mehr-fach">
        <div class="dn-mehr-griff" aria-hidden="true"></div>
        <div class="dn-mehr-kopf">
            <strong>Mehr</strong>
            <button type="button" class="dn-mehr-zu" data-dn-zu aria-label="Schließen"><i class="fa fa-times"></i></button>
        </div>
        <nav class="dn-mehr-ziele">
            <?php foreach ($mehr as $z): ?>
                <a class="dn-ziel dn-<?= $z['farbe'] ?>" href="<?= Html::encode($z['url']) ?>"<?= $z['uffi'] ? ' data-dn-uffi' : '' ?>>
                    <span class="dn-ziel-icon"><?= $z['icon'] ?></span>
                    <span class="dn-ziel-text"><?= Html::encode($z['text']) ?></span>
                </a>
            <?php endforeach ?>
        </nav>
    </div>
</div>

<div class="dn-mehr dn-wetterfach" id="dn-wetterfach" role="dialog" aria-modal="true" aria-label="Wetter in Uffenheim" hidden>
    <div class="dn-mehr-hinter" data-dn-wetter-zu></div>
    <div class="dn-mehr-fach">
        <div class="dn-mehr-griff" aria-hidden="true"></div>
        <div class="dn-mehr-kopf">
            <strong>Wetter in Uffenheim</strong>
            <button type="button" class="dn-mehr-zu" data-dn-wetter-zu aria-label="Schließen"><i class="fa fa-times"></i></button>
        </div>
        <div class="dn-wf-inhalt" aria-live="polite"></div>
    </div>
</div>
