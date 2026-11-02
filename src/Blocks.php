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
        $path = $this->config->plugin['path'];

        wp_register_script(
            'ha-comyli-blocks-js',
            $url . 'js/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
            '3.1.0'
        );

        wp_register_style(
            'ha-comyli-blocks-css',
            $url . 'css/blocks.css',
            [],
            '3.1.0'
        );

        // 1. Discover file-based queries
        $queriesDir = $path . '/queries/';
        $queryList = [];
        if (is_dir($queriesDir)) {
            $files = glob($queriesDir . '*.sql') ?: [];
            $subFiles = glob($queriesDir . '*/*.sql') ?: [];
            foreach (array_merge($files, $subFiles) as $f) {
                $base = basename($f, '.sql');
                $rel = str_replace([$queriesDir, '\\'], ['', '/'], $f);
                $queryList[$base] = [
                    'value' => $base,
                    'label' => '📁 ' . $rel,
                ];
            }
        }

        // 2. Discover CPT-defined queries (from QueryManager)
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

        // Discover database tables
        global $wpdb;
        $tables = [];
        if ($wpdb) {
            $rawTables = $wpdb->get_col("SHOW TABLES");
            if ($rawTables) {
                $tables = $rawTables;
            }
        }

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
        $query = !empty($attributes['query']) ? trim($attributes['query']) : '';
        $table = !empty($attributes['table']) ? trim($attributes['table']) : '';

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
        $query = !empty($attributes['query']) ? trim($attributes['query']) : '';
        $table = !empty($attributes['table']) ? trim($attributes['table']) : '';

        if (empty($query) && empty($table)) {
            return '<div class="comyli-block-placeholder"><p>Bitte eine Abfrage oder Tabelle für das Diagramm konfigurieren.</p></div>';
        }

        if (!empty($query) && class_exists(Permissions::class) && !Permissions::can_view_query($query)) {
            return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieses Diagramms.</div>';
        }

        global $wpdb;
        if (!$wpdb) {
            return '';
        }

        require_once $this->config->plugin['path'] . '/sql-admin/index.php';

        $sql = '';
        if (!empty($query)) {
            $cfg = $this->config->plugin;
            $cfg['queries'] = $this->config->plugin['path'] . '/queries/';
            $configHandler = new \Comyli\Standalone\ConfigHandler($cfg);
            $sql = \Comyli\Standalone\FieldHandler::resolveSQL($query, $configHandler);
        } elseif (!empty($table)) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $sql = "SELECT * FROM `{$safeTable}` LIMIT 50";
        }

        if (empty($sql)) {
            return '<div class="comyli-error">Abfrage konnte nicht geladen werden.</div>';
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)) {
            return '<div class="comyli-notice">Keine Daten für Diagramm gefunden.</div>';
        }

        $labelCol = !empty($attributes['labelColumn']) ? $attributes['labelColumn'] : null;
        $valCol = !empty($attributes['valueColumn']) ? $attributes['valueColumn'] : null;

        $cols = array_keys($rows[0]);
        if (!$labelCol || !in_array($labelCol, $cols)) {
            $labelCol = $cols[0] ?? '';
        }
        if (!$valCol || !in_array($valCol, $cols)) {
            $valCol = $cols[1] ?? ($cols[0] ?? '');
        }

        $labels = [];
        $data = [];
        foreach ($rows as $r) {
            $labels[] = (string)($r[$labelCol] ?? '');
            $data[] = (float)($r[$valCol] ?? 0);
        }

        $chartId = 'comyli_chart_' . wp_unique_id();
        $chartType = in_array($attributes['chartType'], ['bar', 'line', 'pie', 'doughnut']) ? $attributes['chartType'] : 'bar';
        $title = !empty($attributes['title']) ? esc_html($attributes['title']) : '';
        $height = max(200, min(800, (int)($attributes['height'] ?? 350)));

        $colors = "['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16']";

        $jsonLabels = json_encode($labels);
        $jsonData = json_encode($data);

        // Enqueue Chart.js
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js', [], '4.4.2', true);

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
                function init() {
                    var el = document.getElementById('<?php echo esc_js($chartId); ?>');
                    if (!el || typeof Chart === 'undefined') {
                        setTimeout(init, 150);
                        return;
                    }
                    new Chart(el, {
                        type: '<?php echo esc_js($chartType); ?>',
                        data: {
                            labels: <?php echo $jsonLabels; ?>,
                            datasets: [{
                                label: '<?php echo esc_js($valCol); ?>',
                                data: <?php echo $jsonData; ?>,
                                backgroundColor: <?php echo $colors; ?>,
                                borderColor: '<?php echo $chartType === 'line' ? '#3b82f6' : 'transparent'; ?>',
                                tension: 0.2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: <?php echo in_array($chartType, ['pie', 'doughnut']) ? 'true' : 'false'; ?>,
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render callback for ha-comyli/kpi-card block.
     *
     * @param array<string, mixed> $attributes
     * @return string
     */
    public function renderKpiCard(array $attributes): string {
        $query = !empty($attributes['query']) ? trim($attributes['query']) : '';
        $table = !empty($attributes['table']) ? trim($attributes['table']) : '';

        if (!empty($query) && class_exists(Permissions::class) && !Permissions::can_view_query($query)) {
            return '';
        }

        global $wpdb;
        $val = 0;

        if (!empty($query)) {
            require_once $this->config->plugin['path'] . '/sql-admin/index.php';
            $cfg = $this->config->plugin;
            $cfg['queries'] = $this->config->plugin['path'] . '/queries/';
            $configHandler = new \Comyli\Standalone\ConfigHandler($cfg);
            $sql = \Comyli\Standalone\FieldHandler::resolveSQL($query, $configHandler);
            if (!empty($sql) && $wpdb) {
                $val = $wpdb->get_var($sql);
            }
        } elseif (!empty($table) && $wpdb) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $val = $wpdb->get_var("SELECT COUNT(*) FROM `{$safeTable}`");
        }

        $formattedVal = is_numeric($val) ? number_format_i18n((float)$val) : esc_html((string)$val);
        $title = !empty($attributes['title']) ? esc_html($attributes['title']) : 'Kennzahl';
        $unit = !empty($attributes['unit']) ? esc_html($attributes['unit']) : '';
        $trend = !empty($attributes['trend']) ? esc_html($attributes['trend']) : '';
        $trendDir = in_array($attributes['trendDirection'] ?? '', ['up', 'down', 'neutral']) ? $attributes['trendDirection'] : 'up';
        $icon = !empty($attributes['icon']) ? esc_html($attributes['icon']) : '📊';
        $color = !empty($attributes['color']) ? esc_attr($attributes['color']) : '#3b82f6';

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
        return ob_get_clean();
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
        if (!class_exists(QueryManager::class)) {
            return '<div class="comyli-error">QueryManager nicht verfügbar.</div>';
        }

        $category = !empty($attributes['category']) ? trim($attributes['category']) : null;
        $queries = QueryManager::get_available_queries(null, $category);

        $title = $attributes['title'] ?? 'Verfügbare Auswertungen & Berichte';
        $layout = ($attributes['layout'] ?? 'grid') === 'list' ? 'list' : 'grid';
        $showCount = !isset($attributes['showCount']) || !empty($attributes['showCount']);
        $showDesc = !isset($attributes['showDesc']) || !empty($attributes['showDesc']);

        if (empty($queries)) {
            return '<div class="comyli-notice"><p>Keine freigegebenen Abfragen oder Berichte für Ihr Benutzerkonto verfügbar.</p></div>';
        }

        $activeQuery = isset($_GET['comyli_run_qry']) ? sanitize_text_field($_GET['comyli_run_qry']) : '';

        ob_start();
        ?>
        <div class="comyli-query-list-wrap layout-<?php echo esc_attr($layout); ?>">
            <?php if (!empty($title)): ?>
                <div class="comyli-query-list-header">
                    <h3 class="comyli-query-list-title">
                        <?php echo esc_html($title); ?>
                        <?php if ($showCount): ?>
                            <span class="comyli-count-badge"><?php echo count($queries); ?></span>
                        <?php endif; ?>
                    </h3>
                </div>
            <?php endif; ?>

            <div class="comyli-query-items comyli-query-<?php echo esc_attr($layout); ?>">
                <?php foreach ($queries as $q): ?>
                    <div class="comyli-query-card <?php echo ($activeQuery === $q['slug']) ? 'is-active' : ''; ?>">
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
                            $runUrl = add_query_arg('comyli_run_qry', $q['slug']);
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

            <?php if (!empty($activeQuery)): ?>
                <div class="comyli-active-query-output">
                    <div class="comyli-active-query-head">
                        <h4>Aktive Auswertung: <code><?php echo esc_html($activeQuery); ?></code></h4>
                        <a href="<?php echo esc_url(remove_query_arg('comyli_run_qry')); ?>" class="comyli-btn-close">✕ Ausblendung</a>
                    </div>
                    <?php echo Shortcode::renderDirect(['query' => $activeQuery, 'layout' => 'clean', 'filter' => true, 'csvExport' => true], $config); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
