<?php

namespace humhub\modules\kurse\jobs;

use humhub\modules\kiAssistent\components\Uebersetzer;
use humhub\modules\kiAssistent\components\UebersetzungGrenze;
use humhub\modules\queue\LongRunningActiveJob;
use Yii;

/** Vereinsinhalte der Kursseite in eine Sprache übersetzen (ein KI-Aufruf je Paket, siehe components/Inhalt) */
class InhaltUebersetzenJob extends LongRunningActiveJob
{
    /** @var string[] */
    public array $texte = [];
    public string $sprache = '';

    public function run()
    {
        if (!isset(Uebersetzer::SPRACHEN[$this->sprache]) || $this->texte === []) {
            return;
        }
        try {
            $neu = Uebersetzer::translateBatch($this->texte, $this->sprache);
            Yii::info("Kursseite: $neu von " . count($this->texte) . " Texten nach {$this->sprache} übersetzt", 'kurse');
        } catch (UebersetzungGrenze $e) {
            Yii::warning('Kursseite: Übersetzung abgelehnt – ' . $e->getMessage(), 'kurse');
        } catch (\Throwable $e) {
            Yii::warning('Kursseite: Übersetzung fehlgeschlagen – ' . $e->getMessage(), 'kurse');
        }
    }
}
