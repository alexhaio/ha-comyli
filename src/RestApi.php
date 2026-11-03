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

        // AP4: Headless REST-API Endpoints
        register_rest_route('comyli/v1', '/query/(?P<name>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'api_execute_query'],
            'permission_callback' => [$this, 'api_check_query_permission'],
        ]);

        register_rest_route('comyli/v1', '/table/(?P<name>[a-zA-Z0-9_-]+)', [
            'methods'             => ['GET', 'POST', 'PUT', 'DELETE'],
            'callback'            => [$this, 'api_handle_table'],
            'permission_callback' => [$this, 'api_check_table_permission'],
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

        try {
            $config['mysqli'] = ConnectionManager::get_connection_for_config($config);
        } catch (\Exception $e) {
            return [];
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

    /**
     * AP4/AP6: Permission callback for headless query endpoint.
     */
    public function api_check_query_permission(\WP_REST_Request $request): bool {
        $name = (string)$request->get_param('name');
        return Permissions::can_view_query($name);
    }

    /**
     * AP4/AP6: Permission callback for headless table endpoint.
     */
    public function api_check_table_permission(\WP_REST_Request $request): bool {
        $name = (string)$request->get_param('name');
        $method = $request->get_method();
        if ($method === 'GET') {
            return Permissions::can_view_table($name);
        }
        return Permissions::can_edit_table($name);
    }

    /**
     * AP4: Headless REST-API Query Execution.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function api_execute_query(\WP_REST_Request $request) {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$request->get_param('name'));
        if (empty($name)) {
            return new \WP_REST_Response(['error' => 'Ungültiger Abfragename.'], 400);
        }

        require_once dirname(__DIR__) . '/sql-admin/index.php';

        $config = $this->pluginConfig->plugin;
        $config['queries'] = $this->pluginConfig->plugin['path'] . '/queries/';
        $config['params'] = $request->get_params();

        try {
            $mysqli = ConnectionManager::get_connection_for_query($name);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => 'Datenbankfehler: ' . $e->getMessage()], 500);
        }
        $config['mysqli'] = $mysqli;

        $configHandler = new \Comyli\Standalone\ConfigHandler($config);
        $sql = \Comyli\Standalone\FieldHandler::resolveSQL($name, $configHandler);

        if (empty($sql)) {
            return new \WP_REST_Response(['error' => "Abfrage '$name' wurde nicht gefunden."], 404);
        }

        $page = max(1, (int)$request->get_param('page') ?: 1);
        $limit = max(1, min(500, (int)$request->get_param('limit') ?: 25));
        $offset = ($page - 1) * $limit;

        // Total count
        $countSql = "SELECT COUNT(*) FROM ($sql) AS count_subq";
        $countRes = $mysqli ? $mysqli->query($countSql) : null;
        $totalRows = ($countRes && $r = $countRes->fetch_row()) ? (int)$r[0] : 0;

        // Data query
        $dataSql = "SELECT * FROM ($sql) AS data_subq LIMIT $limit OFFSET $offset";
        $dataRes = $mysqli ? $mysqli->query($dataSql) : null;

        if (!$dataRes) {
            $err = $mysqli ? $mysqli->error : 'Datenbankfehler';
            return new \WP_REST_Response(['error' => $err], 500);
        }

        $columns = [];
        foreach ($dataRes->fetch_fields() as $f) {
            $colType = 'string';
            if (in_array($f->type, [1, 2, 3, 8, 9])) $colType = 'int';
            elseif (in_array($f->type, [4, 5, 246])) $colType = 'float';
            elseif (in_array($f->type, [10, 11, 12])) $colType = 'date';
            $columns[] = [
                'name'  => $f->name,
                'label' => ucfirst(str_replace('_', ' ', $f->name)),
                'type'  => $colType,
            ];
        }

        $data = [];
        while ($row = $dataRes->fetch_assoc()) {
            $data[] = $row;
        }

        return new \WP_REST_Response([
            'query'      => $name,
            'total_rows' => $totalRows,
            'page'       => $page,
            'limit'      => $limit,
            'columns'    => $columns,
            'data'       => $data,
        ], 200);
    }

    /**
     * AP4: Headless REST-API Table Operations (CRUD).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function api_handle_table(\WP_REST_Request $request) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$request->get_param('name'));
        if (empty($table)) {
            return new \WP_REST_Response(['error' => 'Ungültiger Tabellenname.'], 400);
        }

        global $wpdb;
        $method = $request->get_method();

        if ($method === 'GET') {
            $page = max(1, (int)$request->get_param('page') ?: 1);
            $limit = max(1, min(500, (int)$request->get_param('limit') ?: 25));
            $offset = ($page - 1) * $limit;

            $totalRows = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            $rows = $wpdb->get_results("SELECT * FROM `$table` LIMIT $limit OFFSET $offset", ARRAY_A);

            // Columns introspection
            $colMeta = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A);
            $columns = [];
            if ($colMeta) {
                foreach ($colMeta as $col) {
                    $typeStr = strtolower($col['Type'] ?? '');
                    $type = 'string';
                    if (str_contains($typeStr, 'int')) $type = 'int';
                    elseif (str_contains($typeStr, 'float') || str_contains($typeStr, 'decimal') || str_contains($typeStr, 'double')) $type = 'float';
                    elseif (str_contains($typeStr, 'date') || str_contains($typeStr, 'time')) $type = 'date';

                    $columns[] = [
                        'name'    => $col['Field'],
                        'label'   => ucfirst(str_replace('_', ' ', $col['Field'])),
                        'type'    => $type,
                        'primary' => ($col['Key'] === 'PRI'),
                    ];
                }
            }

            return new \WP_REST_Response([
                'table'      => $table,
                'total_rows' => $totalRows,
                'page'       => $page,
                'limit'      => $limit,
                'columns'    => $columns,
                'data'       => $rows ?: [],
            ], 200);
        }

        if ($method === 'POST') {
            $data = $request->get_json_params() ?: $request->get_body_params();
            if (empty($data)) {
                return new \WP_REST_Response(['error' => 'Keine Daten übergeben.'], 400);
            }

            $success = $wpdb->insert($table, $data);
            if ($success === false) {
                return new \WP_REST_Response(['error' => $wpdb->last_error], 500);
            }

            return new \WP_REST_Response([
                'success'   => true,
                'insert_id' => $wpdb->insert_id,
            ], 201);
        }

        if ($method === 'PUT') {
            $data = $request->get_json_params() ?: $request->get_body_params();
            $id = $request->get_param('id') ?? ($data['id'] ?? null);

            $pkCol = 'id';
            $priCol = $wpdb->get_row("SHOW COLUMNS FROM `$table` WHERE `Key` = 'PRI'", ARRAY_A);
            if ($priCol && !empty($priCol['Field'])) {
                $pkCol = $priCol['Field'];
                if (isset($data[$pkCol])) {
                    $id = $data[$pkCol];
                }
            }

            if ($id === null) {
                return new \WP_REST_Response(['error' => "Primärschlüssel '$pkCol' erforderlich."], 400);
            }

            unset($data[$pkCol]);
            $updated = $wpdb->update($table, $data, [$pkCol => $id]);
            if ($updated === false) {
                return new \WP_REST_Response(['error' => $wpdb->last_error], 500);
            }

            return new \WP_REST_Response([
                'success' => true,
                'updated' => $updated,
            ], 200);
        }

        if ($method === 'DELETE') {
            $id = $request->get_param('id');
            $pkCol = 'id';
            $priCol = $wpdb->get_row("SHOW COLUMNS FROM `$table` WHERE `Key` = 'PRI'", ARRAY_A);
            if ($priCol && !empty($priCol['Field'])) {
                $pkCol = $priCol['Field'];
            }

            if (!$id) {
                return new \WP_REST_Response(['error' => "Primärschlüssel '$pkCol' erforderlich."], 400);
            }

            $deleted = $wpdb->delete($table, [$pkCol => $id]);
            if ($deleted === false) {
                return new \WP_REST_Response(['error' => $wpdb->last_error], 500);
            }

            return new \WP_REST_Response([
                'success' => true,
                'deleted' => $deleted,
            ], 200);
        }

        return new \WP_REST_Response(['error' => 'Methode nicht unterstützt.'], 405);
    }
}
