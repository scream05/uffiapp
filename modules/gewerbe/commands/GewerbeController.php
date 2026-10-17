<?php

namespace humhub\modules\gewerbe\commands;

use humhub\modules\gewerbe\components\BeitragDienst;
use humhub\modules\gewerbe\components\Logo;
use humhub\modules\gewerbe\components\OsmQuelle;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\modules\gewerbe\models\BetriebVerwalter;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Gewerbeverzeichnis und OpenStreetMap:
 *   /app/yii gewerbe/import [1]   Betriebe aus OSM übernehmen bzw. vorhandene verknüpfen (1 = Probelauf)
 *   /app/yii gewerbe/abgleich [1] wöchentlich: Startlisten-Einträge aus OSM aktualisieren, gepflegte nur prüfen,
 *                                 Websites prüfen, fehlende Logos holen (1 = Probelauf)
 *   /app/yii gewerbe/logos        Logos von den Websites holen (nur Einträge ohne Logo)
 *   /app/yii gewerbe/logo <id> <url>  Logo gezielt setzen/ersetzen (z. B. Markenlogo von Commons)
 *   /app/yii gewerbe/loeschen <ids> Betriebe löschen, ihre OSM-Objekte künftig überspringen
 *   /app/yii gewerbe/beitrag-test <autor> <leser> <betrieb-id> [1=behalten]  Probelauf Gewerbebeiträge
 *   /app/yii gewerbe/beitrag-entfernen <id>  Gewerbebeitrag samt HumHub-Beitrag entfernen
 *   /app/yii gewerbe/osm-stand    einmalig: aktuellen OSM-Stand merken (Abgleich ändert dann nur, was OSM neu ändert)
 * Gepflegte Seiten (Status aktiv, vom Betrieb selbst) werden nie überschrieben; ihre Verwalter sehen Abweichungen als Hinweis.
 */
class GewerbeController extends Controller
{
    /** Felder, die aus OSM übernommen bzw. verglichen werden */
    private const FELDER = ['telefon' => 'Telefon', 'email' => 'E-Mail', 'website' => 'Website', 'zeiten' => 'Öffnungszeiten',
        'oeffnungszeiten' => 'Hinweise zu den Öffnungszeiten', 'adresse' => 'Adresse'];

