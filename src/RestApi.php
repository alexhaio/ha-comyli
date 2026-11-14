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
        require_once dirname(__DIR__) . '/sql-admin/index.php';

        $postConfig = [];
        $ctxData = [];

        $ctxToken = (string)$request->get_param('comyli_ctx');
        if ($ctxToken !== '') {
            $verified = Shortcode::verifyContext($ctxToken);
            if ($verified === null) {
                return [];
            }
            $ctxData = $verified;
        }

        $postId = (int)($ctxData['comyli_id'] ?? $request->get_param('comyli_id'));

        if ($postId > 0) {
            $resolved = ConnectionManager::resolve_table_config($postId, true);
            if ($resolved) {
                $postConfig = $resolved;
            } else {
                $post = get_post($postId);
                if ($post && ($post->post_status === 'publish' || ($post->post_status === 'draft' && current_user_can('edit_post', $post->ID)))) {
                    if ($post->post_type === 'comyli') {
                        return [];
                    }
                    $tableToResolve = '';
                    if (function_exists('parse_blocks')) {
                        $blocks = parse_blocks($post->post_content);
                        foreach ($blocks as $block) {
                            if (in_array($block['blockName'] ?? '', ['comyli/table', 'ha-comyli/query-table'], true) && !empty($block['attrs'])) {
                                $postConfig = array_merge($postConfig, $block['attrs']);
                                if (!empty($block['attrs']['table'])) {
                                    $tableToResolve = (string)$block['attrs']['table'];
                                }
                            }
                        }
                    }
                    if (has_shortcode($post->post_content, 'comyli') || has_shortcode($post->post_content, 'comyli_table')) {
                        $pattern = get_shortcode_regex(['comyli', 'comyli_table']);
                        if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches) && array_key_exists(3, $matches)) {
                            $atts = shortcode_parse_atts($matches[3][0] ?? '');
                            if (is_array($atts)) {
                                $postConfig = array_merge($postConfig, $atts);
                                if (!empty($atts['id'])) {
                                    $tableToResolve = (string)$atts['id'];
                                } elseif (!empty($atts['table'])) {
                                    $tableToResolve = (string)$atts['table'];
                                }
                            }
                        }
                    }
                    if ($tableToResolve !== '') {
                        $resolvedTbl = ConnectionManager::resolve_table_config($tableToResolve, true);
                        if ($resolvedTbl) {
                            $postConfig = array_merge($resolvedTbl, $postConfig);
                            $postId = (int)($resolvedTbl['comyli_id'] ?? $postId);
                        }
                    }
                }
            }
        } elseif (!empty($ctxData)) {
            if (!empty($ctxData['table']) || !empty($ctxData['tables'])) {
                $firstTable = trim(explode(',', (string)($ctxData['table'] ?: $ctxData['tables']))[0]);
                $resolved = ConnectionManager::resolve_table_config($firstTable, true);
                if ($resolved) {
                    $postConfig = $resolved;
                    $postId = (int)($resolved['comyli_id'] ?? 0);
                }
            } elseif (!empty($ctxData['query']) || !empty($ctxData['qry'])) {
                $qIdent = (string)($ctxData['query'] ?: $ctxData['qry']);
                $queryPost = QueryManager::find_query_post($qIdent);
                if ($queryPost) {
                    $tableId = (int)get_post_meta($queryPost->ID, QueryManager::META_TABLE_ID, true);
                    if ($tableId > 0) {
                        $resolved = ConnectionManager::resolve_table_config($tableId, true);
                        if ($resolved) {
                            $postConfig = $resolved;
                            $postId = (int)($resolved['comyli_id'] ?? 0);
                        }
                    }
                }
            }
        }

        if (!empty($ctxData)) {
            $postConfig = array_merge($postConfig, $ctxData);
        }

        if (!empty($postConfig['table']) && empty($postConfig['tables'])) {
            $postConfig['tables'] = $postConfig['table'];
        }

        $hasQuery = !empty($postConfig['query']) || !empty($postConfig['qry']);
        if (empty($postConfig['tables']) && $postId <= 0 && !$hasQuery) {
            return [];
        }

        $config = array_merge($this->pluginConfig->plugin, $postConfig);
        $config['comyli_id'] = $postId;
        $config['is_wp'] = true;
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

        try {
            $config['mysqli'] = ConnectionManager::get_connection_for_config($config);
        } catch (\Exception $e) {
            return [];
        }

        if (is_array($config['tables'] ?? null)) {
            $tablesArr = array_values(array_filter(array_map('trim', $config['tables'])));
        } else {
            $tablesArr = array_values(array_filter(array_map('trim', explode(',', (string)($config['tables'] ?? '')))));
        }
        $config['tables'] = $tablesArr;
        $config['stdTbl'] = $tablesArr[0] ?? (string)($config['table'] ?? '');

        $queryToCheck = (string)($config['query'] ?? ($config['qry'] ?? ''));
        if ($queryToCheck !== '' && class_exists(Permissions::class) && !Permissions::can_view_query($queryToCheck, $postId ?: null)) {
            return [];
        }

        if (class_exists(Permissions::class)) {
            foreach ($tablesArr as $tblCheck) {
                if ($tblCheck !== '' && !Permissions::can_view_table($tblCheck)) {
                    return [];
                }
            }
        }

        $activeTbl = (string)($config['table'] ?: $config['stdTbl']);
        $canEditPerm = true;
        if ($activeTbl !== '' && class_exists(Permissions::class)) {
            $canEditPerm = Permissions::can_edit_table($activeTbl);
        }
        $explicitEditFalse = array_key_exists('edit', $ctxData) && empty($ctxData['edit']);

        if ($explicitEditFalse || !$canEditPerm) {
            $config['_comyli_edit_locked'] = true;
        }
        if (!empty($config['edit']) && !$canEditPerm) {
            $config['edit'] = false;
        }

        $config = apply_filters('comyli_config', $config, $config['stdTbl']);
        if (!empty($config['stdTbl'])) {
            $config = apply_filters('comyli_config_' . $config['stdTbl'], $config);
        }

        if (!empty($config['_comyli_edit_locked']) || !$canEditPerm) {
            $config['edit'] = 0;
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
            return new \WP_REST_Response('Forbidden or Invalid Context', 403);
        }

        $this->prepareGlobals($request);
        $_GET['action'] = 'heartbeat';

        require_once($this->pluginConfig->plugin['path'] . '/sql-admin/index.php');

        if (!class_exists('\\Comyli\\Standalone\\App')) {
            return new \WP_REST_Response('App class not found', 500);
        }

        ob_start();
        $app = new \Comyli\Standalone\App($config);
        $app->run();
        $output = (string)ob_get_clean();

        $response = new \WP_REST_Response($output, 200);
        $response->header('Content-Type', 'text/plain; charset=utf-8');
        return $response;
    }

    public function handleSyncState(\WP_REST_Request $request) {
        $config = $this->bootstrapConfig($request);
        if (empty($config)) {
            return new \WP_REST_Response(['error' => 'Forbidden or Invalid Context'], 403);
        }

        $this->prepareGlobals($request);
        $_GET['action'] = 'syncState';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        require_once($this->pluginConfig->plugin['path'] . '/sql-admin/index.php');

        if (!class_exists('\\Comyli\\Standalone\\App')) {
            return new \WP_REST_Response(['error' => 'App class not found'], 500);
        }

        ob_start();
        $app = new \Comyli\Standalone\App($config);
        $app->run();
        $output = (string)ob_get_clean();

        $decoded = json_decode($output, true);
        if (is_array($decoded)) {
            return new \WP_REST_Response($decoded, 200);
        }

        $response = new \WP_REST_Response($output, 200);
        $response->header('Content-Type', 'application/json; charset=utf-8');
        return $response;
    }

    public function handleSave(\WP_REST_Request $request) {
        $config = $this->bootstrapConfig($request);
        if (empty($config)) {
            return new \WP_REST_Response('Forbidden or Invalid Context', 403);
        }
        if (empty($config['edit'])) {
            return new \WP_REST_Response('Keine Berechtigung zum Bearbeiten dieser Tabelle.', 403);
        }

        $this->prepareGlobals($request);
        require_once dirname(__DIR__) . '/sql-admin/index.php';

        $config['noInterface'] = 1;
        $_GET[\Comyli\Standalone\Security::base64urlEncode('noInterface')] = '1';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        if (!class_exists('\\Comyli\\Standalone\\App')) {
            return new \WP_REST_Response('App class not found', 500);
        }

        ob_start();
        $app = new \Comyli\Standalone\App($config);
        $app->run();
        $output = (string)ob_get_clean();

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
     * Resolves a configured CPT table for headless REST access (strictly rejects unconfigured tables).
     *
     * @return array<string, mixed>|null
     */
    private function resolveConfiguredRestTable(string $rawName): ?array {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return null;
        }

        $resolved = ConnectionManager::resolve_table_config($rawName, true);
        if (!$resolved) {
            return null;
        }

        $tableList = array_values(array_filter(array_map('trim', explode(',', (string)($resolved['tables'] ?? '')))));
        $targetTable = in_array($rawName, $tableList, true) ? $rawName : ($tableList[0] ?? '');
        $table = (string)preg_replace('/[^a-zA-Z0-9_]/', '', $targetTable);
        if ($table === '') {
            return null;
        }

        $resolved['_resolved_table'] = $table;
        $resolved['_resolved_table_list'] = $tableList;
        return $resolved;
    }

    /**
     * AP4/AP6: Permission callback for headless table endpoint.
     * Strictly allows only tables configured as published CoMyLi CPTs (or drafts editable by current user).
     */
    public function api_check_table_permission(\WP_REST_Request $request): bool {
        $rawName = (string)$request->get_param('name');
        $resolved = $this->resolveConfiguredRestTable($rawName);
        if (!$resolved) {
            return false;
        }

        $table = (string)$resolved['_resolved_table'];
        $method = $request->get_method();
        if ($method === 'GET') {
            return Permissions::can_view_table($table);
        }
        return Permissions::can_edit_table($table);
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

        if (!Permissions::can_view_query($name)) {
            return new \WP_REST_Response(['error' => 'Keine Berechtigung zum Ausführen dieser Abfrage.'], 403);
        }

        require_once dirname(__DIR__) . '/sql-admin/index.php';

        $config = $this->pluginConfig->plugin;
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

        // Total count (newline guards trailing SQL -- comments)
        $countSql = "SELECT COUNT(*) FROM ($sql\n) AS count_subq";
        $countRes = $mysqli ? $mysqli->query($countSql) : null;
        $totalRows = ($countRes && $r = $countRes->fetch_row()) ? (int)$r[0] : 0;

        // Data query
        $dataSql = "SELECT * FROM ($sql\n) AS data_subq LIMIT $limit OFFSET $offset";
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
     * Checks whether a column is allowed by limit_cols configuration.
     *
     * @param array<string, mixed> $config
     */
    private function isRestColumnVisible(array $config, string $table, string $colname): bool {
        $limits = $config['limit_cols'] ?? [];
        if (!is_array($limits) || !isset($limits[$table]) || !is_array($limits[$table])) {
            return true;
        }
        if (isset($limits[$table][$colname]) && $limits[$table][$colname] === true) {
            return true;
        }
        return in_array($colname, $limits[$table], true);
    }

    /**
     * AP4: Headless REST-API Table Operations (CRUD) - strictly restricted to configured CPT tables.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function api_handle_table(\WP_REST_Request $request) {
        $rawName = (string)$request->get_param('name');
        $resolved = $this->resolveConfiguredRestTable($rawName);
        if (!$resolved) {
            return new \WP_REST_Response(['error' => 'Tabelle ist nicht über CoMyLi konfiguriert.'], 403);
        }

        $table = (string)$resolved['_resolved_table'];
        $tableList = (array)$resolved['_resolved_table_list'];
        unset($resolved['_resolved_table'], $resolved['_resolved_table_list']);

        require_once dirname(__DIR__) . '/sql-admin/index.php';

        try {
            $mysqli = ConnectionManager::get_connection_for_config($resolved);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => 'Datenbankfehler: ' . $e->getMessage()], 500);
        }

        $method = $request->get_method();
        $canEditPerm = Permissions::can_edit_table($table);

        // Apply declarative JSON config & filters (access_rules, visible_columns, readonly, valueH, postOverride)
        $config = array_merge($this->pluginConfig->plugin, $resolved);
        $config['mysqli'] = $mysqli;
        $config['is_wp'] = true;
        $config['tables'] = $tableList;
        $config['stdTbl'] = $table;
        $config['edit'] = $canEditPerm ? 1 : 0;
        if (!$canEditPerm) {
            $config['_comyli_edit_locked'] = true;
        }

        $config = apply_filters('comyli_config', $config, $table);
        $config = apply_filters('comyli_config_' . $table, $config);

        if (!empty($config['_comyli_edit_locked']) || !$canEditPerm) {
            $config['edit'] = 0;
        }

        if ($method !== 'GET' && empty($config['edit'])) {
            return new \WP_REST_Response(['error' => 'Keine Berechtigung zum Bearbeiten dieser Tabelle.'], 403);
        }

        $db = new \Comyli\Standalone\Database($mysqli);

        // Introspect columns and primary key
        $colRes = $db->query("SHOW COLUMNS FROM `$table`");
        if (!$colRes || !($colRes instanceof \mysqli_result)) {
            return new \WP_REST_Response(['error' => "Tabelle '$table' nicht gefunden oder nicht lesbar."], 404);
        }

        $columns = [];
        $validCols = [];
        $visibleCols = [];
        $pkCol = 'id';
        while ($col = $colRes->fetch_assoc()) {
            $fieldName = (string)$col['Field'];
            $validCols[$fieldName] = true;
            $isPri = (($col['Key'] ?? '') === 'PRI');
            if ($isPri) {
                $pkCol = $fieldName;
            }

            if (!$this->isRestColumnVisible($config, $table, $fieldName)) {
                continue;
            }
            $visibleCols[$fieldName] = true;

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

            $selectList = !empty($visibleCols)
                ? implode(', ', array_map(static fn($c) => "`$c`", array_keys($visibleCols)))
                : '*';

            $countRes = $db->query("SELECT COUNT(*) FROM `$table`");
            $totalRows = ($countRes instanceof \mysqli_result && ($r = $countRes->fetch_row())) ? (int)$r[0] : 0;

            $dataRes = $db->query("SELECT $selectList FROM `$table` LIMIT $limit OFFSET $offset");
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

        $readonlyCols = is_array($config['readonly'] ?? null) ? $config['readonly'] : [];
        $forcedValues = is_array($config['valueH'] ?? null) ? $config['valueH'] : [];
        $postOverrides = is_array($config['postOverride'] ?? null) ? $config['postOverride'] : [];
        $postOverrideRaw = is_array($config['postOverrideRaw'] ?? null) ? $config['postOverrideRaw'] : [];
        $currentUserLogin = (function_exists('wp_get_current_user') && wp_get_current_user()->exists())
            ? wp_get_current_user()->user_login
            : 'System';

        if ($method === 'POST') {
            $data = $request->get_json_params() ?: $request->get_body_params();
            if (empty($data) || !is_array($data)) {
                return new \WP_REST_Response(['error' => 'Keine Daten übergeben.'], 400);
            }

            // Filter writable submitted fields (enforce visible + not readonly + not forced)
            $writeData = [];
            foreach ($data as $k => $v) {
                if (!isset($validCols[$k]) || !isset($visibleCols[$k])) {
                    continue;
                }
                if (!empty($readonlyCols[$k]) || isset($forcedValues[$k]) || in_array($k, ['lastChanged', 'lstChanged', 'lastChangedUser'], true)) {
                    continue;
                }
                $writeData[$k] = $v;
            }

            // Apply forced hidden values (valueH)
            foreach ($forcedValues as $fk => $fv) {
                if (isset($validCols[$fk])) {
                    $writeData[$fk] = $fv;
                }
            }

            // Apply dynamic post_override on insert
            $evalContext = ['stdTbl' => $table, 'columns' => $writeData];
            foreach ($postOverrideRaw as $ok => $rawTpl) {
                if (isset($validCols[$ok])) {
                    $writeData[$ok] = ConfigResolver::resolveTokens($rawTpl, $evalContext, $mysqli);
                    $evalContext['columns'][$ok] = $writeData[$ok];
                }
            }
            foreach ($postOverrides as $ok => $ov) {
                if (!isset($postOverrideRaw[$ok]) && isset($validCols[$ok])) {
                    $writeData[$ok] = $ov;
                }
            }

            $cols = [];
            $placeholders = [];
            $params = [];
            foreach ($writeData as $k => $v) {
                $cols[] = "`$k`";
                if ($v === null) {
                    $placeholders[] = 'NULL';
                } else {
                    $placeholders[] = '?';
                    $params[] = $v;
                }
            }

            foreach (['lastChanged', 'lstChanged'] as $lcName) {
                if (isset($validCols[$lcName])) {
                    $cols[] = "`$lcName`";
                    $placeholders[] = 'CURRENT_TIMESTAMP';
                }
            }
            if (isset($validCols['lastChangedUser'])) {
                $cols[] = "`lastChangedUser`";
                $placeholders[] = '?';
                $params[] = $currentUserLogin;
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

            if (empty($config['primaryEdit'])) {
                unset($data[$pkCol]);
            }

            $writeData = [];
            foreach ($data as $k => $v) {
                if (!isset($validCols[$k]) || !isset($visibleCols[$k])) {
                    continue;
                }
                if (!empty($readonlyCols[$k]) || isset($forcedValues[$k]) || in_array($k, ['lastChanged', 'lstChanged', 'lastChangedUser'], true)) {
                    continue;
                }
                $writeData[$k] = $v;
            }
            foreach ($forcedValues as $fk => $fv) {
                if (isset($validCols[$fk]) && $fk !== $pkCol) {
                    $writeData[$fk] = $fv;
                }
            }

            $sets = [];
            $params = [];
            foreach ($writeData as $k => $v) {
                if ($v === null) {
                    $sets[] = "`$k` = NULL";
                } else {
                    $sets[] = "`$k` = ?";
                    $params[] = $v;
                }
            }

            foreach (['lastChanged', 'lstChanged'] as $lcName) {
                if (isset($validCols[$lcName]) && !empty($sets)) {
                    $sets[] = "`$lcName` = CURRENT_TIMESTAMP";
                }
            }
            if (isset($validCols['lastChangedUser']) && !empty($sets)) {
                $sets[] = "`lastChangedUser` = ?";
                $params[] = $currentUserLogin;
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
