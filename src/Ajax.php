<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class Ajax {
    private Config $pluginConfig;

    public function __construct(Config $pluginConfig) {
        $this->pluginConfig = $pluginConfig;
    }

    public function register(): void {
        add_action('wp_ajax_comyli_csv_export', [$this, 'export']);
        add_action('wp_ajax_nopriv_comyli_csv_export', [$this, 'export']);
    }

    public function export(): void {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $post = get_post($id);
        
        if (!$post) {
            wp_die('Table not found.');
        }

        $unserialized = maybe_unserialize($post->post_content);
        $postConfig = is_array($unserialized) ? $unserialized : [];
        if (empty($postConfig)) {
            $meta = get_post_meta($post->ID);
            foreach ($meta as $k => $v) {
                if (str_starts_with($k, '_comyli_')) {
                    $postConfig[substr($k, 8)] = maybe_unserialize($v[0]);
                }
            }
        }
        $config = array_merge($this->pluginConfig->plugin, $postConfig);
        
        $config['queries'] = '../wp-content/plugins/ha-comyli/queries/';
        
        $tablesStr = isset($config['tables']) ? (string)$config['tables'] : '';
        $config['tables'] = explode(',', $tablesStr);
        $config['stdTbl'] = $config['tables'][0] ?? ''; // Standard-Tabelle
        
        $config['path'] = [];
        $config['path']['css'] = "";
        $config['path']['js'] = "";
        
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
                    wp_die('Datenbankfehler: Verbindung fehlgeschlagen.');
                }
            }
        }
        
        $_GET['Y3N2'] = 1;
        
        // Apply WordPress hooks for configuration
        $config = apply_filters('comyli_config', $config, $config['stdTbl']);
        $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);
        
        require($this->pluginConfig->plugin['path'] . '/sql-admin/index.php');
        
        wp_die();
    }
}
