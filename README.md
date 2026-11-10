# CoMyLi – Configurable-MySQL-Light-Interface

**CoMyLi** ist ein universelles, blockbasiertes WordPress-Plugin zur Anzeige, Auswertung, Archivierung und Bearbeitung (CRUD) von MySQL-Datenbanktabellen und SQL-Abfragen – sowohl für die lokale WordPress-Datenbank als auch für externe MySQL-Datenbankserver.

---

## Inhaltsverzeichnis

1. [Hauptfunktionen](#1-hauptfunktionen)
2. [Installation & Einrichtung](#2-installation--einrichtung)
3. [Gutenberg-Blöcke (Referenz)](#3-gutenberg-blöcke-referenz)
   - [ha-comyli/query-table (CoMyLi Tabelle)](#31-ha-comyliquery-table-comyli-tabelle)
   - [ha-comyli/chart (Diagramm)](#32-ha-comylichart-diagramm)
   - [ha-comyli/kpi-card (KPI-Kachel)](#33-ha-comylikpi-card-kpi-kachel)
   - [ha-comyli/query-list (Abfrage-Liste)](#34-ha-comyliquery-list-abfrage-liste)
4. [Shortcodes (Referenz)](#4-shortcodes-referenz)
   - [`[comyli_table]` / `[comyli]` / `[ha-comyli]`](#41-comyli_table--comyli--ha-comyli)
   - [`[comyli_query_list]` / `[comyli_queries]`](#42-comyli_query_list--comyli_queries)
5. [Programmatische PHP-Einbindung (`comyli_render`)](#5-programmatische-php-einbindung-comyli_render)
6. [SQL-Abfragen, Parameter & Kontext-Token](#6-sql-abfragen-parameter--kontext-token)
7. [Externe Datenbankverbindungen (`ConnectionManager`)](#7-externe-datenbankverbindungen-connectionmanager)
8. [Zeitgesteuerte Abfragen & CSV-Archivierung (`QueryScheduler`)](#8-zeitgesteuerte-abfragen--csv-archivierung-queryscheduler)
9. [Berechtigungssystem & Capabilities (`Permissions`)](#9-berechtigungssystem--capabilities-permissions)
10. [Headless REST-API](#10-headless-rest-api)
11. [Custom CSS, Custom JS & LiveSync](#11-custom-css-custom-js--livesync)
12. [Erweiterte Tabellen-Konfiguration (JSON) & Spalten-CSS (`data-col`)](#12-erweiterte-tabellen-konfiguration-json--spalten-css-data-col)
13. [WordPress-Filter & Entwickler-Hooks](#13-wordpress-filter--entwickler-hooks)

---

## 1. Hauptfunktionen

* **4 Gutenberg-Blöcke:** Interaktive Datentabellen (`ha-comyli/query-table`), Chart.js-Diagramme (`ha-comyli/chart`), KPI-Kennzahlenkacheln (`ha-comyli/kpi-card`) und Berichtsübersichten (`ha-comyli/query-list`).
* **QueryManager (CPT `comyli_query`):** WordPress-Backend-Editor für SQL-Abfragen mit Syntax-Highlighting (CodeJar & Prism SQL), WordPress-Revisionen (Diff-Ansicht), Kategorien und rollenbasierter Zugriffskontrolle.
* **Erweiterte JSON-Tabellenkonfiguration (`ConfigResolver`):** Vollständig deklarative Tabellenregeln für Spalten-Defaults, Fremdschlüssel (SQL, WP-Optionen, Benutzer), Berechtigungen und rollenabhängiges CSS – ganz ohne `eval()`.
* **Spaltenbasiertes CSS (`data-col`):** Alle Tabellenzellen tragen automatisch das Attribut `data-col="<Spaltenname>"`, wodurch Styling unabhängig von variierenden `:nth-of-type(...)`-Reihenfolgen stabil bleibt.
* **Multi-Datenbank-Unterstützung (`ConnectionManager`):** Nahtloser Zugriff auf die lokale WordPress-Datenbank oder beliebige externe MySQL-Server (inkl. benutzerdefinierter Ports).
* **Automatische CSV-Archivierung (`QueryScheduler`):** Geplante Ausführung von Abfragen via WP-Cron (täglich, wöchentlich, monatlich) oder manuell auf Knopfdruck mit geschützter Ablage und Download-Verwaltung.
* **Dynamische SQL-Parameter & Kontext-Token:** Benannte Parameter (`:param_name`), Benutzer-Kontext (`{current_user_id}`, `{current_user_name}`), URL-Parameter (`{get:param}`) sowie verschachtelte Subqueries (`[QRY]...[/QRY]`).
* **Headless REST-API:** JSON-Endpunkte zum Ausführen von Abfragen und für CRUD-Operationen auf Tabellen (`/wp-json/comyli/v1/query/{name}` und `/wp-json/comyli/v1/table/{name}`).
* **Echtzeit-Kollaboration (LiveSync):** Optimistische Sperrprüfung (`lastChanged` / `lstChanged`) und automatische Mehrbenutzer-Synchronisation im Frontend (`Auto` / `An` / `Aus`).
* **Custom CSS & JS Editoren:** Direkte Hinterlegung von eigenem CSS und JavaScript pro Tabelle und pro Abfrage inklusive Revisions-Tracking.

---

## 2. Installation & Einrichtung

1. Das Plugin in das Verzeichnis `/wp-content/plugins/ha-comyli` hochladen und in WordPress aktivieren. Updates erfolgen automatisch über GitHub Releases via *Plugin-Update-Checker (PUC v5)*.
2. **Tabellen konfigurieren:** Unter **CoMyLi Tabellen → Erstellen** eine Datenbanktabelle (lokal oder extern) definieren.
3. **Abfragen anlegen:** Unter **CoMyLi Tabellen → Abfragen** neue SQL-Abfragen im Backend-Editor definieren.
4. **Einbinden:** Über Gutenberg-Blöcke, Shortcodes oder direkt per PHP (`comyli_render()`).

---

## 3. Gutenberg-Blöcke (Referenz)

Alle Blöcke befinden sich im Block-Editor in der Kategorie **Widgets** und bieten eine Live-Vorschau via `ServerSideRender`.

### 3.1 `ha-comyli/query-table` (CoMyLi Tabelle)

Bettet eine gespeicherte SQL-Abfrage oder eine Datenbanktabelle als interaktive Tabelle ein.

| Attribut | Typ | Standard | Beschreibung |
| :--- | :--- | :--- | :--- |
| `query` | `string` | `""` | Slug, Titel oder ID der SQL-Abfrage (CPT `comyli_query`). |
| `table` | `string` | `""` | Alternativ: Name der Datenbanktabelle oder Slug der CoMyLi-Tabelle. |
| `params` | `string` | `""` | Dynamische Parameter für `:platzhalter` (Query-String `id=5&status=aktiv` oder JSON). |
| `layout` | `string` | `"clean"` | `"clean"` (kompakt ohne Seitenleiste) oder `"default"` (vollständiges Interface). |
| `limit` | `number` | `25` | Anzahl der Zeilen pro Seite (`5` bis `200`). |
| `filter` | `boolean` | `true` | Such- und Spaltenfilterzeile einblenden. |
| `csvExport` | `boolean` | `false` | Button für den CSV-Download einblenden. |
| `edit` | `boolean` | `false` | Bearbeitungsmodus (Inline-Edit, Hinzufügen, Löschen) aktivieren (nur für Tabellen). |

### 3.2 `ha-comyli/chart` (Diagramm)

Visualisiert das Ergebnis einer SQL-Abfrage oder Tabelle als interaktives **Chart.js**-Diagramm.

| Attribut | Typ | Standard | Beschreibung |
| :--- | :--- | :--- | :--- |
| `query` | `string` | `""` | Slug/Name der SQL-Abfrage. |
| `table` | `string` | `""` | Alternativ: Name der Datenbanktabelle (lädt bis zu 50 Zeilen). |
| `chartType` | `string` | `"bar"` | Diagrammtyp: `"bar"` (Balken), `"line"` (Linie), `"pie"` (Torte), `"doughnut"` (Donut). |
| `labelColumn`| `string` | `""` | Spaltenname für die Beschriftungen (X-Achse / Segmente). Fallback: 1. Spalte. |
| `valueColumn`| `string` | `""` | Spaltenname für die Zahlenwerte (Y-Achse). Fallback: 2. Spalte. |
| `title` | `string` | `""` | Optionale Überschrift über dem Diagramm. |
| `height` | `number` | `350` | Höhe des Diagramms in Pixeln (`200` bis `800`). |

### 3.3 `ha-comyli/kpi-card` (KPI-Kachel)

Zeigt eine einzelne Kennzahl (1. Zeile, 1. Spalte einer SQL-Abfrage oder `COUNT(*)` einer Tabelle) als Dashboard-Kachel an.

| Attribut | Typ | Standard | Beschreibung |
| :--- | :--- | :--- | :--- |
| `query` | `string` | `""` | Slug/Name der SQL-Abfrage (z. B. `SELECT COUNT(*) FROM ...`). |
| `table` | `string` | `""` | Alternativ: Tabellenname (berechnet automatisch `SELECT COUNT(*) FROM table`). |
| `title` | `string` | `"Gesamtanzahl"`| Titel der KPI-Kachel. |
| `unit` | `string` | `""` | Einheit hinter dem Wert (z. B. `€`, `%`, `Plätze`). |
| `trend` | `string` | `""` | Optionaler Trend-Text in der Fußzeile (z. B. `+12% ggü. Vormonat`). |
| `trendDirection`| `string`| `"up"` | Farbgebung des Trends: `"up"` (grün), `"down"` (rot), `"neutral"` (grau). |
| `icon` | `string` | `"📊"` | Icon oder Emoji oben rechts in der Kachel. |
| `color` | `string` | `"#3b82f6"` | Akzentfarbe des oberen Kachelrahmens (Hex-Farbcode). |

### 3.4 `ha-comyli/query-list` (Abfrage-Liste)

Rendert ein Dashboard aller SQL-Abfragen (`comyli_query`), für die der aktuell eingeloggte Benutzer die erforderliche Berechtigung (Capability) besitzt. Per Klick auf **„📊 Daten anzeigen“** wird die gewählte Auswertung direkt auf der Seite geöffnet.

| Attribut | Typ | Standard | Beschreibung |
| :--- | :--- | :--- | :--- |
| `title` | `string` | `"Verfügbare Auswertungen & Berichte"` | Überschrift der Berichtsliste. |
| `category` | `string` | `""` | Filtert Abfragen nach Kategorie-Name oder Kategorie-Slug (leer = alle). |
| `layout` | `string` | `"grid"` | `"grid"` (Karten-Raster) oder `"list"` (kompakte Liste). |
| `showCount` | `boolean` | `true` | Zeigt die Anzahl verfügbarer Berichte als Badge neben dem Titel. |
| `showDesc` | `boolean` | `true` | Blendet die Kurzbeschreibung der jeweiligen Abfrage ein. |

---

## 4. Shortcodes (Referenz)

### 4.1 `[comyli_table]` / `[comyli]` / `[ha-comyli]`

Alle drei Shortcode-Namen sind gleichwertige Aliase zum Einbetten von Tabellen oder SQL-Abfragen.

#### Unterstützte Attribute

| Attribut | Standard | Beschreibung |
| :--- | :--- | :--- |
| `id` | `0` | Post-ID eines konfigurierten `comyli`-Tabellen-Posts. |
| `query` / `qry` | `""` | Slug, Titel, ID einer `comyli_query`-Abfrage oder Dateiname einer `.sql`-Datei. |
| `table` / `tables` | `""` | Tabellenname in der Datenbank oder Slug eines `comyli`-Tabellen-Posts. |
| `params` | `""` | Parameter für `:platzhalter` (z. B. `params="abteilung=IT&aktiv=1"` oder JSON). |
| `layout` | `"clean"` | `"clean"` (eingebettetes Layout ohne Sidebar) oder `"default"`. |
| `limit` | `25` | Zeilen pro Seite (Standard: `25`, bei Tabellen-Posts gemäß Post-Konfiguration). |
| `filter` | `1` | `1` / `true` = Filterleiste und Volltextsuche anzeigen, `0` / `false` = ausblenden. |
| `csvExport` | `0` | `1` / `true` = CSV-Export-Button anzeigen, `0` / `false` = ausblenden. |
| `edit` | `0` | `1` / `true` = Bearbeitungsmodus aktivieren (setzt `can_edit_table`-Recht voraus). |

#### Beispiele

```text
[comyli_table query="offene-rechnungen" layout="clean" limit="50" csvExport="1"]

[comyli_table query="mitarbeiter-abteilung" params="abteilung=Bauhof&status=aktiv"]

[comyli_table table="wp_ha_sync_phonebook" edit="1" filter="1" limit="100"]

[ha-comyli id="42"]
```

---

### 4.2 `[comyli_query_list]` / `[comyli_queries]`

Gibt eine interaktive Übersicht aller Abfragen aus, auf die der aktuelle Benutzer Zugriff hat.

#### Unterstützte Attribute

| Attribut | Standard | Beschreibung |
| :--- | :--- | :--- |
| `title` | `"Verfügbare Auswertungen & Berichte"` | Überschrift über der Übersicht. |
| `category` | `""` | Optionaler Filter nach WordPress-Kategorie (Name oder Slug). |
| `layout` | `"grid"` | `"grid"` (Kacheln) oder `"list"` (Liste). |
| `showCount` | `1` | `1` = Anzahl-Badge anzeigen, `0` = ausblenden. |
| `showDesc` | `1` | `1` = Kurzbeschreibung anzeigen, `0` = ausblenden. |

#### Beispiele

```text
[comyli_query_list category="Finanzen" layout="grid"]

[comyli_queries title="Abteilungsberichte" layout="list" showCount="1"]
```

---

## 5. Programmatische PHP-Einbindung (`comyli_render`)

Für Theme-Templates oder eigene Fach-Plugins steht die globale Hilfsfunktion `comyli_render(array $args): string` zur Verfügung:

```php
if (function_exists('comyli_render')) {
    echo comyli_render([
        'query'     => 'belegung_unterkuenfte',
        'params'    => [
            'kommune_id' => 12,
            'status'     => 'aktiv',
        ],
        'layout'    => 'clean',
        'limit'     => 50,
        'filter'    => true,
        'csvExport' => true,
        'edit'      => false,
    ]);
}
```

---

## 6. SQL-Abfragen, Parameter & Kontext-Token

SQL-Abfragen werden vollständig in der WordPress-Datenbank über den QueryManager (**CoMyLi Tabellen → Abfragen**, Custom Post Type `comyli_query`) verwaltet. Dies bietet integrierte Syntaxhervorhebung (CodeJar & Prism), WordPress-Revisionen, Zuweisung zu Datenbankverbindungen und feingranulare Berechtigungsprüfungen (Capabilities).

### Dynamische Platzhalter & Kontext-Token

In SQL-Abfragen können folgende Platzhalter verwendet werden. **Wichtig:** Platzhalter werden automatisch typsicher maskiert und bei Strings in einfache Anführungszeichen (`'...'`) eingeschlossen – sie dürfen im SQL-Code daher **nicht** zusätzlich in Anführungszeichen gesetzt werden.

| Token / Platzhalter | Beschreibung | Beispiel in SQL |
| :--- | :--- | :--- |
| `{current_user_id}` | Numerische WordPress User-ID des eingeloggten Nutzers (`0` bei Gästen). | `WHERE bearbeiter_id = {current_user_id}` |
| `{current_user_name}`| `user_login` des eingeloggten Nutzers (automatisch als SQL-String gequotet). | `WHERE createdUser = {current_user_name}` |
| `{get:param_name}` | Liest `$_GET['param_name']` aus der URL und maskiert den Wert typsicher. | `WHERE abteilung = {get:abt}` |
| `:param_name` | Benannter Parameter aus `params` (Shortcode/Block/PHP) oder Fallback `$_GET['param_name']`. | `WHERE status = :status AND jahr = :jahr` |
| `[QRY]name[/QRY]` | Bettet eine andere Abfrage `name` als Subquery `(SELECT ...) AS name` ein. | `SELECT * FROM [QRY]basis_abfrage[/QRY] WHERE ...` |

---

## 7. Externe Datenbankverbindungen (`ConnectionManager`)

CoMyLi kann sowohl Tabellen der lokalen WordPress-Datenbank als auch Tabellen auf externen MySQL-/MariaDB-Servern verwalten:

1. Unter **CoMyLi Tabellen → Erstellen** die Option **„Lokale WordPress-Datenbank verwenden“** deaktivieren.
2. **DB Server (Host)** (optional mit Port, z. B. `10.0.0.15:3307`), **Datenbank (DB Name)**, **DB Benutzer** und **DB Passwort** eintragen.
3. In SQL-Abfragen (**CoMyLi Tabellen → Abfragen**) kann im Feld **„Datenquelle / Datenbank“** jede definierte CoMyLi-Tabelle ausgewählt werden – die Abfrage nutzt dann automatisch deren externe Datenbankverbindung (über Blöcke, Shortcodes, REST-API und Cron-Archivierung hinweg).

---

## 8. Zeitgesteuerte Abfragen & CSV-Archivierung (`QueryScheduler`)

Jede im Abfrage-Editor (`comyli_query`) angelegte Abfrage kann automatisch im Hintergrund ausgeführt und als CSV-Datei (UTF-8 mit BOM, Semikolon-getrennt für Microsoft Excel) archiviert werden:

* **Intervalle:** Deaktiviert, Täglich (`daily`), Wöchentlich (`weekly`), Monatlich (`monthly`, 30 Tage).
* **Manuelle Sofort-Archivierung:** Über den Button **„▶ Jetzt manuell ausführen & archivieren“** direkt in der Abfrage-Bearbeitungsansicht.
* **Speicherort & Schutz:** `wp-content/uploads/ha-comyli-archives/{query_id}/` (automatisch durch `.htaccess` und `index.php` gegen direkten Web-Zugriff geschützt; die letzten 50 Archive pro Abfrage werden vorgehalten).
* **Download:** Berechtigte Nutzer und Administratoren können Archive über den gesicherten Backend-Endpunkt herunterladen oder löschen.

---

## 9. Berechtigungssystem & Capabilities (`Permissions`)

Der Zugriff auf Abfragen und Tabellen wird zentral über die Klasse `ha\CoMyLi\Permissions` (bzw. den globalen Alias `Comyli_Permissions`) gesteuert:

* Administratoren (`manage_options`) haben immer Vollzugriff.
* **Abfragen (`comyli_query`):** Im Abfrage-Editor kann über ein Select2-Dropdown jede im System existierende WordPress-Capability (oder eine frei definierte Capability) zugewiesen werden. Ohne spezielle Vorgabe gilt `read`.
* **Tabellen:** Standardmäßig erfordert das Anzeigen einer Tabelle `read` und das Bearbeiten `edit_posts`. Beides lässt sich über WordPress-Filter feingranular pro Tabelle anpassen (siehe Abschnitt 12).

---

## 10. Headless REST-API

CoMyLi stellt unter dem Namespace `comyli/v1` REST-API-Endpunkte bereit. Authentifizierung erfolgt über WordPress-Cookies + `X-WP-Nonce`-Header oder Application Passwords.

### 10.1 Abfrage ausführen
* **Route:** `GET /wp-json/comyli/v1/query/{name}`
* **Berechtigung:** `Permissions::can_view_query($name)`
* **Query-Parameter:**
  * `page` (Standard: `1`)
  * `limit` (Standard: `25`, Max: `500`)
  * Beliebige weitere Parameter werden automatisch an `:param_name`-Platzhalter im SQL gebunden.
* **Beispiel-Antwort:**
  ```json
  {
    "query": "offene-rechnungen",
    "total_rows": 42,
    "page": 1,
    "limit": 25,
    "columns": [
      { "name": "id", "label": "Id", "type": "int" },
      { "name": "betrag", "label": "Betrag", "type": "float" }
    ],
    "data": [
      { "id": "101", "betrag": "250.00" }
    ]
  }
  ```

### 10.2 Tabellen-CRUD (Lokal & Externe DB)
* **Route:** `/wp-json/comyli/v1/table/{name}` (`{name}` kann ein Tabellenname oder der Slug eines `comyli`-Tabellen-Posts sein)
* **Methoden:**
  * `GET /wp-json/comyli/v1/table/{name}?page=1&limit=25` – Liest Zeilen und Spalten-Metadaten (`Permissions::can_view_table`).
  * `POST /wp-json/comyli/v1/table/{name}` – Fügt einen neuen Datensatz per JSON-Body ein (`Permissions::can_edit_table`).
  * `PUT /wp-json/comyli/v1/table/{name}?id=123` – Aktualisiert den Datensatz anhand des Primärschlüssels (`Permissions::can_edit_table`).
  * `DELETE /wp-json/comyli/v1/table/{name}?id=123` – Löscht den Datensatz anhand des Primärschlüssels (`Permissions::can_edit_table`).

### 10.3 Interne Synchronisations-Endpunkte
* `GET /wp-json/comyli/v1/heartbeat` – Prüft den neuesten `lastChanged`/`lstChanged`-Zeitstempel einer Tabelle.
* `POST /wp-json/comyli/v1/syncState` – Liefert geänderte/neue/gelöschte Zeilen für LiveSync.
* `POST /wp-json/comyli/v1/save` – Verarbeitet Inline-Edits und Zeilen-Löschungen aus dem Tabellen-Frontend.

---

## 11. Custom CSS, Custom JS & LiveSync

* **Custom CSS & Custom JS:** Sowohl in jeder Tabellendefinition (`comyli`) als auch in jeder Abfrage (`comyli_query`) können im Backend eigenes CSS und JavaScript mit Syntax-Highlighting hinterlegt werden. Beim Rendern der Tabelle/Abfrage im Frontend werden diese automatisch injiziert. Bei Abfragen werden CSS- und JS-Änderungen zudem in den WordPress-Revisionen versioniert.
* **Spaltenbeschreibungen (Tooltips):** Kommentare (`COMMENT`), die im MySQL-Spaltenschema hinterlegt sind, werden automatisch als Info-Badge (`ⓘ`) mit Tooltip in den Spaltenköpfen dargestellt.
* **LiveSync:** Besitzt eine editierbare Tabelle einen Primärschlüssel sowie eine Spalte `lastChanged` oder `lstChanged` (`TIMESTAMP` / `DATETIME`), aktiviert CoMyLi automatisch die LiveSync-Steuerung (`Auto` / `An` / `Aus`), hebt fremde Änderungen grün hervor und warnt bei gleichzeitiger Bearbeitung derselben Zeile (Optimistic Locking).

---

## 12. Erweiterte Tabellen-Konfiguration (JSON) & Spalten-CSS (`data-col`)

Ab Version 3.2.0 werden alle früheren hardcodierten `LegacySpecifics` durch ein rein deklaratives JSON-Konfigurationsfeld (`_comyli_advanced_config`) im Backend-Editor abgelöst. Die Auflösung erfolgt über die sichere Engine `ConfigResolver` **vollständig ohne `eval()`**.

### 12.1 Spaltenbasiertes CSS mit `data-col`
Alle Zellen (`.td`) in Kopfzeile, Filterzeile, Datenzeilen und Insert-Zeile tragen automatisch das Attribut `data-col="<Spaltenname>"` (sowie `data-col="_actions"` für die Aktionsspalte).

**Vorteil gegenüber `:nth-of-type(...)`:**
Wenn für bestimmte Benutzerrollen Spalten über `visible_columns` (`limit_cols`) ausgeblendet werden, verschiebt sich die Nummerierung der Spalten. Mit `data-col` greift das CSS immer exakt auf die richtige Spalte:

```css
/* Feste Breite für die Beschreibung */
.comyli .td[data-col="Beschreibung"] { width: 600px; }

/* Maximale Breite für kurze Kennzeichen */
.comyli .td[data-col="KI"] { max-width: 100px; }

/* Erste Spalten horizontal fixieren (Sticky Columns) */
.comyli .td[data-col="_actions"] { width: 80px; position: sticky; left: 0; z-index: 6; }
.comyli .td[data-col="Name_des_Verfahrens"] { position: sticky; left: 80px; z-index: 6; }
```

### 12.2 Sichere dynamische Tokens (ohne `eval`)
In Standardwerten (`default`), erzwungenen Werten (`forced`), Überschreibungen (`post_override`) und SQL-Abfragen können folgende Platzhalter genutzt werden:

| Token | Ersetzung | Beispiel |
| :--- | :--- | :--- |
| `{current_date}` | Heutiges Datum (`Y-m-d`) | `2026-11-10` |
| `{current_datetime}` | Aktueller Zeitstempel (`Y-m-d H:i:s`) | `2026-11-10 14:30:00` |
| `{current_year}` | Aktuelles Kalenderjahr (`Y`) | `2026` |
| `{current_user_login}` | WordPress-Benutzername des eingeloggten Nutzers | `m.mustermann` |
| `{current_user_id}` | WordPress-User-ID | `42` |
| `{current_user_fullname}`| Vollständiger Name (`Nachname, Vorname`) | `Mustermann, Max` |
| `{year_letter:2024}` | Zyklischer Buchstabe (A, B, C...) ab Startjahr | `C` (für 2026) |
| `{column:Spaltenname}` | Referenziert den bereits berechneten Wert einer anderen Spalte | `{column:Px}` |
| `{sql:SELECT ...}` | Führt eine Leseabfrage auf der Tabellenverbindung aus | Erster skalarer Ergebniswert |
| `{sql_pad4:SELECT ...}` | Wie `{sql:...}`, formatiert das Ergebnis jedoch 4-stellig mit führenden Nullen | `0042` |

### 12.3 Vollständiges All-in-One JSON-Referenzbeispiel

Das folgende JSON-Dokument demonstriert **alle** verfügbaren Konfigurationsmöglichkeiten von CoMyLi in einer einzigen, validen Vorlage:

```json
{
  "ui": {
    "stdQry": "index",
    "insert_top": true,
    "insert_bottom": false,
    "default_order": "created",
    "default_dir": "DESC",
    "auto_audit_columns": true,
    "inherit_global_defaults": true
  },
  "columns": {
    "Datum": {
      "default": "{current_date}"
    },
    "Bearbeiter": {
      "forced": "{current_user_fullname}"
    },
    "JahrCode": {
      "default": "{year_letter:2024}"
    },
    "VorgangsNr": {
      "default": "{sql_pad4:SELECT SQL_NO_CACHE IFNULL(MAX(VorgangsNr), 0) + 1 FROM tbl_vorgang WHERE JahrCode='{column:JahrCode}'}",
      "post_override": "{sql_pad4:SELECT SQL_NO_CACHE IFNULL(MAX(VorgangsNr), 0) + 1 FROM tbl_vorgang WHERE JahrCode='{column:JahrCode}'}"
    },
    "Status": {
      "default": "offen"
    },
    "Betrag": {
      "default": "0.00",
      "min": "0.00",
      "step": "0.01",
      "placeholder": "0.00 €"
    },
    "Gebuehr": {
      "default": "0.00",
      "min": "0.00",
      "max": "500.00",
      "step": "3.30"
    },
    "Aktenzeichen": {
      "pattern": "[A-Z]{2}-[0-9]{4}",
      "placeholder": "z. B. FD-2026",
      "required": true
    },
    "Durchmesser": {
      "attr": "onInput=\"umfang(this)\""
    },
    "Umfang": {
      "attr": "onInput=\"durchmesser(this)\""
    },
    "system_id": {
      "readonly": true
    }
  },
  "foreign_keys": {
    "Bezirk": {
      "source": "sql",
      "query": "SELECT * FROM tbl_bezirke",
      "mode": "input"
    },
    "Wohnort": {
      "source": "sql",
      "query": "SELECT Wohnort FROM tbl_buerger GROUP BY Wohnort",
      "mode": "suggestion"
    },
    "BewertungStamm": {
      "source": "sql",
      "query": "SELECT * FROM tbl_bewertungstamm_krone",
      "list_key": "BewertungStammKrone",
      "mode": "input"
    },
    "BewertungKrone": {
      "source": "sql",
      "query": "SELECT * FROM tbl_bewertungstamm_krone",
      "list_key": "BewertungStammKrone",
      "mode": "input"
    },
    "Fachdienst": {
      "source": "wp_option",
      "option": "ha_ask_user_for_information_fachdienst",
      "property": "fachdienst",
      "mode": "input"
    },
    "Sachbearbeiter": {
      "source": "wp_users",
      "field": "user_login",
      "include_empty": true,
      "mode": "input"
    }
  },
  "access_rules": [
    {
      "capabilities": ["stabsstelle", "edv_admin"],
      "edit": true,
      "custom_css": ".comyli .td[data-col=\"Beschreibung\"] { width: 600px; }\n.comyli .td[data-col=\"_actions\"] { width: 80px; position: sticky; left: 0; z-index: 6; }\n.comyli .td[data-col=\"Titel\"] { position: sticky; left: 80px; z-index: 6; }"
    },
    {
      "capabilities": ["fuehrungskraefte"],
      "edit": false,
      "visible_columns": [
        "VorgangsNr",
        "Datum",
        "Titel",
        "Fachdienst",
        "Betrag",
        "Status"
      ],
      "readonly_columns": ["Betrag", "Status"],
      "custom_css": ".comyli .td[data-col=\"Titel\"] { max-width: 300px; }"
    },
    {
      "capabilities": ["*"],
      "edit": false,
      "visible_columns": [
        "VorgangsNr",
        "Datum",
        "Titel",
        "Status"
      ]
    }
  ],
  "readonly_unless_capability": {
    "capability": "fachdienst_leitung",
    "columns": ["Budget", "Kostenstelle", "GenehmigtAm", "GenehmigtVon"]
  },
  "maintenance": {
    "ensure_quarterly_dates": {
      "table": "tbl_naechstekontrolldaten",
      "years_ahead": 3,
      "months": [1, 4, 7, 10],
      "day": 1
    }
  }
}
```

---

### 12.4 Detaillierte Beschreibung aller Konfigurationssektionen

#### 1. `"ui"` (Oberfläche, Sortierung & Verhalten)
* **`"stdQry"`** (`string`): Name der Standardabfrage (z. B. `"index"`), die beim ersten Laden der Tabelle ausgeführt wird, falls in der URL kein `qry`-Parameter übergeben wurde.
* **`"insert_top"`** (`boolean`): Wenn `true`, wird die Formularzeile zum Einfügen neuer Datensätze oberhalb der Datentabelle platziert.
* **`"insert_bottom"`** (`boolean`): Wenn `true`, wird die Einfügezeile unterhalb der Tabelle angezeigt.
* **`"default_order"`** (`string`): Spaltenname für die initiale Sortierung (z. B. `"created"` oder `"Datum"`), sofern keine URL-Sortierung vorliegt.
* **`"default_dir"`** (`string`): Sortierrichtung für `default_order` – entweder `"ASC"` (aufsteigend) oder `"DESC"` (absteigend).
* **`"auto_audit_columns"`** (`boolean`, Standard: `true`): Aktiviert die automatische Befüllung der Revisions- und Auditspalten:
  * `lastChangedUser`: Wird verdeckt (`valueH`) mit dem aktuellen WordPress-Benutzernamen befüllt.
  * `created`: Wird schreibgeschützt gesetzt und beim Insert serverseitig mit dem aktuellen Zeitstempel (`Y-m-d H:i:s`) überschrieben (`postOverride`).
  * `createdUser`: Schreibgeschützt und mit dem Ersteller (`user_login`) belegt.
* **`"inherit_global_defaults"`** (`boolean`, Standard: `true`): Steuert, ob globale Standardeinstellungen für Audit-Felder vererbt werden.

#### 2. `"columns"` (Spaltenspezifische Validierung, Defaults & HTML-Attribute)
Für jede Tabellenspalte (Schlüssel = Spaltenname in der Datenbank) können folgende Eigenschaften definiert werden:
* **`"default"`** (`string` / `number`): Vorbelegung beim Neuanlegen einer Zeile. Unterstützt statische Werte sowie alle sicheren Tokens (z. B. `"{current_date}"` oder `"{sql:...}"`).
* **`"forced"`** (`string` / `number`): Erzwungener Wert (`valueH`), der im Hintergrund festgesetzt wird (z. B. `"{current_user_fullname}"`).
* **`"post_override"`** (`string` / `number`): Wert, der beim Speichern serverseitig zwingend in den Insert-Query geschrieben wird (`postOverride`), z. B. um Beleg- oder Vorgangsnummern lückenlos hochzuzählen:
  `"{sql_pad4:SELECT SQL_NO_CACHE IFNULL(MAX(nr), 0) + 1 FROM meine_tabelle WHERE code='{column:code}'}"`
* **`"readonly"`** (`boolean`): Setzt das Feld auf `readonly`, sodass es im Frontend nicht editiert werden kann.
* **`"min"`** / **`"max"`** (`string` / `number`): HTML5-Eingabegrenzen (`min="0.00"` / `max="500.00"`).
* **`"step"`** (`string` / `number`): Schrittweite für Zahlenfelder (z. B. `"0.01"` für Währungen oder `"3.30"` für feste Gebühreneinheiten).
* **`"pattern"`** (`string`): Regulärer Ausdruck zur clientseitigen Validierung (z. B. `"[A-Z]{2}-[0-9]{4}"`).
* **`"placeholder"`** (`string`): Hilfetext im Eingabefeld (`placeholder="..."`).
* **`"required"`** (`boolean`): Kennzeichnet das Feld als Pflichtfeld (`required`).
* **`"attr"`** (`string`): Benutzerdefinierte HTML-Attribute für dynamische JavaScript-Berechnungen (z. B. `onInput="berechne(this)"`). Aus Sicherheitsgründen werden nur die Attribute `onInput`, `min`, `max`, `step`, `pattern`, `placeholder`, `readonly`, `required` und `style` akzeptiert.

#### 3. `"foreign_keys"` (Fremdschlüssel, Dropdowns & Vorschlagslisten)
Verbindet Spalten mit Nachschlagequellen für Auswahllisten:
* **`"source"`**: Bestimmt die Datenquelle:
  * `"sql"`: Führt eine SQL-Abfrage aus (z. B. `"SELECT * FROM tbl_bezirke"`).
  * `"wp_option"`: Liest ein in den WordPress-Optionen hinterlegtes JSON-Array aus (`"option": "meine_option"`, `"property": "feldname"`).
  * `"wp_users"`: Lädt WordPress-Benutzer (`"field": "user_login"` oder `"display_name"`, `"include_empty": true` für leere Anfangsoption).
* **`"mode"`**:
  * `"input"`: Klassisches `<select>`-Auswahldropdown.
  * `"suggestion"`: Textfeld mit `<datalist>`-Autovervollständigung (freie Eingabe weiterhin möglich).
* **`"list_key"`** (`string`, optional): Gemeinsamer Cache-Schlüssel, wenn mehrere Spalten denselben Datensatz teilen (z. B. `BewertungStamm` und `BewertungKrone`).

#### 4. `"access_rules"` (Rollenbasierte Rechte, Spaltenfilterung & Rollen-CSS)
Ein Array von Berechtigungsregeln. **Wichtig: Die Regeln werden von oben nach unten ausgewertet – die erste zutreffende Regel bestimmt das Verhalten (First-Match-Wins).**
* **`"capabilities"`** (`array`): Liste von WordPress-Capabilities oder Rollennamen (z. B. `["stabsstelle", "edv_admin"]`). Der Eintrag `["*"]` dient als Catch-All-Regel für alle übrigen angemeldeten und nicht angemeldeten Nutzer.
* **`"edit"`** (`boolean`): Erlaubt (`true`) oder verbietet (`false`) das Bearbeiten, Einfügen und Löschen von Zeilen.
* **`"visible_columns"`** (`array`): Whitelist von Spaltennamen. Alle nicht aufgeführten Spalten werden serverseitig ausgeblendet (`limit_cols`).
* **`"readonly_columns"`** (`array`): Spalten, die für diese Rolle sichtbar, aber schreibgeschützt sind.
* **`"custom_css"`** (`string`): Rollenspezifisches CSS (z. B. Spaltenbreiten oder Sticky Columns via `[data-col="..."]`). Wird beim CSV-Export automatisch ignoriert.

#### 5. `"readonly_unless_capability"` (Bedingter Schreibschutz)
Sperrt bestimmte sensible Spalten für alle Nutzer, es sei denn, sie besitzen eine bestimmte Berechtigung:
* **`"capability"`** (`string`): Erforderliche Capability oder Rolle (z. B. `"fachdienst_leitung"`).
* **`"columns"`** (`array`): Liste der zu sperrenden Spaltennamen (z. B. `["Budget", "Kostenstelle"]`).

#### 6. `"maintenance"` (Automatische Tabellen-Wartung)
Führt bei Aufruf der Tabelle automatische Integritäts- und Vorbereitungsroutinen aus:
* **`"ensure_quarterly_dates"`**: Generiert für Kontroll- und Prüfdatenbanken automatisch zukünftige Quartalstermine:
  * `"table"` (`string`): Zieltabelle für die Termine (z. B. `"tbl_naechstekontrolldaten"`).
  * `"years_ahead"` (`number`): Wie viele Jahre im Voraus Termine generiert werden (z. B. `3`).
  * `"months"` (`array`): Monate (z. B. `[1, 4, 7, 10]`).
  * `"day"` (`number`): Monatstag (z. B. `1` oder `15`).

---

## 13. WordPress-Filter & Entwickler-Hooks

| Filter-Hook | Parameter | Beschreibung |
| :--- | :--- | :--- |
| `comyli_config` | `array $config, string $stdTbl` | Globale Anpassung der CoMyLi-Konfiguration vor dem Rendern. |
| `comyli_config_{$table}` | `array $config` | Tabellenspezifische Konfiguration (z. B. `readonly`, `foreignKey`, `value`, `postOverride`). |
| `comyli_render_cell` | `string $cellHtml, string $colName, mixed $val, array $row, string $table` | Anpassung des gerenderten HTMLs einer beliebigen Tabellenzelle. |
| `comyli_render_column_{$colName}` | `string $cellHtml, mixed $val, array $row, string $table` | Gezielter Formatter für eine bestimmte Spalte (z. B. Links, Status-Badges). |
| `comyli_cap_query_{$queryName}` | `string $defaultCap` (`'read'`) | Überschreibt die erforderliche Capability für eine Abfrage. |
| `comyli_cap_table_view_{$table}`| `string $defaultCap` (`'read'`) | Überschreibt die erforderliche Lese-Capability für eine Tabelle. |
| `comyli_cap_table_edit_{$table}`| `string $defaultCap` (`'edit_posts'`) | Überschreibt die erforderliche Schreib-Capability für eine Tabelle. |
| `ha_github_update_token` | `string $token, string $slug` | Übergibt optional ein GitHub Personal Access Token für den Plugin-Updater. |

### Beispiel: Spalten-Formatter per Hook

```php
add_filter('comyli_render_column_status', function (string $cellHtml, $val, array $row, string $table): string {
    if ($val === 'aktiv') {
        return '<div class="td"><span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:12px;">Aktiv</span></div>';
    }
    return $cellHtml;
}, 10, 4);
```
