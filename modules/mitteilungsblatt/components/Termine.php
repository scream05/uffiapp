<?php

namespace humhub\modules\mitteilungsblatt\components;

use humhub\modules\mitteilungsblatt\models\Ausgabe;
use humhub\modules\mitteilungsblatt\Module;

/** Redaktionsschluss und Erscheinungstage aus der Terminliste der Stadt (gespeichert vom Abruf) */
class Termine
{
    public const TAGE = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

    /** @return array<int, array{schluss: string, erscheint: string, vorverlegt: bool}> */
    public static function alle(Module $module): array
    {
        return json_decode((string)$module->settings->get('termine'), true) ?: [];
    }

    /** Nächster Termin, dessen Erscheinungstag heute oder später ist */
    public static function naechster(Module $module, ?string $heute = null): ?array
    {
        $heute ??= date('Y-m-d');
        foreach (self::alle($module) as $t) {
            if ($t['erscheint'] >= $heute) {
                return $t;
            }
        }
        return null;
    }

    /** „Freitag, 23. Oktober 2026“ */
    public static function text(string $datum): string
    {
        $ts = strtotime($datum);
        return self::TAGE[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . '. ' . Ausgabe::MONATE[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /** Hinweiszeile für die Archivseite, null ohne Terminliste */
    public static function hinweis(Module $module): ?string
    {
        $heute = date('Y-m-d');
        $t = self::naechster($module, $heute);
        if ($t === null) {
            return null;
        }
        $satz = $t['erscheint'] === $heute ? 'Die neue Ausgabe erscheint heute.' : 'Nächste Ausgabe: ' . self::text($t['erscheint']) . '.';
        // Schluss schon vorbei → Redaktionsschluss der Ausgabe danach
        $schluss = $t['schluss'] >= $heute ? $t : self::naechster($module, date('Y-m-d', strtotime($t['erscheint'] . ' +1 day')));
        if ($schluss !== null) {
            $satz .= ' Redaktionsschluss' . ($schluss !== $t ? ' für die Ausgabe am ' . self::text($schluss['erscheint']) : '')
                . ': ' . self::text($schluss['schluss']) . ', 10 Uhr' . ($schluss['vorverlegt'] ? ' (vorverlegt)' : '') . '.';
        }
        return $satz;
    }
}
