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
| `modules/ki-assistent` | KI-Assistent „Uffi“ (Claude, Anthropic) mit Stadtwissen |
| `modules/maengelmelder` | Mängelmelder mit Karte (OpenStreetMap) und Bauhof-Verwaltung per WhatsApp |
| `modules/mitteilungsblatt` | Mitteilungsblatt der Stadt: Ausgaben, Archiv, Volltextsuche |
| `modules/notdienst` | Notrufnummern und Apotheken-Notdienst |
| `modules/vereine` | Vereinsverzeichnis mit Vorstellungsseiten und Terminen |
| `modules/whatsapp` | Veranstaltungen aus weitergeleiteten WhatsApp-Plakaten |
| `scripts/` | Einrichtung (`run-setup.sh` + `SetupController.php`), Importe, Sicherung |
| `design/` | Theme-Anpassungen (SCSS), App-Symbol, Schrift Nunito Sans (SIL OFL) |
| `embeddings/` | Lokaler Dienst für Text-Embeddings der Stadtwissen-Suche |

## Betrieb

`compose.yml` startet HumHub, MariaDB und den Embedding-Dienst. Zugangsdaten
gehören in `.env` (Vorlage `.env.example`) und `secrets/`; beides ist nicht Teil
dieses Repositorys. Die eigenen Module werden in `/data/modules` eingehängt und mit
`/app/yii module/enable <id>` aktiviert.

Nicht enthalten sind Daten (Datenbank, Uploads, Stadtwissen), Zugangsdaten und die
Formular-PDFs der Stadt bzw. des Landkreises für den KI-Assistenten
(`modules/ki-assistent/data/formulare/*.pdf`, erhältlich auf uffenheim.de).

## Lizenz

GNU Affero General Public License v3.0 (`LICENSE`), © Bürgerliste Uffenheim e. V.
Mitgelieferte Fremdbibliotheken behalten ihre Lizenz (Leaflet: BSD-2-Clause,
Nunito Sans: SIL Open Font License).
