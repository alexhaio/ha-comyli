<?php
/**
 * Configurable-MySQL-Light-Interface - CoMyLI (Refactored)
 * 
 * Standalone Single-File SQL Administrator.
 * Secures inputs via Prepared Statements and outputs via strict Escaping.
 */
namespace Comyli\Standalone;

if (!defined('ABSPATH') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------------------------
// 1. SECURITY & HELPERS
// -----------------------------------------------------------------------------

class Security {
    private static ?ConfigHandler $config = null;

    public static function setConfig(ConfigHandler $config): void {
        self::$config = $config;
    }

    public static function escapeHtml(?string $string): string {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }

    public static function escapeAttr(?string $string): string {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }

    public static function generateCsrfToken(): string {
        if (self::$config && $cb = self::$config->get('csrf_generator')) {
            return $cb();
        }
        if (empty($_SESSION['comyli_csrf'])) {
            $_SESSION['comyli_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['comyli_csrf'];
    }

    public static function verifyCsrfToken(?string $token): bool {
        if (self::$config && $cb = self::$config->get('csrf_validator')) {
            return $cb($token);
        }
        if (empty($_SESSION['comyli_csrf']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['comyli_csrf'], $token);
    }

    public static function base64urlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $data): string {
        $padLen = (4 - (strlen($data) % 4)) % 4;
        return (string)base64_decode(strtr($data, '-_', '+/') . str_repeat('=', $padLen));
    }

    public static function unslash($value) {
        if (self::$config && $cb = self::$config->get('unslash')) {
            return $cb($value);
        }
        return $value;
    }
}

// -----------------------------------------------------------------------------
// 2. DATABASE (MySQLi Wrapper with Prepared Statements)
// -----------------------------------------------------------------------------

class Database {
    private \mysqli $mysqli;
    public string $customError = '';

    public function __construct(\mysqli $mysqli) {
        $this->mysqli = $mysqli;
        if ($this->mysqli->connect_errno) {
            die('CoMyLi Connect Error: ' . $this->mysqli->connect_errno);
        }
    }

    public function getMysqli(): \mysqli {
        return $this->mysqli;
    }

    /**
     * Executes a raw query (use only for trusted SELECTs or structural queries)
     */
    public function query(string $sql): \mysqli_result|bool {
        return $this->mysqli->query($sql);
    }

    /**
     * Executes a prepared statement and returns the result.
     *
     * @param string $sql The query string with ? placeholders
     * @param array $params The parameters to bind
     * @return \mysqli_result|bool|int Returns result for SELECT, affected_rows for INSERT/UPDATE/DELETE, or false on failure
     */
    public function executePrepared(string $sql, array $params = []) {
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            $this->customError = $this->mysqli->error;
            return false;
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $this->customError = $stmt->error ?: $this->mysqli->error;
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result(); // Only works for SELECT, SHOW, DESCRIBE
        if ($result === false) {
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected >= 0 ? $affected : false;
        }

        $stmt->close();
        return $result;
    }

    public function getError(): string {
        if ($this->customError !== '') {
            return $this->customError;
        }
        return $this->mysqli->error;
    }
}

// -----------------------------------------------------------------------------
// 3. CONFIGURATION HANDLER
// -----------------------------------------------------------------------------

/**
 * Handles the configuration array for the CoMyLi Standalone Application.
 */
class ConfigHandler {
    private array $config;

    public function __construct(array $override = []) {
        $defaults = [
            'version' => 30500,
            'sqlLogDuration' => 60 * 60 * 24 * 7,
            'path' => [
                'codejar' => 'assets/codejar.js',
                'prismjs' => 'assets/prism.min.js',
                'prismcss' => 'assets/prism.min.css',
                'prismsql' => 'assets/prism-sql.min.js',
                'chartJS' => '../js/chart.umd.min.js',
                'css' => 'style.css',
                'js' => 'functions.js',
                'jquery' => ''
            ],
            'localPath' => [
                'files' => './files/'
            ],
            'format' => [
                'decimalSeparator' => ','
            ],
            'db' => '',
            'limit' => 100,
            'edit' => 0,
            'insert' => 1,
            'eval' => 0,
            'editSQL' => 0,
            'primaryEdit' => 0,
            'filter' => 1,
            'pages' => 1,
            'showTypes' => 0,
            'listQueries' => 0,
            'listTables' => 0,
            'csvExport' => 0,
            'imgShow' => 0,
            'noInterface' => 0,
            'gui' => [
                'insert_top' => 0,
                'insert_bottom' => 1
            ],
            'limit_cols' => [],
            'global' => [
                'fileCode' => '[FS_FILE]'
            ],
            'valueH' => [],
            'valueP' => []
        ];

        // LDAP default logic only in standalone mode outside WordPress
        if (!defined('ABSPATH') && (isset($_SESSION["ldap"]["cn"][0]) || isset($_SESSION["ldap"]["samaccountname"][0]))) {
            $user = !empty($_SESSION["ldap"]["cn"][0]) ? $_SESSION["ldap"]["cn"][0] : $_SESSION["ldap"]["samaccountname"][0];
            $defaults['valueH']['lastChangedUser'] = $user;
            $defaults['valueP']['createdUser'] = $user;
        }

        $this->config = array_replace_recursive($defaults, $override);
    }

    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void {
        $this->config[$key] = $value;
    }

    public function getArray(string $key): array {
        if (!isset($this->config[$key])) return [];
        if (is_array($this->config[$key])) return $this->config[$key];

        if (is_string($this->config[$key])) {
            $cols = array_map('trim', explode(',', $this->config[$key]));
            $res = [];
            foreach ($cols as $c) {
                if ($c !== '') $res[$c] = true;
            }
            if ($key === 'limit_cols') {
                $table = $this->config['tables'][0] ?? '';
                if ($table) return [$table => $res];
            }
            return $res;
        }
        return [];
    }

    public function getAll(): array {
        return $this->config;
    }
}

// -----------------------------------------------------------------------------
// 4. FIELD & DATA FORMATTING
// -----------------------------------------------------------------------------

class FieldHandler {
    private static array $tableColumnsCache = [];

    /**
     * Retrieves full column information (including comments, defaults, types) for a table.
     */
    public static function getTableColumns(string $table, Database $db): array {
        if (empty($table)) return [];
        if (!isset(self::$tableColumnsCache[$table])) {
            self::$tableColumnsCache[$table] = [];
            $escaped = $db->getMysqli()->real_escape_string($table);
            $res = $db->query("SHOW FULL COLUMNS FROM `$escaped`");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    self::$tableColumnsCache[$table][$row['Field']] = $row;
                }
            }
        }
        return self::$tableColumnsCache[$table];
    }

    /**
     * Extracts and maps field information from a mysqli_field object.
     */
    public static function getFieldInfo(object $val, Database $db): array {
        $info = [
            'name' => $val->name,
            'table' => $val->table,
            'type' => $val->type,
            'decimals' => $val->decimals ?? 0,
            'max_length' => $val->max_length ?? 0,
            'length' => $val->length ?? 0,
            'def' => $val->def ?? '',
            'flag' => [
                'notNull'   => ($val->flags & 1),
                'primary'   => ($val->flags & 2),
                'unique'    => ($val->flags & 4),
                'multiple'  => ($val->flags & 8),
                'blob'      => ($val->flags & 16),
                'unsigned'  => ($val->flags & 32),
                'zerofill'  => ($val->flags & 64),
                'binary'    => ($val->flags & 128),
                'enum'      => ($val->flags & 256),
                'autoincr'  => ($val->flags & 512),
                'timestamp' => ($val->flags & 2048),
                'noDefault' => ($val->flags & 4096),
                'onUpdate'  => ($val->flags & 8192),
            ]
        ];

        // Column comments and Default detection
        $tableName = !empty($val->orgtable) ? $val->orgtable : $val->table;
        $fieldName = !empty($val->orgname) ? $val->orgname : $val->name;
        $info['comment'] = '';

        if (!empty($tableName)) {
            $cols = self::getTableColumns($tableName, $db);
            if (isset($cols[$fieldName])) {
                $info['comment'] = $cols[$fieldName]['Comment'] ?? '';
                if (empty($info['def']) && !$info['flag']['noDefault'] && isset($cols[$fieldName]['Default'])) {
                    $info['default'] = $cols[$fieldName]['Default'];
                }
            }
        }

        return $info;
    }

    /**
     * Safely escapes a parameter value for inclusion in SQL while preserving leading-zero identifiers.
     */
    public static function escapeSqlLiteral($value, ConfigHandler $config): string {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || (is_string($value) && ($value === '0' || (ctype_digit($value) && $value[0] !== '0')))) {
            return (string)(int)$value;
        }
        if (is_float($value) || (is_string($value) && preg_match('/^-?(?:0|[1-9]\d*)\.\d+$/', $value))) {
            return (string)(float)$value;
        }

        $str = (string)$value;
        if (function_exists('esc_sql')) {
            return "'" . esc_sql($str) . "'";
        }
        if ($mysqli = $config->get('mysqli')) {
            if ($mysqli instanceof \mysqli) {
                return "'" . $mysqli->real_escape_string($str) . "'";
            }
        }
        return "'" . addslashes($str) . "'";
    }

    /**
     * Resolves dynamic context tokens ({current_user_id}, {current_user_name}, {get:param})
     * and named placeholders (:param_name) in SQL queries.
     */
    public static function resolveParameters(string $sql, ConfigHandler $config): string {
        // 1. Context Token: {current_user_id}
        $userId = 0;
        if (function_exists('get_current_user_id')) {
            $userId = (int)get_current_user_id();
        }
        $sql = str_replace('{current_user_id}', (string)$userId, $sql);

        // 2. Context Token: {current_user_name}
        $userName = '';
        if (function_exists('wp_get_current_user')) {
            $u = wp_get_current_user();
            if ($u && !empty($u->user_login)) {
                $userName = $u->user_login;
            }
        }
        $sql = str_replace('{current_user_name}', self::escapeSqlLiteral($userName, $config), $sql);

        // 3. Context Token: {get:param_name}
        $sql = preg_replace_callback('/\{get:([a-zA-Z0-9_-]+)\}/', function($m) use ($config) {
            $paramName = $m[1];
            $val = isset($_GET[$paramName]) ? Security::unslash($_GET[$paramName]) : '';
            return self::escapeSqlLiteral($val, $config);
        }, $sql);

        // 4. Named Placeholders :param_name
        $params = $config->get('params');
        $paramMap = [];
        if (is_array($params)) {
            $paramMap = $params;
        } elseif (is_string($params) && !empty($params)) {
            $trimmed = trim($params);
            if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $paramMap = $decoded;
                }
            } else {
                parse_str($trimmed, $parsed);
                if (is_array($parsed)) {
                    $paramMap = $parsed;
                }
            }
        }

        // Also merge $_GET params with lower priority (if not explicitly passed in params config)
        foreach ($_GET as $gk => $gv) {
            if (!isset($paramMap[$gk]) && is_scalar($gv)) {
                $paramMap[$gk] = Security::unslash($gv);
            }
        }

        if (!empty($paramMap)) {
            $sql = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function($m) use ($paramMap, $config) {
                $name = $m[1];
                if (array_key_exists($name, $paramMap)) {
                    return self::escapeSqlLiteral($paramMap[$name], $config);
                }
                return $m[0];
            }, $sql);
        }

        return $sql;
    }

    /**
     * Resolves custom [QRY] markers recursively with cycle detection and binds dynamic parameters.
     *
     * @param string[] $visited
     */
    public static function resolveSQL(string $qryName, ConfigHandler $config, array $visited = []): string {
        $cleanQryName = trim((string)preg_replace('/\.sql$/i', '', $qryName));
        if ($cleanQryName === '' || in_array($cleanQryName, $visited, true)) {
            return '';
        }
        $visited[] = $cleanQryName;

        $tableId = intval($config->get('comyli_id', 0));
        $tblParam = $tableId > 0 ? $tableId : null;

        // Capability check via Permissions (bypassed only for trusted system cron via skip_perm_check)
        if (!$config->get('skip_perm_check') && class_exists('\\ha\\CoMyLi\\Permissions') && !\ha\CoMyLi\Permissions::can_view_query($cleanQryName, $tblParam)) {
            return '';
        }

        $qry = '';
        if (class_exists('\\ha\\CoMyLi\\QueryManager')) {
            $dbSql = \ha\CoMyLi\QueryManager::get_query_sql($cleanQryName, $tblParam);
            if (!empty($dbSql)) {
                $qry = $dbSql;
            }
        }

        if (empty($qry)) {
            return '';
        }

        $match = preg_split("/(\[QRY\]|\[QUERY\])(.*?)(\[\/QRY\]|\[\/QUERY\])/ims", $qry, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (is_array($match) && sizeof($match) > 1) {
            $resolved = '';
            for ($i = 0; $i < sizeof($match); $i += 4) {
                $resolved .= $match[$i];
                if (!empty($match[$i + 2])) {
                    $subTarget = trim((string)$match[$i + 2]);
                    $subSql = self::resolveSQL($subTarget, $config, $visited);
                    if ($subSql === '') {
                        return '';
                    }
                    $subAlias = preg_replace('/[^a-zA-Z0-9_]/', '_', $subTarget) ?: 'subq';
                    $resolved .= "(" . $subSql . "\n) AS `" . $subAlias . "` ";
                }
            }
            $qry = $resolved;
        }

        // Resolve dynamic context parameters and tokens
        $qry = self::resolveParameters($qry, $config);

        return rtrim(trim($qry), "; \t\n\r\0\x0B");
    }
}

