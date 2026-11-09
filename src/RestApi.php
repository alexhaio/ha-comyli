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
        $postConfig = [];

        if ($postId > 0) {
            $resolved = ConnectionManager::resolve_table_config($postId);
            if ($resolved) {
                $postConfig = $resolved;
            } else {
                $post = get_post($postId);
                if ($post) {
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
                            if (in_array($block['blockName'] ?? '', ['comyli/table', 'ha-comyli/query-table'], true) && !empty($block['attrs'])) {
                                $postConfig = array_merge($postConfig, $block['attrs']);
                            }
                        }
                    }
                    if (has_shortcode($post->post_content, 'comyli') || has_shortcode($post->post_content, 'comyli_table')) {
                        $pattern = get_shortcode_regex(['comyli', 'comyli_table']);
                        if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches) && array_key_exists(2, $matches)) {
                            $atts = shortcode_parse_atts($matches[3][0] ?? '');
                            if (is_array($atts)) {
                                $postConfig = array_merge($postConfig, $atts);
                            }
                        }
                    }
                }
            }
        }

        if ($request->has_param('comyli_atts')) {
            $atts = json_decode((string)$request->get_param('comyli_atts'), true);
            if (is_array($atts)) {
                if (empty($postConfig) && !empty($atts['table'])) {
                    $resolved = ConnectionManager::resolve_table_config($atts['table']);
                    if ($resolved) {
                        $postConfig = $resolved;
                        $postId = (int)($resolved['comyli_id'] ?? 0);
                    }
                }
                $postConfig = array_merge($postConfig, $atts);
            }
        }

        if (!empty($postConfig['table']) && empty($postConfig['tables'])) {
            $postConfig['tables'] = $postConfig['table'];
        }

        if (empty($postConfig['tables']) && $postId <= 0) {
            return [];
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
        $config['tables'] = array_values(array_filter(array_map('trim', explode(',', $tablesStr))));
        $config['stdTbl'] = $config['tables'][0] ?? ($config['table'] ?? '');

        if (!empty($config['stdTbl']) && class_exists(Permissions::class)) {
            if (!Permissions::can_view_table($config['stdTbl'])) {
                return [];
            }
            if (!empty($config['edit']) && !Permissions::can_edit_table($config['stdTbl'])) {
                $config['edit'] = false;
            }
        }

        $config = apply_filters('comyli_config', $config, $config['stdTbl']);
        if (!empty($config['stdTbl'])) {
            $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);
        }

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
        $config['queries'] = class_exists(QueryManager::class)
            ? QueryManager::resolve_queries_dir($config['queries'] ?? '', $name)
            : $this->pluginConfig->plugin['path'] . '/queries/';
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
        $rawName = trim((string)$request->get_param('name'));
        $resolved = ConnectionManager::resolve_table_config($rawName);

        $targetTable = $rawName;
        if ($resolved && !empty($resolved['tables'])) {
            $tableList = array_values(array_filter(array_map('trim', explode(',', (string)$resolved['tables']))));
            if (!empty($tableList[0])) {
                $targetTable = $tableList[0];
            }
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $targetTable);
        if (empty($table)) {
            return new \WP_REST_Response(['error' => 'Ungültiger Tabellenname.'], 400);
        }

        require_once dirname(__DIR__) . '/sql-admin/index.php';

        try {
            $mysqli = $resolved
                ? ConnectionManager::get_connection_for_config($resolved)
                : ConnectionManager::get_connection_for_table($table);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => 'Datenbankfehler: ' . $e->getMessage()], 500);
        }

        $db = new \Comyli\Standalone\Database($mysqli);
        $method = $request->get_method();

        // Introspect columns and primary key
        $colRes = $db->query("SHOW COLUMNS FROM `$table`");
        if (!$colRes || !($colRes instanceof \mysqli_result)) {
            return new \WP_REST_Response(['error' => "Tabelle '$table' nicht gefunden oder nicht lesbar."], 404);
        }

        $columns = [];
        $validCols = [];
        $pkCol = 'id';
        while ($col = $colRes->fetch_assoc()) {
            $fieldName = (string)$col['Field'];
            $validCols[$fieldName] = true;
            $isPri = (($col['Key'] ?? '') === 'PRI');
            if ($isPri) {
                $pkCol = $fieldName;
            }

            $typeStr = strtolower($col['Type'] ?? '');
            $type = 'string';
            if (str_contains($typeStr, 'int')) $type = 'int';
            elseif (str_contains($typeStr, 'float') || str_contains($typeStr, 'decimal') || str_contains($typeStr, 'double')) $type = 'float';
            elseif (str_contains($typeStr, 'date') || str_contains($typeStr, 'time')) $type = 'date';

            $columns[] = [
                'name'    => $fieldName,
                'label'   => ucfirst(str_replace('_', ' ', $fieldName)),
                'type'    => $type,
                'primary' => $isPri,
            ];
        }

        if ($method === 'GET') {
            $page = max(1, (int)$request->get_param('page') ?: 1);
            $limit = max(1, min(500, (int)$request->get_param('limit') ?: 25));
            $offset = ($page - 1) * $limit;

            $countRes = $db->query("SELECT COUNT(*) FROM `$table`");
            $totalRows = ($countRes instanceof \mysqli_result && ($r = $countRes->fetch_row())) ? (int)$r[0] : 0;

            $dataRes = $db->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset");
            $rows = [];
            if ($dataRes instanceof \mysqli_result) {
                while ($row = $dataRes->fetch_assoc()) {
                    $rows[] = $row;
                }
            }

            return new \WP_REST_Response([
                'table'      => $table,
                'total_rows' => $totalRows,
                'page'       => $page,
                'limit'      => $limit,
                'columns'    => $columns,
                'data'       => $rows,
            ], 200);
        }

        if ($method === 'POST') {
            $data = $request->get_json_params() ?: $request->get_body_params();
            if (empty($data) || !is_array($data)) {
                return new \WP_REST_Response(['error' => 'Keine Daten übergeben.'], 400);
            }

            $cols = [];
            $placeholders = [];
            $params = [];
            foreach ($data as $k => $v) {
                if (isset($validCols[$k])) {
                    $cols[] = "`$k`";
                    if ($v === null) {
                        $placeholders[] = 'NULL';
                    } else {
                        $placeholders[] = '?';
                        $params[] = $v;
                    }
                }
            }

            if (empty($cols)) {
                return new \WP_REST_Response(['error' => 'Keine gültigen Tabellenspalten übergeben.'], 400);
            }

            $sql = "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $affected = $db->executePrepared($sql, $params);
            if ($affected === false) {
                return new \WP_REST_Response(['error' => $db->getError()], 500);
            }

            return new \WP_REST_Response([
                'success'   => true,
                'insert_id' => $mysqli->insert_id,
            ], 201);
        }

        if ($method === 'PUT') {
            $data = $request->get_json_params() ?: $request->get_body_params();
            if (!is_array($data)) {
                $data = [];
            }
            $id = $request->get_param('id') ?? ($data[$pkCol] ?? ($data['id'] ?? null));

            if ($id === null || $id === '') {
                return new \WP_REST_Response(['error' => "Primärschlüssel '$pkCol' erforderlich."], 400);
            }

            unset($data[$pkCol]);
            $sets = [];
            $params = [];
            foreach ($data as $k => $v) {
                if (isset($validCols[$k])) {
                    if ($v === null) {
                        $sets[] = "`$k` = NULL";
                    } else {
                        $sets[] = "`$k` = ?";
                        $params[] = $v;
                    }
                }
            }

            if (empty($sets)) {
                return new \WP_REST_Response(['error' => 'Keine zu aktualisierenden Spalten übergeben.'], 400);
            }

            $params[] = $id;
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pkCol` = ?";
            $updated = $db->executePrepared($sql, $params);
            if ($updated === false) {
                return new \WP_REST_Response(['error' => $db->getError()], 500);
            }

            return new \WP_REST_Response([
                'success' => true,
                'updated' => $updated,
            ], 200);
        }

        if ($method === 'DELETE') {
            $jsonParams = $request->get_json_params();
            $id = $request->get_param('id') ?? (is_array($jsonParams) ? ($jsonParams[$pkCol] ?? ($jsonParams['id'] ?? null)) : null);

            if ($id === null || $id === '') {
                return new \WP_REST_Response(['error' => "Primärschlüssel '$pkCol' erforderlich."], 400);
            }

            $sql = "DELETE FROM `$table` WHERE `$pkCol` = ?";
            $deleted = $db->executePrepared($sql, [$id]);
            if ($deleted === false) {
                return new \WP_REST_Response(['error' => $db->getError()], 500);
            }

            return new \WP_REST_Response([
                'success' => true,
                'deleted' => $deleted,
            ], 200);
        }

        return new \WP_REST_Response(['error' => 'Methode nicht unterstützt.'], 405);
    }
}
