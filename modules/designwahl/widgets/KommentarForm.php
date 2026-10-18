<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\comment\widgets\Form;

/**
 * Kommentarformular mit Richtext-Editor wie bei Umfragen (Werkzeugleiste, ohne Anhänge)
 * (bis 18.10. einfaches Textfeld, weil der Editor auf manchen Handys keine Eingabe annahm).
 * Wird über Events::onCommentFormCreate statt des Standard-Formulars erzeugt.
 */
class KommentarForm extends Form
{
    public function render($view, $params = [])
    {
        return parent::render($view === 'form' ? 'kommentarForm' : $view, $params);
    }

    public function getViewPath()
    {
        return __DIR__ . '/views';
    }
}