class FilterHandler {
    private ConfigHandler $config;
    
    public function __construct(ConfigHandler $config) {
        $this->config = $config;
    }
    
    public function getWhereClause(array $fields): array {
        $where = [];
        $params = [];
        $types = "";
                // Volltextsuche (Search row)
        $searchKey = Security::base64urlEncode('search');
        if (!empty($_GET[$searchKey])) {
            $search = Security::unslash($_GET[$searchKey]);
            $orClauses = [];
            foreach ($fields as $field) {
                // only search text/string fields or numeric
                // skip binary blobs (252 with binary flag) and json (245)
                $isBinaryBlob = ($field['type'] == 252 && !empty($field['flag']['binary']));
                if (!$isBinaryBlob && $field['type'] != 245) {
                    $orClauses[] = "`" . $field['name'] . "` LIKE ?";
                    $params[] = "%$search%";
                }
            }
            if (!empty($orClauses)) {
                $where[] = "(" . implode(" OR ", $orClauses) . ")";
                $types = str_repeat("s", count($params));
            }
        }
        
        foreach ($fields as $field) {
            $key = Security::base64urlEncode("whereA01" . $field['name']);
            $keyFrom = Security::base64urlEncode("whereA01from_" . $field['name']);
            $keyTo = Security::base64urlEncode("whereA01to_" . $field['name']);
            
            // Handle Date Ranges
            if ((isset($_GET[$keyFrom]) && $_GET[$keyFrom] !== '') || (isset($_GET[$keyTo]) && $_GET[$keyTo] !== '')) {
                $valFrom = isset($_GET[$keyFrom]) ? Security::unslash($_GET[$keyFrom]) : '';
                $valTo = isset($_GET[$keyTo]) ? Security::unslash($_GET[$keyTo]) : '';
                
                if ($valFrom !== '' && $valTo !== '') {
                    $where[] = "`" . $field['name'] . "` BETWEEN ? AND ?";
                    $params[] = $valFrom;
                    $params[] = $valTo . " 23:59:59"; // Cover the whole end day
                    $types .= "ss";
                } elseif ($valFrom !== '') {
                    $where[] = "`" . $field['name'] . "` >= ?";
                    $params[] = $valFrom;
                    $types .= "s";
                } elseif ($valTo !== '') {
                    $where[] = "`" . $field['name'] . "` <= ?";
                    $params[] = $valTo . " 23:59:59";
                    $types .= "s";
                }
            }
            // Normal field handling
            elseif (isset($_GET[$key]) && $_GET[$key] !== '') {
                $val = Security::unslash($_GET[$key]);
                
                if (preg_match('/^(<=|>=|<|>|!=|=)\s*(.+)$/', trim($val), $matches)) {
                    $op = $matches[1];
                    $cleanVal = ltrim($matches[2]);
                    $where[] = "`" . $field['name'] . "` $op ?";
                    $params[] = $cleanVal;
                    $types .= "s";
                } elseif (str_starts_with(trim($val), '!~')) {
                    $cleanVal = ltrim(substr(trim($val), 2));
                    if (strpos($cleanVal, '%') === false && strpos($cleanVal, '_') === false) $cleanVal = "%" . $cleanVal . "%";
                    $where[] = "`" . $field['name'] . "` NOT LIKE ?";
                    $params[] = $cleanVal;
                    $types .= "s";
                } else {
                    if (strpos($val, '%') === false && strpos($val, '_') === false) $val = "%" . $val . "%";
                    $where[] = "`" . $field['name'] . "` LIKE ?";
                    $params[] = $val;
                    $types .= "s";
                }
            }
        }
        
        return [
            'sql' => empty($where) ? "" : " WHERE " . implode(" AND ", $where),
            'params' => $params,
            'types' => $types
        ];
    }
}

// -----------------------------------------------------------------------------
// 5. HTML RENDERING (Table & Forms)
// -----------------------------------------------------------------------------

class HtmlRenderer {
    private ConfigHandler $config;
    private Database $db;
    private string $uid = '';

    public function __construct(ConfigHandler $config, Database $db) {
        $this->config = $config;
        $this->db = $db;
    }

    public function renderField(array $info, ?string $value, ?array $infos = null, ?array $row = null): string {
        $value = $value ?? '';

        // Value Overrides
        if (isset($this->config->getArray('valueOverride')[$info['name']])) {
            $value = (string)$this->config->getArray('valueOverride')[$info['name']];
        }

        $type = $info['type'];
        $fk = $this->config->getArray('foreignKey');

        if (isset($fk['input'][$info['name']])) {
            return $this->mkSelect($info, $value, 'input');
        } elseif (isset($fk['suggestion'][$info['name']])) {
            return $this->mkSelect($info, $value, 'suggestion');
        } elseif ($type == 1) {
            return $this->mkBool($info, $value);
        } elseif (in_array($type, [2, 3, 8, 9])) {
            return $this->mkInt($info, $value);
        } elseif (in_array($type, [4, 5, 246])) {
            return $this->mkFloat($info, $value);
        } elseif ($type == 10) {
            return $this->mkDate($info, $value);
        } elseif (in_array($type, [7, 12])) {
            return $this->mkTimestamp($info, $value);
        } elseif ($type == 245) {
            return $this->mkJSON($info, $value);
        } elseif ($type == 252 && ($info['flag']['binary'] ?? false)) {
            return $this->mkBlob($info, $value, $infos, $row);
        } elseif ($type == 252) {
            return $this->mkTextarea($info, $value);
        } elseif ($type == 254) {
            return $this->mkEnum($info, $value);
        } else {
            return $this->mkText($info, $value);
        }
    }

    public function renderFilterField(array $info, string $value, string $key, string $formId = 'fsp'): string {
        $type = $info['type'];
        $fAttr = "form='" . Security::escapeAttr($formId) . "'";

        // Date (10), Datetime (12), Timestamp (7)
        if (in_array($type, [7, 10, 12])) {
            $keyFrom = Security::base64urlEncode("whereA01from_" . $info['name']);
            $keyTo = Security::base64urlEncode("whereA01to_" . $info['name']);
            $valFrom = isset($_GET[$keyFrom]) ? Security::escapeAttr((string)Security::unslash($_GET[$keyFrom])) : '';
            $valTo = isset($_GET[$keyTo]) ? Security::escapeAttr((string)Security::unslash($_GET[$keyTo])) : '';

            $out = "<div style='display:flex; flex-direction:column; gap:4px; width:100%;'>";
            $out .= "<div style='display:flex; align-items:center;'><span style='font-size:0.8em; margin-right:4px; width:25px; color:#64748b;'>Von</span> <input $fAttr class='filter' name='" . Security::escapeAttr($keyFrom) . "' type='date' value='$valFrom' style='font-size:0.9em; padding:2px; flex:1;' /></div>";
            $out .= "<div style='display:flex; align-items:center;'><span style='font-size:0.8em; margin-right:4px; width:25px; color:#64748b;'>Bis</span> <input $fAttr class='filter' name='" . Security::escapeAttr($keyTo) . "' type='date' value='$valTo' style='font-size:0.9em; padding:2px; flex:1;' /></div>";
            $out .= "</div>";
            return $out;
        }

        $baseAttr = "$fAttr name='" . Security::escapeAttr($key) . "' class='filter' size='1'";

        // Enum Dropdown
        if (!empty($info['flag']['enum']) || $type == 254) {
            $table = !empty($info['table']) ? $info['table'] : '';
            $field = $info['name'];
            $finfoType = '';
            if (!empty($table)) {
                $cols = FieldHandler::getTableColumns($table, $this->db);
                if (isset($cols[$field])) {
                    $finfoType = $cols[$field]['Type'] ?? '';
                }
            }
            if (empty($finfoType) && !empty($table)) {
                $escapedTable = $this->db->getMysqli()->real_escape_string($table);
                $escapedField = $this->db->getMysqli()->real_escape_string($field);
                $res = $this->db->query("SHOW COLUMNS FROM `$escapedTable` WHERE Field = '$escapedField'");
                if ($res && $finfo = $res->fetch_object()) {
                    $finfoType = $finfo->Type ?? '';
                }
            }
            if (!empty($finfoType)) {
                $parts = [];
                if (preg_match_all("/'((?:''|[^'])*)'/", $finfoType, $matches)) {
                    $parts = array_map(function($v) { return str_replace("''", "'", $v); }, $matches[1]);
                }
                if (!empty($parts)) {
                    $out = "<select $baseAttr>";
                    $out .= "<option value=''></option>";
                    foreach ($parts as $p) {
                        $sel = ($p === $value) ? " selected" : "";
                        $out .= "<option value='" . Security::escapeAttr($p) . "'$sel>" . Security::escapeHtml($p) . "</option>";
                    }
                    $out .= "</select>";
                    return $out;
                }
            }
        }

        // Bool Dropdown
        if ($type == 1) {
            $out = "<select $baseAttr>";
            $out .= "<option value=''></option>";
            $out .= "<option value='1'" . ($value === '1' ? ' selected' : '') . ">True (1)</option>";
            $out .= "<option value='0'" . ($value === '0' ? ' selected' : '') . ">False (0)</option>";
            $out .= "</select>";
            return $out;
        }

        // Foreign Key Suggestion/Input Dropdown
        if (isset($this->config->getArray('foreignKey')['input'][$info['name']]) || isset($this->config->getArray('foreignKey')['suggestion'][$info['name']])) {
            $fkList = $this->config->getArray('foreignKey')['input'][$info['name']] ?? $this->config->getArray('foreignKey')['suggestion'][$info['name']];
            $placeholders = ['= Meier', '!~ Müller', '%GmbH%', '!= Leer'];
            $randomPlaceholder = $placeholders[array_rand($placeholders)];
            return "<div style='display:flex;'><input $baseAttr list='" . Security::escapeAttr($fkList) . "' value='" . Security::escapeAttr($value) . "' placeholder='" . Security::escapeAttr($randomPlaceholder) . "' title='" . Security::escapeAttr($info['name']) . "'></div>";
        }

        // Default text input
        $numericTypes = [1, 2, 3, 4, 5, 8, 9, 246];
        if (in_array($type, $numericTypes)) {
            $placeholders = ['>= 100', '< 50', '!= 0', '= 10'];
        } else {
            $placeholders = ['= Meier', '!~ Müller', '%GmbH%', '!= Leer'];
        }
        $randomPlaceholder = $placeholders[array_rand($placeholders)];

        return "<div style='display:flex;'><input type='text' $baseAttr value='" . Security::escapeAttr($value) . "' placeholder='" . Security::escapeAttr($randomPlaceholder) . "' title='" . Security::escapeAttr($info['name']) . "'></div>";
    }

