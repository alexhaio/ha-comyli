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
12. [WordPress-Filter & Entwickler-Hooks](#12-wordpress-filter--entwickler-hooks)

---

## 1. Hauptfunktionen

* **4 Gutenberg-Blöcke:** Interaktive Datentabellen (`ha-comyli/query-table`), Chart.js-Diagramme (`ha-comyli/chart`), KPI-Kennzahlenkacheln (`ha-comyli/kpi-card`) und Berichtsübersichten (`ha-comyli/query-list`).
* **QueryManager (CPT `comyli_query`):** WordPress-Backend-Editor für SQL-Abfragen mit Syntax-Highlighting (CodeJar & Prism SQL), WordPress-Revisionen (Diff-Ansicht), Kategorien und rollenbasierter Zugriffskontrolle.
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
3. **Abfragen anlegen:** Unter **CoMyLi Tabellen → Abfragen** eine neue SQL-Abfrage im Backend-Editor anlegen oder `.sql`-Dateien im persistenten Verzeichnis `wp-content/comyli-queries/` hinterlegen.
4. **Einbinden:** Über Gutenberg-Blöcke, Shortcodes oder direkt per PHP (`comyli_render()`).

---

## 3. Gutenberg-Blöcke (Referenz)

Alle Blöcke befinden sich im Block-Editor in der Kategorie **Widgets** und bieten eine Live-Vorschau via `ServerSideRender`.

### 3.1 `ha-comyli/query-table` (CoMyLi Tabelle)

Bettet eine gespeicherte SQL-Abfrage oder eine Datenbanktabelle als interaktive Tabelle ein.

| Attribut | Typ | Standard | Beschreibung |
| :--- | :--- | :--- | :--- |
| `query` | `string` | `""` | Slug/Name der SQL-Abfrage (CPT `comyli_query` oder `.sql`-Datei). |
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

SQL-Abfragen können entweder im Backend unter **CoMyLi Tabellen → Abfragen** (Custom Post Type `comyli_query`) oder als `.sql`-Datei im Verzeichnis `wp-content/comyli-queries/` gespeichert werden.

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

## 12. WordPress-Filter & Entwickler-Hooks

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
