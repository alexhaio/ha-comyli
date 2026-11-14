<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class Assets {
    private Config $config;
    private static bool $registered = false;
    private static bool $enqueued = false;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    private static function getVersion(?Config $config = null): string {
        $version = '3.5.1';
        $path = $config ? $config->plugin['path'] : dirname(__DIR__);
        if (function_exists('get_file_data') && file_exists($path . '/ha-comyli.php')) {
            $pluginData = get_file_data($path . '/ha-comyli.php', ['Version' => 'Version']);
            if (!empty($pluginData['Version'])) {
                $version = $pluginData['Version'];
            }
        }
        return $version;
    }

    public static function registerFrontendAssets(?Config $config = null): void {
        if (self::$registered || !function_exists('wp_register_style')) {
            return;
        }
        self::$registered = true;

        $url = $config ? $config->plugin['url'] : plugin_dir_url(dirname(__DIR__) . '/ha-comyli.php');
        $version = self::getVersion($config);

        wp_register_style('TA_CSS', $url . 'sql-admin/style.css', [], $version);
        wp_register_style('TAwp_CSS', $url . 'css/style.css', ['TA_CSS'], $version);
        wp_register_style('ha_comyli_blocks_css', $url . 'css/blocks.css', [], $version);
        wp_register_script('TA_JS', $url . 'sql-admin/functions.js', ['jquery'], $version, ['strategy' => 'defer', 'in_footer' => true]);
        wp_register_style('select2', $url . 'select2/select2.min.css', [], '4.0.13');
        wp_register_script('select2', $url . 'select2/select2.min.js', ['jquery'], '4.0.13', ['strategy' => 'defer', 'in_footer' => true]);
    }

    public static function ensureFrontendEnqueued(?Config $config = null): void {
        self::registerFrontendAssets($config);
        if (self::$enqueued || !function_exists('wp_enqueue_style')) {
            return;
        }
        self::$enqueued = true;

        wp_enqueue_style('TA_CSS');
        wp_enqueue_style('TAwp_CSS');
        wp_enqueue_style('ha_comyli_blocks_css');
        wp_enqueue_script('TA_JS');
        wp_enqueue_style('select2');
        wp_enqueue_script('select2');
    }

    public function enqueueScripts(): void {
        self::registerFrontendAssets($this->config);

        if ($this->shouldEnqueueOnCurrentPage()) {
            self::ensureFrontendEnqueued($this->config);
        }
    }

    private function shouldEnqueueOnCurrentPage(): bool {
        if (is_singular($this->config->plugin['type'])) {
            return true;
        }

        $post = get_queried_object();
        if ($post instanceof \WP_Post) {
            $content = (string)$post->post_content;
            foreach (['comyli', 'comyli_table', 'comyli_query_list', 'comyli_queries', $this->config->plugin['name']] as $sc) {
                if (has_shortcode($content, $sc)) {
                    return true;
                }
            }
            if (function_exists('has_block')) {
                foreach (['ha-comyli/query-table', 'ha-comyli/chart', 'ha-comyli/kpi-card', 'ha-comyli/query-list'] as $blk) {
                    if (has_block($blk, $post)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
