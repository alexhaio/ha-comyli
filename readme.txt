=== CoMyLi - Configurable-MySQL-Light-Interface ===
Contributors: mystryx1337, ha
Tags: mysql, admin, table, reporting, crud, rest-api, gutenberg, chart, cron, export
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 3.1.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Universal block-based MySQL database table viewer, reporting and CRUD engine for WordPress portals and extensions.

== Description ==

CoMyLi (Configurable MySQL Light Interface) transforms MySQL databases and SQL queries into interactive, responsive frontend tables, charts, and reporting dashboards.

= Features =

* **Gutenberg Blocks:** Drag-and-drop data tables (`ha-comyli/query-table`), interactive Chart.js diagrams (`ha-comyli/chart`), live KPI metric tiles (`ha-comyli/kpi-card`), and report overviews (`ha-comyli/query-list`).
* **QueryManager (CPT `comyli_query`):** WordPress backend editor for reusable SQL queries with syntax highlighting, parameter bindings, category filters, and capability-based access control.
* **External Database Connection Manager:** Map queries and tables to external MySQL database servers or the local WordPress database with automatic credential resolution.
* **Scheduled Queries & CSV Archiving:** Recurring WP-Cron query execution (daily, weekly, monthly) with automatic server-side CSV archiving and secure downloads.
* **Dynamic System Capabilities:** Full integration with WordPress roles and capabilities via Select2 search, eliminating hardcoded permission strings.
* **Clean & Embedded Mode:** Seamless integration into modal windows, tabs, and dashboard cards (`layout="clean"` or `comyli_render()`).
* **Dynamic Context Parameters:** Parameterized SQL queries (`:parameter_name`) with automatic token resolution (`{current_user_id}`, `{current_user_name}`, `{get:param}`).
* **Headless REST API:** Modern JSON endpoints for SPA/Vue/React applications (`/wp-json/comyli/v1/query/{name}` and `/wp-json/comyli/v1/table/{name}`).
* **Clean CSV Export:** Header-safe, buffer-cleared CSV streaming with UTF-8 BOM for Microsoft Excel.
* **Custom Column Renderers:** Powerful hook system (`apply_filters('comyli_render_cell')` and `apply_filters('comyli_render_column_{name}')`).
* **LiveSync (Auto / An / Aus):** Real-time optimistic concurrency synchronization with multi-user collision protection.
* **Auto-Updates via GitHub:** Native integration with Plugin-Update-Checker (PUC v5).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ha-comyli` directory, or install via GitHub Updater.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Define MySQL database tables under 'CoMyLi Tabellen' or create queries in the 'Abfragen (Queries)' editor.
4. Add tables or queries via Gutenberg blocks, shortcode `[comyli_table query="MyQuery" layout="clean"]`, or in PHP via `echo comyli_render(['query' => 'MyQuery']);`.

== Changelog ==

= 3.1.4 =
* Fixed: Volltextsuche input field decoupled from column 2 width via CSS absolute overlay (placeholder and query remain fully visible without forcing column expansion).
* Fixed: Trailing single quote on last ENUM values in filter dropdown and edit mode (`aktiv, inaktiv`).
* Added: Column descriptions (Comments) from database schema restored with circular info badges (`ⓘ`) in table headers and hover tooltips.

= 3.1.3 =
* Added: Custom JavaScript Editor in Table Definition (`comyli`) with syntax highlighting and automatic frontend injection.
* Added: Custom JavaScript Editor in Query Editor (`comyli_query`) with syntax highlighting, automatic frontend injection, and full WordPress revision tracking.

= 3.1.2 =
* Fixed: Accurate database source detection and display in the "Alle Tabellen" admin column (correctly resolving external host vs. local WordPress database).
* Added: Custom CSS Editor in Table Definition (`comyli`) with syntax highlighting and automatic frontend injection.
* Added: Custom CSS Editor in Query Editor (`comyli_query`) with syntax highlighting, automatic frontend injection, and full WordPress revision tracking.

= 3.1.1 =
* Improved: Descriptive CSV filenames taking direct reference to table and query names (e.g. `tabelle_{table}_abfrage_{query}_{date}.csv`).
* Added: Full WordPress revisions support in QueryManager (`comyli_query`) with syntax diff viewer for SQL queries and metadata restoration.
* Improved: Replaced custom category text field with standard WordPress post categories (`category` taxonomy) with sidebar selection and admin list filters.

= 3.1.0 =
* Added: QueryManager Custom Post Type (`comyli_query`) with syntax-highlighted SQL editor and capability protection.
* Added: Gutenberg Block `ha-comyli/query-list` and shortcode `[comyli_query_list]` for dashboard report listings.
* Added: Central `ConnectionManager` with automatic credential resolution for external databases across blocks, shortcodes, and REST API.
* Added: Database connection reuse in QueryManager to execute queries directly against external table databases.
* Added: Dynamic WordPress capability selection via Select2 with custom tag creation.
* Added: `QueryScheduler` with automated WP-Cron execution (daily, weekly, monthly), CSV generation, and download management.
* Fixed: Output buffering in CSV export preventing `<script>` and `<style>` tags from corrupting exported CSV files.
* Fixed: External database connection fallback to WordPress DB when displaying tables in the frontend.
* Removed: Hardcoded capability array and isolated local DB checkbox in QueryManager.
* Maintenance: Departmental query files removed from public repository; added `.gitignore` rules for query directories.

= 3.0.0 =
* Added: Gutenberg Blocks for Abfrage-Tabellen (`ha-comyli/query-table`), Chart.js-Diagramme (`ha-comyli/chart`), and KPI-Kacheln (`ha-comyli/kpi-card`).
* Added: Clean / Embedded Layout (`layout="clean"`) and programmatic PHP helper `comyli_render()`.
* Added: Dynamic Context Parameters with safe escaping (`:param_name`, `{current_user_id}`, `{current_user_name}`, `{get:param}`).
* Added: Headless REST-API Endpoints (`/wp-json/comyli/v1/query/{name}`, `/wp-json/comyli/v1/table/{name}`).
* Added: Custom Column Renderers and Formatter Hooks (`comyli_render_cell`, `comyli_render_column_{name}`).
* Added: Granular WordPress Capability Matrix (`Permissions` / `Comyli_Permissions`).
* Fixed: Optimistic concurrency locking false-positives and LiveSync multi-user synchronization.
* Removed: Legacy Dark Mode.
