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

        $resolved = ConnectionManager::resolve_table_config($id);
        $postConfig = $resolved ?: [];
        $config = array_merge($this->pluginConfig->plugin, $postConfig);
        
        $slug = $config['post_name'] ?? ($config['table'] ?? '');
        $config['queries'] = class_exists(QueryManager::class)
            ? QueryManager::resolve_queries_dir($config['queries'] ?? '', $slug)
            : '../wp-content/plugins/ha-comyli/queries/';
        
        $tablesStr = isset($config['tables']) ? (string)$config['tables'] : '';
        $config['tables'] = explode(',', $tablesStr);
        $config['stdTbl'] = $config['tables'][0] ?? ''; // Standard-Tabelle
        
        $config['path'] = [];
        $config['path']['css'] = "";
        $config['path']['js'] = "";
        
        try {
            $config['mysqli'] = ConnectionManager::get_connection_for_config($config);
        } catch (\Exception $e) {
            wp_die('Datenbankfehler: ' . esc_html($e->getMessage()));
        }
        
        $_GET['Y3N2'] = 1;
        
        // Apply WordPress hooks for configuration
        $config = apply_filters('comyli_config', $config, $config['stdTbl']);
        $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);
        
        require($this->pluginConfig->plugin['path'] . '/sql-admin/index.php');
        
        wp_die();
    }
}
