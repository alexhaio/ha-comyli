<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Gutenberg Core Blocks Registration and Rendering.
 *
 * Implements:
 * - ha-comyli/query-table
 * - ha-comyli/chart
 * - ha-comyli/kpi-card
 * - ha-comyli/query-list
 */
class Blocks {
    private Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        add_action('init', [$this, 'registerBlockTypes']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
    }

    /**
     * Registers the Gutenberg block types and their server-side render callbacks.
     */
    public function registerBlockTypes(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        // 1. ha-comyli/query-table
        register_block_type('ha-comyli/query-table', [
            'api_version'     => 2,
            'editor_script'   => 'ha-comyli-blocks-js',
            'editor_style'    => 'ha-comyli-blocks-css',
            'render_callback' => [$this, 'renderQueryTable'],
            'attributes'      => [
                'query'     => ['type' => 'string', 'default' => ''],
                'table'     => ['type' => 'string', 'default' => ''],
                'layout'    => ['type' => 'string', 'default' => 'clean'],
                'filter'    => ['type' => 'boolean', 'default' => true],
                'csvExport' => ['type' => 'boolean', 'default' => false],
                'edit'      => ['type' => 'boolean', 'default' => false],
                'limit'     => ['type' => 'number', 'default' => 25],
                'params'    => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // 2. ha-comyli/chart
        register_block_type('ha-comyli/chart', [
            'api_version'     => 2,
            'editor_script'   => 'ha-comyli-blocks-js',
            'editor_style'    => 'ha-comyli-blocks-css',
            'render_callback' => [$this, 'renderChart'],
            'attributes'      => [
                'query'       => ['type' => 'string', 'default' => ''],
                'table'       => ['type' => 'string', 'default' => ''],
                'chartType'   => ['type' => 'string', 'default' => 'bar'],
                'labelColumn' => ['type' => 'string', 'default' => ''],
                'valueColumn' => ['type' => 'string', 'default' => ''],
                'title'       => ['type' => 'string', 'default' => ''],
                'height'      => ['type' => 'number', 'default' => 350],
                'params'      => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // 3. ha-comyli/kpi-card
        register_block_type('ha-comyli/kpi-card', [
            'api_version'     => 2,
            'editor_script'   => 'ha-comyli-blocks-js',
            'editor_style'    => 'ha-comyli-blocks-css',
            'render_callback' => [$this, 'renderKpiCard'],
            'attributes'      => [
                'query'          => ['type' => 'string', 'default' => ''],
                'table'          => ['type' => 'string', 'default' => ''],
                'title'          => ['type' => 'string', 'default' => 'Gesamtanzahl'],
                'unit'           => ['type' => 'string', 'default' => ''],
                'trend'          => ['type' => 'string', 'default' => ''],
                'trendDirection' => ['type' => 'string', 'default' => 'up'],
                'icon'           => ['type' => 'string', 'default' => '📊'],
                'color'          => ['type' => 'string', 'default' => '#3b82f6'],
                'params'         => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // 4. ha-comyli/query-list
        register_block_type('ha-comyli/query-list', [
            'api_version'     => 2,
            'editor_script'   => 'ha-comyli-blocks-js',
            'editor_style'    => 'ha-comyli-blocks-css',
            'render_callback' => [$this, 'renderQueryList'],
            'attributes'      => [
                'title'     => ['type' => 'string', 'default' => 'Verfügbare Auswertungen & Berichte'],
                'category'  => ['type' => 'string', 'default' => ''],
                'layout'    => ['type' => 'string', 'default' => 'grid'],
                'showCount' => ['type' => 'boolean', 'default' => true],
                'showDesc'  => ['type' => 'boolean', 'default' => true],
            ],
        ]);
    }

    /**
     * Enqueues editor assets and passes query/table discovery lists to the block editor.
     */
    public function enqueueBlockEditorAssets(): void {
        $url = $this->config->plugin['url'];

        wp_register_script(
            'ha-comyli-blocks-js',
            $url . 'js/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
            '3.5.0'
        );

        wp_register_style(
            'ha-comyli-blocks-css',
            $url . 'css/blocks.css',
            [],
            '3.5.0'
        );

        // Discover CPT-defined queries (from QueryManager)
        $queryList = [];
        if (class_exists(QueryManager::class)) {
            $cptQueries = get_posts([
                'post_type'      => QueryManager::POST_TYPE,
                'post_status'    => ['publish', 'draft'],
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            foreach ($cptQueries as $cq) {
                $slug = $cq->post_name ?: sanitize_title($cq->post_title);
                $cap = get_post_meta($cq->ID, QueryManager::META_CAPABILITY, true);
                $capTag = $cap ? " [🔒 {$cap}]" : '';
                $queryList[$slug] = [
                    'value' => $slug,
                    'label' => '⚡ ' . $cq->post_title . $capTag,
                ];
            }
        }

        ksort($queryList);
        $queries = array_values($queryList);

        // Discover database tables (WordPress DB + external tables from CoMyLi post types)
        global $wpdb;
        $tables = [];
        if ($wpdb) {
            $rawTables = $wpdb->get_col("SHOW TABLES");
            if ($rawTables) {
                $tables = $rawTables;
            }
        }

        if (class_exists(ConnectionManager::class)) {
            $tableOptions = ConnectionManager::get_all_tables_options();
            foreach ($tableOptions as $to) {
                if (!empty($to['tables'])) {
                    $split = array_filter(array_map('trim', explode(',', $to['tables'])));
                    foreach ($split as $tbl) {
                        if (!in_array($tbl, $tables, true)) {
                            $tables[] = $tbl;
                        }
                    }
                }
            }
        }

        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        wp_localize_script('ha-comyli-blocks-js', 'comyliBlockData', [
            'queries' => $queries,
            'tables'  => $tables,
        ]);

        wp_enqueue_script('ha-comyli-blocks-js');
        wp_enqueue_style('ha-comyli-blocks-css');
    }

    /**
     * Render callback for ha-comyli/query-table block.
     *
     * @param array<string, mixed> $attributes
     * @return string
     */
    public function renderQueryTable(array $attributes): string {
        $query = !empty($attributes['query']) ? trim((string)$attributes['query']) : '';
        $table = !empty($attributes['table']) ? trim((string)$attributes['table']) : '';

        if (empty($query) && empty($table)) {
            return '<div class="comyli-block-placeholder"><p>Bitte in den Block-Einstellungen eine Abfrage oder Tabelle auswählen.</p></div>';
        }

        if (!empty($query) && class_exists(Permissions::class) && !Permissions::can_view_query($query)) {
            return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieser Abfrage (erforderliche Capability fehlt).</div>';
        }

        $args = [
            'query'     => $query,
            'table'     => $table,
            'layout'    => $attributes['layout'] ?? 'clean',
            'filter'    => !empty($attributes['filter']),
            'csvExport' => !empty($attributes['csvExport']),
            'edit'      => !empty($attributes['edit']),
            'limit'     => (int)($attributes['limit'] ?? 25),
            'params'    => $attributes['params'] ?? '',
        ];

        return comyli_render($args);
    }

    /**
     * Render callback for ha-comyli/chart block.
     *
     * @param array<string, mixed> $attributes
     * @return string
     */
    public function renderChart(array $attributes): string {
        Assets::ensureFrontendEnqueued($this->config);

        $query = !empty($attributes['query']) ? trim((string)$attributes['query']) : '';
        $table = !empty($attributes['table']) ? trim((string)$attributes['table']) : '';

        if (empty($query) && empty($table)) {
            return '<div class="comyli-block-placeholder"><p>Bitte eine Abfrage oder Tabelle für das Diagramm konfigurieren.</p></div>';
        }

        if (!empty($query) && class_exists(Permissions::class) && !Permissions::can_view_query($query)) {
            return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieses Diagramms.</div>';
        }

        if (empty($query) && !empty($table)) {
            if (class_exists(Permissions::class) && !Permissions::can_view_table($table)) {
                return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieser Tabelle.</div>';
            }
            $tableCfg = ConnectionManager::resolve_table_config($table, true);
            if (!$tableCfg && !current_user_can('manage_options')) {
                return '<div class="comyli-error">Die gewählte Tabelle ist nicht über CoMyLi freigegeben.</div>';
            }
        }

        require_once $this->config->plugin['path'] . '/sql-admin/index.php';

        try {
            $conn = !empty($query)
                ? ConnectionManager::get_connection_for_query($query)
                : ConnectionManager::get_connection_for_table($table);
        } catch (\Exception $e) {
            return '<div class="comyli-error">Datenbankfehler: ' . esc_html($e->getMessage()) . '</div>';
        }

        $sql = '';
        if (!empty($query)) {
            $cfgArr = $this->config->plugin;
            $cfgArr['mysqli'] = $conn;
            $cfgArr['params'] = $attributes['params'] ?? '';
            $qPost = QueryManager::find_query_post($query);
            if ($qPost) {
                $cfgArr['comyli_id'] = (int)get_post_meta($qPost->ID, QueryManager::META_TABLE_ID, true);
            }
            $configHandler = new \Comyli\Standalone\ConfigHandler($cfgArr);
            $sql = \Comyli\Standalone\FieldHandler::resolveSQL($query, $configHandler);
        } elseif (!empty($table)) {
            $safeTable = (string)preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            if ($safeTable !== '') {
                $sql = "SELECT * FROM `{$safeTable}` LIMIT 50";
            }
        }

        if (empty($sql)) {
            return '<div class="comyli-error">Abfrage konnte nicht geladen werden.</div>';
        }

        $res = @$conn->query($sql);
        if (!$res || !($res instanceof \mysqli_result)) {
            return '<div class="comyli-error">Abfragefehler: ' . esc_html($conn->error ?: 'Keine Daten gefunden') . '</div>';
        }

        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }

        if (empty($rows)) {
            return '<div class="comyli-notice">Keine Daten für Diagramm gefunden.</div>';
        }

        $labelCol = !empty($attributes['labelColumn']) ? (string)$attributes['labelColumn'] : null;
        $valCol = !empty($attributes['valueColumn']) ? (string)$attributes['valueColumn'] : null;

        $cols = array_keys($rows[0]);
        if (!$labelCol || !in_array($labelCol, $cols, true)) {
            $labelCol = $cols[0] ?? '';
        }
        if (!$valCol || !in_array($valCol, $cols, true)) {
            $valCol = $cols[1] ?? ($cols[0] ?? '');
        }

        $labels = [];
        $data = [];
        foreach ($rows as $r) {
            $labels[] = (string)($r[$labelCol] ?? '');
            $data[] = (float)($r[$valCol] ?? 0);
        }

        $chartId = 'comyli_chart_' . wp_unique_id();
        $chartType = in_array($attributes['chartType'] ?? '', ['bar', 'line', 'pie', 'doughnut'], true) ? $attributes['chartType'] : 'bar';
        $title = !empty($attributes['title']) ? esc_html((string)$attributes['title']) : '';
        $height = max(200, min(800, (int)($attributes['height'] ?? 350)));

        $colors = "['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16']";

        $jsonLabels = wp_json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP);
        $jsonData = wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP);

        // Enqueue local Chart.js bundle (GDPR-safe, no external CDN)
        wp_enqueue_script('comyli-chartjs', $this->config->plugin['url'] . 'js/chart.umd.min.js', [], '4.4.2', true);

        ob_start();
        ?>
        <div class="comyli-chart-container">
            <?php if ($title): ?>
                <h4 class="comyli-chart-title"><?php echo $title; ?></h4>
            <?php endif; ?>
            <div class="comyli-chart-canvas-wrap" style="height: <?php echo $height; ?>px;">
                <canvas id="<?php echo esc_attr($chartId); ?>"></canvas>
            </div>
            <script>
            (function() {
                function initChart() {
                    if (typeof Chart === 'undefined') {
                        setTimeout(initChart, 50);
                        return;
                    }
                    var ctx = document.getElementById('<?php echo esc_js($chartId); ?>');
                    if (!ctx) return;
                    new Chart(ctx, {
                        type: '<?php echo esc_js($chartType); ?>',
                        data: {
                            labels: <?php echo $jsonLabels; ?>,
                            datasets: [{
                                label: '<?php echo esc_js($valCol); ?>',
                                data: <?php echo $jsonData; ?>,
                                backgroundColor: <?php echo $colors; ?>,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: '<?php echo in_array($chartType, ['pie', 'doughnut'], true) ? 'true' : 'false'; ?>' === 'true'
                                }
                            }
                        }
                    });
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initChart);
                } else {
                    initChart();
                }
            })();
            </script>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Render callback for ha-comyli/kpi-card block.
     *
     * @param array<string, mixed> $attributes
     * @return string
     */
    public function renderKpiCard(array $attributes): string {
        Assets::ensureFrontendEnqueued($this->config);

        $query = !empty($attributes['query']) ? trim((string)$attributes['query']) : '';
        $table = !empty($attributes['table']) ? trim((string)$attributes['table']) : '';

        if (!empty($query) && class_exists(Permissions::class) && !Permissions::can_view_query($query)) {
            return '';
        }

        if (empty($query) && !empty($table)) {
            if (class_exists(Permissions::class) && !Permissions::can_view_table($table)) {
                return '';
            }
            if (!ConnectionManager::resolve_table_config($table, true) && !current_user_can('manage_options')) {
                return '';
            }
        }

        $val = 0;
        $sql = '';

        if (!empty($query) || !empty($table)) {
            require_once $this->config->plugin['path'] . '/sql-admin/index.php';
            try {
                $conn = !empty($query)
                    ? ConnectionManager::get_connection_for_query($query)
                    : ConnectionManager::get_connection_for_table($table);

                if (!empty($query)) {
                    $cfgArr = $this->config->plugin;
                    $cfgArr['mysqli'] = $conn;
                    $cfgArr['params'] = $attributes['params'] ?? '';
                    $qPost = QueryManager::find_query_post($query);
                    if ($qPost) {
                        $cfgArr['comyli_id'] = (int)get_post_meta($qPost->ID, QueryManager::META_TABLE_ID, true);
                    }
                    $configHandler = new \Comyli\Standalone\ConfigHandler($cfgArr);
                    $sql = \Comyli\Standalone\FieldHandler::resolveSQL($query, $configHandler);
                } else {
                    $safeTable = (string)preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                    if ($safeTable !== '') {
                        $sql = "SELECT COUNT(*) FROM `{$safeTable}`";
                    }
                }

                if (!empty($sql)) {
                    $res = @$conn->query($sql);
                    if ($res && ($res instanceof \mysqli_result)) {
                        $row = $res->fetch_row();
                        $val = $row[0] ?? 0;
                    }
                }
            } catch (\Exception $e) {
                $val = 0;
            }
        }

        $formattedVal = is_numeric($val) ? number_format_i18n((float)$val) : esc_html((string)$val);
        $title = !empty($attributes['title']) ? esc_html((string)$attributes['title']) : 'Kennzahl';
        $unit = !empty($attributes['unit']) ? esc_html((string)$attributes['unit']) : '';
        $trend = !empty($attributes['trend']) ? esc_html((string)$attributes['trend']) : '';
        $trendDir = in_array($attributes['trendDirection'] ?? '', ['up', 'down', 'neutral'], true) ? $attributes['trendDirection'] : 'up';
        $icon = !empty($attributes['icon']) ? esc_html((string)$attributes['icon']) : '📊';
        $color = !empty($attributes['color']) ? esc_attr((string)$attributes['color']) : '#3b82f6';

        ob_start();
        ?>
        <div class="comyli-kpi-card" style="border-top: 4px solid <?php echo $color; ?>;">
            <div class="comyli-kpi-header">
                <h4 class="comyli-kpi-title"><?php echo $title; ?></h4>
                <span class="comyli-kpi-icon"><?php echo $icon; ?></span>
            </div>
            <div class="comyli-kpi-body">
                <span class="comyli-kpi-value"><?php echo $formattedVal; ?></span>
                <?php if ($unit): ?>
                    <span class="comyli-kpi-unit"><?php echo $unit; ?></span>
                <?php endif; ?>
            </div>
            <?php if ($trend): ?>
                <div class="comyli-kpi-footer">
                    <span class="comyli-kpi-trend comyli-kpi-trend-<?php echo esc_attr($trendDir); ?>">
                        <?php echo $trend; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Render callback for ha-comyli/query-list block.
     * Renders a list/grid of all queries the current user has permission to see.
     *
     * @param array<string, mixed> $attributes
     * @return string
     */
    public function renderQueryList(array $attributes): string {
        return self::renderQueryListDirect($attributes, $this->config);
    }

    /**
     * Direct programmatic rendering of the query list.
     *
     * @param array<string, mixed> $attributes
     * @param Config|null $config
     * @return string
     */
    public static function renderQueryListDirect(array $attributes, ?Config $config = null): string {
        static $listCounter = 0;
        $listCounter++;
        $listId = 'comyli_list_' . $listCounter;

        Assets::ensureFrontendEnqueued($config);

        if (!class_exists(QueryManager::class)) {
            return '<div class="comyli-error">QueryManager nicht verfügbar.</div>';
        }

        $category = !empty($attributes['category']) ? trim((string)$attributes['category']) : null;
        $queries = QueryManager::get_available_queries(null, $category);

        $title = $attributes['title'] ?? 'Verfügbare Auswertungen & Berichte';
        $layout = ($attributes['layout'] ?? 'grid') === 'list' ? 'list' : 'grid';
        $showCount = !isset($attributes['showCount']) || !empty($attributes['showCount']);
        $showDesc = !isset($attributes['showDesc']) || !empty($attributes['showDesc']);

        if (empty($queries)) {
            return '<div class="comyli-notice"><p>Keine freigegebenen Abfragen oder Berichte für Ihr Benutzerkonto verfügbar.</p></div>';
        }

        $activeQuery = isset($_GET['comyli_run_qry']) ? sanitize_text_field((string)wp_unslash($_GET['comyli_run_qry'])) : '';
        $activeList = isset($_GET['comyli_list']) ? sanitize_text_field((string)wp_unslash($_GET['comyli_list'])) : '';
        $isActiveHere = ($activeQuery !== '' && ($activeList === '' || $activeList === $listId));

        ob_start();
        ?>
        <div class="comyli-query-list-wrap layout-<?php echo esc_attr($layout); ?>" id="<?php echo esc_attr($listId); ?>">
            <?php if (!empty($title)): ?>
                <div class="comyli-query-list-header">
                    <h3 class="comyli-query-list-title">
                        <?php echo esc_html((string)$title); ?>
                        <?php if ($showCount): ?>
                            <span class="comyli-count-badge"><?php echo count($queries); ?></span>
                        <?php endif; ?>
                    </h3>
                </div>
            <?php endif; ?>

            <div class="comyli-query-items comyli-query-<?php echo esc_attr($layout); ?>">
                <?php foreach ($queries as $q): ?>
                    <div class="comyli-query-card <?php echo ($isActiveHere && $activeQuery === $q['slug']) ? 'is-active' : ''; ?>">
                        <div class="comyli-query-card-header">
                            <h4 class="comyli-query-card-title"><?php echo esc_html($q['title']); ?></h4>
                            <?php if (!empty($q['category'])): ?>
                                <span class="comyli-category-pill"><?php echo esc_html($q['category']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($showDesc && !empty($q['description'])): ?>
                            <p class="comyli-query-card-desc"><?php echo esc_html($q['description']); ?></p>
                        <?php endif; ?>
                        <div class="comyli-query-card-actions">
                            <?php
                            $runUrl = add_query_arg([
                                'comyli_run_qry' => $q['slug'],
                                'comyli_list'    => $listId,
                            ]);
                            ?>
                            <a href="<?php echo esc_url($runUrl); ?>" class="comyli-btn-run">
                                📊 Daten anzeigen
                            </a>
                            <?php if (!empty($q['capability']) && current_user_can('manage_options')): ?>
                                <span class="comyli-cap-tag" title="Erforderliche Berechtigung">🔒 <?php echo esc_html($q['capability']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($isActiveHere): ?>
                <div class="comyli-active-query-output">
                    <div class="comyli-active-query-head">
                        <h4>Aktive Auswertung: <code><?php echo esc_html($activeQuery); ?></code></h4>
                        <a href="<?php echo esc_url(remove_query_arg(['comyli_run_qry', 'comyli_list'])); ?>" class="comyli-btn-close">✕ Ausblenden</a>
                    </div>
                    <?php echo Shortcode::renderDirect(['query' => $activeQuery, 'layout' => 'clean', 'filter' => true, 'csvExport' => true], $config); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
