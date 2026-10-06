<?php
/**
 * Configurable-MySQL-Light-Interface - CoMyLI (Refactored)
 * 
 * Standalone Single-File SQL Administrator.
 * Secures inputs via Prepared Statements and outputs via strict Escaping.
 */
namespace Comyli\Standalone;

if (session_status() === PHP_SESSION_NONE) {
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
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    public static function unslash($value) {
        if (self::$config && $cb = self::$config->get('unslash')) {
            return $cb($value);
        }
        // Standalone fallback: do NOT strip slashes, because modern PHP doesn't slash inputs.
        // If we strictly wanted to support PHP < 5.4 we would check get_magic_quotes_gpc(), but it's 2026.
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
            // error_log("CoMyLi SQL Prepare Error: " . $this->mysqli->error . " | SQL: $sql");
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
            // error_log("CoMyLi SQL Execute Error: " . $stmt->error . " | SQL: $sql");
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result(); // Only works for SELECT, SHOW, DESCRIBE
        if ($result === false) {
            // It was an INSERT/UPDATE/DELETE or failed
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected >= 0 ? $affected : false;
        }

        $stmt->close();
        return $result;
    }

    public function getError(): string {
        if ($this->customError !== '') return $this->customError;
        return $this->mysqli->error;
    }
}

// -----------------------------------------------------------------------------
// 3. CONFIGURATION HANDLER
// -----------------------------------------------------------------------------

/**
 * Handles the configuration array for the CoMyLi Standalone Application.
 * 
 * Key configuration options include:
 * - is_wp (bool): Whether the app is running inside WordPress (disables standalone assets).
 * - noInterface (bool): If true, renders raw data without HTML wrappers.
 * - edit (bool): Enable/disable edit mode for tables.
 * - insert (bool): Enable/disable inserting new rows.
 * - primaryEdit (bool): Allow editing of primary key fields.
 * - readonly (array): Fields to mark as read-only.
 * - valueH (array): Hidden static values for specific fields (e.g. ['field' => 'value']).
 * - tables (array): Allowed tables.
 * - stdTbl (string): Default table to load.
 */
class ConfigHandler {
    private array $config;