    private function mkSelect(array $info, string $value, string $type = 'input'): string {
        $fkList = $this->config->getArray('foreignKey')[$type][$info['name']] ?? '';
        $param = $this->config->getArray('param')[$info['name']] ?? '';

        $out = "<div class='td'><input name='" . Security::escapeAttr($info['name']) . "' list='" . Security::escapeAttr($fkList) . "' value='" . Security::escapeAttr($value) . "'";
        if ($param) $out .= " $param";
        if ($type == 'input') $out .= " data-strict='true'";
        $out .= " />";
        if ($info['flag']['primary'] ?? false) {
            $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
        }
        $out .= "</div>";
        return $out;
    }

    private function mkJSON(array $info, string $value): string {
        if ($value === "null" || $value === "") $value = '[]';
        $param = $this->config->getArray('param')[$info['name']] ?? '';
        $fk = $this->config->getArray('foreignKey');
        $fkList = $fk['input'][$info['name']] ?? ($fk['suggestion'][$info['name']] ?? $info['name']);

        $out = "<div class='td'>";
        $out .= "<input type='hidden' name='" . Security::escapeAttr($info['name']) . "' class='jsonInput' value='" . Security::escapeAttr($value) . "' />";
        $out .= "<select class='select2Input' size='1' data-list='" . Security::escapeAttr($info['name']) . "' data-fk-list='" . Security::escapeAttr((string)$fkList) . "' multiple='multiple'";
        if ($param) $out .= " $param";
        $out .= "></select></div>";
        return $out;
    }

    private function mkBool(array $info, string $value): string {
        $param = $this->config->getArray('param')[$info['name']] ?? '';
        $out = "<div class='td'><input type='hidden' name='" . Security::escapeAttr($info['name']) . "' value='0' />";
        $out .= "<input type='checkbox' name='" . Security::escapeAttr($info['name']) . "' value='1'";
        if ($param) $out .= " $param";
        if ($value === '1') $out .= " checked";
        $out .= " />";
        if ($info['flag']['primary'] ?? false) {
            $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
        }
        $out .= "</div>";
        return $out;
    }

    private function mkInt(array $info, string $value): string {
        if ($value === '' && isset($this->config->getArray('value')[$info['name']])) {
            $value = $this->config->getArray('value')[$info['name']];
        }
        $param = $this->config->getArray('param')[$info['name']] ?? '';

        $out = "<div class='td'><input name='" . Security::escapeAttr($info['name']) . "'";
        if ($value !== '') $out .= " value='" . Security::escapeAttr($value) . "'";
        $out .= " step='1' type='number' size='" . Security::escapeAttr((string)($info['length'] ?? 0)) . "'";
        if ($param) $out .= " $param";
        $out .= " />";
        if ($info['flag']['primary'] ?? false) {
            $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
        }
        $out .= "</div>";
        return $out;
    }

    private function mkFloat(array $info, string $value): string {
        if ($value === '' && isset($this->config->getArray('value')[$info['name']])) {
            $value = $this->config->getArray('value')[$info['name']];
        }
        $param = $this->config->getArray('param')[$info['name']] ?? '';
        $step = $this->config->getArray('param_step')[$info['name']] ?? 'any';

        $out = "<div class='td'><input name='" . Security::escapeAttr($info['name']) . "' type='number' size='" . Security::escapeAttr((string)($info['length'] ?? 0)) . "'";
        if ($value !== '') $out .= " value='" . Security::escapeAttr($value) . "'";
        if ($param) $out .= " $param";
        $out .= " step='" . Security::escapeAttr((string)$step) . "' />";
        if ($info['flag']['primary'] ?? false) {
            $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
        }
        $out .= "</div>";
        return $out;
    }

    private function mkDate(array $info, string $value): string {
        $iVal = ($value !== '0000-00-00' && $value !== '') ? " value='" . Security::escapeAttr($value) . "'" : "";
        $param = $this->config->getArray('param')[$info['name']] ?? '';

        $out = "<div class='td'><input name='" . Security::escapeAttr($info['name']) . "' type='date'$iVal";
        if ($param) $out .= " $param";
        if ($info['flag']['primary'] ?? false) $out .= " required";
        $out .= " />";
        if ($info['flag']['primary'] ?? false) {
            $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "'$iVal />";
        }
        $out .= "</div>";
        return $out;
    }

    private function mkTimestamp(array $info, string $value): string {
        if ($info['flag']['onUpdate'] ?? false) {
            $ts = $value ? strtotime($value) : time();
            $now = function_exists('wp_date') ? wp_date('d.m.Y', $ts) : gmdate('d.m.Y', $ts);
            $out = "<div class='td' name='" . Security::escapeAttr($info['name']) . "'>" . Security::escapeHtml($now);
            if ($info['flag']['primary'] ?? false) {
                $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
            }
            $out .= "</div>";
        } else {
            $param = $this->config->getArray('param')[$info['name']] ?? '';
            $out = "<div class='td'><input name='" . Security::escapeAttr($info['name']) . "' class='timestamp' type='datetime-local' value='" . Security::escapeAttr($value) . "'";
            if ($param) $out .= " $param";
            $out .= " />";
            if ($info['flag']['primary'] ?? false) {
                $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
            }
            $out .= "</div>";
        }
        return $out;
    }

    private function mkTextarea(array $info, string $value): string {
        $classes = 'td textarea';
        $textareaClasses = '';

        $nameLower = strtolower($info['name']);
        $isSql = in_array($nameLower, ['sql', 'query', 'sql_query', 'mysql', 'abfrage'], true);

        $configuredEditors = $this->config->getArray('sql_editor');
        if (!empty($configuredEditors[$info['name']])) {
            $isSql = true;
        }

        if ($isSql) {
            $textareaClasses = 'sql-editor';
        }

        return "<div class='$classes'><textarea class='$textareaClasses' name='" . Security::escapeAttr($info['name']) . "'>" . Security::escapeHtml($value) . "</textarea></div>";
    }

    private function mkText(array $info, string $value): string {
        if ($value === '' && isset($this->config->getArray('value')[$info['name']])) {
            $value = $this->config->getArray('value')[$info['name']];
        }
        $param = $this->config->getArray('param')[$info['name']] ?? '';
        $pattern = $this->config->getArray('pattern')[$info['name']] ?? '';

        $out = "<div class='td'><input name='" . Security::escapeAttr($info['name']) . "' class='mkText'";
        if ($value !== '') $out .= " value='" . Security::escapeAttr($value) . "'";
        if ($pattern) $out .= " pattern='" . Security::escapeAttr($pattern) . "' title='Format: " . Security::escapeAttr($pattern) . "'";
        if ($param) $out .= " $param";
        $out .= " />";
        if ($info['flag']['primary'] ?? false) {
            $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
        }
        $out .= "</div>";
        return $out;
    }

    private function mkEnum(array $info, string $value): string {
        $table = $this->db->getMysqli()->real_escape_string($info['table']);
        $field = $this->db->getMysqli()->real_escape_string($info['name']);

        $res = $this->db->query("SHOW COLUMNS FROM `$table` WHERE Field = '$field'");
        $enumOpts = [];
        $isNull = false;

        if ($res && $finfo = $res->fetch_object()) {
            if ($finfo->Null === 'YES') $isNull = true;
            if (!empty($finfo->Type) && preg_match_all("/'((?:''|[^'])*)'/", $finfo->Type, $matches)) {
                $enumOpts = array_map(function($v) { return str_replace("''", "'", $v); }, $matches[1]);
            }
        }

        $param = $this->config->getArray('param')[$info['name']] ?? '';
        $out = "<div class='td'><select name='" . Security::escapeAttr($info['name']) . "' size='1'";
        if ($param) $out .= " $param";
        $out .= ">";

        if ($isNull) {
            $out .= "<option" . ($value === '' ? ' selected' : '') . "></option>";
        }
        foreach ($enumOpts as $opt) {
            $out .= "<option" . ($value === $opt ? ' selected' : '') . " value='" . Security::escapeAttr($opt) . "'>" . Security::escapeHtml($opt) . "</option>";
        }
        $out .= "</select>";
        if ($info['flag']['primary'] ?? false) {
            $out .= "<input type='hidden' name='prim" . Security::escapeAttr($info['name']) . "' value='" . Security::escapeAttr($value) . "' />";
        }
        $out .= "</div>";
        return $out;
    }

    private function mkBlob(array $info, string $value, ?array $infos, ?array $row): string {
        $param = $this->config->getArray('param')[$info['name']] ?? '';
        $pattern = $this->config->getArray('pattern')[$info['name']] ?? '';

        $out = "<div class='td'><input class='mkBlob' type='file' name='" . Security::escapeAttr($info['name']) . "'";
        if ($pattern) $out .= " pattern='" . Security::escapeAttr($pattern) . "' title='Format: " . Security::escapeAttr($pattern) . "'";
        if ($param) $out .= " $param";
        $out .= " />";

        $fileCode = $this->config->getArray('global')['fileCode'] ?? '[FS_FILE]';

        if (str_starts_with($value, $fileCode)) {
            $path = substr($value, strlen($fileCode));
            $fileName = substr($path, strripos($path, "/") + 1);
            $fileNameShort = (strlen($fileName) > 12) ? substr($fileName, 0, 10) . "..." : $fileName;

            if (file_exists($path)) {
                $out .= "<a href='" . Security::escapeAttr($path) . "' target='_blank'>" . Security::escapeHtml($fileNameShort) . "</a>";
            } else {
                $out .= "<a class='red' href='" . Security::escapeAttr($path) . "' target='_blank'>fehlt!</a>";
            }
        } elseif ($value !== '') {
            $out .= "<span><i>Bild-Blob vorhanden</i></span>";
        }
        $out .= "</div>";
        return $out;
    }

