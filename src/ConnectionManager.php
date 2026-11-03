<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Manages database connection resolution and table credential mappings.
 * Ensures queries and tables execute on their designated external or local database.
 */
class ConnectionManager {

    /**
     * Resolves configuration and database credentials for a table by its post ID, slug, or table name.
     *
     * @param string|int $tableOrId Table name, post slug, or post ID.
     * @return array<string, mixed>|null Resolved config array, or null if not found.
     */
    public static function resolve_table_config(string|int $tableOrId): ?array {
        $tableOrId = is_string($tableOrId) ? trim($tableOrId) : $tableOrId;
        if ($tableOrId === '' || $tableOrId === 0) {
            return null;
        }

        $post = null;

        // 1. By direct numeric ID
        if (is_numeric($tableOrId) && (int)$tableOrId > 0) {
            $candidate = get_post((int)$tableOrId);
            if ($candidate && $candidate->post_type === 'comyli') {
                $post = $candidate;
            }
        }

        // 2. By post_name (slug)
        if (!$post && is_string($tableOrId)) {
            $posts = get_posts([
                'post_type'      => 'comyli',
                'name'           => sanitize_title($tableOrId),
                'posts_per_page' => 1,
                'post_status'    => ['publish', 'draft'],
            ]);
            if (!empty($posts)) {
                $post = $posts[0];
            }
        }

        // 3. By matching table name in _comyli_tables or post_content
        if (!$post && is_string($tableOrId)) {
            // First check by direct meta match
            $posts = get_posts([
                'post_type'      => 'comyli',
                'posts_per_page' => -1,
                'post_status'    => ['publish', 'draft'],
            ]);

            foreach ($posts as $p) {
                $metaTables = (string)get_post_meta($p->ID, '_comyli_tables', true);
                if (empty($metaTables)) {
                    $unserialized = maybe_unserialize($p->post_content);
                    if (is_array($unserialized) && !empty($unserialized['tables'])) {
                        $metaTables = (string)$unserialized['tables'];
                    }
                }

                $tableList = array_map('trim', explode(',', $metaTables));
                if (in_array($tableOrId, $tableList, true)) {
                    $post = $p;
                    break;
                }
            }
        }

        if (!$post) {
            return null;
        }

        // Build config from post_content and post_meta
        $config = [];
        $unserialized = maybe_unserialize($post->post_content);
        if (is_array($unserialized)) {
            $config = $unserialized;
        }

        $meta = get_post_meta($post->ID);
        foreach ($meta as $k => $v) {
            if (str_starts_with($k, '_comyli_')) {
                $key = substr($k, 8);
                $config[$key] = maybe_unserialize($v[0]);
            }
        }

        $config['comyli_id'] = $post->ID;
        $config['post_title'] = $post->post_title;
        $config['post_name']  = $post->post_name;

        // Normalize use_wp_db boolean
        if (isset($config['use_wp_db'])) {
            $val = $config['use_wp_db'];
            $config['use_wp_db'] = ($val === true || $val === 1 || $val === '1');
        } else {
            // If dbserver is explicitly provided, default to false, else true
            $config['use_wp_db'] = empty($config['dbserver']);
        }

        return $config;
    }

    /**
     * Creates or returns a mysqli connection object for the given configuration.
     *
     * @param array<string, mixed> $config Configuration array.
     * @return \mysqli
     * @throws \Exception When connection to external database fails.
     */
    public static function get_connection_for_config(array $config): \mysqli {
        $useWpDb = !empty($config['use_wp_db']) || empty($config['dbserver']);

        if ($useWpDb) {
            global $wpdb;
            if (isset($wpdb) && $wpdb->dbh instanceof \mysqli) {
                return $wpdb->dbh;
            }

            if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_NAME')) {
                $mysqli = @new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
                if ($mysqli->connect_error) {
                    throw new \Exception('Lokale WordPress-Datenbankverbindung fehlgeschlagen: ' . $mysqli->connect_error);
                }
                @$mysqli->query("SET character_set_results = 'utf8mb4', character_set_client = 'utf8mb4', character_set_connection = 'utf8mb4', character_set_database = 'utf8mb4', character_set_server = 'utf8mb4'");
                return $mysqli;
            }

            throw new \Exception('Keine gültige lokale WordPress-Datenbankverbindung gefunden.');
        }

