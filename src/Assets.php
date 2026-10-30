<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class Assets {
    private Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    public function enqueueScripts(): void {
                        $url = $this->config->plugin['url'];
        $path = $this->config->plugin['path'];
        $version = '2.0';
        if (function_exists('get_file_data')) {
            $pluginData = get_file_data($path . '/ha-comyli.php', ['Version' => 'Version']);
            if (!empty($pluginData['Version'])) {
                $version = $pluginData['Version'];
            }
        }

        wp_register_style('TA_CSS', $url . 'sql-admin/style.css', [], $version);
        wp_enqueue_style('TA_CSS');
        
        wp_register_style('TAwp_CSS', $url . 'css/style.css', [], $version);
        wp_enqueue_style('TAwp_CSS');

        wp_register_style('ha_comyli_blocks_css', $url . 'css/blocks.css', [], $version);
        wp_enqueue_style('ha_comyli_blocks_css');
        
        wp_register_script('TA_JS', $url . 'sql-admin/functions.js', [], $version);
        wp_enqueue_script('TA_JS');
        
        wp_register_style('select2', $url . 'select2/select2.min.css', []);
        wp_enqueue_style('select2');
        
        wp_register_script('select2', $url . 'select2/select2.min.js', ['jquery'], '1.0', ['strategy' => 'defer']);
        wp_enqueue_script('select2');
        
                                                    }

    public function enqueueAdminScripts(): void {
                        $url = $this->config->plugin['url'];
        $path = $this->config->plugin['path'];
        $version = '2.0';
        if (function_exists('get_file_data')) {
            $pluginData = get_file_data($path . '/ha-comyli.php', ['Version' => 'Version']);
            if (!empty($pluginData['Version'])) {
                $version = $pluginData['Version'];
            }
        }
        
        wp_register_style('TAadmin_CSS', $url . 'css/admin-style.css', [], $version);
        wp_enqueue_style('TAadmin_CSS');
    }
}