    private function renderDatalists(): void {
        if ($this->config->get('edit') && $this->config->get('foreignKey')) {
            $fk = $this->config->getArray('foreignKey');
            if (isset($fk['list']) && is_array($fk['list'])) {
                foreach ($fk['list'] as $id => $qryOrArray) {
                    echo "<datalist id='" . Security::escapeAttr($id) . "'>";
                    if (is_array($qryOrArray)) {
                        foreach ($qryOrArray as $val) {
                            echo "<option value='" . Security::escapeAttr((string)$val) . "'>" . Security::escapeHtml((string)$val) . "</option>";
                        }
                    } else {
                        $res = $this->db->query((string)$qryOrArray);
                        if ($res) {
                            while ($row = $res->fetch_row()) {
                                echo "<option value='" . Security::escapeAttr((string)$row[0]) . "'>";
                            }
                        }
                    }
                    echo "</datalist>";
                }
            }
        }
    }

    public function renderTable(string $table, array $fields, array $rows, bool $editMode, bool $insertMode, int $totalRows, int $page, int $limit): string {
        $this->renderDatalists();

        static $instanceCounter = 0;
        $instanceCounter++;
        $this->uid = function_exists('wp_unique_id') ? wp_unique_id('comyli_') : ('comyli_' . $instanceCounter);
        $fspId = 'fsp_' . $this->uid;

        $orderKey = Security::base64urlEncode('order');
        $dirKey = Security::base64urlEncode('d');

        $out = "<div class='table select comyli' data-comyli-uid='" . Security::escapeAttr($this->uid) . "' data-order-key='" . Security::escapeAttr($orderKey) . "' data-dir-key='" . Security::escapeAttr($dirKey) . "'>";

        // Output Filter Form for this table instance
        $out .= "<form id='" . Security::escapeAttr($fspId) . "' class='comyli-fsp-form' method='GET'>";
        $out .= "<input type='submit' style='display:none;' aria-hidden='true'>";
        if ($this->config->get('comyli_id')) {
            $out .= "<input type='hidden' name='comyli_id' value='" . Security::escapeAttr((string)$this->config->get('comyli_id')) . "'>";
        }
        if ($this->config->get('comyli_ctx')) {
            $out .= "<input type='hidden' name='comyli_ctx' value='" . Security::escapeAttr((string)$this->config->get('comyli_ctx')) . "'>";
        }
        // Retain safe GET params EXCEPT transient search, whereA01, op_, page, limit, csv, img
        $csvKey = Security::base64urlEncode('csv');
        $imgKey = Security::base64urlEncode('img');
        foreach ($_GET as $key => $val) {
            $decoded = Security::base64urlDecode((string)$key);
            if ($key === 'comyli_id' || $key === 'comyli_ctx' || $key === $csvKey || $key === $imgKey || $decoded === 'search' || str_starts_with($decoded, 'whereA01') || str_starts_with($decoded, 'op_') || $decoded === 'page' || $decoded === 'limit') {
                continue;
            }
            if (is_array($val)) {
                foreach ($val as $subVal) {
                    if (is_scalar($subVal)) {
                        $out .= "<input type='hidden' name='" . Security::escapeAttr((string)$key) . "[]' value='" . Security::escapeAttr((string)$subVal) . "'>";
                    }
                }
            } elseif (is_scalar($val)) {
                $out .= "<input type='hidden' name='" . Security::escapeAttr((string)$key) . "' value='" . Security::escapeAttr((string)$val) . "'>";
            }
        }
        $out .= "</form>";

        $visibleFieldsCount = 0;
        foreach ($fields as $f) {
            if ($this->isColVisible($table, $f['name'])) $visibleFieldsCount++;
        }

        // Pagination Menu
        if ($this->config->get('pages')) {
            $totalPages = $limit > 0 ? max(1, (int)ceil($totalRows / $limit)) : 1;
            if ($page >= $totalPages) {
                $page = $totalPages - 1;
            }

            $out .= "<div class='tr menu comyli-no-print'><div class='td seitenListe'><div>";
            $out .= "$totalRows Eintr&auml;ge | Seite: ";
            $out .= "<select form='" . Security::escapeAttr($fspId) . "' name='" . Security::base64urlEncode('page') . "' class='page'>";
            for ($i = 0; $i < $totalPages; $i++) {
                $sel = ($i === $page) ? " selected" : "";
                $out .= "<option value='$i'$sel>" . ($i + 1) . "</option>";
            }
            $out .= "</select>";

            $out .= " <select form='" . Security::escapeAttr($fspId) . "' name='" . Security::base64urlEncode('limit') . "' class='limit'>";
            $limits = [10, 50, 100, 200, 500, 1000];
            if (!in_array($limit, $limits)) $limits[] = $limit;
            sort($limits);
            foreach ($limits as $l) {
                $sel = ($l === $limit) ? " selected" : "";
                $out .= "<option value='$l'$sel>$l Zeilen</option>";
            }
            $out .= "</select>";

            // Bulk Delete Button
            if ($this->config->get('edit')) {
                $out .= " <button type='button' class='comyli-bulk-delete-btn' style='margin-left:20px; color:red; border-color:red; display:none;' title='Ausgewählte Einträge löschen'>&#10008; Ausgewählte löschen</button>";
            }

            // Export Buttons
            if ($this->config->get('csvExport')) {
                $out .= " <button type='submit' form='" . Security::escapeAttr($fspId) . "' name='" . Security::base64urlEncode('csv') . "' value='1' style='margin-left:20px;' title='Als CSV exportieren'>&#128190; CSV</button>";
            }

            $hasPK = false;
            $hasLC = false;
            foreach ($fields as $field) {
                if (!empty($field['flag']['primary'])) $hasPK = true;
                if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') $hasLC = true;
            }
            if ($hasPK && $hasLC) {
                $out .= " <div style='margin-left:auto; display:flex; align-items:center; gap:5px;'>";
                $out .= " <button type='button' class='comyli-btn-sync comyli-live-sync-btn' onclick='typeof COMYLI !== \"undefined\" && COMYLI.toggleLiveSync(this)' title='Live-Sync umschalten (Auto / An / Aus)' style='background-color:#3b82f6; color:white;'>&#128259; Live-Sync (Auto)</button>";
                $out .= " <span style='font-size:0.85em; color:#ef4444; font-weight:bold; cursor:help; padding:2px 4px; border-radius:4px; border:1px solid #ef4444;' title='Alpha-Status: Diese Funktion befindet sich in der Erprobungsphase. Es gleicht Änderungen von anderen Nutzern in Echtzeit ab. Bei aktiver Nutzung bitte auf etwaige rote Kollisions-Warnungen an den bearbeiteten Zeilen achten!'>ALPHA &#9432;</span>";
                $out .= " </div>";
            } else {
                $reason = !$hasLC
                    ? "Live-Sync nicht verfügbar: Tabelle besitzt keine Spalte 'lastChanged' (oder 'lstChanged')."
                    : "Live-Sync nicht verfügbar: Tabelle besitzt keinen Primärschlüssel (Primary Key).";
                $out .= " <div style='margin-left:auto; display:flex; align-items:center; gap:5px;'>";
                $out .= " <button type='button' class='comyli-btn-sync' disabled style='background-color:#94a3b8; color:#f1f5f9; cursor:not-allowed; opacity:0.6;' title='" . Security::escapeAttr($reason) . "'>&#128259; Live-Sync (Inaktiv)</button>";
                $out .= " <span style='font-size:0.85em; color:#64748b; font-weight:bold; cursor:help; padding:2px 5px; border-radius:4px; border:1px solid #cbd5e1;' title='" . Security::escapeAttr($reason) . "'>&#9432;</span>";
                $out .= " </div>";
            }

            $out .= "</div></div></div>";
        }

        // Search & Filter Rows
        if ($this->config->get('filter')) {
            $searchKey = Security::base64urlEncode('search');
            $searchValue = isset($_GET[$searchKey]) ? (string)Security::unslash($_GET[$searchKey]) : '';
            $out .= "<div class='tr comyli-no-print searchRow'><div class='td'><input form='" . Security::escapeAttr($fspId) . "' type='submit' value='Suchen &#9775;'></div>";
            $out .= "<div class='td volltextsuche'><input form='" . Security::escapeAttr($fspId) . "' name='" . Security::escapeAttr($searchKey) . "' value='" . Security::escapeAttr($searchValue) . "' placeholder='Volltextsuche'></div>";
            for ($i = 0; $i < max(0, $visibleFieldsCount - 1); $i++) {
                $out .= "<div class='td'></div>";
            }
            $out .= "</div>";

            $out .= "<div class='tr comyli-no-print'><div class='td' data-col='_actions'><input form='" . Security::escapeAttr($fspId) . "' class='filter' type='submit' value='Filtern'><a href='#' class='cleanFilter'>&#129529;</a></div>";
            foreach ($fields as $field) {
                if (!$this->isColVisible($table, $field['name'])) continue;

                $key = Security::base64urlEncode("whereA01" . $field['name']);
                $val = isset($_GET[$key]) ? (string)Security::unslash($_GET[$key]) : '';
                $out .= "<div class='td filterCell' data-col='" . Security::escapeAttr($field['name']) . "'>";
                if ($field['type'] != 252 || !($field['flag']['binary'] ?? false)) {
                    $out .= $this->renderFilterField($field, $val, $key, $fspId);
                }
                $out .= "</div>";
            }
            $out .= "</div>";
        }

        // 1. Header
        $currentOrder = isset($_GET[$orderKey]) ? (string)Security::unslash($_GET[$orderKey]) : '';
        $currentDir = strtoupper(isset($_GET[$dirKey]) ? (string)Security::unslash($_GET[$dirKey]) : 'ASC');

        $out .= "<div class='tr b colNames comyli-colnames-row'>";
        $out .= "<div class='td' data-col='_actions'><input type='checkbox' class='comyli-bulk-select-all' title='Alle sichtbaren auswählen'></div>";
        foreach ($fields as $field) {
            if (!$this->isColVisible($table, $field['name'])) continue;

            $colName = $field['name'];
            $nextDir = ($currentOrder === $colName && $currentDir === 'ASC') ? 'DESC' : 'ASC';
            $arrow = '';
            if ($currentOrder === $colName) {
                $arrow = $currentDir === 'ASC' ? ' &#9650;' : ' &#9660;';
            }

            $comment = $field['comment'] ?? '';
            $titleAttr = !empty($comment) ? " title='" . Security::escapeAttr($comment) . "'" : "";
            $out .= "<div class='td colNamesWrap' data-col='" . Security::escapeAttr($colName) . "'>";
            $out .= "<div class='colnames comyli-sortable'$titleAttr style='cursor:pointer; user-select:none;' data-sort-col='" . Security::escapeAttr($colName) . "' data-sort-dir='" . Security::escapeAttr($nextDir) . "'>";
            $out .= "<span>" . Security::escapeHtml($colName) . $arrow . "</span>";
            if (!empty($comment)) {
                $out .= "<span class='comyli-col-info' title='" . Security::escapeAttr($comment) . "'>&#9432;</span>";
            }
            $out .= "</div>";
            $out .= "</div>";
        }
        $out .= "</div>";

        // 2. Insert Row (Top)
        if ($insertMode && ($this->config->getArray('gui')['insert_top'] ?? false)) {
            $out .= $this->renderInsertRow($fields, $table);
        }

        // 3. Data Rows
        foreach ($rows as $row) {
            $out .= $this->renderDataRow($fields, $row, $editMode, $table);
        }

        // 4. Insert Row (Bottom)
        if ($insertMode && ($this->config->getArray('gui')['insert_bottom'] ?? true)) {
            $out .= $this->renderInsertRow($fields, $table);
        }

        $out .= "</div>";
        return $out;
    }