        $dbserver = (string)($config['dbserver'] ?? '');
        $dbuser   = (string)($config['dbuser'] ?? '');
        $dbpass   = (string)($config['dbpass'] ?? '');
        $db       = (string)($config['db'] ?? '');

        $port = 3306;
        $host = $dbserver;
        if (str_contains($dbserver, ':')) {
            $parts = explode(':', $dbserver, 2);
            $host = $parts[0];
            $port = (int)($parts[1] ?? 3306);
        }

        $mysqli = @new \mysqli($host, $dbuser, $dbpass, $db, $port);
        if ($mysqli->connect_error) {
            throw new \Exception("Verbindung zur externen Datenbank ($host) fehlgeschlagen: " . $mysqli->connect_error);
        }

        @$mysqli->query("SET character_set_results = 'utf8mb4', character_set_client = 'utf8mb4', character_set_connection = 'utf8mb4', character_set_database = 'utf8mb4', character_set_server = 'utf8mb4'");

        return $mysqli;
    }

    /**
     * Resolves the database connection for a specific table name or ID.
     *
     * @param string|int $tableOrId Table name or Post ID.
     * @return \mysqli
     * @throws \Exception
     */
    public static function get_connection_for_table(string|int $tableOrId): \mysqli {
        $config = self::resolve_table_config($tableOrId);
        if ($config) {
            return self::get_connection_for_config($config);
        }
        return self::get_connection_for_config(['use_wp_db' => true]);
    }

    /**
     * Resolves the database connection for a given query identifier.
     * Checks if the query has an associated table definition with external credentials.
     *
     * @param string|int $queryIdentifier Query slug, title or ID.
     * @return \mysqli
     * @throws \Exception
     */
    public static function get_connection_for_query(string|int $queryIdentifier): \mysqli {
        if (class_exists(QueryManager::class)) {
            $post = QueryManager::find_query_post((string)$queryIdentifier);
            if ($post) {
                $tableId = (int)get_post_meta($post->ID, QueryManager::META_TABLE_ID, true);
                if ($tableId > 0) {
                    $tableConfig = self::resolve_table_config($tableId);
                    if ($tableConfig) {
                        return self::get_connection_for_config($tableConfig);
                    }
                }
            }
        }

        return self::get_connection_for_config(['use_wp_db' => true]);
    }

    /**
     * Returns a formatted list of all available CoMyLi tables for select dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_all_tables_options(): array {
        $posts = get_posts([
            'post_type'      => 'comyli',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $options = [];
        foreach ($posts as $p) {
            $unserialized = maybe_unserialize($p->post_content);
            $data = is_array($unserialized) ? $unserialized : [];
            $meta = get_post_meta($p->ID);
            foreach ($meta as $k => $v) {
                if (str_starts_with($k, '_comyli_')) {
                    $data[substr($k, 8)] = maybe_unserialize($v[0]);
                }
            }

            $useWpDb = !empty($data['use_wp_db']) && $data['use_wp_db'] !== '0';
            $dbName = (string)($data['db'] ?? '');
            $dbServer = (string)($data['dbserver'] ?? '');
            $tables = (string)($data['tables'] ?? '');

            $dbInfo = $useWpDb ? 'Lokale WordPress-DB' : ($dbName ? "$dbName @ $dbServer" : 'Externe DB');

            $options[] = [
                'id'        => $p->ID,
                'title'     => $p->post_title ?: 'Tabelle #' . $p->ID,
                'slug'      => $p->post_name,
                'tables'    => $tables,
                'db_info'   => $dbInfo,
                'use_wp_db' => $useWpDb,
            ];
        }

        return $options;
    }
}
