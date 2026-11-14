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
     * Generates an HMAC-SHA256 signed context token for safe client-side round-tripping
     * (REST AJAX actions, Live-Sync, and CSV exports) without exposing secrets.
     *
     * @param array<string, mixed> $ctx
     */
    public static function signContext(array $ctx): string {
        $allowedKeys = [
            'comyli_id', 'table', 'tables', 'query', 'qry', 'params',
            'layout', 'limit', 'edit', 'primaryEdit', 'filter',
            'csvExport', 'listQueries', 'listTables', 'stdTbl', 'stdQry',
        ];
        $payload = [];
        foreach ($allowedKeys as $k) {
            if (array_key_exists($k, $ctx)) {
                $payload[$k] = $ctx[$k];
            }
        }
        $json = function_exists('wp_json_encode') ? (string)wp_json_encode($payload) : (string)json_encode($payload);
        $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $secret = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'comyli-fallback-salt');
        $sig = hash_hmac('sha256', $b64, $secret);
        return $b64 . '.' . $sig;
    }

    /**
     * Verifies and decodes an HMAC-SHA256 signed context token.
     *
     * @return array<string, mixed>|null
     */
    public static function verifyContext(string $token): ?array {
        $token = trim($token);
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$b64, $sig] = $parts;
        $secret = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'comyli-fallback-salt');
        $expected = hash_hmac('sha256', $b64, $secret);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $padLen = (4 - (strlen($b64) % 4)) % 4;
        $json = base64_decode(strtr($b64, '-_', '+/') . str_repeat('=', $padLen), true);
        if (!is_string($json) || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function normalizeBool(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int)$value !== 0;
        }
        $str = strtolower(trim((string)$value));
        return in_array($str, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private static function normalizeShortcodeAtts(array $atts): array {
        $aliasMap = [
            'csvexport'   => 'csvExport',
            'listqueries' => 'listQueries',
            'listtables'  => 'listTables',
            'primaryedit' => 'primaryEdit',
            'stdtbl'      => 'stdTbl',
            'stdqry'      => 'stdQry',
            'usewpdb'     => 'use_wp_db',
        ];
        foreach ($aliasMap as $lower => $canonical) {
            if (array_key_exists($lower, $atts) && !array_key_exists($canonical, $atts)) {
                $atts[$canonical] = $atts[$lower];
                unset($atts[$lower]);
            }
        }
        foreach (['edit', 'filter', 'csvExport', 'insert', 'primaryEdit', 'listQueries', 'listTables', 'use_wp_db'] as $boolKey) {
            if (array_key_exists($boolKey, $atts)) {
                $atts[$boolKey] = self::normalizeBool($atts[$boolKey]);
            }
        }
        return $atts;
    }

    /**
     * Initializes shortcodes and intercepts early requests (like CSV downloads).
     */
    public function initShortcodes(): void {
        if (!is_admin()) {
            add_filter('the_content', [$this, 'filterTheContent']);

            // Intercept CSV export or Image requests before headers are sent
            $isCsv = isset($_GET['Y3N2']) && $_GET['Y3N2'] === '1';
            $isImg = isset($_GET['aW1n']) && $_GET['aW1n'] === '1';
            if ($isCsv || $isImg) {
                $ctxToken = isset($_GET['comyli_ctx']) ? (string)wp_unslash($_GET['comyli_ctx']) : '';
                if ($ctxToken !== '') {
                    $ctx = self::verifyContext($ctxToken);
                    if ($ctx !== null) {
                        if ($isCsv && empty($ctx['csvExport'])) {
                            return;
                        }
                        self::renderDirect($ctx, $this->pluginConfig);
                        return;
                    }
                }

                $id = isset($_GET['comyli_id']) ? (int)$_GET['comyli_id'] : 0;
                if (!$id && is_singular($this->pluginConfig->plugin['type'])) {
                    $id = (int)get_the_ID();
                }

                if ($id > 0) {
                    $this->renderShortcode(['id' => $id]);
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
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode(array|string $atts): string {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        $atts = is_array($atts) ? self::normalizeShortcodeAtts($atts) : [];
        $id = isset($atts['id']) ? (int)$atts['id'] : 0;
        $postConfig = [];

        $rawTable = (string)($atts['table'] ?? ($atts['tables'] ?? ''));
        $rawQuery = (string)($atts['query'] ?? ($atts['qry'] ?? ''));

        if ($id > 0) {
            $resolved = ConnectionManager::resolve_table_config($id, true);
            if ($resolved) {
                $postConfig = $resolved;
            }
        } elseif ($rawTable !== '') {
            $firstTable = trim(explode(',', $rawTable)[0]);
            $resolved = ConnectionManager::resolve_table_config($firstTable, true);
            if ($resolved) {
                $postConfig = $resolved;
                $id = (int)($resolved['comyli_id'] ?? 0);
            }
        } elseif ($rawQuery !== '') {
            $queryPost = QueryManager::find_query_post($rawQuery);
            if ($queryPost) {
                $tableId = (int)get_post_meta($queryPost->ID, QueryManager::META_TABLE_ID, true);
                if ($tableId > 0) {
                    $resolved = ConnectionManager::resolve_table_config($tableId, true);
                    if ($resolved) {
                        $postConfig = $resolved;
                        $id = (int)($resolved['comyli_id'] ?? 0);
                    }
                }
            }
        }

        // If neither valid post ID nor table/query attribute is supplied
        if (!$id && $rawTable === '' && $rawQuery === '') {
            return '<p class="comyli-notice">Keine Tabelle oder Abfrage angegeben.</p>';
        }

        if ($id > 0 && empty($postConfig)) {
            return '<div class="comyli-error">Die angegebene CoMyLi-Tabelle wurde nicht gefunden oder ist nicht ver&ouml;ffentlicht.</div>';
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
    public function renderQueryListShortcode(array|string $atts): string {
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
        $args = self::normalizeShortcodeAtts($args);

        $pluginPath = $pluginConfig ? $pluginConfig->plugin['path'] : dirname(__DIR__);
        $pluginUrl = $pluginConfig ? $pluginConfig->plugin['url'] : plugin_dir_url(dirname(__DIR__) . '/ha-comyli.php');

        Assets::ensureFrontendEnqueued($pluginConfig);

        $explicitEditFalse = array_key_exists('edit', $args) && empty($args['edit']);

        $defaults = [
            'table'     => '',
            'query'     => '',
            'params'    => [],
            'layout'    => 'clean',
            'limit'     => 25,
            'edit'      => false,
            'filter'    => true,
            'csvExport' => false,
        ];

        // Resolve CPT table/query config if credentials are not explicitly provided
        $comyliId = (int)($args['comyli_id'] ?? 0);
        if (empty($args['dbserver']) && !isset($args['use_wp_db'])) {
            if ($comyliId > 0) {
                $resolved = ConnectionManager::resolve_table_config($comyliId, true);
                if ($resolved) {
                    $args = array_merge($resolved, $args);
                }
            } elseif (!empty($args['table']) || !empty($args['tables'])) {
                $firstTable = trim(explode(',', (string)($args['table'] ?: $args['tables']))[0]);
                $resolved = ConnectionManager::resolve_table_config($firstTable, true);
                if ($resolved) {
                    $args = array_merge($resolved, $args);
                }
            } elseif (!empty($args['query']) || !empty($args['qry'])) {
                $qIdent = (string)($args['query'] ?: $args['qry']);
                $queryPost = QueryManager::find_query_post($qIdent);
                if ($queryPost) {
                    $tableId = (int)get_post_meta($queryPost->ID, QueryManager::META_TABLE_ID, true);
                    if ($tableId > 0) {
                        $resolved = ConnectionManager::resolve_table_config($tableId, true);
                        if ($resolved) {
                            $args = array_merge($resolved, $args);
                        }
                    }
                }
            }
        }

        $config = array_merge($defaults, $args);
        $postId = (int)($config['comyli_id'] ?? 0);

        // Map alias keys
        if (!empty($config['query']) && empty($config['qry'])) {
            $config['qry'] = $config['query'];
        }
        if (!empty($config['table']) && empty($config['tables'])) {
            $config['tables'] = $config['table'];
        }

        // Normalize tables array early for permission checks
        if (is_array($config['tables'])) {
            $tablesArr = array_values(array_filter(array_map('trim', $config['tables'])));
        } else {
            $tablesArr = array_values(array_filter(array_map('trim', explode(',', (string)$config['tables']))));
        }
        $config['tables'] = $tablesArr;
        if (empty($config['stdTbl'])) {
            $config['stdTbl'] = $tablesArr[0] ?? (string)($config['table'] ?? '');
        }

        // Check Permissions (Query & Table)
        $queryToCheck = (string)($config['query'] ?? ($config['qry'] ?? ''));
        if ($queryToCheck !== '' && class_exists(Permissions::class) && !Permissions::can_view_query($queryToCheck, $postId ?: null)) {
            return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieser Abfrage.</div>';
        }

        $tablesToCheck = $tablesArr;
        if (!empty($config['table']) && !in_array((string)$config['table'], $tablesToCheck, true)) {
            $tablesToCheck[] = (string)$config['table'];
        }
        if (class_exists(Permissions::class)) {
            foreach ($tablesToCheck as $tblCheck) {
                if ($tblCheck !== '' && !Permissions::can_view_table($tblCheck)) {
                    return '<div class="comyli-error">Keine Berechtigung zum Anzeigen dieser Tabelle.</div>';
                }
            }
        }

        $activeTableForPerm = (string)($config['table'] ?: $config['stdTbl']);
        $canEditPerm = true;
        if ($activeTableForPerm !== '' && class_exists(Permissions::class)) {
            $canEditPerm = Permissions::can_edit_table($activeTableForPerm);
        }

        if ($explicitEditFalse || !$canEditPerm) {
            $config['_comyli_edit_locked'] = true;
        }
        if (!empty($config['edit']) && !$canEditPerm) {
            $config['edit'] = false;
        }

        $config['path'] = [];
        $config['path']['lightboxCSS'] = "";
        $config['path']['lightboxJS']  = "";
        $config['path']['codejar']     = $pluginUrl . "sql-admin/assets/codejar.js";
        $config['path']['prismjs']     = $pluginUrl . "sql-admin/assets/prism.min.js";
        $config['path']['prismcss']    = $pluginUrl . "sql-admin/assets/prism.min.css";
        $config['path']['prismsql']    = $pluginUrl . "sql-admin/assets/prism-sql.min.js";
        $config['path']['chartJS']     = $pluginUrl . "js/chart.umd.min.js";
        $config['path']['jquery']      = "";

        $config['prevent_auto_run'] = true;
        $config['unslash'] = static function($value) {
            return function_exists('wp_unslash') ? \wp_unslash($value) : stripslashes_deep($value);
        };
        $config['csrf_generator'] = static function(): string {
            return function_exists('wp_create_nonce') ? wp_create_nonce('comyli_row_action') : '';
        };
        $config['csrf_validator'] = static function(?string $token): bool {
            return $token !== null && function_exists('wp_verify_nonce') && (bool)wp_verify_nonce($token, 'comyli_row_action');
        };

        // Connect to the appropriate database (external or WordPress DB)
        try {
            $config['mysqli'] = ConnectionManager::get_connection_for_config($config);
        } catch (\Exception $e) {
            return '<div class="comyli-error">Datenbankfehler: ' . esc_html($e->getMessage()) . '</div>';
        }
        $config['is_wp'] = true;

        if (function_exists('apply_filters')) {
            $config = apply_filters('comyli_config', $config, $config['stdTbl']);
            if (!empty($config['stdTbl'])) {
                $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);
            }
        }

        // Re-clamp edit permission after external filters
        if (!empty($config['_comyli_edit_locked']) || !$canEditPerm) {
            $config['edit'] = 0;
        }

        // Sign safe context token (never exposes dbpass or server paths)
        $signedCtx = self::signContext([
            'comyli_id'   => $postId,
            'table'       => (string)($config['table'] ?? ''),
            'tables'      => is_array($config['tables']) ? implode(',', $config['tables']) : (string)$config['tables'],
            'query'       => (string)($config['query'] ?? ''),
            'qry'         => (string)($config['qry'] ?? ''),
            'params'      => $config['params'] ?? [],
            'layout'      => (string)($config['layout'] ?? 'clean'),
            'limit'       => (int)($config['limit'] ?? 25),
            'edit'        => !empty($config['edit']) ? 1 : 0,
            'primaryEdit' => !empty($config['primaryEdit']) ? 1 : 0,
            'filter'      => !empty($config['filter']) ? 1 : 0,
            'csvExport'   => !empty($config['csvExport']) ? 1 : 0,
            'listQueries' => !empty($config['listQueries']) ? 1 : 0,
            'listTables'  => !empty($config['listTables']) ? 1 : 0,
            'stdTbl'      => (string)($config['stdTbl'] ?? ''),
            'stdQry'      => (string)($config['stdQry'] ?? ''),
        ]);

        $restUrl = function_exists('rest_url') ? esc_url_raw(rest_url('comyli/v1/')) : '';
        $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';
        $wpEnv = [
            'restUrl' => $restUrl,
            'nonce'   => $nonce,
            'postId'  => $postId,
            'ctx'     => $signedCtx,
        ];
        $config['comyli_ctx'] = $signedCtx;
        $config['wp_env'] = $wpEnv;

        ob_start();

        $isCsv = isset($_GET['Y3N2']) && $_GET['Y3N2'] === '1';
        $isImg = isset($_GET['aW1n']) && $_GET['aW1n'] === '1';

        if (!$isCsv && !$isImg) {
            $envJson = function_exists('wp_json_encode')
                ? wp_json_encode($wpEnv, JSON_HEX_TAG | JSON_HEX_AMP)
                : json_encode($wpEnv, JSON_HEX_TAG | JSON_HEX_AMP);
            echo "<script>window.COMYLI_ENV = " . $envJson . ";</script>";

            // 1. Output Custom CSS from Table
            $tableCssParts = [];
            if ($postId > 0) {
                $postCss = (string)get_post_meta($postId, '_comyli_custom_css', true);
                if (!empty($postCss)) {
                    $tableCssParts[] = $postCss;
                }
            }
            if (!empty($config['custom_css'])) {
                $tableCssParts[] = (string)$config['custom_css'];
            }
            $tableCss = implode("\n", array_unique($tableCssParts));
            if (!empty($tableCss)) {
                $cleanTableCss = trim((string)preg_replace('/<\/?\s*style\b[^>]*>/i', '', $tableCss));
                echo "<style id='comyli-custom-css-table-" . $postId . "'>\n" . wp_strip_all_tags($cleanTableCss) . "\n</style>";
            }

            // 2. Output Custom CSS from Query
            $queryName = (string)($config['query'] ?? ($config['qry'] ?? ''));
            $queryPost = null;
            if ($queryName !== '') {
                $queryPost = QueryManager::find_query_post($queryName, $postId ?: null);
                if ($queryPost) {
                    $queryCss = (string)get_post_meta($queryPost->ID, QueryManager::META_CUSTOM_CSS, true);
                    if (!empty($queryCss)) {
                        $cleanQueryCss = trim((string)preg_replace('/<\/?\s*style\b[^>]*>/i', '', $queryCss));
                        echo "<style id='comyli-custom-css-query-" . $queryPost->ID . "'>\n" . wp_strip_all_tags($cleanQueryCss) . "\n</style>";
                    }
                }
            }

            // 3. Output Custom JS from Table
            $tableJsParts = [];
            if ($postId > 0) {
                $postJs = (string)get_post_meta($postId, '_comyli_custom_js', true);
                if (!empty($postJs)) {
                    $tableJsParts[] = $postJs;
                }
            }
            if (!empty($config['custom_js'])) {
                $tableJsParts[] = (string)$config['custom_js'];
            }
            $tableJs = implode("\n", array_unique($tableJsParts));
            if (!empty($tableJs)) {
                $cleanTableJs = trim((string)preg_replace('/<\/?\s*script\b[^>]*>/i', '', $tableJs));
                echo "<script id='comyli-custom-js-table-" . $postId . "'>\n" . $cleanTableJs . "\n</script>";
            }

            // 4. Output Custom JS from Query
            if ($queryPost) {
                $queryJs = (string)get_post_meta($queryPost->ID, QueryManager::META_CUSTOM_JS, true);
                if (!empty($queryJs)) {
                    $cleanQueryJs = trim((string)preg_replace('/<\/?\s*script\b[^>]*>/i', '', $queryJs));
                    echo "<script id='comyli-custom-js-query-" . $queryPost->ID . "'>\n" . $cleanQueryJs . "\n</script>";
                }
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
