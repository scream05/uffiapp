<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\comment\widgets\Form;

/**
 * Kommentarformular mit einfachem Textfeld statt Richtext-Editor
 * (der Editor nimmt auf manchen Handys keine Eingabe an).
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
