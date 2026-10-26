<?php

namespace humhub\modules\vorschlaege\components;

use humhub\modules\content\models\Content;
use humhub\modules\vorschlaege\models\Stimme;
use humhub\modules\vorschlaege\models\Vorschlag;
use Yii;
use yii\helpers\Url;

/** Abschnitt für den KI-Assistenten: Übersicht der Vorschläge (nur öffentliche, ohne Namen der Verfasser) */
class Uffi
{
    /** Abgeschlossene Vorschläge so viele Tage nach der letzten Änderung noch nennen */
    public const ABGESCHLOSSEN_TAGE = 90;

    public static function forAssistant(): ?string
    {
        $space = Yii::$app->getModule('vorschlaege')->getSpace();
        if ($space === null) {
            return null;
        }
        $vorschlaege = Vorschlag::find()->alias('v')->innerJoinWith('content c')
            ->where(['c.contentcontainer_id' => $space->contentcontainer_id, 'c.state' => Content::STATE_PUBLISHED,
                'c.visibility' => Content::VISIBILITY_PUBLIC, 'c.hidden' => 0])
            ->andWhere(['or', ['not in', 'v.status', Vorschlag::CLOSED],
                ['>=', 'v.updated_at', date('Y-m-d', strtotime('-' . self::ABGESCHLOSSEN_TAGE . ' days'))]])
            ->orderBy(['v.created_at' => SORT_DESC])->limit(80)->all();
        $ids = array_map(fn($v) => (int)$v->content_id, $vorschlaege);
        $summen = Stimme::summen($ids);
        $kommentare = $ids ? (new \yii\db\Query())->select(['n' => 'COUNT(*)', 'content_id'])->from('comment')
            ->where(['content_id' => $ids, 'parent_comment_id' => null])
            ->groupBy('content_id')->indexBy('content_id')->column() : [];
        // Meiste Zustimmung zuerst, damit beliebte Ideen auch bei langer Liste oben stehen
        usort($vorschlaege, fn($a, $b) => (($summen[$b->content_id]['dafuer'] ?? 0) - ($summen[$b->content_id]['dagegen'] ?? 0))
            <=> (($summen[$a->content_id]['dafuer'] ?? 0) - ($summen[$a->content_id]['dagegen'] ?? 0)));
        $lines = [];
        foreach ($vorschlaege as $v) {
            $s = $summen[$v->content_id] ?? ['dafuer' => 0, 'dagegen' => 0];
            $text = Vorschlag::beschreibung($v->content->getPolymorphicRelation()->message ?? '');
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            $lines[] = "- {$v->titel} ({$v->kategorie}) – Stand: {$v->getLabel()}"
                . ($v->updated_at ? ' (seit ' . date('d.m.Y', strtotime($v->updated_at)) . ')' : '')
                . ($v->note ? " – Hinweis des Teams: {$v->note}" : '')
                . " – {$s['dafuer']} dafür, {$s['dagegen']} dagegen, " . (int)($kommentare[$v->content_id] ?? 0) . ' Kommentare'
                . ' – eingereicht ' . date('d.m.Y', strtotime($v->created_at)) . ' – ' . Url::to($v->content->getUrl(), true)
                . ($text !== '' ? "\n  " . (mb_strlen($text) > 250 ? mb_substr($text, 0, 250) . '…' : $text) : '');
        }
        return "## Ideen & Vorschläge für die Plattform (von Mitgliedern eingereicht; offene und in den letzten "
            . self::ABGESCHLOSSEN_TAGE . " Tagen abgeschlossene, meiste Zustimmung zuerst)\n"
            . 'Übersicht: ' . $space->createUrl('/vorschlaege/vorschlag/index', [], true)
            . ' · Neuen Vorschlag einreichen: ' . $space->createUrl('/vorschlaege/vorschlag/create', [], true) . "\n"
            . 'Stände: ' . implode(', ', array_column(Vorschlag::STATUSES, 0)) . ". Den Stand setzt das Team der Plattform.\n"
            . ($lines ? implode("\n", $lines) : '(noch keine Vorschläge)');
    }
}
