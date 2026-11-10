=== CoMyLi - Configurable-MySQL-Light-Interface ===
Contributors: alexhaio, ha
Tags: mysql, admin, table, reporting, crud, rest-api, gutenberg, chart, cron, export
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 3.4.0
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
* **Custom CSS & JavaScript Editors:** Dedicated syntax-highlighted CSS and JS editors per table (`comyli`) and per query (`comyli_query`) with WordPress revision tracking and automatic frontend injection.
* **Custom Column Renderers:** Powerful hook system (`apply_filters('comyli_render_cell')` and `apply_filters('comyli_render_column_{name}')`).
* **LiveSync (Auto / An / Aus):** Real-time optimistic concurrency synchronization with multi-user collision protection.
* **Declarative JSON Table Configuration (ConfigResolver):** Define column defaults, forced audit values, dynamic token replacements, foreign keys, role-based access rules, and data-col CSS styling via clean JSON in the WordPress backend without eval().
* **Auto-Updates via GitHub:** Native integration with Plugin-Update-Checker (PUC v5).

= Gutenberg Blocks =

1. **CoMyLi Tabelle (`ha-comyli/query-table`):**
   * Attributes: `query` (string), `table` (string), `params` (string, e.g. `id=123&status=aktiv`), `layout` (`clean` | `default`), `limit` (number, 5–200), `filter` (boolean), `csvExport` (boolean), `edit` (boolean).
2. **CoMyLi Diagramm (`ha-comyli/chart`):**
   * Attributes: `query` (string), `table` (string), `chartType` (`bar` | `line` | `pie` | `doughnut`), `labelColumn` (string), `valueColumn` (string), `title` (string), `height` (number, 200–800).
3. **CoMyLi KPI-Kachel (`ha-comyli/kpi-card`):**
   * Attributes: `query` (string), `table` (string), `title` (string), `unit` (string), `trend` (string), `trendDirection` (`up` | `down` | `neutral`), `icon` (string), `color` (hex string).
4. **CoMyLi Abfrage-Liste (`ha-comyli/query-list`):**
   * Attributes: `title` (string), `category` (string), `layout` (`grid` | `list`), `showCount` (boolean), `showDesc` (boolean).

= Shortcodes =

* **`[comyli_table]` / `[comyli]` / `[ha-comyli]`:**
  * `id`: Post ID of a configured `comyli` table post.
  * `query` / `qry`: Slug, title, or ID of a `comyli_query` post.
  * `table` / `tables`: Database table name or `comyli` post slug.
  * `params`: Query string (`key=val&key2=val2`) or JSON string for `:param_name` placeholders.
  * `layout`: `clean` (default, embedded view without sidebar) or `default` (full UI with sidebar).
  * `limit`: Rows per page (default: `25`).
  * `filter`: `1` (show search & column filters) or `0` (hide).
  * `csvExport`: `1` (show CSV export button) or `0` (hide).
  * `edit`: `1` (enable inline editing/insert/delete if user has `can_edit_table` capability) or `0` (read-only).
  * Example: `[comyli_table query="offene-posten" params="jahr=2026" layout="clean" limit="50" csvExport="1"]`

* **`[comyli_query_list]` / `[comyli_queries]`:**
  * Renders an interactive overview of all queries accessible to the current user.
  * Attributes: `title` (string), `category` (category slug or name), `layout` (`grid` | `list`), `showCount` (`1` | `0`), `showDesc` (`1` | `0`).
  * Example: `[comyli_query_list category="Controlling" layout="grid"]`

= Dynamic SQL Parameters & Context Tokens =

Note: Placeholders are automatically escaped and quoted as SQL literals where appropriate; do not wrap them in single quotes in your SQL code.

* `{current_user_id}`: Current WordPress user ID (integer, `0` for guests).
* `{current_user_name}`: Current WordPress `user_login` (quoted SQL string).
* `{get:param_name}`: Reads `$_GET['param_name']` from the URL and escapes it safely.
* `:param_name`: Binds values passed via `params` attribute or `$_GET['param_name']`.
* `[QRY]sub_query_name[/QRY]`: Embeds another query as a subquery `(SELECT ...) AS sub_query_name`.

= Programmatic PHP Rendering =

`echo comyli_render(['query' => 'my_query', 'params' => ['status' => 'aktiv'], 'layout' => 'clean', 'limit' => 25, 'filter' => true, 'csvExport' => false, 'edit' => false]);`

= Headless REST API & Developer Hooks =

