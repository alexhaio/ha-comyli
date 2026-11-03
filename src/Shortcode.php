<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Handles the frontend rendering of the CoMyLi tables via shortcode and custom post type content filter.
 */
class Shortcode {
    private Config $pluginConfig;

    /**
     * @param Config $pluginConfig The plugin configuration object.
     */
    public function __construct(Config $pluginConfig) {
        $this->pluginConfig = $pluginConfig;
    }

    /**
     * Registers WordPress hooks for shortcode processing.
     */
    public function register(): void {
        add_action('template_redirect', [$this, 'initShortcodes']);
        add_filter('widget_text', 'do_shortcode');

        // Register shortcode early for block editor and programmatic rendering
        add_shortcode('comyli', [$this, 'renderShortcode']);
        add_shortcode('comyli_table', [$this, 'renderShortcode']);
        add_shortcode('comyli_query_list', [$this, 'renderQueryListShortcode']);
        add_shortcode('comyli_queries', [$this, 'renderQueryListShortcode']);
        if ($this->pluginConfig->plugin['name'] !== 'comyli') {
            add_shortcode($this->pluginConfig->plugin['name'], [$this, 'renderShortcode']);
        }
    }

    /**
     * Initializes shortcodes and intercepts early requests (like CSV downloads).
     */
    public function initShortcodes(): void {
        if (!is_admin()) {
            add_filter('the_content', [$this, 'filterTheContent']);

            // Intercept CSV export or Image requests before headers are sent
            if ((isset($_GET['Y3N2']) && $_GET['Y3N2'] === '1') || (isset($_GET['aW1n']) && $_GET['aW1n'] === '1')) {
                $id = isset($_GET['comyli_id']) ? (int)$_GET['comyli_id'] : 0;
                if (!$id && is_singular($this->pluginConfig->plugin['type'])) {
                    $id = get_the_ID();
                }
                
                if ($id) {
                    $this->renderShortcode(['id' => $id]);
                    exit;
                }
            }
        }
    }

