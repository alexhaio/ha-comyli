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
    }

    /**
     * Initializes shortcodes and intercepts early requests (like CSV downloads).
     */
    public function initShortcodes(): void {
        if (!is_admin()) {
            add_shortcode($this->pluginConfig->plugin['name'], [$this, 'renderShortcode']);
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
        $post = get_post($id);
        
        if (!$post) {
            return 'Table not found.';
        }
        
        $unserialized = maybe_unserialize($post->post_content);
        $postConfig = is_array($unserialized) ? $unserialized : [];
        
        $meta = get_post_meta($post->ID);
        foreach ($meta as $k => $v) {
            if (str_starts_with($k, '_comyli_')) {
                $postConfig[substr($k, 8)] = maybe_unserialize($v[0]);
            }
        }
        
        $config = array_merge($this->pluginConfig->plugin, $postConfig, $atts);
        $config['comyli_id'] = $id; // Pass ID for the frontend form to include as hidden input
        
        $config['path'] = [];
        $tablesStr = isset($config['tables']) ? (string)$config['tables'] : '';
        $config['tables'] = explode(',', $tablesStr);
        $config['stdTbl'] = $config['tables'][0] ?? '';
        $config['path']['lightboxCSS']   = "";
        $config['path']['lightboxJS']    = "";
        $config['path']['codejar'] = $this->pluginConfig->plugin['url'] . "sql-admin/assets/codejar.js";
        $config['path']['prismjs'] = $this->pluginConfig->plugin['url'] . "sql-admin/assets/prism.min.js";
        $config['path']['prismcss'] = $this->pluginConfig->plugin['url'] . "sql-admin/assets/prism.min.css";
        $config['path']['prismsql'] = $this->pluginConfig->plugin['url'] . "sql-admin/assets/prism-sql.min.js";
        $config['path']['jquery']		 = "";
        
        $config['stdTbl'] = $config['tables'][0] ?? ''; // Standard-Tabelle
        $config['prevent_auto_run'] = true; // Tell standalone script not to execute automatically
        
        // Pass WordPress specific Environment Callbacks
        
        $config['unslash'] = function($value) {
            return \wp_unslash($value);
        };
        
        if (!empty($config['use_wp_db'])) {
            global $wpdb;
            if ($wpdb->dbh instanceof \mysqli) {
                $config['mysqli'] = $wpdb->dbh;
            } else {
                // Fallback
                $config['mysqli'] = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
                $config['mysqli']->query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'");
            }
        } else {
            $dbserver = $config['dbserver'] ?? '';
            $dbuser = $config['dbuser'] ?? '';
            $dbpass = $config['dbpass'] ?? '';
            $db = $config['db'] ?? '';
            
            if (!empty($dbserver)) {
                try {
                    $config['mysqli'] = new \mysqli($dbserver, $dbuser, $dbpass, $db);
                    if ($config['mysqli']->connect_error) {
                        throw new \Exception($config['mysqli']->connect_error);
                    }
                    $config['mysqli']->query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'");
                } catch (\Exception $e) {
                    return "Datenbankfehler: Verbindung fehlgeschlagen.";
                }
            }
        }

        ob_start();

        $restUrl = esc_url_raw(rest_url('comyli/v1/'));
                echo "<script>window.COMYLI_ENV = { restUrl: '" . esc_js($restUrl) . "', nonce: '" . wp_create_nonce('wp_rest') . "', postId: " . (int)$id . ", atts: " . json_encode($atts) . " };</script>";
        echo "<style>body{ overflow: scroll; }</style>";

        $config['is_wp'] = true;

        // Apply WordPress hooks for configuration
        $config = apply_filters('comyli_config', $config, $config['stdTbl']);
        $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);

        // Include sql-admin core (only classes)
        require_once($this->pluginConfig->plugin['path'] . '/sql-admin/index.php');
        
        // Execute the app explicitly
        if (class_exists('\\Comyli\\Standalone\\App')) {
            $app = new \Comyli\Standalone\App($config);
            $app->run();
        }
        
        return ob_get_clean() ?: '';
    }
}
