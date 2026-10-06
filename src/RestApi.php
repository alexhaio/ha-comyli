<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Handles WordPress REST API endpoints for high-performance AJAX routing.
 */
class RestApi {
    private Config $pluginConfig;

    public function __construct(Config $pluginConfig) {
        $this->pluginConfig = $pluginConfig;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'registerEndpoints']);
    }

    public function registerEndpoints(): void {
        register_rest_route('comyli/v1', '/heartbeat', [
            'methods' => 'GET',
            'callback' => [$this, 'handleHeartbeat'],
            'permission_callback' => '__return_true', 
        ]);

        register_rest_route('comyli/v1', '/syncState', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSyncState'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('comyli/v1', '/save', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSave'],
            'permission_callback' => '__return_true',
        ]);
    }

    private function bootstrapConfig(\WP_REST_Request $request): array {
        $postId = (int)$request->get_param('comyli_id');
        $post = get_post($postId);
        
        if (!$post) {
            return [];
        }
        
                                $unserialized = maybe_unserialize($post->post_content);
        $postConfig = is_array($unserialized) ? $unserialized : [];
        
        $meta = get_post_meta($post->ID);
        foreach ($meta as $k => $v) {
            if (str_starts_with($k, '_comyli_')) {
                $postConfig[substr($k, 8)] = maybe_unserialize($v[0]);
            }
        }
        if (function_exists('parse_blocks')) {
            $blocks = parse_blocks($post->post_content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'comyli/table' && !empty($block['attrs'])) {
                    $postConfig = array_merge($postConfig, $block['attrs']);
                }
            }
        }
        if (has_shortcode($post->post_content, 'comyli')) {
            $pattern = get_shortcode_regex(['comyli']);
            if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches) && array_key_exists(2, $matches) && in_array('comyli', $matches[2])) {
                $index = array_search('comyli', $matches[2]);
                $atts = shortcode_parse_atts($matches[3][$index]);
                if (is_array($atts)) $postConfig = array_merge($postConfig, $atts);
            }
        }
        if ($request->has_param('comyli_atts')) {
            $atts = json_decode($request->get_param('comyli_atts'), true);
            if (is_array($atts)) {
                $postConfig = array_merge($postConfig, $atts);
            }
        }
        
$config = array_merge($this->pluginConfig->plugin, $postConfig);
        $config['comyli_id'] = $postId;
                $config['is_wp'] = true;
        
        
        if (!empty($config['use_wp_db'])) {
            global $wpdb;
            if ($wpdb->dbh instanceof \mysqli) {
                $config['mysqli'] = $wpdb->dbh;
            } else {
                $config['mysqli'] = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
                $config['mysqli']->query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'");
            }
        } else {
            $dbserver = $config['dbserver'] ?? '';
            $dbuser = $config['dbuser'] ?? '';
            $dbpass = $config['dbpass'] ?? '';
            $db = $config['db'] ?? '';
            
            if (!empty($dbserver)) {
                $config['mysqli'] = new \mysqli($dbserver, $dbuser, $dbpass, $db);
                $config['mysqli']->query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'");
            }
        }

        $tablesStr = isset($config['tables']) ? (string)$config['tables'] : '';
        $config['tables'] = explode(',', $tablesStr);
        $config['stdTbl'] = $config['tables'][0] ?? '';

        $config = apply_filters('comyli_config', $config, $config['stdTbl']);
        $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);

        return $config;
    }

    private function prepareGlobals(\WP_REST_Request $request): void {
        $_GET = array_merge($_GET, $request->get_query_params());
        $_POST = array_merge($_POST, $request->get_body_params());
    }

    public function handleHeartbeat(\WP_REST_Request $request) {
        $config = $this->bootstrapConfig($request);
        if (empty($config)) {
            return new \WP_REST_Response('Invalid Post', 400);
        }
        
        $this->prepareGlobals($request);
        $_GET['action'] = 'heartbeat';

        require_once($this->pluginConfig->plugin['path'] . '/sql-admin/index.php');
        
        if (class_exists('\\Comyli\\Standalone\\App')) {
            $app = new \Comyli\Standalone\App($config);
            $app->run(); 
        }
        
        return new \WP_REST_Response('App class not found', 500);
    }

    public function handleSyncState(\WP_REST_Request $request) {
        $config = $this->bootstrapConfig($request);
        if (empty($config)) {
            return new \WP_REST_Response('Invalid Post', 400);
        }
        
        $this->prepareGlobals($request);
        $_GET['action'] = 'syncState';
        $_SERVER['REQUEST_METHOD'] = 'POST'; 

        require_once($this->pluginConfig->plugin['path'] . '/sql-admin/index.php');
        
        if (class_exists('\\Comyli\\Standalone\\App')) {
            $app = new \Comyli\Standalone\App($config);
            $app->run(); 
        }
        
        return new \WP_REST_Response('App class not found', 500);
    }

    public function handleSave(\WP_REST_Request $request) {
        ob_start();

        $config = $this->bootstrapConfig($request);
        if (empty($config)) {
            ob_end_clean();
            return new \WP_REST_Response('Invalid Post', 400);
        }

        $this->prepareGlobals($request);
        require_once dirname(__DIR__) . '/sql-admin/index.php';

        $config['noInterface'] = 1;
        $_GET[\Comyli\Standalone\Security::base64urlEncode('noInterface')] = '1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        if (class_exists('\\Comyli\\Standalone\\App')) {
            $app = new \Comyli\Standalone\App($config);
            $app->run(); 
        }

        $output = ob_get_clean();
        $statusCode = http_response_code() ?: 200;
        $response = new \WP_REST_Response($output, $statusCode);
        $response->header('Content-Type', 'text/plain; charset=utf-8');
        return $response;
    }
}
