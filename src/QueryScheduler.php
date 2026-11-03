<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Manages recurring scheduled execution of queries, CSV archiving, and download management.
 */
class QueryScheduler {
    public const CRON_HOOK = 'comyli_scheduled_queries_cron';
    public const META_INTERVAL = '_comyli_schedule_interval';
    public const META_NEXT_RUN = '_comyli_next_run';
    public const META_LAST_RUN = '_comyli_last_run';
    public const META_ARCHIVES = '_comyli_archives';

    private Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        add_filter('cron_schedules', [$this, 'addCronIntervals']);
        add_action(self::CRON_HOOK, [$this, 'processScheduledQueries']);

        // Admin actions
        add_action('admin_post_comyli_download_archive', [$this, 'handleDownloadArchive']);
        add_action('admin_post_comyli_run_archive_now', [$this, 'handleManualRunArchive']);
        add_action('admin_post_comyli_delete_archive', [$this, 'handleDeleteArchive']);

        // Schedule master runner if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Adds weekly and monthly intervals to WordPress Cron schedules.
     *
     * @param array<string, array<string, mixed>> $schedules Existing schedules.
     * @return array<string, array<string, mixed>>
     */
    public function addCronIntervals(array $schedules): array {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => 7 * DAY_IN_SECONDS,
                'display'  => 'Wöchentlich',
            ];
        }
        if (!isset($schedules['monthly'])) {
            $schedules['monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => 'Monatlich (alle 30 Tage)',
            ];
        }
        return $schedules;
    }

    /**
     * Master cron worker: scans for queries that are due and executes/archives them.
     */
    public function processScheduledQueries(): void {
        $posts = get_posts([
            'post_type'      => QueryManager::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => self::META_INTERVAL,
                    'value'   => ['daily', 'weekly', 'monthly'],
                    'compare' => 'IN',
                ],
            ],
        ]);

        $now = time();

        foreach ($posts as $post) {
            $interval = get_post_meta($post->ID, self::META_INTERVAL, true);
            $nextRun  = (int)get_post_meta($post->ID, self::META_NEXT_RUN, true);

            // Execute if nextRun is past or never set
            if ($nextRun === 0 || $now >= $nextRun) {
                $this->archiveQuery($post->ID);

                // Compute next run time
                $next = $this->calculateNextRunTime($interval, $now);
                update_post_meta($post->ID, self::META_LAST_RUN, $now);
                update_post_meta($post->ID, self::META_NEXT_RUN, $next);
            }
        }
    }

    /**
     * Calculates timestamp for the next run.
     */
    public function calculateNextRunTime(string $interval, int $baseTime): int {
        switch ($interval) {
            case 'daily':
                return $baseTime + DAY_IN_SECONDS;
            case 'weekly':
                return $baseTime + (7 * DAY_IN_SECONDS);
            case 'monthly':
                return $baseTime + (30 * DAY_IN_SECONDS);
            default:
                return 0;
        }
    }

    /**
     * Executes the query and saves its output as a CSV file in the uploads archive directory.
     *
     * @param int $queryId Query Post ID.
     * @return array<string, mixed> Result status.
     */
    public function archiveQuery(int $queryId): array {
        $post = get_post($queryId);
        if (!$post || $post->post_type !== QueryManager::POST_TYPE) {
            return ['success' => false, 'message' => 'Abfrage nicht gefunden.'];
        }

        $slug = $post->post_name ?: 'abfrage-' . $post->ID;
        $sql = QueryManager::get_query_sql((string)$queryId);

        if (empty($sql)) {
            return ['success' => false, 'message' => 'Kein SQL-Abfragecode hinterlegt.'];
        }

        // Resolve dynamic context parameters
        require_once $this->config->plugin['path'] . '/sql-admin/index.php';
        $cfg = $this->config->plugin;
        $configHandler = new \Comyli\Standalone\ConfigHandler($cfg);
        $resolvedSql = \Comyli\Standalone\FieldHandler::resolveParameters($sql, $configHandler);

        try {
            $conn = ConnectionManager::get_connection_for_query($queryId);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()];
        }

        $res = @$conn->query($resolvedSql);
        if (!$res || !($res instanceof \mysqli_result)) {
            $err = $conn->error ?: 'Unbekannter SQL-Fehler.';
            return ['success' => false, 'message' => 'SQL-Ausführungsfehler: ' . $err];
        }

        // Setup storage directory
        $uploadDir = wp_upload_dir();
        $baseDir = rtrim($uploadDir['basedir'], '/\\') . '/ha-comyli-archives/' . $queryId . '/';

        if (!file_exists($baseDir)) {
            wp_mkdir_p($baseDir);
            @file_put_contents($baseDir . 'index.php', '<?php // Silence is golden.');
            @file_put_contents($baseDir . '.htaccess', "Order Deny,Allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>");
        }

        $tableId = (int)get_post_meta($queryId, QueryManager::META_TABLE_ID, true);
        $nameParts = [];
        if ($tableId > 0) {
            $tablePost = get_post($tableId);
            if ($tablePost) {
                $rawTable = get_post_meta($tableId, '_comyli_tables', true);
                if (empty($rawTable)) {
                    $rawTable = $tablePost->post_name ?: $tablePost->post_title;
                }
                $cleanTable = preg_replace('/[^a-zA-Z0-9_-]/', '_', $rawTable);
                if (!empty($cleanTable)) {
                    $nameParts[] = $cleanTable;
                }
            }
        }
        $cleanSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);
        $nameParts[] = 'abfrage_' . $cleanSlug;

        $baseName = !empty($nameParts) ? implode('_', $nameParts) : 'abfrage_' . $queryId;
        $baseName = preg_replace('/_+/', '_', trim($baseName, '_'));
        $filename = $baseName . '_' . gmdate('Y-m-d_H-i-s') . '.csv';
        $filePath = $baseDir . $filename;

        $fp = @fopen($filePath, 'w');
        if (!$fp) {
            return ['success' => false, 'message' => 'Konnte CSV-Datei nicht zum Schreiben öffnen.'];
        }

        // Write UTF-8 BOM for Microsoft Excel compatibility
        fwrite($fp, "\xEF\xBB\xBF");

        $rowCount = 0;
        $headersWritten = false;

        while ($row = $res->fetch_assoc()) {
            if (!$headersWritten) {
                fputcsv($fp, array_keys($row), ';');
                $headersWritten = true;
            }
            fputcsv($fp, array_values($row), ';');
            $rowCount++;
        }

        // If no rows were returned, write column headers from field metadata if available
        if (!$headersWritten) {
            $fields = [];
            foreach ($res->fetch_fields() as $f) {
                $fields[] = $f->name;
            }
            if (!empty($fields)) {
                fputcsv($fp, $fields, ';');
            }
        }

        fclose($fp);
        $fileSize = (int)@filesize($filePath);

        // Update archive metadata
        $archiveId = uniqid('arc_');
        $archives = get_post_meta($queryId, self::META_ARCHIVES, true);
        if (!is_array($archives)) {
            $archives = [];
        }

        array_unshift($archives, [
            'id'         => $archiveId,
            'filename'   => $filename,
            'created_at' => time(),
            'row_count'  => $rowCount,
            'file_size'  => $fileSize,
        ]);

        // Keep last 50 archives
        if (count($archives) > 50) {
            $removed = array_slice($archives, 50);
            foreach ($removed as $rm) {
                $oldFile = $baseDir . ($rm['filename'] ?? '');
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $archives = array_slice($archives, 0, 50);
        }

        update_post_meta($queryId, self::META_ARCHIVES, $archives);
        update_post_meta($queryId, self::META_LAST_RUN, time());

        return [
            'success'   => true,
            'message'   => "Erfolgreich archiviert: $filename ($rowCount Zeilen).",
            'filename'  => $filename,
            'row_count' => $rowCount,
            'file_size' => $fileSize,
        ];
    }

    /**
     * Handles manual execution & archiving trigger from backend.
     */
    public function handleManualRunArchive(): void {
        $queryId = (int)($_POST['query_id'] ?? 0);
        check_admin_referer('comyli_manual_archive_' . $queryId);

        if (!current_user_can('edit_post', $queryId)) {
            wp_die('Keine Berechtigung.');
        }

        $result = $this->archiveQuery($queryId);

        $redirectUrl = add_query_arg([
            'post'   => $queryId,
            'action' => 'edit',
            'comyli_archived' => $result['success'] ? '1' : '0',
            'comyli_msg' => urlencode($result['message']),
        ], admin_url('post.php'));

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * Secure CSV archive download endpoint.
     */
    public function handleDownloadArchive(): void {
        $queryId   = (int)($_GET['query_id'] ?? 0);
        $archiveId = sanitize_text_field($_GET['archive_id'] ?? '');

        if (!$queryId || empty($archiveId)) {
            wp_die('Ungültige Anfrage.');
        }

        $post = get_post($queryId);
        if (!$post || $post->post_type !== QueryManager::POST_TYPE) {
            wp_die('Abfrage nicht gefunden.');
        }

        // Capability check
        $slug = $post->post_name ?: 'abfrage-' . $post->ID;
        if (!current_user_can('manage_options') && !Permissions::can_view_query($slug)) {
            wp_die('Keine Berechtigung zum Herunterladen dieses Abfrage-Archivs.');
        }

        $archives = get_post_meta($queryId, self::META_ARCHIVES, true);
        if (!is_array($archives)) {
            wp_die('Keine Archive vorhanden.');
        }

        $target = null;
        foreach ($archives as $arc) {
            if (($arc['id'] ?? '') === $archiveId) {
                $target = $arc;
                break;
            }
        }

        if (!$target) {
            wp_die('Archiv nicht gefunden.');
        }

        $uploadDir = wp_upload_dir();
        $filePath = rtrim($uploadDir['basedir'], '/\\') . '/ha-comyli-archives/' . $queryId . '/' . basename($target['filename']);

        if (!file_exists($filePath)) {
            wp_die('Archivdatei nicht mehr auf dem Server vorhanden.');
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($target['filename']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }

    /**
     * Deletes an archive file and removes its entry from post metadata.
     */
    public function handleDeleteArchive(): void {
        $queryId   = (int)($_POST['query_id'] ?? 0);
        $archiveId = sanitize_text_field($_POST['archive_id'] ?? '');
        check_admin_referer('comyli_delete_archive_' . $archiveId);

        if (!current_user_can('edit_post', $queryId)) {
            wp_die('Keine Berechtigung.');
        }

        $archives = get_post_meta($queryId, self::META_ARCHIVES, true);
        if (is_array($archives)) {
            $uploadDir = wp_upload_dir();
            $baseDir = rtrim($uploadDir['basedir'], '/\\') . '/ha-comyli-archives/' . $queryId . '/';

            $newArchives = [];
            foreach ($archives as $arc) {
                if (($arc['id'] ?? '') === $archiveId) {
                    $file = $baseDir . basename($arc['filename']);
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                } else {
                    $newArchives[] = $arc;
                }
            }
            update_post_meta($queryId, self::META_ARCHIVES, $newArchives);
        }

        wp_safe_redirect(add_query_arg(['post' => $queryId, 'action' => 'edit'], admin_url('post.php')));
        exit;
    }
}
