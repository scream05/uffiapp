<?php

namespace humhub\modules\kiAssistent\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\comment\models\Comment;
use humhub\modules\content\models\Content;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\kiAssistent\components\Uebersetzer;
use humhub\modules\kiAssistent\components\UebersetzungGrenze;
use humhub\modules\kiAssistent\Module;
use Yii;

/**
 * „Übersetzen“ unter Inhalten (resources/uebersetzen.js). Übersetzt nur Inhalte, die der Nutzer sehen darf,
 * und nur Text, den der Server selbst lädt – kein freier Übersetzungsdienst.
 * @property Module $module
 */
class UebersetzungController extends Controller
{
    protected function getAccessRules()
    {
        // offen für alle, auch Gäste, solange sie den Inhalt sehen dürfen (Grenzen in Module::reserveUebersetzung)
        return [[ControllerAccess::RULE_POST => ['text']]];
    }

    /** POST typ (content|comment|verein|betrieb), id → JSON {html, titel?} | {alter: true} | {fehler} */
    public function actionText()
    {
        $lang = Uebersetzer::ziel();
        if ($lang === null || $this->module->getApiKey() === null) {
            return $this->asJson(['fehler' => Yii::t('KiAssistentModule.base', 'Übersetzen ist gerade nicht verfügbar.')]);
        }
        if (!$this->module->isAgeConfirmed()) {
            return $this->asJson(['alter' => true]);
        }
        $quelle = $this->quelle((string)Yii::$app->request->post('typ'), (int)Yii::$app->request->post('id'));
        if ($quelle === null) {
            return $this->asJson(['fehler' => Yii::t('KiAssistentModule.base', 'Dieser Inhalt kann nicht übersetzt werden.')]);
        }
        [$text, $titel, $record] = $quelle;
        try {
            // Reines HTML (ohne Hülle des RichText-Widgets), ersetzt den Inhalt des vorhandenen Textblocks
            $result = ['html' => RichText::convert(Uebersetzer::translate($text, $lang), RichText::FORMAT_HTML, $record ? ['record' => $record] : [])];
            if ($titel !== null && $titel !== '') {
                $result['titel'] = Uebersetzer::translate($titel, $lang);
            }
            return $this->asJson($result);
        } catch (UebersetzungGrenze $e) {
            return $this->asJson(['fehler' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Yii::error('Übersetzung: ' . $e->getMessage(), 'ki-assistent');
            return $this->asJson(['fehler' => Yii::t('KiAssistentModule.base', 'Die Übersetzung hat nicht geklappt. Bitte versuch es später noch einmal.')]);
        }
    }

    /** @return array|null [Text (Markdown), Titel oder null, Datensatz für RichText oder null] */
    private function quelle(string $typ, int $id): ?array
    {
        switch ($typ) {
            case 'content':
                $content = Content::findOne($id);
                $record = $content?->getModel();
                if ($record === null || !$content->canView()) {
                    return null;
                }
                foreach (['message', 'description'] as $feld) {
                    if ($record->hasAttribute($feld) && trim((string)$record->$feld) !== '') {
                        $titel = null;
                        foreach (['title', 'question'] as $t) {
                            if ($feld !== 'message' && $record->hasAttribute($t)) {
                                $titel = (string)$record->$t;
                                break;
                            }
                        }
                        return [(string)$record->$feld, $titel, $record];
                    }
                }
                return null;
            case 'comment':
                $comment = Comment::findOne($id);
                if ($comment === null || trim((string)$comment->message) === '' || !$comment->content?->canView()) {
                    return null;
                }
                return [(string)$comment->message, null, $comment];
            case 'verein':
                if (!class_exists(\humhub\modules\vereine\models\Verein::class)) {
                    return null;
                }
                $v = \humhub\modules\vereine\models\Verein::findOne($id);
                return $v && $v->status !== $v::STATUS_VORGESCHLAGEN && $v->canView() && trim((string)$v->beschreibung) !== ''
                    ? [(string)$v->beschreibung, null, null] : null;
            case 'betrieb':
                if (!class_exists(\humhub\modules\gewerbe\models\Betrieb::class)) {
                    return null;
                }
                $b = \humhub\modules\gewerbe\models\Betrieb::findOne($id);
                return $b && $b->status !== $b::STATUS_VORGESCHLAGEN && $b->canView() && trim((string)$b->beschreibung) !== ''
                    ? [(string)$b->beschreibung, null, null] : null;
        }
        return null;
    }
}
