=== CoMyLi - Configurable-MySQL-Light-Interface ===
Contributors: mystryx1337, ha
Tags: mysql, admin, table, reporting, crud, rest-api, gutenberg, chart
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 3.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Universal block-based MySQL database table viewer, reporting and CRUD engine for WordPress portals and extensions.

== Description ==

CoMyLi (Configurable MySQL Light Interface) transforms MySQL databases and SQL queries into interactive, responsive frontend tables, charts, and reporting dashboards.

= Features =

* **Gutenberg Blocks:** Drag-and-drop data tables (`ha-comyli/query-table`), interactive Chart.js diagrams (`ha-comyli/chart`), and live KPI metric tiles (`ha-comyli/kpi-card`).
* **Embedded / Clean Mode:** Seamless integration into modal windows, tabs, and dashboard cards (`layout="clean"` or `comyli_render()`).
* **Dynamic Context Parameters:** Parameterized SQL queries (`:parameter_name`) with automatic token resolution (`{current_user_id}`, `{current_user_name}`, `{get:param}`).
* **Headless REST API:** Modern JSON endpoints for SPA/Vue/React applications (`/wp-json/comyli/v1/query/{name}` and `/wp-json/comyli/v1/table/{name}`).
* **Custom Column Renderers:** Powerful hook system (`apply_filters('comyli_render_cell')` and `apply_filters('comyli_render_column_{name}')`) for custom badges and document links (e.g., 2charta eAkte).
* **Granular WordPress Permissions:** Fine-grained access control with custom capabilities (`comyli_cap_query_{name}`, `comyli_cap_table_edit_{name}`).
* **LiveSync (Auto / An / Aus):** Real-time optimistic concurrency synchronization with multi-user collision protection.
* **Auto-Updates via GitHub:** Native integration with Plugin-Update-Checker (PUC v5).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ha-comyli` directory, or install via GitHub Updater.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Place `.sql` query files in `/queries/` or use standard MySQL database tables.
4. Add tables via Gutenberg block, shortcode `[comyli_table query="MyQuery" layout="clean"]`, or in PHP via `echo comyli_render(['query' => 'MyQuery']);`.

== Changelog ==

= 3.0.0 =
* Added: Gutenberg Blocks for Abfrage-Tabellen (`ha-comyli/query-table`), Chart.js-Diagramme (`ha-comyli/chart`), and KPI-Kacheln (`ha-comyli/kpi-card`).
* Added: Clean / Embedded Layout (`layout="clean"`) and programmatic PHP helper `comyli_render()`.
* Added: Dynamic Context Parameters with safe escaping (`:param_name`, `{current_user_id}`, `{current_user_name}`, `{get:param}`).
* Added: Headless REST-API Endpoints (`/wp-json/comyli/v1/query/{name}`, `/wp-json/comyli/v1/table/{name}`).
* Added: Custom Column Renderers and Formatter Hooks (`comyli_render_cell`, `comyli_render_column_{name}`).
* Added: Granular WordPress Capability Matrix (`Permissions` / `Comyli_Permissions`).
* Fixed: Optimistic concurrency locking false-positives and LiveSync multi-user synchronization.
* Removed: Legacy Dark Mode.