    public function renderDataRow(array $fields, array $row, bool $editMode, string $activeTable = ''): string {
        $out = "";

        // 1. Gather all hidden meta fields for this row (Primary Keys + lastChanged)
        $hiddenInputs = "";
        foreach ($fields as $i => $field) {
            $val = $row[$i] ?? '';
            $isPrimary = !empty($field['flag']['primary']);

            if ($isPrimary) {
                $hiddenInputs .= "<input type='hidden' name='prim" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr((string)$val) . "'>";
                if (!$editMode || ($editMode && !$this->config->get('primaryEdit'))) {
                    $hiddenInputs .= "<input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr((string)$val) . "'>";
                }
            }
            if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') {
                $hiddenInputs .= "<input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr((string)$val) . "'>";
            }
        }

        // 2. Render Row Wrapper
        if ($editMode) {
            $csrf = Security::escapeAttr(Security::generateCsrfToken());
            $out .= <<<HTML
<form class='tr formEdit' method='POST' enctype='multipart/form-data'>
    <div class='td' data-col='_actions' style='white-space:nowrap;'>
        {$hiddenInputs}
        <input type='checkbox' class='comyli-bulk-check' style='margin-right:8px;'>
        <input type='hidden' name='csrf_token' value='{$csrf}'>
        <button class='btnSave' type='submit' name='TA_btnSubmit' value='[S]'>&#10004;</button> 
        <button class='btnDel' type='submit' name='TA_btnSubmit' value='[X]'>&#10008;</button>
    </div>
HTML;
        } else {
            $out .= <<<HTML
<div class='tr formView'>
    <div class='td' data-col='_actions'>{$hiddenInputs}</div>
HTML;
        }

        // 3. Render Columns
        foreach ($fields as $i => $field) {
            $visTbl = $activeTable !== '' ? $activeTable : (string)($field['table'] ?? '');
            if (!$this->isColVisible($visTbl, $field['name'])) continue;

            $val = $row[$i] ?? '';
            $readonly = !empty($this->config->getArray('readonly')[$field['name']]) || in_array($field['name'], ['lastChanged', 'lstChanged', 'lastChangedUser'], true);
            $valueH = isset($this->config->getArray('valueH')[$field['name']]);
            $isPrimary = !empty($field['flag']['primary']);
            $isAuto = !empty($field['flag']['autoincr']);

            $cellHtml = '';
            if ($editMode && !$readonly && !$valueH && ($this->config->get('primaryEdit') || (!$isPrimary && !$isAuto))) {
                $cellHtml = $this->renderField($field, $val, null, $row);
            } elseif ($editMode && $valueH) {
                $forcedVal = $this->config->getArray('valueH')[$field['name']];
                $cellHtml = "<div class='td'><input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr((string)$forcedVal) . "'>" . Security::escapeHtml((string)$val) . "</div>";
            } else {
                $cellHtml = "<div class='td'>" . Security::escapeHtml((string)$val) . "</div>";
            }

            if (function_exists('apply_filters')) {
                $tbl = $activeTable !== '' ? $activeTable : ($field['table'] ?? '');
                $cellHtml = apply_filters('comyli_render_cell', $cellHtml, $field['name'], $val, $row, $tbl);
                $cellHtml = apply_filters("comyli_render_column_{$field['name']}", $cellHtml, $val, $row, $tbl);
            }
            if ($cb = $this->config->get('render_cell_callback')) {
                if (is_callable($cb)) {
                    $cellHtml = $cb($cellHtml, $field['name'], $val, $row, $activeTable !== '' ? $activeTable : ($field['table'] ?? ''));
                }
            }

            if (!preg_match('/^\s*<div\b[^>]*\btd\b/i', $cellHtml)) {
                $cellHtml = "<div class='td'>" . $cellHtml . "</div>";
            }
            if (!str_contains($cellHtml, 'data-col=')) {
                $cellHtml = (string)preg_replace('/^(\s*<div\b[^>]*\bclass=[\'"][^\'"]*\btd\b[^\'"]*[\'"])/i', '$1 data-col="' . Security::escapeAttr($field['name']) . '"', $cellHtml);
            }

            $out .= $cellHtml;
        }

        if ($editMode) {
            $out .= "</form>";
        } else {
            $out .= "</div>";
        }

        return $out;
    }

    private function renderInsertRow(array $fields, string $activeTable = ''): string {
        $out = "<form class='tr formAdd' method='POST' enctype='multipart/form-data'>";
        $out .= "<div class='td' data-col='_actions'>";
        $out .= "<input type='hidden' name='csrf_token' value='" . Security::escapeAttr(Security::generateCsrfToken()) . "'>";
        $out .= "<button class='btnAdd' type='submit' name='TA_btnSubmit' value='[Hinzuf]'>&#10009;</button></div>";

        foreach ($fields as $field) {
            $visTbl = $activeTable !== '' ? $activeTable : (string)($field['table'] ?? '');
            if (!$this->isColVisible($visTbl, $field['name'])) continue;

            $readonly = !empty($this->config->getArray('readonly')[$field['name']]) || in_array($field['name'], ['lastChanged', 'lstChanged', 'lastChangedUser'], true);
            $valueH = isset($this->config->getArray('valueH')[$field['name']]);
            $isAuto = !empty($field['flag']['autoincr']);

            if ((!$isAuto || $this->config->get('primaryEdit')) && !$readonly && !$valueH) {
                $default = $this->config->getArray('value')[$field['name']] ?? '';
                $cellHtml = $this->renderField($field, (string)$default);
            } elseif ($valueH) {
                $forcedVal = $this->config->getArray('valueH')[$field['name']];
                $cellHtml = "<div class='td'><input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr((string)$forcedVal) . "'>" . Security::escapeHtml((string)$forcedVal) . "</div>";
            } else {
                $cellHtml = "<div class='td'></div>";
            }
            if (!str_contains($cellHtml, 'data-col=')) {
                $cellHtml = (string)preg_replace('/^(\s*<div\b[^>]*\bclass=[\'"][^\'"]*\btd\b[^\'"]*[\'"])/i', '$1 data-col="' . Security::escapeAttr($field['name']) . '"', $cellHtml);
            }
            $out .= $cellHtml;
        }
        $out .= "</form>";
        return $out;
    }

    public function isColVisible(string $table, string $colname): bool {
        $limits = $this->config->getArray('limit_cols');
        if (!isset($limits[$table]) || !is_array($limits[$table])) return true;

        if (isset($limits[$table][$colname]) && $limits[$table][$colname] === true) {
            return true;
        }

        return in_array($colname, $limits[$table], true);
    }
}

// -----------------------------------------------------------------------------
// 6. ACTION HANDLER (Insert, Update, Delete)
// -----------------------------------------------------------------------------

class ActionHandler {
    private Database $db;
    private ConfigHandler $config;

    public function __construct(Database $db, ConfigHandler $config) {
        $this->db = $db;
        $this->config = $config;
    }

    public function handlePost(array $fields, string $table, array $postData, array $filesData): int|bool {
        if (empty($this->config->get('edit'))) {
            $this->db->customError = 'Bearbeitung ist für diese Tabelle nicht erlaubt.';
            return false;
        }

        if (!Security::verifyCsrfToken($postData['csrf_token'] ?? '')) {
            $this->db->customError = 'Sicherheitsprüfung (CSRF-Token) fehlgeschlagen. Bitte Seite neu laden.';
            return false;
        }

        $btnSubmit = $postData['TA_btnSubmit'] ?? '';
        if (!$btnSubmit) return false;

        $visChecker = new HtmlRenderer($this->config, $this->db);
        $valueHMap = $this->config->getArray('valueH');
        $valuePMap = $this->config->getArray('valueP');
        $valueMap = $this->config->getArray('value');
        $readonlyMap = $this->config->getArray('readonly');
        $postOverrideMap = $this->config->getArray('postOverride');
        $postOverrideRawMap = $this->config->getArray('postOverrideRaw');
        $primaryEdit = !empty($this->config->get('primaryEdit'));

        $insertCols = [];
        $insertPlaceholders = [];
        $updateSets = [];
        $params = [];

        $primaryKeys = [];
        $primaryParams = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $isPrimary = $field['flag']['primary'] ?? false;
            if ($isPrimary && isset($postData['prim' . $name])) {
                $primaryKeys[] = "`$name` = ?";
                $primaryParams[] = $postData['prim' . $name];
            }
        }

