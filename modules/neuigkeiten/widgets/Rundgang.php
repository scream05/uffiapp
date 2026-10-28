<?php

namespace humhub\modules\neuigkeiten\widgets;

use humhub\components\Widget;
use humhub\modules\neuigkeiten\assets\NeuigkeitenAsset;
use humhub\modules\space\models\Space;
use Yii;
use yii\helpers\Url;

/** Willkommensrundgang: einmal je Konto auf der Startseite (statt des Neuigkeiten-Popups), ein paar Schritte mit Knopf zum Ziel */
class Rundgang extends Widget
{
    public function run()
    {
        try {
            NeuigkeitenAsset::register($this->view);
            $user = Yii::$app->user->getIdentity();
            return $this->render('rundgang', [
                'vorname' => trim((string)($user->profile->firstname ?? '')) ?: $user->displayName,
                'schritte' => $this->schritte(),
                'avatar' => $this->uffiAvatar(),
            ]);
        } catch (\Throwable $e) {
            Yii::error($e, 'neuigkeiten');
            return '';
        }
    }

    /** @return array[] [symbol, titel, text, knopf-text|null, url|null, uffi] – Schritte ohne vorhandenes Ziel entfallen */
    private function schritte(): array
    {
        $t = fn($s) => Yii::t('NeuigkeitenModule.base', $s);
        $space = fn($url, $route) => ($s = Space::findOne(['url' => $url])) ? $s->createUrl($route) : null;
        $schritte = [
            ['home', $t('Schön, dass du da bist!'),
                $t('Hier findest du alles aus Uffenheim und den Ortsteilen: Neues aus dem Rathaus, Termine, Abfuhr, Vereine und Geschäfte. In der Leiste geht es zu Termine, Abfuhr und Mangel melden, unter „Mehr“ zu Vereinen, Gewerbe, Rathaus, Notdiensten und dem Mitteilungsblatt.'),
                null, null, false],
            ['comments', $t('Frag Uffi'),
                $t('Uffi ist unser KI-Assistent. Er beantwortet Fragen zu Uffenheim rund um die Uhr – von Öffnungszeiten über Formulare bis zur nächsten Abfuhr. Du findest ihn unten rechts auf jeder Seite.'),
                $t('Uffi ausprobieren'), Url::to(['/ki-assistent/chat']), true],
            ['trash', $t('Nie mehr die Tonne vergessen'),
                $t('Sag uns, ob du in der Kernstadt oder einem Ortsteil wohnst und welche Tonnen du hast. Wir erinnern dich am Vorabend oder am Morgen.'),
                $t('Erinnerung einrichten'), Url::to(['/abfuhr/einstellungen']), false],
            ['exclamation-triangle', $t('Mängel melden'),
                $t('Schlagloch, kaputte Laterne oder wilder Müll? Mach ein Foto, setz den Ort auf der Karte – und verfolge, wie die Meldung bearbeitet wird.'),
                $t('Mangel melden'), $space('mangelmelder', '/maengelmelder/meldung/create'), false],
            ['mobile', $t('Immer dabei'),
                $t('Am Handy legst du Rund um Uffni am besten als Web-App auf den Startbildschirm – wie das geht, zeigt die Anleitung. Erlaube Mitteilungen, dann verpasst du keine Antworten und Erinnerungen. Ganz unten auf jeder Seite findest du „Hilfe & Regeln“ und „Ideen & Vorschläge“.'),
                $t('Zur Anleitung'), Url::to(['/neuigkeiten/hilfe/seite', 'seite' => 'app']), false],
        ];
        return array_values(array_filter($schritte, fn($s) => $s[3] === null || $s[4] !== null));
    }

    private function uffiAvatar(): ?string
    {
        if (!Yii::$app->hasModule('ki-assistent')) {
            return null;
        }
        return \humhub\modules\kiAssistent\assets\ChatAsset::register($this->view)->baseUrl . '/avatar.svg';
    }
}
