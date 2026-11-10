<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Resolves declarative JSON table configurations without eval().
 * Replaces LegacySpecifics with structured, safe, role-aware configuration handling.
 */
class ConfigResolver {
    private ?Config $config;

    public function __construct(?Config $config = null) {
        $this->config = $config;
    }

    public function register(): void {
        add_filter('comyli_config', [$this, 'resolveConfig'], 10, 2);
    }

    /**
     * Resolves and applies global defaults and table-specific declarative configuration.
     *
     * @param array<string, mixed> $config
     * @param string $stdTbl
     * @return array<string, mixed>
     */
    public function resolveConfig(array $config, string $stdTbl = ''): array {
        if (empty($stdTbl) && !empty($config['stdTbl'])) {
            $stdTbl = (string)$config['stdTbl'];
        }

        // 1. Resolve raw advanced config JSON
        $advancedConfig = $this->loadAdvancedConfig($config, $stdTbl);

        // 2. Global Defaults (Audit columns & default foreign keys)
        $inheritGlobal = $advancedConfig['ui']['inherit_global_defaults'] ?? true;
        if ($inheritGlobal !== false) {
            $config = $this->applyGlobalDefaults($config, $advancedConfig);
        }

        if (empty($advancedConfig)) {
            return $config;
        }

        $mysqli = $config['mysqli'] ?? null;
        if (!($mysqli instanceof \mysqli)) {
            $mysqli = null;
        }

        $context = [
            'stdTbl'  => $stdTbl,
            'columns' => [],
        ];

        // 3. UI and Query options
        if (!empty($advancedConfig['ui']) && is_array($advancedConfig['ui'])) {
            $ui = $advancedConfig['ui'];
            if (!empty($ui['stdQry'])) {
                $config['stdQry'] = sanitize_text_field($ui['stdQry']);
            }
            if (isset($ui['insert_top'])) {
                $config['gui']['insert_top'] = !empty($ui['insert_top']) ? 1 : 0;
            }
            if (isset($ui['insert_bottom'])) {
                $config['gui']['insert_bottom'] = !empty($ui['insert_bottom']) ? 1 : 0;
            }

            // Default sorting order if not specified in request
            if (!empty($ui['default_order'])) {
                $orderCol = sanitize_text_field($ui['default_order']);
                $orderDir = !empty($ui['default_dir']) ? strtoupper(sanitize_text_field($ui['default_dir'])) : 'ASC';

                $orderParam = \Comyli\Standalone\Security::base64urlEncode('order');
                $dParam = \Comyli\Standalone\Security::base64urlEncode('d');
                $qryParam = \Comyli\Standalone\Security::base64urlEncode('qry');

                $isOrderSet = isset($_GET['order']) || isset($_GET['b3JkZXI']) || isset($_GET[$orderParam]);
                $isQrySet = isset($_GET['qry']) || isset($_GET['cXJ5']) || isset($_GET[$qryParam]);

                if (!$isOrderSet && !$isQrySet) {
                    $_GET['order'] = $orderCol;
                    $_GET['b3JkZXI'] = $orderCol;
                    $_GET[$orderParam] = $orderCol;

                    $_GET['d'] = $orderDir;
                    $_GET['ZA'] = $orderDir;
                    $_GET[$dParam] = $orderDir;
                }
            }
        }

        // 4. Queries folder override if specified
        if (!empty($advancedConfig['queries']) && is_string($advancedConfig['queries'])) {
            $config['queries'] = $advancedConfig['queries'];
        }

        // 5. Columns configuration
        if (!empty($advancedConfig['columns']) && is_array($advancedConfig['columns'])) {
            foreach ($advancedConfig['columns'] as $colName => $colSettings) {
                if (!is_array($colSettings)) {
                    continue;
                }

                // Default value
                if (isset($colSettings['default'])) {
                    $resolvedDef = self::resolveTokens($colSettings['default'], $context, $mysqli);
                    $config['value'][$colName] = $resolvedDef;
                    $context['columns'][$colName] = $resolvedDef;
                }

                // Forced hidden value (valueH)
                if (isset($colSettings['forced'])) {
                    $resolvedForced = self::resolveTokens($colSettings['forced'], $context, $mysqli);
                    $config['valueH'][$colName] = $resolvedForced;
                    $context['columns'][$colName] = $resolvedForced;
                }

                // Post override on insert (postOverride)
                if (isset($colSettings['post_override'])) {
                    $config['postOverride'][$colName] = self::resolveTokens($colSettings['post_override'], $context, $mysqli);
                }

                // Readonly
                if (!empty($colSettings['readonly'])) {
                    $config['readonly'][$colName] = true;
                }

                // Step attribute
                if (isset($colSettings['step'])) {
                    $config['param_step'][$colName] = (string)$colSettings['step'];
                }

                // HTML Parameters / Constraints
                $paramAttrs = [];
                if (isset($colSettings['min'])) {
                    $paramAttrs[] = "min='" . esc_attr((string)$colSettings['min']) . "'";
                }
                if (isset($colSettings['max'])) {
                    $paramAttrs[] = "max='" . esc_attr((string)$colSettings['max']) . "'";
                }
                if (isset($colSettings['pattern'])) {
                    $paramAttrs[] = "pattern='" . esc_attr((string)$colSettings['pattern']) . "'";
                }
                if (isset($colSettings['placeholder'])) {
                    $paramAttrs[] = "placeholder='" . esc_attr((string)$colSettings['placeholder']) . "'";
                }
                if (!empty($colSettings['required'])) {
                    $paramAttrs[] = "required";
                }
                if (!empty($colSettings['attr']) && is_string($colSettings['attr'])) {
                    $cleanAttr = self::sanitizeHtmlParam($colSettings['attr']);
                    if (!empty($cleanAttr)) {
                        $paramAttrs[] = $cleanAttr;
                    }
                }

                if (!empty($paramAttrs)) {
                    $currentParam = $config['param'][$colName] ?? '';
                    $config['param'][$colName] = trim($currentParam . ' ' . implode(' ', $paramAttrs));
                }
            }
        }

        // 6. Foreign Keys
        if (!empty($advancedConfig['foreign_keys']) && is_array($advancedConfig['foreign_keys'])) {
            foreach ($advancedConfig['foreign_keys'] as $colName => $fkDef) {
                if (!is_array($fkDef)) {
                    continue;
                }

                $mode = ($fkDef['mode'] ?? 'input') === 'suggestion' ? 'suggestion' : 'input';
                $listKey = !empty($fkDef['list_key']) ? (string)$fkDef['list_key'] : $colName;
                $source = $fkDef['source'] ?? 'sql';

                if ($source === 'sql') {
                    $query = (string)($fkDef['query'] ?? '');
                    if (!empty($query)) {
                        // If suggestion mode or complex query requiring immediate resolution
                        if ($mode === 'suggestion' || str_contains(strtoupper($query), ' JOIN ') || str_contains(strtoupper($query), ' WHERE ') || str_contains(strtoupper($query), ' GROUP BY ')) {
                            if ($mysqli instanceof \mysqli) {
                                $res = $mysqli->query($query);
                                $items = [];
                                if ($res) {
                                    while ($r = $res->fetch_row()) {
                                        if (isset($r[0])) {
                                            $items[] = (string)$r[0];
                                        }
                                    }
                                }
                                $config['foreignKey']['list'][$listKey] = $items;
                            } else {
                                $config['foreignKey']['list'][$listKey] = $query;
                            }
                        } else {
                            $config['foreignKey']['list'][$listKey] = $query;
                        }
                    }
                } elseif ($source === 'wp_option') {
                    $optName = (string)($fkDef['option'] ?? '');
                    $prop = (string)($fkDef['property'] ?? '');
                    $raw = get_option($optName, '{}');
                    $decoded = is_string($raw) ? json_decode($raw) : $raw;
                    $items = [];
                    foreach ((array)$decoded as $item) {
                        if (is_object($item) && !empty($prop) && isset($item->$prop)) {
                            $items[] = (string)$item->$prop;
                        } elseif (is_array($item) && !empty($prop) && isset($item[$prop])) {
                            $items[] = (string)$item[$prop];
                        } elseif (is_scalar($item)) {
                            $items[] = (string)$item;
                        }
                    }
                    $config['foreignKey']['list'][$listKey] = $items;
                } elseif ($source === 'wp_users') {
                    $field = (string)($fkDef['field'] ?? 'user_login');
                    $includeEmpty = !empty($fkDef['include_empty']);
                    $users = get_users();
                    $items = $includeEmpty ? [""] : [];
                    foreach ($users as $user) {
                        if (isset($user->$field)) {
                            $items[] = (string)$user->$field;
                        }
                    }
                    $config['foreignKey']['list'][$listKey] = $items;
                }

                $config['foreignKey'][$mode][$colName] = $listKey;
            }
        }

        // 7. Readonly unless user has capability
        if (!empty($advancedConfig['readonly_unless_capability']) && is_array($advancedConfig['readonly_unless_capability'])) {
            $cap = (string)($advancedConfig['readonly_unless_capability']['capability'] ?? '');
            $columns = (array)($advancedConfig['readonly_unless_capability']['columns'] ?? []);
            $currentUser = wp_get_current_user();

            $hasAccess = false;
            if ($currentUser && $currentUser->exists() && !empty($cap)) {
                if (current_user_can($cap) || in_array($cap, (array)$currentUser->roles, true)) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess) {
                foreach ($columns as $col) {
                    $config['readonly'][(string)$col] = true;
                }
            }
        }

        // 8. Access Rules (evaluated in order, first matching rule wins)
        if (!empty($advancedConfig['access_rules']) && is_array($advancedConfig['access_rules'])) {
            $currentUser = wp_get_current_user();
            $isExport = (isset($_GET['Y3N2']) && $_GET['Y3N2'] === '1') 
                || (isset($_GET['aW1n']) && $_GET['aW1n'] === '1') 
                || !empty($_GET['csv']);

            foreach ($advancedConfig['access_rules'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $caps = (array)($rule['capabilities'] ?? []);
                $ruleMatches = false;

                if (empty($caps) || in_array('*', $caps, true)) {
                    $ruleMatches = true;
                } else {
                    foreach ($caps as $c) {
                        if ($c === '*' || current_user_can($c) || ($currentUser && in_array($c, (array)$currentUser->roles, true))) {
                            $ruleMatches = true;
                            break;
                        }
                    }
                }

                if ($ruleMatches) {
                    // Edit permission
                    if (isset($rule['edit'])) {
                        $config['edit'] = !empty($rule['edit']) ? 1 : 0;
                    }

                    // Visible columns (limit_cols)
                    if (!empty($rule['visible_columns']) && is_array($rule['visible_columns'])) {
                        $colMap = [];
                        foreach ($rule['visible_columns'] as $col) {
                            $colMap[(string)$col] = true;
                        }
                        $config['limit_cols'][$stdTbl] = $colMap;
                    }

                    // Readonly columns
                    if (!empty($rule['readonly_columns']) && is_array($rule['readonly_columns'])) {
                        foreach ($rule['readonly_columns'] as $col) {
                            $config['readonly'][(string)$col] = true;
                        }
                    }

                    // Dynamic role-dependent CSS (appended to custom_css for Shortcode rendering)
                    if (!empty($rule['custom_css']) && is_string($rule['custom_css']) && !$isExport) {
                        $currentCss = $config['custom_css'] ?? '';
                        $config['custom_css'] = trim($currentCss . "\n" . $rule['custom_css']);
                    }

                    // First matched rule determines the access configuration
                    break;
                }
            }
        }

        // 9. Maintenance / Auto-generators
        if (!empty($advancedConfig['maintenance']) && is_array($advancedConfig['maintenance']) && $mysqli instanceof \mysqli) {
            $maint = $advancedConfig['maintenance'];
            if (!empty($maint['ensure_quarterly_dates']) && is_array($maint['ensure_quarterly_dates'])) {
                $qDef = $maint['ensure_quarterly_dates'];
                $maintTbl = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($qDef['table'] ?? 'tbl_naechstekontrolldaten'));
                $years = max(1, min(10, (int)($qDef['years_ahead'] ?? 3)));
                $months = (array)($qDef['months'] ?? [1, 2, 3, 4]);
                $day = max(1, min(28, (int)($qDef['day'] ?? 1)));
                $currYear = (int)gmdate('Y');

                for ($y = 0; $y < $years; $y++) {
                    foreach ($months as $m) {
                        $dateStr = sprintf('%04d-%02d-%02d', $currYear + $y, (int)$m, $day);
                        @$mysqli->query("INSERT IGNORE INTO `{$maintTbl}` VALUES('{$dateStr}')");
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Loads the advanced configuration for a table from post meta, attributes, or built-in presets.
     *
     * @param array<string, mixed> $config
     * @param string $stdTbl
     * @return array<string, mixed>
     */
    private function loadAdvancedConfig(array $config, string $stdTbl): array {
        // 1. From pre-resolved config array
        if (!empty($config['advanced_config'])) {
            $raw = $config['advanced_config'];
            if (is_array($raw)) {
                return $raw;
            }
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        // 2. From Post Meta if comyli_id is available
        $postId = (int)($config['comyli_id'] ?? 0);
        if ($postId > 0) {
            $metaVal = get_post_meta($postId, '_comyli_advanced_config', true);
            if (!empty($metaVal)) {
                $decoded = is_string($metaVal) ? json_decode($metaVal, true) : $metaVal;
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        }

        // 3. Fallback to built-in presets for legacy tables
        $preset = self::getBuiltinPreset($stdTbl);
        if ($preset !== null) {
            return $preset;
        }

        return [];
    }

    /**
     * Applies standard global defaults across all tables (audit fields, global foreign keys).
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $advancedConfig
     * @return array<string, mixed>
     */
    public function applyGlobalDefaults(array $config, array $advancedConfig = []): array {
        $autoAudit = $advancedConfig['ui']['auto_audit_columns'] ?? true;
        if ($autoAudit) {
            $currentUser = wp_get_current_user();
            $userLogin = ($currentUser && $currentUser->exists()) ? $currentUser->user_login : 'System';

            $config['valueH']['lastChangedUser'] = $userLogin;
            $config['readonly']['created'] = true;
            $config['postOverride']['created'] = gmdate("Y-m-d H:i:s");
            $config['readonly']['createdUser'] = true;
            $config['valueP']['createdUser'] = $userLogin;
        }

        // Standard Fachdienst foreign key from WP option if available
        $fachdienstOption = get_option('ha_ask_user_for_information_fachdienst', '');
        if (!empty($fachdienstOption) && empty($config['foreignKey']['list']['fachdienst'])) {
            $decoded = is_string($fachdienstOption) ? json_decode($fachdienstOption) : $fachdienstOption;
            $list = [];
            foreach ((array)$decoded as $data) {
                if (isset($data->fachdienst)) {
                    $list[] = (string)$data->fachdienst;
                }
            }
            if (!empty($list)) {
                $config['foreignKey']['list']['fachdienst'] = $list;
                if (empty($config['foreignKey']['input']['Fachdienst'])) {
                    $config['foreignKey']['input']['Fachdienst'] = 'fachdienst';
                }
            }
        }

        return $config;
    }

    /**
     * Resolves dynamic safe placeholder tokens in values and SQL queries without using eval().
     *
     * @param mixed $value
     * @param array<string, mixed> $context
     * @param \mysqli|null $mysqli
     * @return mixed
     */
    public static function resolveTokens(mixed $value, array $context = [], ?\mysqli $mysqli = null): mixed {
        if (!is_string($value)) {
            return $value;
        }

        $str = $value;

        // Current date / time tokens
        $str = str_replace('{current_date}', gmdate('Y-m-d'), $str);
        $str = str_replace('{current_datetime}', gmdate('Y-m-d H:i:s'), $str);
        $str = str_replace('{current_year}', gmdate('Y'), $str);

        // User tokens
        if (str_contains($str, '{current_user_login}')) {
            $u = wp_get_current_user();
            $str = str_replace('{current_user_login}', ($u && $u->exists()) ? $u->user_login : '', $str);
        }
        if (str_contains($str, '{current_user_id}')) {
            $str = str_replace('{current_user_id}', (string)get_current_user_id(), $str);
        }
        if (str_contains($str, '{current_user_fullname}')) {
            $u = wp_get_current_user();
            $fullName = '';
            if ($u && $u->exists()) {
                $first = get_user_meta($u->ID, 'first_name', true);
                $last  = get_user_meta($u->ID, 'last_name', true);
                $fullName = trim("$last, $first");
                if (empty($fullName)) {
                    $fullName = $u->display_name ?: $u->user_login;
                }
            }
            $str = str_replace('{current_user_fullname}', $fullName, $str);
        }

        // Year letter token: {year_letter:2024} -> calculates A-Z cyclically from base year
        $str = preg_replace_callback('/\{year_letter:(\d{4})\}/', function ($m) {
            $baseYear = (int)$m[1];
            $currentYear = (int)gmdate('Y');
            return chr(ord('A') + ($currentYear + 26 - $baseYear) % 26);
        }, $str);

        // Reference other evaluated columns: {column:Px}
        $str = preg_replace_callback('/\{column:([a-zA-Z0-9_]+)\}/', function ($m) use ($context, $mysqli) {
            $col = $m[1];
            $val = $context['columns'][$col] ?? '';
            if ($mysqli instanceof \mysqli) {
                return $mysqli->real_escape_string((string)$val);
            }
            return addslashes((string)$val);
        }, $str);

        // SQL resolution: {sql:SELECT ...} or {sql_pad4:SELECT ...}
        if (preg_match('/^\{sql(?:_pad(\d+))?:(SELECT\s+.+)\}$/is', trim($str), $sqlMatches)) {
            $padLen = !empty($sqlMatches[1]) ? (int)$sqlMatches[1] : 0;
            $query = trim($sqlMatches[2]);

            if ($mysqli instanceof \mysqli) {
                $res = $mysqli->query($query);
                if ($res && ($row = $res->fetch_row())) {
                    $scalar = $row[0] ?? '';
                    if ($padLen > 0) {
                        return str_pad((string)$scalar, $padLen, '0', STR_PAD_LEFT);
                    }
                    return $scalar;
                }
            }
            return $padLen > 0 ? str_pad('1', $padLen, '0', STR_PAD_LEFT) : '';
        }

        return $str;
    }

    /**
     * Sanitizes custom HTML attributes allowed on inputs.
     */
    private static function sanitizeHtmlParam(string $attr): string {
        $allowed = ['onInput', 'min', 'max', 'step', 'pattern', 'placeholder', 'readonly', 'required', 'style'];
        $clean = [];

        if (preg_match_all('/([a-zA-Z_-]+)\s*=\s*(["\'])(.*?)\2/s', $attr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = $m[1];
                $val = $m[3];
                if (in_array(strtolower($name), array_map('strtolower', $allowed), true)) {
                    $clean[] = $name . '="' . esc_attr($val) . '"';
                }
            }
        }

        return implode(' ', $clean);
    }

    /**
     * Returns built-in fallback configuration presets for legacy CoMyLi tables.
     *
     * @param string $tableName
     * @return array<string, mixed>|null
     */
    public static function getBuiltinPreset(string $tableName): ?array {
        $presets = [
            'arbokat' => [
                'queries' => './wp-content/plugins/ha-comyli/queries/bauhof/',
                'ui' => [
                    'auto_audit_columns'      => true,
                    'inherit_global_defaults' => true,
                ],
                'columns' => [
                    'Bearbeiter'  => [
                        'forced' => '{current_user_fullname}',
                    ],
                    'BaumNr'      => [
                        'default' => '{sql:SELECT IF(MAX(BaumNr) IS NULL,1,MAX(BaumNr)+1) FROM arbokat}',
                    ],
                    'Durchmesser' => [
                        'attr' => 'onInput="umfang(this)"',
                    ],
                    'Umfang'      => [
                        'attr' => 'onInput="durchmesser(this)"',
                    ],
                ],
                'foreign_keys' => [
                    'Bezirk'               => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_bezirke', 'mode' => 'input'],
                    'ArtLateinisch'        => ['source' => 'sql', 'query' => 'SELECT ArtLat FROM tbl_baumarten', 'mode' => 'input'],
                    'BewertungWurzel'      => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_bewertungwurzel', 'mode' => 'input'],
                    'BewertungStamm'       => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_bewertungstamm_krone', 'list_key' => 'BewertungStammKrone', 'mode' => 'input'],
                    'BewertungKrone'       => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_bewertungstamm_krone', 'list_key' => 'BewertungStammKrone', 'mode' => 'input'],
                    'SymptomeKrone'        => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_symptomekrone', 'mode' => 'input'],
                    'Vitalitaet'           => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_vitalitaet', 'mode' => 'input'],
                    'Physiologie'          => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_physiologie', 'mode' => 'input'],
                    'Verkehrssicherheit'   => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_verkehrssicherheit', 'mode' => 'input'],
                    'naechsteKontrolle'    => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_naechstekontrolldaten', 'mode' => 'input'],
                    'MassnahmenStandort'   => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_massnahmenstandort', 'mode' => 'input'],
                    'StandortAusfuehrung'  => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_ausfuehrung', 'list_key' => 'StandortAusfuehrung', 'mode' => 'input'],
                    'MassnahmenAusfuehrung'=> ['source' => 'sql', 'query' => 'SELECT * FROM tbl_ausfuehrung', 'list_key' => 'StandortAusfuehrung', 'mode' => 'input'],
                    'MassnahmenBaum'       => ['source' => 'sql', 'query' => 'SELECT * FROM tbl_massnahmenbaum', 'mode' => 'input'],
                ],
                'readonly_unless_capability' => [
                    'capability' => 'arbokat_write',
                    'columns'    => ['Bezirk', 'BaumNr', 'Ort', 'GenauerStandort', 'ArtLateinisch', 'Durchmesser', 'Umfang', 'Kronendurchmesser', 'Hoehe', 'gefaellt'],
                ],
                'maintenance' => [
                    'ensure_quarterly_dates' => [
                        'table'       => 'tbl_naechstekontrolldaten',
                        'years_ahead' => 3,
                        'months'      => [1, 2, 3, 4],
                        'day'         => 1,
                    ],
                ],
            ],

            'wp_ha_sync_phonebook' => [
                'queries' => './wp-content/plugins/ha-comyli/queries/telefonverzeichnis/',
                'ui' => [
                    'stdQry'                  => 'index',
                    'auto_audit_columns'      => true,
                    'inherit_global_defaults' => true,
                ],
            ],

            'wp_ha_verfahrensliste_edv_rpa' => [
                'foreign_keys' => [
                    'FDs_test' => [
                        'source'   => 'wp_option',
                        'option'   => 'ha_ask_user_for_information_fachdienst',
                        'property' => 'fachdienst',
                        'mode'     => 'input',
                    ],
                ],
                'access_rules' => [
                    [
                        'capabilities' => ['stabsstelledigitalisierung', 'edv', 'rechnungspruefung', 'datenschutz'],
                        'edit'         => true,
                        'custom_css'   => ".comyli .td:nth-of-type(4), .comyli .td[data-col=\"Beschreibung\"] { width: 600px; }\n.comyli .td:nth-of-type(1), .comyli .td[data-col=\"_actions\"] { width: 80px; min-width: 80px; max-width: 80px; position: sticky; left: 0; z-index: 6; background-color: inherit; border-right: 1px solid #e2e8f0; }\n.comyli .td:nth-of-type(2), .comyli .td[data-col=\"Name_des_Verfahrens\"] { position: sticky; left: 80px; z-index: 6; background-color: inherit; border-right: 2px solid #cbd5e1; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }\n.comyli .td { max-width: 400px; }\n#colNames > .td:nth-of-type(1), #colNames > .td:nth-of-type(2), #colNames > .td[data-col=\"_actions\"], #colNames > .td[data-col=\"Name_des_Verfahrens\"] { z-index: 12; }",
                    ],
                    [
                        'capabilities'    => ['fuehrungskraefte'],
                        'edit'            => false,
                        'visible_columns' => [
                            "Name_des_Verfahrens",
                            "Beschreibung",
                            "KI",
                            "Nutzungshinweise",
                            "Link",
                            "Verfahrensadministrator",
                            "Verfahrensverantwortlicher",
                            "Fachdienste",
                            "Finanzvolumen",
                            "Anzahl_Transaktionen",
                            "Anzahl_Benutzer",
                            "Zertifizierungen",
                            "Vorverfahren",
                            "Schnittstellen zu anderen Systemen",
                            "Anwenderdokumentation",
                            "Verfahrensaufruf",
                            "LeikaID",
                            "Kosten",
                            "Bemerkungen",
                        ],
                        'custom_css'      => ".comyli .td:nth-of-type(1), .comyli .td[data-col=\"_actions\"] { width: 80px; min-width: 80px; max-width: 80px; position: sticky; left: 0; z-index: 6; background-color: inherit; border-right: 1px solid #e2e8f0; }\n.comyli .td:nth-of-type(2), .comyli .td[data-col=\"Name_des_Verfahrens\"] { position: sticky; left: 80px; z-index: 6; background-color: inherit; border-right: 2px solid #cbd5e1; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }\n.comyli .td:nth-of-type(4), .comyli .td[data-col=\"KI\"] { max-width: 100px; }\n.comyli .td:nth-of-type(5), .comyli .td[data-col=\"Nutzungshinweise\"] { max-width: 400px; }\n.comyli .td:nth-of-type(6), .comyli .td[data-col=\"Link\"] { max-width: 200px; }\n.comyli .td:nth-of-type(7), .comyli .td[data-col=\"Verfahrensadministrator\"] { max-width: 400px; }\n.comyli .td:nth-of-type(13), .comyli .td:nth-of-type(14), .comyli .td:nth-of-type(15), .comyli .td:nth-of-type(16), .comyli .td[data-col=\"Vorverfahren\"], .comyli .td[data-col=\"Schnittstellen zu anderen Systemen\"], .comyli .td[data-col=\"Anwenderdokumentation\"], .comyli .td[data-col=\"Verfahrensaufruf\"] { width: 400px; }",
                    ],
                    [
                        'capabilities'    => ['*'],
                        'edit'            => false,
                        'visible_columns' => [
                            "Name_des_Verfahrens",
                            "Beschreibung",
                            "KI",
                            "Nutzungshinweise",
                            "Link",
                            "Fachdienste",
                        ],
                        'custom_css'      => ".comyli .td:nth-of-type(1), .comyli .td[data-col=\"_actions\"] { width: 80px; min-width: 80px; max-width: 80px; position: sticky; left: 0; z-index: 6; background-color: inherit; border-right: 1px solid #e2e8f0; }\n.comyli .td:nth-of-type(2), .comyli .td[data-col=\"Name_des_Verfahrens\"] { position: sticky; left: 80px; z-index: 6; background-color: inherit; border-right: 2px solid #cbd5e1; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }\n.comyli .td:nth-of-type(4), .comyli .td[data-col=\"KI\"] { max-width: 50px; }\n.comyli .td:nth-of-type(6), .comyli .td[data-col=\"Link\"] { max-width: 200px; }",
                    ],
                ],
            ],

            'wp_ha_ask_user_for_information' => [
                'foreign_keys' => [
                    'fachdienst'            => ['source' => 'wp_option', 'option' => 'ha_ask_user_for_information_fachdienst', 'property' => 'fachdienst', 'mode' => 'input'],
                    'Sachgebiet'            => ['source' => 'wp_option', 'option' => 'ha_ask_user_for_information_sachgebiet', 'property' => 'sachgebiet', 'mode' => 'input'],
                    'Ort'                   => ['source' => 'wp_option', 'option' => 'ha_ask_user_for_information_standort', 'property' => 'standort', 'mode' => 'input'],
                    'Positionsbezeichnung'  => ['source' => 'sql', 'query' => 'SELECT name FROM wp_ha_ask_user_positionsbezeichnungen', 'mode' => 'input'],
                    'username'              => ['source' => 'wp_users', 'field' => 'user_login', 'include_empty' => true, 'mode' => 'input'],
                ],
            ],

            'wp_ha_fuehrerscheinstelle_gebuehrenliste' => [
                'queries' => './wp-content/plugins/ha-comyli/queries/fuehrerscheinstelle/',
                'ui' => [
                    'insert_top'              => true,
                    'default_order'           => 'created',
                    'default_dir'             => 'DESC',
                    'auto_audit_columns'      => true,
                    'inherit_global_defaults' => true,
                ],
                'columns' => [
                    'Datum'      => ['default' => '{current_date}'],
                    'Gebuehren'  => ['default' => '0.00', 'min' => '0.00'],
                    'FAER'       => ['default' => '0.00', 'min' => '0.00', 'step' => '3.30'],
                    'ZFER_Probe' => ['default' => '0.00', 'min' => '0.00', 'max' => '1.80', 'step' => '1.80'],
                    'ZFER'       => ['default' => '0.00', 'min' => '0.00'],
                    'Status'     => ['default' => 'offen'],
                    'Px'         => ['default' => '{year_letter:2024}'],
                    'nr'         => [
                        'default'       => '{sql_pad4:SELECT SQL_NO_CACHE IFNULL(MAX(nr), 0) + 1 FROM wp_ha_fuehrerscheinstelle_gebuehrenliste WHERE Px=\'{column:Px}\'}',
                        'post_override' => '{sql_pad4:SELECT SQL_NO_CACHE IFNULL(MAX(nr), 0) + 1 FROM wp_ha_fuehrerscheinstelle_gebuehrenliste WHERE Px=\'{column:Px}\'}',
                    ],
                ],
                'foreign_keys' => [
                    'Wohnort' => [
                        'source' => 'sql',
                        'query'  => 'SELECT Wohnort FROM wp_ha_fuehrerscheinstelle_gebuehrenliste GROUP BY Wohnort',
                        'mode'   => 'suggestion',
                    ],
                    'Beleg'   => [
                        'source' => 'sql',
                        'query'  => 'SELECT id FROM wp_ha_zentrale_rechnungsnr LEFT JOIN wp_ha_fuehrerscheinstelle_gebuehrenliste ON wp_ha_zentrale_rechnungsnr.id = wp_ha_fuehrerscheinstelle_gebuehrenliste.Beleg WHERE wp_ha_zentrale_rechnungsnr.fd = \'strassenverkehr\' AND wp_ha_fuehrerscheinstelle_gebuehrenliste.Beleg IS NULL AND wp_ha_zentrale_rechnungsnr.created < NOW() - INTERVAL 2 WEEK ORDER BY wp_ha_zentrale_rechnungsnr.created DESC',
                        'mode'   => 'suggestion',
                    ],
                ],
            ],
        ];

        return $presets[$tableName] ?? null;
    }

    /**
     * Migrates legacy serialized post content and fills _comyli_advanced_config
     * with ready-to-use JSON presets for the 5 legacy tables.
     */
    public function migrateAllTables(): void {
        $postType = $this->config ? ($this->config->plugin['type'] ?? 'comyli') : 'comyli';
        $posts = get_posts([
            'post_type'      => $postType,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
        ]);

        foreach ($posts as $post) {
            $data = [];
            $unserialized = maybe_unserialize($post->post_content);
            if (is_array($unserialized)) {
                $data = $unserialized;
            }

            $meta = get_post_meta($post->ID);
            foreach ($meta as $k => $v) {
                if (str_starts_with($k, '_comyli_')) {
                    $key = substr($k, 8);
                    $data[$key] = maybe_unserialize($v[0]);
                }
            }

            $table = (string)($data['tables'] ?? '');
            if (empty($table)) {
                $table = (string)($data['table'] ?? '');
            }

            // If advanced config is empty, check for built-in preset
            if (empty($data['advanced_config']) && !empty($table)) {
                $preset = self::getBuiltinPreset($table);
                if ($preset !== null) {
                    $data['advanced_config'] = wp_json_encode($preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (empty($data['queries']) && !empty($preset['queries'])) {
                        $data['queries'] = $preset['queries'];
                    }
                }
            }

            if (empty($data)) {
                continue;
            }

            foreach ($data as $key => $val) {
                update_post_meta($post->ID, '_comyli_' . $key, $val);
            }

            wp_update_post([
                'ID'           => $post->ID,
                'post_content' => wp_slash(serialize($data)),
            ]);
        }
    }
}