        // Optimistic locking check for updates
        if ($btnSubmit === '[S]') {
            $lastChangedCol = null;
            $oldLastChanged = null;
            foreach ($fields as $field) {
                if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') {
                    $lastChangedCol = $field['name'];
                    $oldLastChanged = $postData[$field['name']] ?? null;
                    break;
                }
            }
            if ($lastChangedCol && $oldLastChanged && !empty($primaryKeys)) {
                $checkSql = "SELECT `$lastChangedCol` FROM `$table` WHERE " . implode(' AND ', $primaryKeys);
                $res = $this->db->executePrepared($checkSql, $primaryParams);
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $dbLastChanged = $row[$lastChangedCol];
                    // Strip decimals from timestamps if they exist (MySQL 5.6+ can have them, PHP often lacks them)
                    $dbLastChanged = preg_replace('/\.\d+$/', '', (string)$dbLastChanged);
                    $oldLastChanged = preg_replace('/\.\d+$/', '', (string)$oldLastChanged);

                    if ($dbLastChanged !== $oldLastChanged) {
                        $this->db->customError = "Der Datensatz wurde inzwischen von jemand anderem geändert. (DB: " . ($dbLastChanged ?: 'NULL') . ", Form: " . ($oldLastChanged ?: 'NULL') . "). Bitte Seite neu laden (Ihre Änderungen gehen andernfalls verloren).";
                        return false;
                    }
                }
            }
        }

        // Evaluate dynamic token overrides (e.g. {sql_pad4:... WHERE col='{column:Col}'}) at POST time for inserts
        $resolvedDynamicOverrides = [];
        if ($btnSubmit === '[Hinzuf]' && !empty($postOverrideRawMap)) {
            $evalCols = $postData;
            foreach ($valueMap as $vk => $vv) {
                if (!isset($evalCols[$vk]) || $evalCols[$vk] === '') {
                    $evalCols[$vk] = $vv;
                }
            }
            foreach ($valuePMap as $vk => $vv) {
                if (!isset($evalCols[$vk]) || $evalCols[$vk] === '') {
                    $evalCols[$vk] = $vv;
                }
            }
            foreach ($valueHMap as $hk => $hv) {
                $evalCols[$hk] = $hv;
            }
            foreach ($postOverrideRawMap as $ok => $rawTpl) {
                if (class_exists('\\ha\\CoMyLi\\ConfigResolver')) {
                    $resolvedVal = \ha\CoMyLi\ConfigResolver::resolveTokens(
                        $rawTpl,
                        ['stdTbl' => $table, 'columns' => $evalCols],
                        $this->db->getMysqli()
                    );
                } else {
                    $resolvedVal = $postOverrideMap[$ok] ?? $rawTpl;
                }
                $resolvedDynamicOverrides[$ok] = $resolvedVal;
                $evalCols[$ok] = $resolvedVal;
            }
        }

        foreach ($fields as $field) {
            $name = $field['name'];

            // Auto-update tracking fields on save
            if ($name === 'lstChanged' || $name === 'lastChanged') {
                $insertCols[] = "`$name`";
                $insertPlaceholders[] = "CURRENT_TIMESTAMP";
                $updateSets[] = "`$name` = CURRENT_TIMESTAMP";
                continue;
            }
            if ($name === 'lastChangedUser') {
                $user = 'System';
                if (function_exists('wp_get_current_user') && \wp_get_current_user()->exists()) {
                    $user = \wp_get_current_user()->user_login;
                }
                $insertCols[] = "`$name`";
                $insertPlaceholders[] = "?";
                $updateSets[] = "`$name` = ?";
                $params[] = $user;
                continue;
            }

            $isVisible = $visChecker->isColVisible($table, $name);
            $isReadonly = !empty($readonlyMap[$name]);
            $isPrimary = !empty($field['flag']['primary']);
            $isAuto = !empty($field['flag']['autoincr']);

            $hasValueH = array_key_exists($name, $valueHMap);
            $hasDynOverride = ($btnSubmit === '[Hinzuf]') && array_key_exists($name, $resolvedDynamicOverrides);
            $hasStaticOverride = ($btnSubmit === '[Hinzuf]') && array_key_exists($name, $postOverrideMap);
            $hasInsertDefault = ($btnSubmit === '[Hinzuf]') && array_key_exists($name, $valuePMap);

            $val = null;
            if ($btnSubmit === '[Hinzuf]') {
                if ($hasDynOverride) {
                    $val = $resolvedDynamicOverrides[$name];
                } elseif ($hasStaticOverride) {
                    $val = $postOverrideMap[$name];
                } elseif ($hasValueH) {
                    $val = $valueHMap[$name];
                } elseif ($hasInsertDefault && (!isset($postData[$name]) || $postData[$name] === '' || $isReadonly || !$isVisible)) {
                    $val = $valuePMap[$name];
                } elseif (isset($postData[$name]) && $isVisible && !$isReadonly && (!$isAuto || $primaryEdit)) {
                    $val = $postData[$name];
                } else {
                    continue;
                }
            } elseif ($btnSubmit === '[S]') {
                if ($isPrimary && !$primaryEdit) {
                    continue;
                }
                if ($hasValueH) {
                    $val = $valueHMap[$name];
                } elseif (isset($postData[$name]) && $isVisible && !$isReadonly && (!$isAuto || $primaryEdit)) {
                    $val = $postData[$name];
                } else {
                    continue;
                }
            } else {
                continue;
            }

            if (($field['type'] ?? 0) == 1 && $val === '' && ($field['flag']['notNull'] ?? 0) != 0) {
                $val = '0';
            }

            if ($val === '' || $val === null) {
                if (($field['flag']['notNull'] ?? 0) == 0) {
                    $insertCols[] = "`$name`";
                    $insertPlaceholders[] = "NULL";
                    $updateSets[] = "`$name` = NULL";
                } else {
                    $insertCols[] = "`$name`";
                    $insertPlaceholders[] = "?";
                    $updateSets[] = "`$name` = ?";
                    $params[] = '';
                }
            } else {
                $insertCols[] = "`$name`";
                $insertPlaceholders[] = "?";
                $updateSets[] = "`$name` = ?";
                $params[] = (string)$val;
            }
        }

        if ($btnSubmit === '[Hinzuf]') {
            $sql = "INSERT INTO `$table` (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            return $this->db->executePrepared($sql, $params);
        }

        if ($btnSubmit === '[S]') {
            if (empty($primaryKeys)) {
                $dbg = [];
                foreach ($fields as $f) {
                    $dbg[] = $f['name'] . "(PK:" . ($f['flag']['primary'] ? '1' : '0') . ", POST:" . (isset($postData['prim' . $f['name']]) ? '1' : '0') . ")";
                }
                $this->db->customError = "Die Tabelle hat keinen erkannten Prim&auml;rschl&uuml;ssel (" . implode(', ', $dbg) . "). Speichern nicht m&ouml;glich.";
                return false;
            }
            if (empty($updateSets)) {
                return 0; // Nothing to update
            }
            $sql = "UPDATE `$table` SET " . implode(', ', $updateSets) . " WHERE " . implode(' AND ', $primaryKeys);
            $fullParams = array_merge($params, $primaryParams);
            return $this->db->executePrepared($sql, $fullParams);
        }

        if ($btnSubmit === '[X]') {
            if (empty($primaryKeys)) return false;
            $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $primaryKeys);
            return $this->db->executePrepared($sql, $primaryParams);
        }

        return false;
    }
}

// -----------------------------------------------------------------------------
// 7. APP CORE
// -----------------------------------------------------------------------------

/**
 * Main Application class for CoMyLi Standalone.
 * Coordinates routing, database interaction, action handling, and HTML rendering.
 */
class App {
    private Database $db;
    private ConfigHandler $config;
    private ActionHandler $actionHandler;

    public function __construct(array $config) {
        $this->config = new ConfigHandler($config);
        Security::setConfig($this->config);

        $noInterfaceKey = Security::base64urlEncode('noInterface');
        if (isset($_GET[$noInterfaceKey]) && Security::unslash($_GET[$noInterfaceKey]) == 1) {
            $this->config->set('noInterface', 1);
        }

        if ($this->config->get('mysqli')) {
            $this->db = new Database($this->config->get('mysqli'));
        } else {
            die('CoMyLi: No mysqli connection provided.');
        }
        $this->actionHandler = new ActionHandler($this->db, $this->config);
    }

