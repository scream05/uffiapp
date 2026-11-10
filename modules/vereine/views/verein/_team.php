<?php
/**
 * Teamübersicht der Vereinsseite: Teamfoto, Text und Teams mit Personen.
 *
 * @var \humhub\modules\vereine\models\Verein $verein
 */

use humhub\helpers\Html;

$teams = $verein->getTeamList();
$bild = $verein->getTeambild();
$text = trim((string)$verein->team_text);
if (!$teams && !$bild && $text === '') {
    return;
}
?>
<style <?= Html::nonce() ?>>
    .verein-teams { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-top: 12px; }
    .verein-team { padding: 12px 14px; border-radius: 10px; background: var(--hh-background-color-secondary, #f4f6f8); }
    .verein-team h3 { font-size: 1em; margin: 0 0 8px; }
    .verein-team ul { margin: 0; padding: 0; list-style: none; line-height: 1.7; }
</style>
<section class="verein-team-bereich" style="margin-top:22px">
    <h2 style="font-size:1.45em;margin-top:24px"><?= Yii::t('VereineModule.base', 'Unser Team') ?></h2>
    <?php if ($bild): ?>
        <?= Html::img($bild->getUrl(), ['alt' => Yii::t('VereineModule.base', 'Teamfoto'), 'loading' => 'lazy',
            'style' => 'width:100%;max-height:420px;object-fit:cover;border-radius:12px;display:block;margin-bottom:10px']) ?>
    <?php endif ?>
    <?php if ($text !== ''): ?>
        <div style="white-space:pre-line"><?= Html::encode($text) ?></div>
    <?php endif ?>
    <?php if ($teams): ?>
        <div class="verein-teams">
            <?php foreach ($teams as $team): ?>
                <div class="verein-team">
                    <?php if (($team['titel'] ?? '') !== ''): ?><h3><?= Html::encode($team['titel']) ?></h3><?php endif ?>
                    <ul>
                        <?php foreach ($team['mitglieder'] ?? [] as $p): ?>
                            <li><strong><?= Html::encode($p['name']) ?></strong><?php if (($p['funktion'] ?? '') !== ''): ?> <span class="text-muted">· <?= Html::encode($p['funktion']) ?></span><?php endif ?></li>
                        <?php endforeach ?>
                    </ul>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