    public function __construct(array $override = []) {
        $defaults = [
            'version' => 20260824,
            'sqlLogDuration' => 60 * 60 * 24 * 7,
            'path' => [
                
                'codejar' => 'assets/codejar.js',
                'prismjs' => 'assets/prism.min.js',
                'prismcss' => 'assets/prism.min.css',
                'prismsql' => 'assets/prism-sql.min.js',
                'chartJS' => '/common/chart.js',
                'css' => 'style.css',
                'js' => 'functions.js',
                'jquery' => '/common/jquery.min.js'
            ],
            'localPath' => [
                'files' => './files/'
            ],
            'format' => [
                'decimalSeparator' => ','
            ],
            'db' => 'trainer_lexikon',
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

        // LDAP default logic if session is active
        if (isset($_SESSION["ldap"]["cn"][0]) || isset($_SESSION["ldap"]["samaccountname"][0])) {
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

        // Format conversion (TINYINT -> BOOL)
        if ($info['type'] == 1 && isset($_POST[$val->name]) && Security::unslash($_POST[$val->name]) === '') {
            $_POST[$val->name] = 0;
        }

        // Default detection
        if (empty($info['def']) && !$info['flag']['noDefault'] && !empty($val->table)) {
            $table = $db->getMysqli()->real_escape_string($val->table);
            $field = $db->getMysqli()->real_escape_string($val->name);
            $res = $db->query("SHOW COLUMNS FROM `$table` WHERE Field = '$field'");
            if ($res && $row = $res->fetch_assoc()) {
                $info['default'] = $row['Default'];
            }
        }

        return $info;
    }

    /**
     * Resolves custom [QRY] markers recursively.
     */
    public static function resolveSQL(string $qryName, ConfigHandler $config): string {
        $path = $config->get('queries') . $qryName . '.sql';
        if (!file_exists($path)) {
            return '';
        }
        $qry = file_get_contents($path);
        
        $match = preg_split("/(\[QRY\]|\[QUERY\])(.*?)(\[\/QRY\]|\[\/QUERY\])/ms", $qry, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (sizeof($match) > 1) {
            $resolved = '';
            for ($i = 0; $i < sizeof($match); $i = $i + 4) {
                $resolved .= $match[$i];
                if (!empty($match[$i + 2])) {
                    $resolved .= "(" . self::resolveSQL($match[$i + 2], $config) . ") AS " . $match[$i + 2] . " ";
                }
            }
            $qry = $resolved;
        }
        return $qry;
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
        
        if ($type == 253 && isset($this->config->getArray('foreignKey')['input'][$info['name']])) {
            return $this->mkSelect($info, $value, 'input');
        } elseif (isset($this->config->getArray('foreignKey')['suggestion'][$info['name']])) {
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

    public function renderFilterField(array $info, string $value, string $key): string {
        $type = $info['type'];
        // Date (10), Datetime (12), Timestamp (7)
        if (in_array($type, [7, 10, 12])) {
            $keyFrom = Security::base64urlEncode("whereA01from_" . $info['name']);
            $keyTo = Security::base64urlEncode("whereA01to_" . $info['name']);
            $valFrom = isset($_GET[$keyFrom]) ? Security::escapeAttr(Security::unslash($_GET[$keyFrom])) : '';
            $valTo = isset($_GET[$keyTo]) ? Security::escapeAttr(Security::unslash($_GET[$keyTo])) : '';
            
            $out = "<div style='display:flex; flex-direction:column; gap:4px; width:100%;'>";
            $out .= "<div style='display:flex; align-items:center;'><span style='font-size:0.8em; margin-right:4px; width:25px; color:#64748b;'>Von</span> <input form='fsp' class='filter' name='" . Security::escapeAttr($keyFrom) . "' type='date' value='$valFrom' style='font-size:0.9em; padding:2px; flex:1;' /></div>";
            $out .= "<div style='display:flex; align-items:center;'><span style='font-size:0.8em; margin-right:4px; width:25px; color:#64748b;'>Bis</span> <input form='fsp' class='filter' name='" . Security::escapeAttr($keyTo) . "' type='date' value='$valTo' style='font-size:0.9em; padding:2px; flex:1;' /></div>";
            $out .= "</div>";
            return $out;
        }
        
        $baseAttr = "form='fsp' name='" . Security::escapeAttr($key) . "' class='filter' size='1'";
        
        // Enum Dropdown
        if (!empty($info['flag']['enum']) || $type == 254) {
            $table = $this->db->getMysqli()->real_escape_string($info['table']);
            $field = $this->db->getMysqli()->real_escape_string($info['name']);
            $res = $this->db->query("SHOW COLUMNS FROM `$table` WHERE Field = '$field'");
            if ($res && $finfo = $res->fetch_object()) {
                $typeStr = substr($finfo->Type, strpos($finfo->Type, "'"));
                $typeStr = substr($typeStr, 0, -1);
                $parts = explode("','", substr($typeStr, 1));
                
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
        
        $out = "<div class='td'>";
        $out .= "<input type='hidden' name='" . Security::escapeAttr($info['name']) . "' class='jsonInput' value='" . Security::escapeAttr($value) . "' />";
        $out .= "<select class='select2Input' size='1' data-list='" . Security::escapeAttr($info['name']) . "' multiple='multiple'";
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
            $now = $value ? gmdate("d.m.Y", strtotime($value)) : gmdate("d.m.Y");
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
        
        // Allow overriding via config if they need it for specific columns
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
            $typeStr = substr($finfo->Type, strpos($finfo->Type, "'"));
            $typeStr = substr($typeStr, 0, -1);
            $parts = explode("','", substr($typeStr, 1));
            foreach ($parts as $p) {
                $enumOpts[] = $p;
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
            $out .= "<span>";
            // Native image blobs are stripped for brevity, assuming standard FS_FILE is primarily used.
            $out .= "<i>Bild-Blob vorhanden</i>";
            $out .= "</span>";
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
                            echo "<option value='" . Security::escapeAttr($val) . "'>" . Security::escapeHtml($val) . "</option>";
                        }
                    } else {
                        $res = $this->db->query($qryOrArray);
                        if ($res) {
                            while ($row = $res->fetch_row()) {
                                echo "<option value='" . Security::escapeAttr($row[0]) . "'>";
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
        $out = "<div class='table select comyli'>";
        
        // Output Filter Form wrapping the entire table
        $out .= "<form id='fsp' method='GET'>";
        $out .= "<input type='submit' style='display:none;' aria-hidden='true'>";
        if ($this->config->get('comyli_id')) {
            $out .= "<input type='hidden' name='comyli_id' value='" . Security::escapeAttr($this->config->get('comyli_id')) . "'>";
        }
        // Retain GET params EXCEPT search, whereA01, op_, page, limit
        foreach ($_GET as $key => $val) {
            $decoded = Security::base64urlDecode($key);
            if ($key !== 'comyli_id' && $decoded !== 'search' && !str_starts_with($decoded, 'whereA01') && !str_starts_with($decoded, 'op_') && $decoded !== 'page' && $decoded !== 'limit') {
                $out .= "<input type='hidden' name='" . Security::escapeAttr($key) . "' value='" . Security::escapeAttr($val) . "'>";
            }
        }


        $out .= "</form>";

        // Pagination Menu
        if ($this->config->get('pages')) {
            $visibleFieldsCount = 0;
            foreach ($fields as $f) {
                if ($this->isColVisible($table, $f['name'])) $visibleFieldsCount++;
            }
            
            $colspan = $visibleFieldsCount + 1;
            $out .= "<div class='tr menu comyli-no-print'><div class='td seitenListe' colspan='$colspan'><div>";
            $out .= "$totalRows Eintr&auml;ge | Seite: ";
            $out .= "<select form='fsp' name='" . Security::base64urlEncode('page') . "' class='page' onchange='this.form.submit()'>";
            for ($i = 0; $i < $totalPages; $i++) {
                $sel = ($i === $page) ? " selected" : "";
                $out .= "<option value='$i'$sel>" . ($i + 1) . "</option>";
            }
            $out .= "</select>";
            
            $out .= " <select form='fsp' name='" . Security::base64urlEncode('limit') . "' class='limit' onchange='this.form.submit()'>";
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
                $out .= " <button type='button' id='comyli-bulk-delete-btn' style='margin-left:20px; color:red; border-color:red; display:none;' title='Ausgewählte Einträge löschen'>&#10008; Ausgewählte löschen</button>";
            }
            
            // Export Buttons
            if ($this->config->get('csvExport')) {
                $out .= " <button type='submit' form='fsp' name='" . Security::base64urlEncode('csv') . "' value='1' style='margin-left:20px;' title='Als CSV exportieren'>&#128190; CSV</button>";
            }
            
            $hasPK = false;
            $hasLC = false;
            foreach ($fields as $field) {
                if (!empty($field['flag']['primary'])) $hasPK = true;
                if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') $hasLC = true;
            }
            if ($hasPK && $hasLC) {
                $out .= " <div style='margin-left:auto; display:flex; align-items:center; gap:5px;'>";
                $out .= " <button type='button' id='btnLiveSync' class='comyli-btn-sync' onclick='typeof COMYLI !== \"undefined\" && COMYLI.toggleLiveSync(this)' title='Live-Sync umschalten (Auto / An / Aus)' style='background-color:#3b82f6; color:white;'>&#128259; Live-Sync (Auto)</button>";
                $out .= " <span style='font-size:0.85em; color:#ef4444; font-weight:bold; cursor:help; padding:2px 4px; border-radius:4px; border:1px solid #ef4444;' title='Alpha-Status: Diese Funktion befindet sich in der Erprobungsphase. Es gleicht �&Auml;nderungen von anderen Nutzern in Echtzeit ab. Bei aktiver Nutzung bitte auf etwaige rote Kollisions-Warnungen an den bearbeiteten Zeilen achten!'>ALPHA &#9432;</span>";
                $out .= " </div>";
            } else {
                $reason = !$hasLC 
                    ? "Live-Sync nicht verf&uuml;gbar: Tabelle besitzt keine Spalte 'lastChanged' (oder 'lstChanged')." 
                    : "Live-Sync nicht verf&uuml;gbar: Tabelle besitzt keinen Prim&auml;rschl&uuml;ssel (Primary Key).";
                $out .= " <div style='margin-left:auto; display:flex; align-items:center; gap:5px;'>";
                $out .= " <button type='button' class='comyli-btn-sync' disabled style='background-color:#94a3b8; color:#f1f5f9; cursor:not-allowed; opacity:0.6;' title='" . Security::escapeAttr($reason) . "'>&#128259; Live-Sync (Inaktiv)</button>";
                $out .= " <span style='font-size:0.85em; color:#64748b; font-weight:bold; cursor:help; padding:2px 5px; border-radius:4px; border:1px solid #cbd5e1;' title='" . Security::escapeAttr($reason) . "'>&#9432;</span>";
                $out .= " </div>";
            }
            
            $out .= "</div></div></div>";
        }

        // Search & Filter Rows
        if ($this->config->get('filter')) {
            // Search Row
            $searchKey = Security::base64urlEncode('search');
            $searchValue = isset($_GET[$searchKey]) ? Security::unslash($_GET[$searchKey]) : '';
            $out .= "<div class='tr comyli-no-print'><div class='td'><input form='fsp' type='submit' value='Suchen &#9775;'></div>";
            $out .= "<div class='td volltextsuche'><input form='fsp' name='" . Security::escapeAttr($searchKey) . "' value='" . Security::escapeAttr($searchValue) . "' placeholder='Volltextsuche'></div>";
            for ($i = 0; $i < $visibleFieldsCount - 1; $i++) {
                $out .= "<div class='td'></div>";
            }
            $out .= "</div>";

            // Filter Row
            $out .= "<div class='tr comyli-no-print'><div class='td'><input form='fsp' class='filter' type='submit' value='Filtern'><a href='#' class='cleanFilter'>&#129529;</a></div>";
            foreach ($fields as $field) {
                if (!$this->isColVisible($table, $field['name'])) continue;
                
                $key = Security::base64urlEncode("whereA01" . $field['name']);
                $val = isset($_GET[$key]) ? Security::unslash($_GET[$key]) : '';
                $out .= "<div class='td filterCell'>";
                if ($field['type'] != 252 || !($field['flag']['binary'] ?? false)) {
                    $out .= $this->renderFilterField($field, $val, $key);
                }
                $out .= "</div>";
            }
            $out .= "</div>";
        }

        // 1. Header
        $orderKey = Security::base64urlEncode('order');
        $dirKey = Security::base64urlEncode('d');
        
        $currentOrder = isset($_GET[$orderKey]) ? Security::unslash($_GET[$orderKey]) : '';
        $currentDir = strtoupper(isset($_GET[$dirKey]) ? Security::unslash($_GET[$dirKey]) : 'ASC');
        
        $out .= "<div class='tr b' id='colNames'>";
        $out .= "<div class='td'><input type='checkbox' id='comyli-bulk-select-all' title='Alle sichtbaren auswählen'></div>";
        foreach ($fields as $field) {
            if (!$this->isColVisible($table, $field['name'])) continue;
            
            $colName = $field['name'];
            $nextDir = ($currentOrder === $colName && $currentDir === 'ASC') ? 'DESC' : 'ASC';
            $arrow = '';
            if ($currentOrder === $colName) {
                $arrow = $currentDir === 'ASC' ? ' &#9650;' : ' &#9660;';
            }
            
            $jsCol = Security::escapeAttr($colName);
            
            $out .= "<div class='td colNamesWrap'><div class='colnames' style='cursor:pointer; user-select:none;' onclick='comyliSort(\"$jsCol\", \"$nextDir\")'>";
            $out .= Security::escapeHtml($colName) . $arrow;
            $out .= "</div></div>";
        }
        $out .= "</div>";
        
        $out .= "<script>
        function comyliSort(col, dir) {
            var form = document.getElementById('fsp');
            if (!form) return;
            
            var orderKey = '" . Security::escapeAttr($orderKey) . "';
            var dirKey = '" . Security::escapeAttr($dirKey) . "';
            
            var orderInput = form.querySelector('input[name=\"' + orderKey + '\"]');
            if (!orderInput) {
                orderInput = document.createElement('input');
                orderInput.type = 'hidden';
                orderInput.name = orderKey;
                form.appendChild(orderInput);
            }
            orderInput.value = col;
            
            var dirInput = form.querySelector('input[name=\"' + dirKey + '\"]');
            if (!dirInput) {
                dirInput = document.createElement('input');
                dirInput.type = 'hidden';
                dirInput.name = dirKey;
                form.appendChild(dirInput);
            }
            dirInput.value = dir;
            
            if (typeof jQuery !== 'undefined') {
                jQuery(form).submit();
            } else {
                form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
            }
        }
        </script>";

        // 2. Insert Row (Top)
        if ($insertMode && $this->config->getArray('gui')['insert_top'] ?? false) {
            $out .= $this->renderInsertRow($fields);
        }

        // 3. Data Rows
        foreach ($rows as $row) {
            $out .= $this->renderDataRow($fields, $row, $editMode);
        }

        // 4. Insert Row (Bottom)
        if ($insertMode && ($this->config->getArray('gui')['insert_bottom'] ?? true)) {
            $out .= $this->renderInsertRow($fields);
        }

        $out .= "</div>";
        return $out;
    }

        public function renderDataRow(array $fields, array $row, bool $editMode): string {
        $out = "";
        
        // 1. Gather all hidden meta fields for this row (Primary Keys + lastChanged)
        $hiddenInputs = "";
        foreach ($fields as $i => $field) {
            $val = $row[$i] ?? '';
            $isPrimary = !empty($field['flag']['primary']);
            
            if ($isPrimary) {
                $hiddenInputs .= "<input type='hidden' name='prim" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr($val) . "'>";
                if (!$editMode || ($editMode && !$this->config->get('primaryEdit'))) {
                    // In edit mode we only need the value if primaryEdit is false, but safe to always have it.
                    $hiddenInputs .= "<input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr($val) . "'>";
                }
            }
            if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') {
                $hiddenInputs .= "<input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr($val) . "'>";
            }
        }

        // 2. Render Row Wrapper
        if ($editMode) {
            $csrf = Security::escapeAttr(Security::generateCsrfToken());
            $out .= <<<HTML
<form class='tr formEdit' method='POST' enctype='multipart/form-data'>
    <div class='td' style='white-space:nowrap;'>
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
    <div class='td'>{$hiddenInputs}</div>
HTML;
        }

        // 3. Render Columns
        foreach ($fields as $i => $field) {
            if (!$this->isColVisible($field['table'], $field['name'])) continue;

            $val = $row[$i] ?? '';
            $readonly = !empty($this->config->getArray('readonly')[$field['name']]) || in_array($field['name'], ['lastChanged', 'lstChanged', 'lastChangedUser']);
            $valueH = isset($this->config->getArray('valueH')[$field['name']]);
            $isPrimary = !empty($field['flag']['primary']);
            $isAuto = !empty($field['flag']['autoincr']);
            
            if ($editMode && !$readonly && !$valueH && ($this->config->get('primaryEdit') || (!$isPrimary && !$isAuto))) {
                $out .= $this->renderField($field, $val, null, $row);
            } elseif ($editMode && $valueH) {
                $forcedVal = $this->config->getArray('valueH')[$field['name']];
                $out .= "<div class='td'><input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr($forcedVal) . "'>";
                $out .= Security::escapeHtml($val) . "</div>";
            } else {
                $out .= "<div class='td'>" . Security::escapeHtml($val) . "</div>";
            }
        }

        if ($editMode) {
            $out .= "</form>";
        } else {
            $out .= "</div>";
        }

        return $out;
    }

    private function renderInsertRow(array $fields): string {
        $out = "<form class='tr formAdd' method='POST' enctype='multipart/form-data'>";
        $out .= "<div class='td'>";
        $out .= "<input type='hidden' name='csrf_token' value='" . Security::escapeAttr(Security::generateCsrfToken()) . "'>";
        $out .= "<button class='btnAdd' type='submit' name='TA_btnSubmit' value='[Hinzuf]'>&#10009;</button></div>";
        
        foreach ($fields as $field) {
            if (!$this->isColVisible($field['table'], $field['name'])) continue;

            $readonly = !empty($this->config->getArray('readonly')[$field['name']]) || in_array($field['name'], ['lastChanged', 'lstChanged', 'lastChangedUser']);
            $valueH = isset($this->config->getArray('valueH')[$field['name']]);
            $isAuto = !empty($field['flag']['autoincr']);
            
            if ((!$isAuto || $this->config->get('primaryEdit')) && !$readonly && !$valueH) {
                $default = $this->config->getArray('value')[$field['name']] ?? '';
                $out .= $this->renderField($field, $default);
            } elseif ($valueH) {
                $forcedVal = $this->config->getArray('valueH')[$field['name']];
                $out .= "<div class='td'><input type='hidden' name='" . Security::escapeAttr($field['name']) . "' value='" . Security::escapeAttr($forcedVal) . "'>" . Security::escapeHtml($forcedVal) . "</div>";
            } else {
                $out .= "<div class='td'></div>";
            }
        }
        $out .= "</form>";
        return $out;
    }

    private function isColVisible(string $table, string $colname): bool {
        $limits = $this->config->getArray('limit_cols');
        $limits = $this->config->getArray('limit_cols');
        if (!isset($limits[$table]) || !is_array($limits[$table])) return true;
        
        // Check if column is provided as key (e.g. ['col' => true])
        if (isset($limits[$table][$colname]) && $limits[$table][$colname] === true) {
            return true;
        }
        
        // Fallback to checking if it's in the array as a value (e.g. ['col1', 'col2'])
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
        if (!Security::verifyCsrfToken($postData['csrf_token'] ?? '')) {
                        // error_log('CoMyLi: CSRF Token validation failed.');
            return false;
        }

        $btnSubmit = $postData['TA_btnSubmit'] ?? '';
                if (!$btnSubmit) return false;
        $insertCols = [];
        $insertPlaceholders = [];
        $updateSets = [];
        $params = [];
        
        $primaryKeys = [];
        $primaryParams = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $isPrimary = $field['flag']['primary'] ?? false;
            if ($isPrimary && isset($postData['prim' . $name]) && isset($postData[$name])) {
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
                $res = $this->db->executePrepared($checkSql, $primaryParams, true);
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $dbLastChanged = $row[$lastChangedCol];
                    // Strip decimals from timestamps if they exist (MySQL 5.6+ can have them, PHP often lacks them)
                    $dbLastChanged = preg_replace('/\.\d+$/', '', $dbLastChanged);
                    $oldLastChanged = preg_replace('/\.\d+$/', '', $oldLastChanged);
                    
                                        if ($dbLastChanged !== $oldLastChanged) {
                        error_log("COMYLI DBG: Optimistic Lock Failed! Table=$table PK=" . json_encode($primaryKeys) . " DB=$dbLastChanged Form=$oldLastChanged"); $this->db->customError = "Der Datensatz wurde inzwischen von jemand anderem geändert. (DB: " . ($dbLastChanged ?: 'NULL') . ", Form: " . ($oldLastChanged ?: 'NULL') . "). Bitte Seite neu laden (Ihre Ä&Auml;nderungen gehen andernfalls verloren).";
                        return false;
                    }
                }
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

            // Skip fields that are not submitted
            if (!isset($postData[$name])) {
                continue;
            }

            // Handle overrides on insert
            if (isset($this->config->getArray('postOverride')[$name]) && $btnSubmit === '[Hinzuf]') {
                $postData[$name] = $this->config->getArray('postOverride')[$name];
            }

            $val = $postData[$name];

            if ($val === '') {
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
                $params[] = $val;
            }
        }

        // Handle BLOB/File uploads (simplified for this structure, fileCode mapping needed)
        // ... (File handling logic would go here if needed in standalone mode)

        if ($btnSubmit === '[Hinzuf]') {
            $sql = "INSERT INTO `$table` (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            return $this->db->executePrepared($sql, $params);
        } 
        
        if ($btnSubmit === '[S]') {
            if (empty($primaryKeys)) {
                $dbg = [];
                foreach ($fields as $f) {
                    $dbg[] = $f['name'] . "(PK:" . ($f['flag']['primary'] ? '1' : '0') . ", POST:" . (isset($postData['prim'.$f['name']]) ? '1' : '0') . ")";
                }
            // error_log("CoMyLi: No primary keys defined. Fields: " . implode(', ', $dbg) . " | POST keys: " . implode(', ', array_keys($postData)));
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
            $table = $this->config->get('stdTbl', '');
        }

        if ($isQuery) {
            $this->config->set('edit', false);
            $this->config->set('insert', false);
        }
        
        // Handle Query Frontend Editor
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->config->get('edit') && $this->config->get('editQueriesFrontend')) {
            if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                // CSRF Token invalid
            } else {
                $qName = preg_replace('/[^a-zA-Z0-9_\-]/', '', Security::unslash($_POST['SQLName'] ?? ''));
                $qCode = Security::unslash($_POST['SQLContent'] ?? '');
                $dir = rtrim($this->config->get('queries'), '/\\') . '/';
                if (!empty($qName) && is_dir($dir)) {
                    $f = $dir . $qName . '.sql';
                    if (isset($_POST['btnSQLSave'])) {
                        file_put_contents($f, $qCode);
                        $url = "?" . Security::base64urlEncode('qry') . "=" . Security::base64urlEncode($qName);
                        header("Location: $url");
                        return;
                    } elseif (isset($_POST['btnSQLDelete']) && file_exists($f)) {
                        unlink($f);
                        header("Location: ?");
                        return;
                    }
                }
            }
        }
        // Handle POST Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($table) && !$isQuery) {
            $res = $this->db->query("SELECT * FROM `$table` LIMIT 0");
            $fields = [];
            if ($res) {
                foreach ($res->fetch_fields() as $val) {
                    $fields[] = FieldHandler::getFieldInfo($val, $this->db);
                }
            }
            $success = $this->actionHandler->handlePost($fields, $table, Security::unslash($_POST), $_FILES);
            
            if ($this->config->get('noInterface')) {
            if (ob_get_level()) ob_clean();

                if ($success !== false) {
                    http_response_code(200);
                    $newTs = '';
                    $lcCol = null;
                    $pkCol = null;
                    $pkVal = null;
                    foreach ($fields as $field) {
                        if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') $lcCol = $field['name'];
                        if (!empty($field['flag']['primary'])) { $pkCol = $field['name']; $pkVal = $_POST['prim'.$field['name']] ?? null; }
                    }
                    if ($lcCol && $pkCol && $pkVal) {
                        $resTs = $this->db->executePrepared("SELECT `$lcCol` FROM `$table` WHERE `$pkCol` = ?", [$pkVal], true);
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

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=export_' . gmdate('Y-m-d_H-i-s') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo "sep=,\n";
        
        $qryName = $this->getRequestedQuery();
        $isQuery = !empty($qryName);
        if ($isQuery) {
            $sqlRaw = FieldHandler::resolveSQL($qryName, $this->config);
            $fromClause = "FROM ($sqlRaw) AS subq";
        } else {
            $fromClause = "FROM `$table`";
        }
        
        $fields = [];
        $res = $this->db->query("SELECT * $fromClause LIMIT 0");
        if ($res) {
            foreach ($res->fetch_fields() as $val) {
                $fields[] = FieldHandler::getFieldInfo($val, $this->db);
            }
        }
        
        $filterHandler = new FilterHandler($this->config);
        $filterData = $filterHandler->getWhereClause($fields);
        
        // Sorting
        $orderKey = Security::base64urlEncode('order');
        $dirKey = Security::base64urlEncode('d');
        $orderBy = isset($_GET[$orderKey]) ? Security::unslash($_GET[$orderKey]) : '';
        $dir = strtoupper(isset($_GET[$dirKey]) ? Security::unslash($_GET[$dirKey]) : '');
        
        $orderSql = '';
        if (!empty($orderBy) && in_array($dir, ['ASC', 'DESC'])) {
            $valid = false;
            foreach ($fields as $f) {
                if ($f['name'] === $orderBy) { $valid = true; break; }
            }
            if ($valid) $orderSql = " ORDER BY `$orderBy` $dir";
        }
        
        $sql = "SELECT * $fromClause" . $filterData['sql'] . $orderSql;
        $res = $this->db->executePrepared($sql, $filterData['params']);
        
        $fp = fopen('php://output', 'w');
        if ($res) {
            $header = false;
            while ($row = $res->fetch_assoc()) {
                if (!$header) {
                    fputcsv($fp, array_keys($row));
                    $header = true;
                }
                fputcsv($fp, $row);
            }
        }
        fclose($fp);
        exit;
    }

    private function renderBlob(): void {
        // Implementation for Blob image serving
        exit;
    }

    private function getRequestedTable(): string {
        $raw = isset($_GET[Security::base64urlEncode('table')]) ? Security::unslash($_GET[Security::base64urlEncode('table')]) : '';
        if ($raw === '') return '';
        $decoded = Security::base64urlDecode($raw);
        $tables = $this->config->getArray('tables');
        if (in_array($decoded, $tables)) return $decoded;
        if (in_array($raw, $tables)) return $raw;
        return $decoded;
    }

    private function getRequestedQuery(): string {
        $raw = isset($_GET[Security::base64urlEncode('qry')]) ? Security::unslash($_GET[Security::base64urlEncode('qry')]) : '';
        if ($raw === '') return '';
        $decoded = Security::base64urlDecode($raw);
        $files = glob($this->config->get('queries') . '*.sql') ?: [];
        $queries = array_map(function($f) { return basename($f, '.sql'); }, $files);
        if (in_array($decoded, $queries)) return $decoded;
        if (in_array($raw, $queries)) return $raw;
        return $decoded;
    }

    private function handleSyncState(string $table, array $fields, array $dbRows, int $totalRows, int $page, int $limit): void {
          if (ob_get_level()) ob_clean();
        
        header('Content-Type: application/json; charset=utf-8');

        $clientRowsJson = isset($_POST['clientRows']) ? Security::unslash($_POST['clientRows']) : '{}';
        $clientRows = json_decode($clientRowsJson, true) ?: [];

        $htmlRenderer = new HtmlRenderer($this->config, $this->db);
        $editMode = $this->config->get('edit');
        
        $pkIndex = -1;
        $lcIndex = -1;
        foreach ($fields as $idx => $field) {
            if (!empty($field['flag']['primary'])) $pkIndex = $idx;
            if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') $lcIndex = $idx;
        }

        if ($pkIndex === -1 || $lcIndex === -1) {
            echo json_encode(['error' => 'Missing PK or lastChanged']);
            exit;
        }

        $upsert = [];
        $delete = [];
        $seenClientIds = [];

        foreach ($dbRows as $row) {
            $id = (string)$row[$pkIndex];
            $dbLastChanged = $row[$lcIndex];
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
                $html = $htmlRenderer->renderDataRow($fields, $row, $editMode);
                $upsert[] = ['id' => $id, 'html' => $html, 'ts' => $dbLastChanged, 'debug_row' => $row];
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
        exit;
    }

    private function renderUi(string $table): void {
        if ($this->config->get('noInterface')) return;

        // Base scripts and styles (only for standalone mode; in WordPress they are enqueued via Assets.php)
        if (!$this->config->get('is_wp')) {
            if ($css = $this->config->getArray('path')['css'] ?? '') {
                echo "<link rel='stylesheet' href='" . Security::escapeAttr($css) . "'>";
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

        echo "<div class='comyli comyli-layout'>";
        
        $hasSidebar = $this->config->get('listQueries') || $this->config->get('listTables');
        if ($hasSidebar) {
            echo "<div class='comyli-sidebar'>";
            
            if ($this->config->get('listTables') && !empty($this->config->getArray('tables'))) {
                echo "<div class='comyli-sidebar-section'>";
                echo "<h4 class='comyli-sidebar-title'>Tabellen</h4>";
                echo "<div class='comyli-nav-list'>";
                $activeTbl = $this->getRequestedTable();
                foreach ($this->config->getArray('tables') as $t) {
                    if (!empty($t)) {
                        $url = "?" . Security::base64urlEncode('table') . "=" . Security::base64urlEncode($t);
                        $active = ($activeTbl === $t) ? 'active' : '';
                        echo "<a href='$url' class='comyli-nav-link $active'>" . Security::escapeHtml($t) . "</a>";
                    }
                }
                echo "</div></div>";
            }

            if ($this->config->get('listQueries') && !empty($this->config->get('queries')) && is_dir($this->config->get('queries'))) {
                echo "<div class='comyli-sidebar-section'>";
                echo "<h4 class='comyli-sidebar-title'>Abfragen</h4>";
                echo "<div class='comyli-nav-list'>";
                $files = glob($this->config->get('queries') . '*.sql');
                $activeQry = $this->getRequestedQuery();
                $allQueries = [];
                if ($files) {
                    foreach ($files as $f) {
                        $base = basename($f, '.sql');
                        $url = "?" . Security::base64urlEncode('qry') . "=" . Security::base64urlEncode($base);
                        $active = ($activeQry === $base) ? 'active' : '';
                        
                        echo "<div class='comyli-nav-item $active'>";
                        echo "<a href='$url' class='comyli-nav-link'>" . Security::escapeHtml($base) . "</a>";
                        
                        if ($this->config->get('edit') && $this->config->get('editQueriesFrontend')) {
                            $code = file_get_contents($f);
                            $allQueries[$base] = $code;
                            echo "<button type='button' class='comyli-edit-qry-btn' data-qry='" . Security::escapeAttr($base) . "' title='Bearbeiten'>&#9998;</button>";
                        }
                        echo "</div>";
                    }
                }
                if ($this->config->get('edit') && $this->config->get('editQueriesFrontend')) {
                    echo "<button type='button' class='comyli-new-qry-btn' onclick='openComyliQueryModal()'>+ Neue Abfrage</button>";
                    
                    // Render Modal HTML
                    $jsonQueries = json_encode($allQueries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    echo "<div id='comyli-query-modal' class='comyli-modal' style='display:none;'>";
                    echo "<div class='comyli-modal-content'>";
                    echo "<div class='comyli-modal-header'><h3 style='margin:0;' id='comyli-modal-title'>Abfrage bearbeiten</h3><span class='comyli-modal-close' onclick='closeComyliQueryModal()'>&times;</span></div>";
                    echo "<form method='POST' action=''>";
                    echo "<input type='hidden' name='csrf_token' value='" . Security::escapeAttr(Security::generateCsrfToken()) . "'>";
                    echo "<label style='display:block; margin-bottom:5px; font-weight:bold;'>Name (ohne .sql):</label>";
                    echo "<input type='text' id='comyli-modal-name' name='SQLName' value='' required style='width:100%; margin-bottom:15px;' class='comyli-input'>";
                    echo "<label style='display:block; margin-bottom:5px; font-weight:bold;'>SQL Code:</label>";
                    echo "<div class='editor language-sql' id='sqlEditor' style='height:200px; background:#fff; border:1px solid #ccc; border-radius:4px; font-family:monospace; padding:10px; box-sizing:border-box;'></div>";
                    echo "<textarea name='SQLContent' id='sqlHidden' style='display:none;'></textarea>";
                    echo "<div style='margin-top:15px; display:flex; justify-content:space-between;'>";
                    echo "<button type='submit' name='btnSQLSave' class='comyli-btn-primary'>Speichern</button>";
                    echo "<button type='submit' name='btnSQLDelete' id='comyli-modal-delete' class='comyli-btn-danger' onclick='return confirm(\"Wirklich l&ouml;schen?\");' style='display:none;'>L&ouml;schen</button>";
                    echo "</div>";
                    echo "</form></div></div>";
                    
                    // Modal JS
                    echo "<script>
                        window.comyliQueries = " . $jsonQueries . ";
                        function openComyliQueryModal(name) {
                            document.getElementById('comyli-query-modal').style.display = 'flex';
                            document.getElementById('comyli-modal-name').value = name || '';
                            const isNew = !name;
                            document.getElementById('comyli-modal-title').innerText = isNew ? 'Neue Abfrage' : 'Abfrage bearbeiten';
                            document.getElementById('comyli-modal-delete').style.display = isNew ? 'none' : 'block';
                            
                            const code = isNew ? '' : (window.comyliQueries[name] || '');
                            if (window.comyliCodeJar) {
                                window.comyliCodeJar.updateCode(code);
                            } else {
                                document.getElementById('sqlHidden').value = code;
                            }
                        }
                        function closeComyliQueryModal() {
                            document.getElementById('comyli-query-modal').style.display = 'none';
                        }
                        
                        document.addEventListener('DOMContentLoaded', function() {
                            const editBtns = document.querySelectorAll('.comyli-edit-qry-btn');
                            editBtns.forEach(btn => {
                                btn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    openComyliQueryModal(this.getAttribute('data-qry'));
                                });
                            });
                        });
                    </script>";
                }
                echo "</div></div>";
            }
            echo "</div>"; // End sidebar
        }
        
        echo "<div class='comyli-main'>";
        
        // Toast container for JS notifications
        echo "<div id='toast'></div>";
            if ($this->config->getArray('path')['codejar'] ?? '') {
                echo "<script>
                    if (typeof CodeJar !== 'undefined') {
                        const editor = document.getElementById('sqlEditor');
                        const hidden = document.getElementById('sqlHidden');
                        if (editor && hidden) {
                            const highlight = editor => {
                                if (typeof Prism !== 'undefined' && Prism.languages && Prism.languages.sql) {
                                    editor.innerHTML = Prism.highlight(editor.textContent, Prism.languages.sql, 'sql'); console.log('Prism Output:', editor.innerHTML);
                                } else {
                                    editor.textContent = editor.textContent;
                                }
                            };
                            window.comyliCodeJar = CodeJar(editor, highlight);
                            window.comyliCodeJar.onUpdate(code => {
                                hidden.value = code;
                            });
                        }
                    }
                </script>";
            }

        $qryName = $this->getRequestedQuery();
        $isQuery = !empty($qryName);
        if ($isQuery) {
            $sqlRaw = FieldHandler::resolveSQL($qryName, $this->config);
            if (empty($sqlRaw)) {
                echo "<p>Abfrage <strong>" . Security::escapeHtml($qryName) . "</strong> nicht gefunden.</p></div>";
                return;
            }
            $fromClause = "FROM ($sqlRaw) AS subq";
        } else {
            $fromClause = "FROM `$table`";
            if (empty($table)) {
                echo "<p>Keine Tabelle ausgew&auml;hlt.</p>";
                echo "</div>";
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
        $orderBy = isset($_GET[$orderKey]) ? Security::unslash($_GET[$orderKey]) : '';
        $dir = strtoupper(isset($_GET[$dirKey]) ? Security::unslash($_GET[$dirKey]) : '');
        
        $orderSql = '';
        if (!empty($orderBy) && in_array($dir, ['ASC', 'DESC'])) {
            // Verify column exists to prevent SQL injection
            $valid = false;
            foreach ($fields as $f) {
                if ($f['name'] === $orderBy) { $valid = true; break; }
            }
            if ($valid) {
                $orderSql = " ORDER BY `$orderBy` $dir";
            }
        }
        
        $sql = "SELECT * $fromClause" . $filterData['sql'] . $orderSql . " LIMIT $limit OFFSET $offset";
        $res = $this->db->executePrepared($sql, $filterData['params']);
        
        if (!$res) {
            echo "<div class='error'>Fehler beim Laden der Tabelle: " . Security::escapeHtml($this->db->getError()) . "</div></div>";
            return;
        }

        $rows = [];
        while ($row = $res->fetch_row()) {
            $rows[] = $row;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'heartbeat') {
              if (ob_get_level()) ob_clean();
            
            header('Content-Type: text/plain; charset=utf-8');
            $lcCol = null;
            foreach ($fields as $field) {
                if ($field['name'] === 'lastChanged' || $field['name'] === 'lstChanged') {
                    $lcCol = $field['name'];
                    break;
                }
            }
            if ($lcCol && !$isQuery) {
                $resMax = $this->db->query("SELECT MAX(`$lcCol`) FROM `$table`");
                if ($resMax && $resMax->num_rows > 0) {
                    $maxTs = $resMax->fetch_row()[0];
                    echo preg_replace('/\.\d+$/', '', (string)$maxTs);
                }
            }
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'syncState' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleSyncState($table, $fields, $rows, $totalRows, $page, $limit);
            exit;
        }

        $htmlRenderer = new HtmlRenderer($this->config, $this->db);
        echo $htmlRenderer->renderTable($table, $fields, $rows, $this->config->get('edit'), $this->config->get('insert'), $totalRows, $page, $limit);
        echo "</div>";

        // Bulk Actions JS
        if ($this->config->get('edit')) {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('comyli-bulk-select-all');
                const rowChecks = document.querySelectorAll('.comyli-bulk-check');
                const bulkDelBtn = document.getElementById('comyli-bulk-delete-btn');
                
                function updateBulkDelBtn() {
                    let anyChecked = Array.from(rowChecks).some(cb => cb.checked);
                    if (bulkDelBtn) bulkDelBtn.style.display = anyChecked ? 'inline-block' : 'none';
                }
                
                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        rowChecks.forEach(cb => cb.checked = selectAll.checked);
                        updateBulkDelBtn();
                    });
                }
                
                rowChecks.forEach(cb => {
                    cb.addEventListener('change', function() {
                        if (!this.checked && selectAll) selectAll.checked = false;
                        let allChecked = Array.from(rowChecks).every(c => c.checked);
                        if (allChecked && selectAll) selectAll.checked = true;
                        updateBulkDelBtn();
                    });
                });
                
                if (bulkDelBtn) {
                    bulkDelBtn.addEventListener('click', function() {
                        if (!confirm('Wirklich alle ausgewählten Zeilen löschen?')) return;
                        rowChecks.forEach(cb => {
                            if (cb.checked) {
                                let tr = cb.closest('.tr');
                                if (tr) {
                                    let btnDel = tr.querySelector('.btnDel');
                                    if (btnDel) btnDel.click(); // Trigger existing AJAX delete
                                }
                            }
                        });
                        // Uncheck all after triggering delete
                        rowChecks.forEach(cb => cb.checked = false);
                        if (selectAll) selectAll.checked = false;
                        updateBulkDelBtn();
                    });
                }
            });
            </script>";
        }

        // Chart.js rendering
        $chartsConfig = $this->config->getArray('charts');
        if (isset($chartsConfig[$table]) && is_array($chartsConfig[$table]) && !empty($chartsConfig[$table])) {
            $cType = Security::escapeAttr($chartsConfig[$table][0] ?? 'pie');
            $cCols = (int)($chartsConfig[$table][1] ?? 1);
            $cOpts = $chartsConfig[$table][2] ?? '';
            
            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                // Collect labels up to cCols, and the value is at cCols
                $labelParts = [];
                for ($j = 0; $j < $cCols; $j++) {
                    $labelParts[] = $row[$j] ?? '';
                }
                $labels[] = implode(" ", $labelParts);
                $data[] = (float)($row[$cCols] ?? 0);
            }
            
            $colors = "['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40']"; // Fallback basic colors
            
            echo "<canvas id='myChart' width='400' height='400'></canvas>";
            if ($chartJS = $this->config->getArray('path')['chartJS'] ?? '') {
                echo "<script src='" . Security::escapeAttr($chartJS) . "'></script>";
            }
            echo "<script>
                var ctx = document.getElementById('myChart');
                if (ctx && typeof Chart !== 'undefined') {
                    var myChart = new Chart(ctx, {
                        type: '$cType',
                        data: {
                            labels: " . json_encode($labels) . ",
                            datasets: [{
                                data: " . json_encode($data) . ",
                                backgroundColor: $colors
                            }]
                        },
                        options: {
                            responsive: false
                            " . ($cOpts ? "," . $cOpts : "") . "
                        }
                    });
                }
            </script>";
        }

        // Init Select2 if we are editing and have foreignKey configs
        if ($this->config->get('edit') && $this->config->get('foreignKey')) {
            $fkInput = $this->config->getArray('foreignKey')['input'] ?? [];
            if (!empty($fkInput)) {
                $jsonColNames = [];
                foreach ($fkInput as $col_name => $list_name) {
                    $jsonColNames[] = "['" . Security::escapeHtml($col_name) . "','" . Security::escapeHtml($list_name) . "']";
                }
                echo "<script>
                    jQuery(document).ready(function() {
                        const data_lists = [" . implode(',', $jsonColNames) . "];
                        var options = {};
                        for (let i in data_lists) {
                            let col_name = data_lists[i][0];
                            let list_name = data_lists[i][1];
                            
                            options[col_name] = [];
                            document.querySelectorAll(`datalist#\${list_name} option`).forEach(function(option) {
                                options[col_name].push(option.value);
                            });
                            
                            document.querySelectorAll(`.select2Input[data-list=\"\${col_name}\"]`).forEach(function(select) {
                                jQuery(select).select2({
                                    data: options[col_name].map(item => ({ id: item, text: item })),
                                    allowClear: true
                                });
                                
                                let hiddenInput = select.previousElementSibling;
                                let initialValues = [];
                                try { initialValues = JSON.parse(hiddenInput.value || '[]'); } catch(e) {}
                                
                                Array.from(select.options).forEach(function(option) {
                                    option.selected = initialValues.includes(option.value);
                                });
                                select.dispatchEvent(new Event('change'));
                                
                                jQuery(select).on('change', function(e) {
                                    let selectedValues = Array.from(this.selectedOptions).map(option => option.value);
                                    let newValue = JSON.stringify(selectedValues);
                                    if (hiddenInput.value !== newValue) {
                                        hiddenInput.value = newValue;
                                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                });
                            });
                        }
                    });
                </script>";
            }
        }
        
        // AJAX Form Submission for Filters and Pagination
        echo "<script>
            
            jQuery(document).ready(function($) {
                $(document).on('submit', '#fsp', function(e) {
                    var submitter = e.originalEvent ? e.originalEvent.submitter : null;
                    if (submitter && submitter.name === '" . Security::base64urlEncode('csv') . "') {
                        return; // Let the browser handle the file download natively
                    }
                    
                    e.preventDefault();
                    var form = $(this);
                    var url = '?' + form.serialize();
                    
                    var tableWrapper = $('.table.select.comyli').parent();
                    tableWrapper.css('opacity', '0.5');
                    
                    $.ajax({
                        url: url,
                        type: 'GET',
                        success: function(response) {
                            var newTable = $(response).find('.table.select.comyli');
                            if (newTable.length) {
                                $('.table.select.comyli').replaceWith(newTable);
                                if (typeof initSelect2 === 'function') initSelect2();
                                if (typeof COMYLI !== 'undefined' && typeof COMYLI.initTable === 'function') COMYLI.initTable();
                                window.history.pushState({path: url}, '', url);
                            }
                        },
                        complete: function() {
                            tableWrapper.css('opacity', '1');
                        }
                    });
                });
                
                $(window).on('popstate', function() {
                    location.reload();
                });
            });
        </script>";
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