    public function filterTheContent(string $content): string {
        if (is_singular($this->pluginConfig->plugin['type']) && in_the_loop() && is_main_query()) {
            return $this->renderShortcode(['id' => get_the_ID()]);
        }
        return $content;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function renderShortcode(array $atts): string {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        $id = isset($atts['id']) ? (int)$atts['id'] : 0;
        $postConfig = [];
        
        if ($id > 0) {
            $resolved = ConnectionManager::resolve_table_config($id);
            if ($resolved) {
                $postConfig = $resolved;
            }
        } elseif (!empty($atts['table'])) {
            $resolved = ConnectionManager::resolve_table_config($atts['table']);
            if ($resolved) {
                $postConfig = $resolved;
                $id = $resolved['comyli_id'] ?? 0;
            }
        } elseif (!empty($atts['query'])) {
            $queryPost = QueryManager::find_query_post($atts['query']);
            if ($queryPost) {
                $tableId = (int)get_post_meta($queryPost->ID, QueryManager::META_TABLE_ID, true);
                if ($tableId > 0) {
                    $resolved = ConnectionManager::resolve_table_config($tableId);
                    if ($resolved) {
                        $postConfig = $resolved;
                    }
                }
            }
        }

        // If neither valid post ID nor table/query attribute is supplied
        if (!$id && empty($atts['table']) && empty($atts['tables']) && empty($atts['query']) && empty($atts['qry'])) {
            return '<p class="comyli-notice">Keine Tabelle oder Abfrage angegeben.</p>';
        }

        $config = array_merge($this->pluginConfig->plugin, $postConfig, $atts);
        $config['comyli_id'] = $id;

        return self::renderDirect($config, $this->pluginConfig);
    }

    /**
     * Renders a list of queries accessible to the user based on capability.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public function renderQueryListShortcode($atts): string {
        $atts = is_array($atts) ? $atts : [];
        return Blocks::renderQueryListDirect($atts, $this->pluginConfig);
    }

    /**
     * Direct programmatic rendering of a CoMyLi table or query.
     *
     * @param array<string, mixed> $args
     * @param Config|null $pluginConfig
     * @return string
     */
    public static function renderDirect(array $args, ?Config $pluginConfig = null): string {
        $pluginPath = $pluginConfig ? $pluginConfig->plugin['path'] : dirname(__DIR__);
        $pluginUrl = $pluginConfig ? $pluginConfig->plugin['url'] : plugin_dir_url(dirname(__DIR__) . '/ha-comyli.php');

        $defaults = [
            'table'     => '',
            'query'     => '',
            'params'    => [],
            'layout'    => 'clean',
            'limit'     => 25,
            'edit'      => false,
            'filter'    => true,
            'csvExport' => false,
            'queries'   => $pluginPath . '/queries/',
        ];

        // If credentials are not explicitly provided, try to resolve table or query configuration
        if (empty($args['dbserver']) && !isset($args['use_wp_db'])) {
            if (!empty($args['table'])) {
                $resolved = ConnectionManager::resolve_table_config($args['table']);
                if ($resolved) {
                    $args = array_merge($resolved, $args);
                }
            } elseif (!empty($args['query'])) {
                $queryPost = QueryManager::find_query_post($args['query']);
                if ($queryPost) {
                    $tableId = (int)get_post_meta($queryPost->ID, QueryManager::META_TABLE_ID, true);
                    if ($tableId > 0) {
                        $resolved = ConnectionManager::resolve_table_config($tableId);
                        if ($resolved) {
                            $args = array_merge($resolved, $args);
                        }
                    }
                }
            }
        }

        $config = array_merge($defaults, $args);

        // Map alias keys
        if (!empty($config['query']) && empty($config['qry'])) {
            $config['qry'] = $config['query'];
        }
        if (!empty($config['table']) && empty($config['tables'])) {
            $config['tables'] = $config['table'];
        }

        // Check Permissions
        $queryToCheck = $config['query'] ?? ($config['qry'] ?? '');
        if (!empty($queryToCheck) && class_exists(Permissions::class) && !Permissions::can_view_query($queryToCheck)) {
            return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieser Abfrage.</div>';
        }

        $tableToCheck = $config['table'] ?? '';
        if (!empty($tableToCheck) && class_exists(Permissions::class) && !Permissions::can_view_table($tableToCheck)) {
            return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieser Tabelle.</div>';
        }

        if (!empty($config['edit']) && !empty($tableToCheck) && class_exists(Permissions::class) && !Permissions::can_edit_table($tableToCheck)) {
            // Degrade to read-only if no edit capability
            $config['edit'] = false;
        }

        $config['path'] = [];
        $tablesStr = isset($config['tables']) ? (string)$config['tables'] : '';
        $config['tables'] = array_filter(array_map('trim', explode(',', $tablesStr)));
        $config['stdTbl'] = $config['tables'][0] ?? ($config['table'] ?? '');
        $config['path']['lightboxCSS'] = "";
        $config['path']['lightboxJS']  = "";
        $config['path']['codejar']     = $pluginUrl . "sql-admin/assets/codejar.js";
        $config['path']['prismjs']     = $pluginUrl . "sql-admin/assets/prism.min.js";
        $config['path']['prismcss']    = $pluginUrl . "sql-admin/assets/prism.min.css";
        $config['path']['prismsql']    = $pluginUrl . "sql-admin/assets/prism-sql.min.js";
        $config['path']['jquery']      = "";

        $config['prevent_auto_run'] = true;
        $config['unslash'] = function($value) {
            return function_exists('wp_unslash') ? \wp_unslash($value) : stripslashes_deep($value);
        };

        // Connect to the appropriate database (external or WordPress DB)
        try {
            $config['mysqli'] = ConnectionManager::get_connection_for_config($config);
        } catch (\Exception $e) {
            return '<div class="comyli-error">Datenbankfehler: ' . esc_html($e->getMessage()) . '</div>';
        }

        ob_start();

        $restUrl = function_exists('rest_url') ? esc_url_raw(rest_url('comyli/v1/')) : '';
        $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';
        $postId = (int)($config['comyli_id'] ?? 0);
        echo "<script>window.COMYLI_ENV = { restUrl: '" . esc_js($restUrl) . "', nonce: '" . esc_js($nonce) . "', postId: " . $postId . ", atts: " . json_encode($args) . " };</script>";
        echo "<style>body{ overflow: scroll; }</style>";

        $config['is_wp'] = true;

        if (function_exists('apply_filters')) {
            $config = apply_filters('comyli_config', $config, $config['stdTbl']);
            if (!empty($config['stdTbl'])) {
                $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);
            }
        }

        require_once($pluginPath . '/sql-admin/index.php');

        if (class_exists('\\Comyli\\Standalone\\App')) {
            $app = new \Comyli\Standalone\App($config);
            $app->run();
        }

        return ob_get_clean() ?: '';
    }
}
