<?php
// „Letzte Aktivitäten“: Der Kern übersetzt „created a new {content}“ fest mit „hat ein neues {content} erstellt“ –
// falsch bei Beitrag, Termin, Umfrage usw. ({content} = Inhaltstyp + Vorschau, z. B. Beitrag "…"). Daher ohne Artikel
// (Hinweis Nutzer 10.11.2026).
return [
    '{displayName} created a new {content} and {groupCount} more.' => 'Neu von {displayName}: {content} und {groupCount} weitere',
    '{displayName} created a new {content}.' => 'Neu von {displayName}: {content}',
];