    public function actionImport($probe = 0)
    {
        $elemente = $this->osm();
        if ($elemente === null) {
            return ExitCode::UNAVAILABLE;
        }
        $kandidaten = $this->kandidaten($elemente);
        $verknuepft = $neu = 0;
        $vergeben = []; // Betriebe, die in diesem Lauf schon ein OSM-Objekt bekommen haben
        foreach ($kandidaten as $ref => [$e, $art, $adresse]) {
            $t = $e['tags'];
            $treffer = Betrieb::findOne(['osm_ref' => $ref]) ?? $this->passend($t['name'], (float)$e['lat'], (float)$e['lon'], $vergeben);
            if ($treffer !== null && isset($vergeben[$treffer->id]) && $treffer->osm_ref !== $ref) {
                $this->stdout("· {$t['name']}: weiteres OSM-Objekt zu {$treffer->name}, übersprungen\n");
                continue;
            }
            if ($treffer !== null) {
                $vergeben[$treffer->id] = true;
                $this->stdout("= {$t['name']} → {$treffer->name}" . ($treffer->osm_ref ? '' : ' (verknüpft)') . "\n");
                if (!$probe) {
                    $treffer->updateAttributes(['osm_ref' => $ref]);
                    if ($treffer->status === Betrieb::STATUS_OFFEN) {
                        $this->uebernehmen($treffer, $e, $adresse, false);
                    }
                }
                $verknuepft++;
                continue;
            }
            $this->stdout("+ {$t['name']} ({$art[0]}" . ($art[1] ? ", {$art[1]}" : '') . ') ' . ($adresse ?? 'ohne Adresse') . "\n");
            if (!$probe) {
                $b = new Betrieb(['name' => mb_substr($t['name'], 0, 150), 'kategorie' => $art[0], 'angebot' => $art[1] ?: null,
                    'ortsteil' => OsmQuelle::ortsteil((float)$e['lat'], (float)$e['lon']), 'status' => Betrieb::STATUS_OFFEN,
                    'osm_ref' => $ref, 'quelle' => OsmQuelle::url($ref), 'recherchiert_am' => date('Y-m-d'),
                    'lat' => round((float)$e['lat'], 6), 'lng' => round((float)$e['lon'], 6)]);
                $b->geocodeAus = true; // Ort kommt aus OSM
                if (!$b->save()) {
                    $this->stderr("! {$t['name']}: " . json_encode($b->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                    continue;
                }
                $this->uebernehmen($b, $e, $adresse, false);
            }
            $neu++;
        }
        $this->stdout(($probe ? 'Probelauf: ' : '') . "$neu neu, $verknuepft mit vorhandenen Einträgen verknüpft\n");
        Yii::$app->cache->delete('ki-link-domains');
        return ExitCode::OK;
    }

    /**
     * Probelauf Gewerbebeiträge: Testbeitrag von <autor> für Betrieb <id> anlegen, für Betriebsseite + Übersicht freigeben,
     * prüfen, ob er in der Übersicht von <leser> (kein Mitglied des Gewerbe-Space) und von Gästen erscheint, wieder entfernen.
     * Ohne Benachrichtigungen und Mails.
     */
    public function actionBeitragTest($autor, $leser, $betriebId, $behalten = 0)
    {
        $a = \humhub\modules\user\models\User::findOne(['username' => $autor]);
        $l = \humhub\modules\user\models\User::findOne(['username' => $leser]);
        $betrieb = Betrieb::findOne((int)$betriebId);
        if (!$a || !$l || !$betrieb) {
            $this->stderr("Autor, Leser oder Betrieb nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->user->switchIdentity($a); // Verfasser = Prüfer → keine Glocke „freigegeben“
        Yii::$app->getModule('dashboard')->memberFilterClass = \humhub\modules\uebersicht\filters\MeineUebersicht::class;
        $b = new BetriebBeitrag(['betrieb_id' => $betrieb->id, 'user_id' => $a->id, 'status' => BetriebBeitrag::STATUS_EINGEREICHT,
            'text' => 'TEST – bitte ignorieren (Probelauf Gewerbebeiträge)']);
        $b->save(false);
        BeitragDienst::freigeben($b, true, true);
        $b->refresh();
        $this->stdout("Beitrag {$b->id} für „{$betrieb->name}“: Status {$b->status}, Content {$b->content_id}\n");
        $ids = function ($user) {
            Yii::$app->user->switchIdentity($user);
            $q = new \humhub\modules\dashboard\stream\DashboardStreamQuery(['user' => $user, 'limit' => 5, 'activity' => false]);
            return array_map(fn($c) => $c->id, $q->all());
        };
        $this->stdout("Übersicht {$l->username}: " . (in_array($b->content_id, $ids($l)) ? 'sichtbar' : 'NICHT sichtbar') . "\n");
        $this->stdout('Übersicht Gast: ' . (in_array($b->content_id, $ids(null)) ? 'sichtbar' : 'NICHT sichtbar') . "\n");
        $alt = $b->content_id;
        BeitragDienst::ausUebersicht($b);
        $b->refresh();
        $this->stdout('Aus der Übersicht genommen: ' . (\humhub\modules\content\models\Content::findOne($alt) ? 'NEIN' : 'ja')
            . ", auf der Betriebsseite: " . ($b->auf_betriebsseite ? 'ja' : 'NEIN') . "\n");
        BeitragDienst::freigeben($b, true, true, false);
        $b->refresh();
        $b->begruendung = 'Beispiel-Begründung';
        $this->stdout("--- Ablehnungs-Mail (Vorschau) ---\n" . Yii::$app->view->renderFile('@gewerbe/views/mail/abgelehnt_text.php', [
            'beitrag' => $b, 'name' => $a->displayName, 'link' => $betrieb->getUrl(true), 'kontaktMail' => Yii::$app->getModule('gewerbe')->kontaktMail,
        ]) . "---\n");
        if ($behalten) {
            $this->stdout("Behalten – entfernen mit: gewerbe/beitrag-entfernen {$b->id}\n");
            return ExitCode::OK;
        }
        BeitragDienst::entfernen($b);
        $this->stdout('Entfernt: ' . (\humhub\modules\content\models\Content::findOne($b->content_id) ? 'NEIN' : 'ja') . "\n");
        return ExitCode::OK;
    }

    public function actionBeitragEntfernen($id)
    {
        $b = BetriebBeitrag::findOne((int)$id);
        if ($b === null) {
            $this->stderr("Beitrag $id nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        BeitragDienst::entfernen($b);
        $this->stdout("Beitrag $id entfernt\n");
        return ExitCode::OK;
    }

    /**
     * Einmalig nach Migration m261016_000005: aktuellen OSM-Stand je Betrieb merken, ohne etwas zu übernehmen.
     * Danach übernimmt der Abgleich nur noch echte OSM-Änderungen an unveränderten Feldern.
     */
    public function actionOsmStand()
    {
        $elemente = $this->osm();
        if ($elemente === null) {
            return ExitCode::UNAVAILABLE;
        }
        $n = 0;
        foreach (Betrieb::find()->where(['not', ['osm_ref' => null]])->all() as $b) {
            if (isset($elemente[$b->osm_ref])) {
                $neu = OsmQuelle::felder($elemente[$b->osm_ref]['tags']);
                $neu['email'] = self::geschaeftlich($neu['email'], $neu['website'] ?? $b->website);
                $neu['adresse'] = OsmQuelle::adresse($elemente[$b->osm_ref]['tags']);
                $b->updateAttributes(['osm_stand' => json_encode($neu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                $n++;
            }
        }
        $this->stdout("OSM-Stand für $n Betriebe gemerkt\n");
        return ExitCode::OK;
    }

    public function actionAbgleich($probe = 0)
    {
        $this->stdout(date('Y-m-d H:i') . " Abgleich Gewerbeverzeichnis\n");
        $elemente = $this->osm();
        if ($elemente === null) {
            return ExitCode::UNAVAILABLE; // ohne vollständige Daten nichts ändern
        }
        $geaendert = $hinweise = 0;
        foreach (Betrieb::find()->where(['!=', 'status', Betrieb::STATUS_VORGESCHLAGEN])->orderBy('name')->all() as $b) {
            $notizen = [];
            if ($b->osm_ref !== null) {
                $e = $elemente[$b->osm_ref] ?? null;
                if ($e === null) {
                    $notizen[] = 'Der Eintrag ist nicht mehr in OpenStreetMap – ist der Betrieb geschlossen oder umgezogen?';
                } elseif ($b->status === Betrieb::STATUS_OFFEN) {
                    $aenderungen = $this->uebernehmen($b, $e, null, (bool)$probe);
                    if ($aenderungen) {
                        $geaendert++;
                        $this->stdout("~ {$b->name}: " . implode(', ', $aenderungen) . "\n");
                    }
                } else {
                    // Gepflegte Seite: nichts überschreiben, nur auf Abweichungen hinweisen
                    foreach ($this->abweichungen($b, $e) as $feld => $wert) {
                        $notizen[] = self::FELDER[$feld] . " laut OpenStreetMap: $wert";
                    }
                }
            }
            if ($b->website) {
                $status = null;
                $html = Logo::laden($b->website, 500_000, $endUrl, $status);
                if ($html === null && in_array($status, [403, 429], true)) {
                    // Server antwortet, wehrt aber automatische Abrufe ab – gilt als erreichbar
                } elseif ($html === null) {
                    $notizen[] = 'Die Website ' . $b->website . ' war beim Abgleich nicht erreichbar' . ($status ? " (HTTP $status)" : '') . '.';
                } elseif (Logo::geparkt($html)) {
                    $notizen[] = 'Die Website ' . $b->website . ' scheint nicht mehr zum Betrieb zu gehören (Domain geparkt oder zu verkaufen).';
                } elseif ($endUrl && Logo::domain($endUrl) !== Logo::domain($b->website)) {
                    $notizen[] = 'Die Website ' . $b->website . ' leitet auf ' . Logo::host($endUrl) . ' weiter – ist die Adresse noch aktuell?';
                }
            }
            $hinweis = $notizen ? implode("\n", $notizen) : null;
            if ($hinweis !== null) {
                $hinweise++;
                $this->stdout("! {$b->name}: " . str_replace("\n", ' | ', $hinweis) . "\n");
            }
            if (!$probe) {
                $b->updateAttributes(['abgleich_hinweis' => $hinweis, 'abgleich_am' => date('Y-m-d H:i:s')]);
                if (!$b->logo_guid && $b->website && $b->status === Betrieb::STATUS_OFFEN) {
                    $grund = Logo::holen($b);
                    $this->stdout($grund === null ? "+ Logo {$b->name}\n" : '');
                }
            }
        }
        // Neue Betriebe in OSM melden, aber nicht selbst anlegen (das macht gewerbe/import nach Durchsicht)
        $bekannt = array_flip(Betrieb::find()->select('osm_ref')->where(['not', ['osm_ref' => null]])->column()) + Betrieb::osmAusgeschlossen();
        $namen = array_flip(array_map([self::class, 'norm'], Betrieb::find()->select('name')->column()));
        $offen = array_filter(array_keys($elemente), fn($r) => !isset($bekannt[$r]) && OsmQuelle::art($elemente[$r]['tags']) !== null
            && ($elemente[$r]['tags']['addr:city'] ?? '') === 'Uffenheim' && !isset($namen[self::norm($elemente[$r]['tags']['name'])]));
        $this->stdout(($probe ? 'Probelauf: ' : '') . "$geaendert aktualisiert, $hinweise mit Hinweis, " . count($offen)
            . " Betriebe in OSM mit Uffenheimer Adresse noch nicht im Verzeichnis"
            . ($offen ? ' (' . implode(', ', array_slice(array_map(fn($r) => $elemente[$r]['tags']['name'], $offen), 0, 10)) . ')' : '') . "\n");
        Yii::$app->cache->delete('ki-link-domains');
        return ExitCode::OK;
    }

    /**
     * Einmalige Web-Recherche einspielen: /app/yii gewerbe/web-import <json> [1 = Probelauf]
     * JSON-Array je Betrieb (id, gefunden, beschreibung, angebot, website, email, telefon, social [{typ,url}],
     * logo_url, logo_ersetzen, quellen, hinweis). Nur Einträge mit Status „offen“; leere Angaben lassen
     * vorhandene (aus OSM/Startliste) stehen. Ein vorhandenes Logo wird nur mit logo_ersetzen getauscht.
     */
    public function actionWebImport($pfad, $probe = 0)
    {
        $stats = ['geändert' => 0, 'übersprungen' => 0, 'logos' => 0];
        foreach (json_decode(file_get_contents($pfad), true) as $row) {
            $b = Betrieb::findOne((int)($row['id'] ?? 0));
            if ($b === null || $b->status !== Betrieb::STATUS_OFFEN || empty($row['gefunden'])) {
                $stats['übersprungen']++;
                continue;
            }
            $text = fn($k, $max) => mb_substr(trim((string)($row[$k] ?? '')), 0, $max);
            $b->beschreibung = $text('beschreibung', 20000) ?: $b->beschreibung;
            $b->angebot = $text('angebot', 500) ?: $b->angebot;
            $b->website = $text('website', 255) ?: $b->website;
            $b->telefon = $text('telefon', 50) ?: $b->telefon;
            $email = self::geschaeftlich($text('email', 150) ?: null, $b->website);
            $b->email = $email ?? $b->email;
            if (!empty($row['social'])) {
                $b->socialRows = array_map(fn($s) => ['typ' => $s['typ'] ?? '', 'url' => preg_replace('#^http://#', 'https://', (string)($s['url'] ?? ''))],
                    (array)$row['social']);
            }
            // Ungültige Einzelangaben verwerfen statt den ganzen Betrieb
            if (!$b->validate(['beschreibung', 'angebot', 'website', 'telefon', 'email', 'socialRows'])) {
                foreach (array_keys($b->getErrors()) as $attr) {
                    $this->stderr("  {$b->name}: $attr verworfen (" . implode(' ', $b->getErrors($attr)) . ")\n");
                    if ($attr === 'socialRows') {
                        $b->socialRows = $b->getSocialList();
                    } else {
                        $b->$attr = $b->getOldAttribute($attr);
                    }
                }
            }
            $quellen = array_values(array_filter((array)($row['quellen'] ?? []), fn($u) => preg_match('#^https?://\S+$#', (string)$u)));
            $b->web_quellen = $quellen ? json_encode(array_slice($quellen, 0, 8), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $b->web_recherche_am = date('Y-m-d');
            $logo = trim((string)($row['logo_url'] ?? ''));
            $logo = $logo !== '' && (!$b->logo_guid || !empty($row['logo_ersetzen'])) ? $logo : '';
            $this->stdout("~ {$b->name}: " . mb_strlen((string)$b->beschreibung) . ' Zeichen' . ($logo ? ', Logo' : '')
                . (!empty($row['hinweis']) ? ' | Hinweis: ' . $row['hinweis'] : '') . "\n");
            if ($probe) {
                continue;
            }
            $b->geocodeAus = true; // Ort bleibt (Adresse wird hier nicht geändert)
            if (!$b->save(false)) {
                $this->stderr("! {$b->name}: nicht gespeichert\n");
                continue;
            }
            if ($logo !== '') {
                $grund = Logo::vonUrl($b, $logo);
                $grund === null ? $stats['logos']++ : $this->stderr("  {$b->name}: Logo $logo – $grund\n");
            } elseif (!empty($row['logo_ersetzen']) && ($alt = $b->getLogo())) {
                // Bisheriges Logo war kein Firmenlogo (Foto, fremdes Logo) und es gibt keinen Ersatz
                $alt->delete();
                $b->updateAttributes(['logo_guid' => null, 'logo_quelle' => null]);
                $this->stdout("  {$b->name}: falsches Logo entfernt\n");
            }
            $stats['geändert']++;
        }
        $this->stdout(($probe ? 'Probelauf: ' : '') . implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($stats), $stats)) . "\n");
        Yii::$app->cache->delete('ki-link-domains');
        return ExitCode::OK;
    }

    /** Betriebe löschen (geschlossen, Dublette): /app/yii gewerbe/loeschen 34,47 – OSM-Objekte werden nicht wieder angelegt */
    public function actionLoeschen($ids)
    {
        foreach (array_filter(array_map('intval', explode(',', $ids))) as $id) {
            $b = Betrieb::findOne($id);
            if ($b === null) {
                $this->stderr("- $id: nicht gefunden\n");
                continue;
            }
            BetriebVerwalter::deleteAll(['betrieb_id' => $b->id]);
            $b->delete();
            $this->stdout("- {$b->name} gelöscht" . ($b->osm_ref ? " ({$b->osm_ref} ausgeschlossen)" : '') . "\n");
        }
        return ExitCode::OK;
    }

    public function actionLogos()
    {
        foreach (Betrieb::find()->where(['logo_guid' => null])->andWhere(['not', ['website' => null]])->orderBy('name')->all() as $b) {
            $grund = Logo::holen($b);
            $this->stdout(($grund === null ? '+ ' : '- ') . $b->name . ($grund ? ": $grund" : '') . "\n");
        }
        return ExitCode::OK;
    }

    /** Logo gezielt setzen/ersetzen (z. B. Markenlogo von Wikimedia Commons): /app/yii gewerbe/logo <id> <bild-url> */
    public function actionLogo($id, $url)
    {
        $b = Betrieb::findOne((int)$id);
        if ($b === null) {
            $this->stderr("Betrieb $id nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        $grund = Logo::vonUrl($b, $url);
        $this->stdout(($grund === null ? '+ ' : '- ') . $b->name . ($grund ? ": $grund" : '') . "\n");
        return $grund === null ? ExitCode::OK : ExitCode::DATAERR;
    }

    private function osm(): ?array
    {
        try {
            return OsmQuelle::laden();
        } catch (\Throwable $e) {
            $this->stderr('OpenStreetMap nicht erreichbar: ' . $e->getMessage() . "\n");
            return null;
        }
    }

    /**
     * Betriebe im Stadtgebiet, doppelte (gleicher Name, unter 150 m) zusammengefasst – es bleibt der mit den meisten Angaben.
     * @return array ref => [Element, [Branche, Angebot], Adresse|null]
     */
    private function kandidaten(array $elemente): array
    {
        $liste = [];
        $ausgeschlossen = Betrieb::osmAusgeschlossen();
        foreach ($elemente as $ref => $e) {
            $art = OsmQuelle::art($e['tags']);
            if ($art === null || isset($ausgeschlossen[$ref])) {
                continue;
            }
            $adresse = OsmQuelle::adresse($e['tags']);
            if (!OsmQuelle::inUffenheim($e, $adresse)) {
                continue;
            }
            $schluessel = self::norm($e['tags']['name']);
            foreach ($liste as $r => [$f]) {
                // gleicher Name: ein Eintrag (Namen sind im Verzeichnis eindeutig) – der mit den meisten Angaben
                if (self::norm($f['tags']['name']) === $schluessel) {
                    if (count($e['tags']) <= count($f['tags'])) {
                        continue 2;
                    }
                    unset($liste[$r]);
                }
            }
            $liste[$ref] = [$e, $art, $adresse];
        }
        return $liste;
    }

    /** Vorhandener Eintrag zum OSM-Objekt: gleicher Name, oder unter 80 m entfernt mit einem gemeinsamen Namensteil */
    private function passend(string $name, float $lat, float $lng, array $vergeben = []): ?Betrieb
    {
        $woerter = self::woerter($name);
        foreach (Betrieb::find()->where(['osm_ref' => null])->all() as $b) {
            if (isset($vergeben[$b->id])) {
                continue;
            }
            if (self::norm($b->name) === self::norm($name)) {
                return $b;
            }
            if ($b->lat !== null && self::meter($lat, $lng, (float)$b->lat, (float)$b->lng) < 80
                && array_intersect($woerter, self::woerter($b->name))) {
                return $b;
            }
        }
        return null;
    }

    /**
     * OSM-Angaben in einen Startlisten-Eintrag übernehmen (nur Felder, die OSM kennt).
     * @return string[] geänderte Felder
     */
    private function uebernehmen(Betrieb $b, array $e, ?string $adresse, bool $probe): array
    {
        $neu = OsmQuelle::felder($e['tags']);
        $neu['email'] = self::geschaeftlich($neu['email'], $neu['website'] ?? $b->website);
        $neu['adresse'] = OsmQuelle::adresse($e['tags']) ?? $adresse;
        // Mit Stand (seit dem letzten Lauf): nur Felder ändern, die OSM geändert hat und die noch den alten OSM-Wert haben.
        // So bleiben Korrekturen von Hand und aus der Web-Recherche erhalten, auch geleerte Felder.
        $stand = $b->osm_stand !== null ? (array)json_decode($b->osm_stand, true) : null;
        $aenderungen = [];
        $werte = [];
        foreach ($neu as $feld => $wert) {
            if ($wert === null || $wert === (string)$b->$feld) {
                continue;
            }
            if ($stand !== null && (($stand[$feld] ?? null) === $wert || (string)($stand[$feld] ?? '') !== (string)$b->$feld)) {
                continue;
            }
            // Eigene Hinweise (nicht aus OSM) nicht ersetzen
            if ($feld === 'oeffnungszeiten' && $b->oeffnungszeiten && !str_starts_with($b->oeffnungszeiten, 'Laut OpenStreetMap')) {
                continue;
            }
            $werte[$feld] = $wert;
            $aenderungen[] = self::FELDER[$feld] ?? $feld;
        }
        // Strukturierte Zeiten ersetzen den bisherigen OSM-Hinweis
        if (isset($werte['zeiten']) && str_starts_with((string)$b->oeffnungszeiten, 'Laut OpenStreetMap')) {
            $werte['oeffnungszeiten'] = null;
        }
        if (!$probe) {
            $b->updateAttributes(['osm_stand' => json_encode($neu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
        if ($werte && !$probe) {
            $werte['recherchiert_am'] = date('Y-m-d');
            $werte['quelle'] = OsmQuelle::url($b->osm_ref ?? ($e['type'] . '/' . $e['id']));
            $b->updateAttributes($werte);
            $b->refresh();
            if ($b->lat === null && $b->adresse) {
                $b->updateAttributes(['lat' => round((float)$e['lat'], 6), 'lng' => round((float)$e['lon'], 6)]);
            }
        }
        return $aenderungen;
    }

    /** Abweichungen zwischen gepflegter Seite und OSM (nur Felder, die beide haben) */
    private function abweichungen(Betrieb $b, array $e): array
    {
        $neu = OsmQuelle::felder($e['tags']);
        $diff = [];
        if ($neu['telefon'] && $b->telefon && preg_replace('/\D/', '', $neu['telefon']) !== preg_replace('/\D/', '', $b->telefon)) {
            $diff['telefon'] = $neu['telefon'];
        }
        $host = fn($u) => preg_replace('/^www\./', '', strtolower((string)parse_url((string)$u, PHP_URL_HOST)));
        if ($neu['website'] && $b->website && $host($neu['website']) !== $host($b->website)) {
            $diff['website'] = $neu['website'];
        }
        if ($neu['zeiten'] && $b->zeiten && json_decode($neu['zeiten'], true) != json_decode($b->zeiten, true)) {
            $diff['zeiten'] = $e['tags']['opening_hours'];
        }
        return $diff;
    }

    /** E-Mail nur, wenn sie allgemein ist (info@ …) oder zur Website gehört – keine persönlichen Adressen */
    private static function geschaeftlich(?string $mail, ?string $website): ?string
    {
        if ($mail === null) {
            return null;
        }
        [$lokal, $domain] = explode('@', strtolower($mail), 2);
        $webHost = preg_replace('/^www\./', '', strtolower((string)parse_url((string)$website, PHP_URL_HOST)));
        return in_array($lokal, ['info', 'kontakt', 'mail', 'post', 'office', 'service', 'buero', 'büro', 'hallo', 'hello', 'shop', 'team'], true)
            || ($webHost !== '' && ($domain === $webHost || str_ends_with($domain, '.' . $webHost))) ? $mail : null;
    }

    private static function norm(string $name): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($name)));
    }

    /** Aussagekräftige Namensteile (ohne „Gasthof“, „zum“ …) */
    private static function woerter(string $name): array
    {
        $leer = ['gasthof', 'gasthaus', 'landgasthof', 'restaurant', 'hotel', 'cafe', 'café', 'cafè', 'konditorei', 'bäckerei',
            'backhaus', 'pizzeria', 'eiscafé', 'eiscafe', 'imbiss', 'zum', 'zur', 'zu', 'der', 'die', 'das', 'und', 'am', 'im',
            'uffenheim', 'gmbh', 'co', 'kg', 'ferienwohnung', 'campingplatz', 'camping', 'döner', 'kebap', 'kebab'];
        return array_values(array_diff(array_filter(explode(' ', self::norm($name)), fn($w) => mb_strlen($w) > 2), $leer));
    }

    private static function meter(float $a, float $b, float $c, float $d): float
    {
        return sqrt((($a - $c) * 111200) ** 2 + (($b - $d) * 72300) ** 2);
    }
}
