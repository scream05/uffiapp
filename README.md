# Rund um Uffni – Quellcode

Eigene Erweiterungen und Einrichtungsskripte der Bürgerplattform
[community.rundumuffni.de](https://community.rundumuffni.de) für Uffenheim,
betrieben von der Bürgerliste Uffenheim e. V. (kein Angebot der Stadt Uffenheim).

Die Plattform läuft auf der [HumHub](https://www.humhub.com) Community Edition
(offizielles Docker-Image `humhub/humhub`, Kern und Marketplace-Module unverändert).
Dieses Repository enthält ausschließlich unsere eigenen Ergänzungen und wird
nach § 13 der AGPL-3.0 veröffentlicht.

## Inhalt

| Ordner | Inhalt |
|---|---|
| `modules/abfuhr` | Abfuhr-Erinnerung (Benachrichtigung/Push am Vorabend oder Morgen) |
| `modules/designwahl` | Design „Uffi frisch“, Hauptmenü, Nur-Text-Eingabefelder, Anmeldeseite |
| `modules/facebook` | Übernahme der Beiträge der Facebook-Seite der Stadt (mit deren Zustimmung) |
| `modules/gewerbe` | Gewerbeverzeichnis mit Betriebsseiten, Karte, Beiträgen und Rabatt-Gutscheinen (Theken-QR) |
| `modules/ki-assistent` | KI-Assistent „Uffi“ (Claude, Anthropic) mit Stadtwissen |
| `modules/maengelmelder` | Mängelmelder mit Karte (OpenStreetMap) und Bauhof-Verwaltung per WhatsApp |
| `modules/mitteilungsblatt` | Mitteilungsblatt der Stadt: Ausgaben, Archiv, Volltextsuche |
| `modules/notdienst` | Notrufnummern und Apotheken-Notdienst |
| `modules/statistik` | Cookielose Besucherstatistik für Admins (Tages-Salz, keine IP gespeichert) |
| `modules/tanken` | Spritpreise rund um Uffenheim (Daten MTS-K über Tankerkönig, CC BY 4.0; eigener API-Schlüssel nötig) |
| `modules/vereine` | Vereinsverzeichnis mit Vorstellungsseiten und Terminen |
| `modules/vorschlaege` | Ideen & Vorschläge: Verbesserungsvorschläge einreichen, kommentieren, dafür/dagegen stimmen; Stand durch Admins |
| `modules/whatsapp` | Veranstaltungen aus weitergeleiteten WhatsApp-Plakaten |
| `scripts/` | Einrichtung (`run-setup.sh` + `SetupController.php`), Importe, Sicherung |
| `design/` | Theme-Anpassungen (SCSS), App-Symbol, Schrift Nunito Sans (SIL OFL) |
| `embeddings/` | Lokaler Dienst für Text-Embeddings der Stadtwissen-Suche |

## Betrieb

`compose.yml` startet HumHub, MariaDB und den Embedding-Dienst. Zugangsdaten
gehören in `.env` (Vorlage `.env.example`) und `secrets/`; beides ist nicht Teil
dieses Repositorys. Die eigenen Module werden in `/data/modules` eingehängt und mit
`/app/yii module/enable <id>` aktiviert. Abhängigkeiten per Composer (nicht im Repository):
`scripts/run-setup.sh ki-composer` (Claude-SDK) und `scripts/run-setup.sh gewerbe-composer`
(QR-Codes, bacon/bacon-qr-code, BSD-2-Clause).

Nicht enthalten sind Daten (Datenbank, Uploads, Stadtwissen), Zugangsdaten und die
Formular-PDFs der Stadt bzw. des Landkreises für den KI-Assistenten
(`modules/ki-assistent/data/formulare/*.pdf`, erhältlich auf uffenheim.de).

## Lizenz

GNU Affero General Public License v3.0 (`LICENSE`), © Bürgerliste Uffenheim e. V.
Mitgelieferte Fremdbibliotheken behalten ihre Lizenz (Leaflet: BSD-2-Clause, bacon/bacon-qr-code: BSD-2-Clause,
Nunito Sans: SIL Open Font License).