    public function run(): void {
        $table = $this->getRequestedTable();
        $qryName = $this->getRequestedQuery();
        $isQuery = !empty($qryName);

        if (empty($table) && !$isQuery) {
            $table = (string)preg_replace('/[^a-zA-Z0-9_]/', '', (string)$this->config->get('stdTbl', ''));
        }

        if ($isQuery) {
            $this->config->set('edit', false);
            $this->config->set('insert', false);
        } elseif (empty($this->config->get('edit'))) {
            $this->config->set('insert', false);
        }

        // Fast-path Heartbeat check (avoids heavy COUNT(*) and paginated queries)
        if (isset($_GET['action']) && $_GET['action'] === 'heartbeat' && empty($this->config->get('noInterface'))) {
            if (ob_get_level()) ob_clean();
            if (!headers_sent() && !$this->config->get('is_wp')) {
                header('Content-Type: text/plain; charset=utf-8');
            }
            if (!$isQuery && !empty($table)) {
                $resMeta = $this->db->query("SELECT * FROM `$table` LIMIT 0");
                $lcCol = null;
                if ($resMeta) {
                    foreach ($resMeta->fetch_fields() as $val) {
                        if ($val->name === 'lastChanged' || $val->name === 'lstChanged') {
                            $lcCol = $val->name;
                            break;
                        }
                    }
                }
                if ($lcCol) {
                    $resMax = $this->db->query("SELECT MAX(`$lcCol`) FROM `$table`");
                    if ($resMax && $resMax->num_rows > 0) {
                        $maxTs = $resMax->fetch_row()[0];
                        echo preg_replace('/\.\d+$/', '', (string)$maxTs);
                    }
                }
            }
            if ($this->config->get('is_wp')) {
                return;
            }
            exit;
        }

        // Handle POST Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($table) && !$isQuery && $this->config->get('edit')) {
            $res = $this->db->query("SELECT * FROM `$table` LIMIT 0");
            $fields = [];
            if ($res) {
                foreach ($res->fetch_fields() as $val) {
                    $fields[] = FieldHandler::getFieldInfo($val, $this->db);
                }
            }
            $postData = Security::unslash($_POST);
            $success = $this->actionHandler->handlePost($fields, $table, $postData, $_FILES);

            if ($this->config->get('noInterface')) {
                if (ob_get_level()) ob_clean();

                if ($success !== false) {
                    http_response_code(200);
                    $newTs = '';
                    $lcCol = null;
                    $pkWheres = [];
                    $pkVals = [];
                    $primaryEdit = !empty($this->config->get('primaryEdit'));
                    foreach ($fields as $field) {
                        if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') {
                            $lcCol = $field['name'];
                        }
                        if (!empty($field['flag']['primary'])) {
                            $pkName = $field['name'];
                            if ($primaryEdit && isset($postData[$pkName]) && $postData[$pkName] !== '') {
                                $pkWheres[] = "`$pkName` = ?";
                                $pkVals[] = $postData[$pkName];
                            } elseif (isset($postData['prim' . $pkName])) {
                                $pkWheres[] = "`$pkName` = ?";
                                $pkVals[] = $postData['prim' . $pkName];
                            }
                        }
                    }
                    if ($lcCol && !empty($pkWheres)) {
                        $resTs = $this->db->executePrepared("SELECT `$lcCol` FROM `$table` WHERE " . implode(' AND ', $pkWheres), $pkVals);
                        if ($resTs && $resTs->num_rows > 0) {
                            $newTs = $resTs->fetch_row()[0];
                            $newTs = preg_replace('/\.\d+$/', '', (string)$newTs);
                        }
                    }

                    if (is_int($success)) {
                        if ($success > 0) {
                            echo "Daten gespeichert! ($success Zeile(n))";
                        } else {
                            echo "Unverändert. (0 Zeilen aktualisiert)";
                        }
                    } else {
                        echo "Daten gespeichert!";
                    }
                    if ($newTs) {
                        echo "|TS|" . $newTs;
                    }
                    return;
                } else {
                    http_response_code(400);
                    $err = $this->db->customError ?: $this->db->getMysqli()->error;
                    echo "Fehler beim Speichern! " . Security::escapeHtml($err);
                    return;
                }
            }
        }

        // Fallback for noInterface requests that did not trigger a POST action
        if ($this->config->get('noInterface')) {
            if (ob_get_level()) ob_clean();

            http_response_code(400);
            echo "Fehler: Keine gültige Aktion für AJAX-Anfrage gefunden.";
            return;
        }

        // Handle Routing
        $isCsv = (isset($_GET[Security::base64urlEncode('csv')]) ? Security::unslash($_GET[Security::base64urlEncode('csv')]) : '') === '1';
        $isImg = (isset($_GET[Security::base64urlEncode('img')]) ? Security::unslash($_GET[Security::base64urlEncode('img')]) : '') === '1';

        if ($isCsv && $this->config->get('csvExport')) {
            $this->renderCsv($table);
        } elseif ($isImg && !empty($_GET[Security::base64urlEncode('src')])) {
            $this->renderBlob();
        } else {
            $this->renderUi($table);
        }
    }

    private function renderCsv(string $table): void {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $qryName = $this->getRequestedQuery();
        $isQuery = !empty($qryName);
        if ($isQuery) {
            $sqlRaw = FieldHandler::resolveSQL($qryName, $this->config);
            if (empty($sqlRaw)) {
                http_response_code(404);
                echo 'Abfrage nicht gefunden.';
                exit;
            }
            $fromClause = "FROM ($sqlRaw\n) AS subq";
        } else {
            if (empty($table)) {
                http_response_code(404);
                echo 'Keine Tabelle ausgewählt.';
                exit;
            }
            $fromClause = "FROM `$table`";
        }

        $fields = [];
        $metaRes = $this->db->query("SELECT * $fromClause LIMIT 0");
        if (!$metaRes) {
            http_response_code(500);
            echo 'Fehler beim Laden der CSV-Daten: ' . Security::escapeHtml($this->db->getError());
            exit;
        }
        foreach ($metaRes->fetch_fields() as $val) {
            $fields[] = FieldHandler::getFieldInfo($val, $this->db);
        }

        $htmlRenderer = new HtmlRenderer($this->config, $this->db);
        $visibleColNames = [];
        foreach ($fields as $f) {
            if ($htmlRenderer->isColVisible($table, $f['name'])) {
                $visibleColNames[] = $f['name'];
            }
        }

        $nameParts = [];
        if (!empty($table)) {
            $nameParts[] = preg_replace('/[^a-zA-Z0-9_-]/', '_', $table);
        }
        if ($isQuery) {
            $qLabel = $qryName;
            if (strpos($qryName, 'cpt_') === 0 && function_exists('get_post')) {
                $cptId = (int)substr($qryName, 4);
                $cptPost = get_post($cptId);
                if ($cptPost) {
                    $qLabel = $cptPost->post_name ?: $cptPost->post_title;
                }
            }
            $cleanQ = preg_replace('/[^a-zA-Z0-9_-]/', '_', $qLabel);
            $nameParts[] = 'abfrage_' . $cleanQ;
        }

        $baseName = !empty($nameParts) ? implode('_', $nameParts) : 'export';
        $baseName = preg_replace('/_+/', '_', trim($baseName, '_'));
        $timestamp = function_exists('wp_date') ? wp_date('Y-m-d_H-i-s') : gmdate('Y-m-d_H-i-s');
        $filename = $baseName . '_' . $timestamp . '.csv';

        $filterHandler = new FilterHandler($this->config);
        $filterData = $filterHandler->getWhereClause($fields);

        // Sorting
        $orderKey = Security::base64urlEncode('order');
        $dirKey = Security::base64urlEncode('d');
        $orderBy = isset($_GET[$orderKey]) ? (string)Security::unslash($_GET[$orderKey]) : '';
        $dir = strtoupper(isset($_GET[$dirKey]) ? (string)Security::unslash($_GET[$dirKey]) : '');

        $orderSql = '';
        if (!empty($orderBy) && in_array($dir, ['ASC', 'DESC'], true)) {
            $valid = false;
            foreach ($fields as $f) {
                if ($f['name'] === $orderBy) {
                    $valid = true;
                    break;
                }
            }
            if ($valid) {
                $orderSql = " ORDER BY `$orderBy` $dir";
            }
        }

        $selectCols = '*';
        if (!empty($visibleColNames) && (!$isQuery || count($visibleColNames) < count($fields))) {
            $selectCols = implode(', ', array_map(static fn($c) => "`$c`", $visibleColNames));
        } elseif (!empty($fields) && empty($visibleColNames)) {
            $visibleColNames = [];
        }

        $res = null;
        if (!empty($visibleColNames)) {
            $sql = "SELECT $selectCols $fromClause" . $filterData['sql'] . $orderSql;
            $res = $this->db->executePrepared($sql, $filterData['params']);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Unified UTF-8 BOM + semicolon delimiter for Microsoft Excel (matching QueryScheduler)
        echo "\xEF\xBB\xBF";

        $fp = fopen('php://output', 'w');
        if ($fp !== false) {
            if (!empty($visibleColNames)) {
                fputcsv($fp, $visibleColNames, ';');
            }
            if ($res && !empty($visibleColNames)) {
                while ($row = $res->fetch_assoc()) {
                    $line = [];
                    foreach ($visibleColNames as $colName) {
                        $line[] = $row[$colName] ?? '';
                    }
                    fputcsv($fp, $line, ';');
                }
            }
            fclose($fp);
        }
        exit;
    }

    private function renderBlob(): void {
        // Implementation for Blob image serving
        exit;
    }

    /**
     * Normalizes configured whitelist tables from sequential or associative ConfigHandler arrays.
     *
     * @return string[]
     */
    private function getConfiguredTables(): array {
        $rawTables = $this->config->getArray('tables');
        $tables = [];
        foreach ($rawTables as $k => $v) {
            if ($v === true && is_string($k) && $k !== '') {
                $tables[] = (string)preg_replace('/[^a-zA-Z0-9_]/', '', $k);
            } elseif (is_string($v) && $v !== '') {
                $tables[] = (string)preg_replace('/[^a-zA-Z0-9_]/', '', $v);
            }
        }
        return array_values(array_unique(array_filter($tables)));
    }

    private function getRequestedTable(): string {
        $tables = $this->getConfiguredTables();
        $raw = isset($_GET[Security::base64urlEncode('table')]) ? (string)Security::unslash($_GET[Security::base64urlEncode('table')]) : '';
        if ($raw === '') {
            $raw = (string)($this->config->get('table', '') ?: $this->config->get('stdTbl', ''));
        }
        if ($raw === '') {
            return $tables[0] ?? '';
        }

        $rawClean = (string)preg_replace('/[^a-zA-Z0-9_]/', '', $raw);
        $decoded = Security::base64urlDecode($raw);
        $decodedClean = (string)preg_replace('/[^a-zA-Z0-9_]/', '', $decoded);

        // Strictly enforce configured table whitelist to prevent URL parameter tampering (e.g. ?table=wp_users)
        if (!empty($tables)) {
            if ($decodedClean !== '' && in_array($decodedClean, $tables, true)) {
                return $decodedClean;
            }
            if ($rawClean !== '' && in_array($rawClean, $tables, true)) {
                return $rawClean;
            }
            $fallback = (string)preg_replace('/[^a-zA-Z0-9_]/', '', (string)$this->config->get('stdTbl', ''));
            if ($fallback !== '' && in_array($fallback, $tables, true)) {
                return $fallback;
            }
            return $tables[0];
        }

        // No whitelist: only trust explicitly configured single table, never arbitrary URL param
        $cfgTbl = (string)($this->config->get('table', '') ?: $this->config->get('stdTbl', ''));
        return $cfgTbl !== '' ? (string)preg_replace('/[^a-zA-Z0-9_]/', '', $cfgTbl) : '';
    }

    private function getRequestedQuery(): string {
        if (isset($_GET[Security::base64urlEncode('qry')])) {
            $raw = (string)Security::unslash($_GET[Security::base64urlEncode('qry')]);
            $decoded = Security::base64urlDecode($raw);
            if ($decoded !== '' && Security::base64urlEncode($decoded) === rtrim($raw, '=')) {
                return $decoded;
            }
            return $raw;
        }
        return (string)($this->config->get('query', '') ?: ($this->config->get('qry', '') ?: $this->config->get('stdQry', '')));
    }

    private function handleSyncState(string $table, array $fields, array $dbRows, int $totalRows, int $page, int $limit): void {
        if (ob_get_level()) ob_clean();

        if (!headers_sent() && !$this->config->get('is_wp')) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $clientRowsJson = isset($_POST['clientRows']) ? (string)Security::unslash($_POST['clientRows']) : '{}';
        $clientRows = json_decode($clientRowsJson, true) ?: [];

        $htmlRenderer = new HtmlRenderer($this->config, $this->db);
        $editMode = (bool)$this->config->get('edit');

        $pkIndex = -1;
        $lcIndex = -1;
        foreach ($fields as $idx => $field) {
            if (!empty($field['flag']['primary'])) $pkIndex = $idx;
            if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') $lcIndex = $idx;
        }

        if ($pkIndex === -1 || $lcIndex === -1) {
            echo json_encode(['error' => 'Missing PK or lastChanged']);
            if ($this->config->get('is_wp')) return;
            exit;
        }

        $upsert = [];
        $delete = [];
        $seenClientIds = [];

        foreach ($dbRows as $row) {
            $id = (string)$row[$pkIndex];
            $dbLastChanged = (string)$row[$lcIndex];
            $dbLastChanged = preg_replace('/\.\d+$/', '', $dbLastChanged); // Normalize timestamp

            $seenClientIds[$id] = true;

            $needsUpdate = false;
            if (!isset($clientRows[$id])) {
                $needsUpdate = true; // New row
            } else {
                $clientLastChanged = preg_replace('/\.\d+$/', '', (string)$clientRows[$id]);
                if ($dbLastChanged !== $clientLastChanged) {
                    $needsUpdate = true; // Updated row
                }
            }

            if ($needsUpdate) {
                $html = $htmlRenderer->renderDataRow($fields, $row, $editMode, $table);
                $upsert[] = ['id' => $id, 'html' => $html, 'ts' => $dbLastChanged];
            }
        }

        foreach ($clientRows as $clientId => $ts) {
            if (!isset($seenClientIds[$clientId])) {
                $delete[] = $clientId;
            }
        }

        echo json_encode([
            'upsert' => $upsert,
            'delete' => $delete
        ]);
        if ($this->config->get('is_wp')) return;
        exit;
    }

    private function renderUi(string $table): void {
        if ($this->config->get('noInterface')) return;

        $isSyncState = (isset($_GET['action']) && $_GET['action'] === 'syncState' && $_SERVER['REQUEST_METHOD'] === 'POST');

        if (!$isSyncState) {
            // Base scripts and styles (only for standalone mode; in WordPress they are enqueued via Assets.php)
            if (!$this->config->get('is_wp')) {
                if ($css = $this->config->getArray('path')['css'] ?? '') {
                    echo "<link rel='stylesheet' href='" . Security::escapeAttr($css) . "'>";
                }
                if ($customCss = $this->config->get('custom_css')) {
                    $cleanCss = trim(preg_replace('/<\/?\s*style\b[^>]*>/i', '', (string)$customCss));
                    echo "<style class='comyli-custom-css'>\n" . strip_tags($cleanCss) . "\n</style>";
                }
                if ($customJs = $this->config->get('custom_js')) {
                    $cleanJs = trim(preg_replace('/<\/?\s*script\b[^>]*>/i', '', (string)$customJs));
                    echo "<script class='comyli-custom-js'>\n" . $cleanJs . "\n</script>";
                }
                if ($jquery = $this->config->getArray('path')['jquery'] ?? '') {
                    echo "<script src='" . Security::escapeAttr($jquery) . "'></script>";
                }
                if ($js = $this->config->getArray('path')['js'] ?? '') {
                    echo "<script src='" . Security::escapeAttr($js) . "?v=" . filemtime(__DIR__ . '/functions.js') . "'></script>";
                }
            }
            if ($prismcss = $this->config->getArray('path')['prismcss'] ?? '') {
                echo "<link rel='stylesheet' href='" . Security::escapeAttr($prismcss) . "'>";
            }
            if ($codejar = $this->config->getArray('path')['codejar'] ?? '') {
                echo "<script src='" . Security::escapeAttr($codejar) . "'></script>";
            }
            if ($prismjs = $this->config->getArray('path')['prismjs'] ?? '') {
                echo "<script src='" . Security::escapeAttr($prismjs) . "'></script>";
            }
            if ($prismsql = $this->config->getArray('path')['prismsql'] ?? '') {
                echo "<script src='" . Security::escapeAttr($prismsql) . "'></script>";
            }

            $configuredTables = $this->getConfiguredTables();
            $hasSidebar = !empty($this->config->get('listQueries')) || !empty($this->config->get('listTables'));
            $isClean = ($this->config->get('layout') === 'clean') && !$hasSidebar;
            $layoutClass = 'comyli comyli-layout';
            if ($isClean) {
                $layoutClass .= ' comyli-clean-layout comyli-embedded-grid';
            }

            $envAttr = '';
            if ($wpEnv = $this->config->get('wp_env')) {
                $envJson = function_exists('wp_json_encode') ? wp_json_encode($wpEnv) : json_encode($wpEnv);
                $envAttr = " data-comyli-env='" . Security::escapeAttr((string)$envJson) . "'";
            }
            echo "<div class='$layoutClass'$envAttr>";

            if ($hasSidebar) {
                echo "<div class='comyli-sidebar'>";

                if ($this->config->get('listTables') && !empty($configuredTables)) {
                    echo "<div class='comyli-sidebar-section'>";
                    echo "<h4 class='comyli-sidebar-title'>Tabellen</h4>";
                    echo "<div class='comyli-nav-list'>";
                    $activeTbl = $this->getRequestedTable();
                    foreach ($configuredTables as $t) {
                        if (!empty($t)) {
                            $url = function_exists('add_query_arg')
                                ? esc_url(add_query_arg([Security::base64urlEncode('table') => Security::base64urlEncode($t), Security::base64urlEncode('qry') => false]))
                                : "?" . Security::base64urlEncode('table') . "=" . Security::base64urlEncode($t);
                            $active = ($activeTbl === $t) ? 'active' : '';
                            echo "<a href='$url' class='comyli-nav-link $active'>" . Security::escapeHtml($t) . "</a>";
                        }
                    }
                    echo "</div></div>";
                }

                if (!empty($this->config->get('listQueries'))) {
                    $cptQueries = [];
                    $tableId = (int)($this->config->get('comyli_id') ?: ($this->config->get('id') ?: 0));
                    if (class_exists('\\ha\\CoMyLi\\QueryManager')) {
                        $postStatus = (function_exists('current_user_can') && current_user_can('edit_posts')) ? ['publish', 'draft'] : 'publish';

                        $args = [
                            'post_type'      => \ha\CoMyLi\QueryManager::POST_TYPE,
                            'posts_per_page' => -1,
                            'post_status'    => $postStatus,
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        ];

                        if ($tableId > 0) {
                            $useWpDb = !empty($this->config->get('use_wp_db')) || empty($this->config->get('dbserver'));
                            if ($useWpDb) {
                                $args['meta_query'] = [
                                    'relation' => 'OR',
                                    [
                                        'key'     => \ha\CoMyLi\QueryManager::META_TABLE_ID,
                                        'value'   => $tableId,
                                        'compare' => '=',
                                    ],
                                    [
                                        'key'     => \ha\CoMyLi\QueryManager::META_TABLE_ID,
                                        'value'   => 0,
                                        'compare' => '=',
                                    ],
                                    [
                                        'key'     => \ha\CoMyLi\QueryManager::META_TABLE_ID,
                                        'value'   => '',
                                        'compare' => '=',
                                    ],
                                    [
                                        'key'     => \ha\CoMyLi\QueryManager::META_TABLE_ID,
                                        'compare' => 'NOT EXISTS',
                                    ],
                                ];
                            } else {
                                $args['meta_query'] = [
                                    [
                                        'key'     => \ha\CoMyLi\QueryManager::META_TABLE_ID,
                                        'value'   => $tableId,
                                        'compare' => '=',
                                    ],
                                ];
                            }
                            $cptQueries = get_posts($args);

                            // If no queries specifically tied to this external table, fallback to any unassigned queries
                            if (empty($cptQueries) && !$useWpDb) {
                                $fallbackArgs = $args;
                                $fallbackArgs['meta_query'] = [
                                    'relation' => 'OR',
                                    [
                                        'key'     => \ha\CoMyLi\QueryManager::META_TABLE_ID,
                                        'value'   => 0,
                                        'compare' => '=',
                                    ],
                                    [
                                        'key'     => \ha\CoMyLi\QueryManager::META_TABLE_ID,
                                        'compare' => 'NOT EXISTS',
                                    ],
                                ];
                                $cptQueries = get_posts($fallbackArgs);
                            }
                        } else {
                            $cptQueries = get_posts($args);
                        }
                    }

                    if (!empty($cptQueries)) {
                        $activeQry = $this->getRequestedQuery();
                        $sidebarItemsHtml = '';

                        foreach ($cptQueries as $cq) {
                            $slug = $cq->post_name ?: sanitize_title($cq->post_title);
                            if (class_exists('\\ha\\CoMyLi\\Permissions') && !\ha\CoMyLi\Permissions::can_view_query($slug, $tableId ?: null)) {
                                continue;
                            }
                            $url = function_exists('add_query_arg')
                                ? esc_url(add_query_arg([Security::base64urlEncode('qry') => Security::base64urlEncode($slug), Security::base64urlEncode('table') => false]))
                                : "?" . Security::base64urlEncode('qry') . "=" . Security::base64urlEncode($slug);
                            $active = ($activeQry === $slug || $activeQry === $cq->post_name || $activeQry === sanitize_title($cq->post_title)) ? 'active' : '';
                            $sidebarItemsHtml .= "<div class='comyli-nav-item $active'>";
                            $sidebarItemsHtml .= "<a href='$url' class='comyli-nav-link'>⚡ " . Security::escapeHtml($cq->post_title) . "</a>";
                            $sidebarItemsHtml .= "</div>";
                        }

                        if ($sidebarItemsHtml !== '') {
                            echo "<div class='comyli-sidebar-section'>";
                            echo "<h4 class='comyli-sidebar-title'>Abfragen</h4>";
                            echo "<div class='comyli-nav-list'>" . $sidebarItemsHtml . "</div></div>";
                        }
                    }
                }
                echo "</div>"; // End sidebar
            }

            echo "<div class='comyli-main'>";
        }

        $qryName = $this->getRequestedQuery();
        $isQuery = !empty($qryName);
        if ($isQuery) {
            $sqlRaw = FieldHandler::resolveSQL($qryName, $this->config);
            if (empty($sqlRaw)) {
                if (!$isSyncState) {
                    echo "<p>Abfrage <strong>" . Security::escapeHtml($qryName) . "</strong> nicht gefunden.</p></div></div>";
                }
                return;
            }
            $fromClause = "FROM ($sqlRaw\n) AS subq";
        } else {
            $fromClause = "FROM `$table`";
            if (empty($table)) {
                if (!$isSyncState) {
                    echo "<p>Keine Tabelle ausgew&auml;hlt.</p></div></div>";
                }
                return;
            }
        }

        // Fetch schema for filters and table
        $res = $this->db->query("SELECT * $fromClause LIMIT 0");
        $fields = [];
        if ($res) {
            foreach ($res->fetch_fields() as $val) {
                $fields[] = FieldHandler::getFieldInfo($val, $this->db);
            }
        }

        // Apply filters
        $filterHandler = new FilterHandler($this->config);
        $filterData = $filterHandler->getWhereClause($fields);

        // Pagination logic
        $pageKey = Security::base64urlEncode('page');
        $limitKey = Security::base64urlEncode('limit');
        $page = isset($_GET[$pageKey]) ? (int)Security::unslash($_GET[$pageKey]) : 0;
        if ($page < 0) $page = 0;

        $limit = isset($_GET[$limitKey]) ? (int)Security::unslash($_GET[$limitKey]) : (int)$this->config->get('limit', 100);
        if ($limit <= 0) $limit = 100;

        $offset = $page * $limit;

        $countSql = "SELECT COUNT(*) $fromClause" . $filterData['sql'];
        $countRes = $this->db->executePrepared($countSql, $filterData['params']);
        $totalRows = $countRes ? (int)$countRes->fetch_row()[0] : 0;

        // Sorting
        $orderKey = Security::base64urlEncode('order');
        $dirKey = Security::base64urlEncode('d');
        $orderBy = isset($_GET[$orderKey]) ? (string)Security::unslash($_GET[$orderKey]) : '';
        $dir = strtoupper(isset($_GET[$dirKey]) ? (string)Security::unslash($_GET[$dirKey]) : '');

        $orderSql = '';
        if (!empty($orderBy) && in_array($dir, ['ASC', 'DESC'], true)) {
            $valid = false;
            foreach ($fields as $f) {
                if ($f['name'] === $orderBy) {
                    $valid = true;
                    break;
                }
            }
            if ($valid) {
                $orderSql = " ORDER BY `$orderBy` $dir";
            }
        }

        $sql = "SELECT * $fromClause" . $filterData['sql'] . $orderSql . " LIMIT $limit OFFSET $offset";
        $res = $this->db->executePrepared($sql, $filterData['params']);

        if (!$res) {
            if ($isSyncState) {
                if (ob_get_level()) ob_clean();
                echo json_encode(['error' => $this->db->getError()]);
                if ($this->config->get('is_wp')) return;
                exit;
            }
            echo "<div class='error'>Fehler beim Laden der Tabelle: " . Security::escapeHtml($this->db->getError()) . "</div></div></div>";
            return;
        }

        $rows = [];
        while ($row = $res->fetch_row()) {
            $rows[] = $row;
        }

        if ($isSyncState) {
            $this->handleSyncState($table, $fields, $rows, $totalRows, $page, $limit);
            return;
        }

        $htmlRenderer = new HtmlRenderer($this->config, $this->db);
        echo $htmlRenderer->renderTable($table, $fields, $rows, (bool)$this->config->get('edit'), (bool)$this->config->get('insert'), $totalRows, $page, $limit);
        echo "</div>"; // End comyli-main
        echo "</div>"; // End comyli-layout
    }
}

// -----------------------------------------------------------------------------
// EXECUTION
// -----------------------------------------------------------------------------

// Ensure $config is available in the global scope (passed by included file in standalone mode)
if (!defined('ABSPATH') && isset($config) && is_array($config) && empty($config['prevent_auto_run'])) {
    $app = new App($config);
    $app->run();
}