* `GET /wp-json/comyli/v1/query/{name}?page=1&limit=25` – Executes a query and returns columns, pagination metadata, and rows.
* `GET|POST|PUT|DELETE /wp-json/comyli/v1/table/{name}` – Full CRUD operations on local or external database tables.
* Filters: `comyli_config`, `comyli_config_{$table}`, `comyli_render_cell`, `comyli_render_column_{$colName}`, `comyli_cap_query_{$queryName}`, `comyli_cap_table_view_{$table}`, `comyli_cap_table_edit_{$table}`.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ha-comyli` directory, or install via GitHub Updater.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Define MySQL database tables under 'CoMyLi Tabellen' or create queries in the 'Abfragen (Queries)' editor.
4. Add tables or queries via Gutenberg blocks, shortcode `[comyli_table query="MyQuery" layout="clean"]`, or in PHP via `echo comyli_render(['query' => 'MyQuery']);`.

== Changelog ==

= 3.4.0 =
* Major Architecture: Completely retired and removed all legacy file-based query handling, directory scanning, and server filesystem fallbacks in favor of 100% database-stored QueryManager (CPT `comyli_query`) queries.
* Cleanup: Removed file query editor metabox, `.sql` saving/deleting handlers, and filesystem creation routines in `PostType`.
* Cleanup: Removed frontend query modal and CodeJar scripts in `sql-admin/index.php`.
* Cleanup: Removed file migration buttons, handlers, and directory scan helpers (`get_default_queries_dir`, `resolve_queries_dir`, `find_sql_files`, `ensure_queries_dir`, `migrate_file_queries`) from `QueryManager`.
* Performance: Streamlined Gutenberg block discovery and query lookup by eliminating filesystem I/O across blocks, shortcodes, REST API, and AJAX.

= 3.3.1 =
* Fixed: Resolved form action collision in QueryManager editor where nested scheduler forms overrode WordPress post action, causing redirection to the posts overview without saving query changes.
* Improved: Replaced nested scheduler action forms with direct nonce-verified admin URLs for manual archiving and archive file deletion.
* Improved: Direct SQL post content updates via $wpdb->update to prevent re-entrant post update hooks and recursion during query saves.

= 3.3.0 =
* Added: One-click SQL Query Importer to migrate file-based `.sql` queries into database-stored `comyli_query` posts (available in the query list, tables overview, and individual table editors).
* Improved: Query resolution priority now checks database-stored queries before disk files, with table-level ID scoping and original file slug fallback (`_comyli_original_slug`).
* Improved: Sidebar query list deduplication prevents duplicate entries when a query exists both in the database and as a legacy file.
* Cleanup: Fully removed legacy fallback presets and obsolete configuration migration handlers.

= 3.2.1 =
* Fixed: Self-contained `base64urlEncode` helper in `ConfigResolver` to prevent fatal error when resolving table configs before `sql-admin/index.php` is loaded.
* Fixed: Defensive `wp_get_current_user()` user object validation in `ConfigResolver` audit fields and token resolution.

= 3.2.0 =
* Major Architecture: Completely retired and removed `LegacySpecifics.php` in favor of declarative JSON table configurations evaluated via `ConfigResolver`.
* Security & Clean Code: Zero `eval()` usage; all dynamic behaviors (dates, user logins, year letters, and scalar queries) are resolved through safe, strictly validated tokens (`{current_date}`, `{current_user_fullname}`, `{year_letter:2024}`, `{column:Col}`, `{sql:...}`, `{sql_pad4:...}`).
* CSS & Layout: Added `data-col="<column>"` attributes to all header, filter, action, data, and insert table cells, allowing reliable column styling independent of shifting `:nth-of-type(...)` indices when columns are hidden by user roles.
* Role-Based Access: Introduced declarative `access_rules` supporting multi-capability matching, read-only permissions, column whitelisting (`visible_columns` / `limit_cols`), and role-dependent inline `custom_css`.
* Backend UI: Added a CodeJar-powered JSON configuration editor metabox with real-time JSON syntax validation and inline documentation for table configurations.
* Migration: Updated one-click migration routine (`migrateAllTables()`) to safely populate `_comyli_advanced_config` and queries paths for all legacy tables without disrupting existing functionality.

= 3.1.6 =
* Added: Comprehensive `README.md` and expanded `readme.txt` documentation covering all Gutenberg blocks, shortcodes, dynamic parameters, REST API endpoints, and developer hooks.
* Fixed: Implemented `LegacySpecifics::migrateConfigs()` with nonce protection to prevent a fatal error when migrating legacy table configurations.
* Fixed: Nonce mismatch when deleting CSV archives other than the most recent entry in `QueryManager` / `QueryScheduler`.
* Fixed: Table event bindings (`COMYLI.initTable()` and bulk selection/deletion) now properly re-initialize after AJAX filtering, sorting, and pagination.
* Fixed: Automatic removal of trailing semicolons in SQL queries to prevent syntax errors in subquery wrappers.
* Fixed: Added external database support via `ConnectionManager` and prepared statements to the headless `/wp-json/comyli/v1/table/{name}` REST endpoint.
* Fixed: `base64url_encode` and `base64url_decode` global regex replacements and Modulo-4 padding in frontend JavaScript, and scoped column resize handles strictly to table headers (`#colNames > .td`).

= 3.1.5 =
* Improved: Persistent query storage moved to `wp-content/comyli-queries/` by default, protecting custom queries from being wiped during plugin updates.
* Fixed: Canonical query path resolution supporting relative, absolute, and Windows/Unix directory formats across admin, shortcode, AJAX, and REST API.
* Fixed: Recursive directory discovery and case-insensitive resolution for queries located in subfolders.
* Added: Automatic directory creation and security hardening (`.htaccess` and `index.php`) preventing public web access to raw `.sql` files.

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
