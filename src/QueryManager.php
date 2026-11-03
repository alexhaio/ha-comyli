<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Manages the Custom Post Type 'comyli_query' for defining SQL queries and capability requirements.
 * Provides backend GUI for queries, capabilities, database connections, and block/shortcode integrations.
 */
class QueryManager {
    public const POST_TYPE = 'comyli_query';
    public const META_CAPABILITY = '_comyli_capability';
    public const META_DESCRIPTION = '_comyli_description';
    public const META_CATEGORY = '_comyli_category';
    public const META_TABLE_ID = '_comyli_table_id';

    private Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        add_action('init', [$this, 'registerPostType']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'savePost']);
        add_filter('wp_insert_post_data', [$this, 'filterPostData'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueEditorAssets']);
        
        // Revision support for SQL queries and metadata
        add_filter('_wp_post_revision_fields', [$this, 'filterRevisionFields'], 10, 2);
        add_filter('wp_post_revision_meta_keys', [$this, 'filterRevisionMetaKeys']);
        add_action('wp_restore_post_revision', [$this, 'restoreRevisionMeta'], 10, 2);

        // Custom Columns in post list
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'setCustomColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderCustomColumn'], 10, 2);
    }

    public function registerPostType(): void {
        $labels = [
            'name'               => 'Abfragen (Queries)',
            'singular_name'      => 'Abfrage',
            'menu_name'          => 'Abfragen',
            'all_items'          => 'Alle Abfragen',
            'add_new'            => 'Erstellen',
            'add_new_item'       => 'Neue Abfrage erstellen',
            'edit_item'          => 'Abfrage bearbeiten',
            'new_item'           => 'Neue Abfrage',
            'view_item'          => 'Abfrage ansehen',
            'search_items'       => 'Abfragen suchen',
            'not_found'          => 'Keine Abfragen gefunden.',
            'not_found_in_trash' => 'Keine Abfragen im Papierkorb.',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=' . $this->config->plugin['type'],
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 31,
            'menu_icon'          => 'dashicons-database',
            'supports'           => ['title', 'revisions'],
            'taxonomies'         => ['category'],
        ];

        register_post_type(self::POST_TYPE, $args);
        register_taxonomy_for_object_type('category', self::POST_TYPE);
    }

    public function enqueueEditorAssets($hook): void {
        global $post_type;
        if ($post_type === self::POST_TYPE) {
            $url = $this->config->plugin['url'];
            wp_enqueue_style('prism-css', $url . 'sql-admin/assets/prism.min.css');
            wp_enqueue_script('codejar-js', $url . 'sql-admin/assets/codejar.js', [], null, true);
            wp_enqueue_script('prism-js', $url . 'sql-admin/assets/prism.min.js', [], null, true);
            wp_enqueue_script('prism-sql-js', $url . 'sql-admin/assets/prism-sql.min.js', ['prism-js'], null, true);

            // Select2 for capability selection
            wp_enqueue_style('select2', $url . 'select2/select2.min.css');
            wp_enqueue_script('select2', $url . 'select2/select2.min.js', ['jquery'], null, true);
        }
    }

    public function addMetaBoxes(): void {
        // 1. SQL Editor
        add_meta_box(
            'comyli_query_sql_box',
            'SQL-Abfragecode',
            [$this, 'renderSqlBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        // 2. Berechtigungs- und Datenquellen-Konfiguration
        add_meta_box(
            'comyli_query_perms_box',
            'Datenbank-Quelle & Berechtigung',
            [$this, 'renderPermissionsBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        // 3. Regelmäßige Ausführung & Archivierung
        add_meta_box(
            'comyli_query_schedule_box',
            'Automatische Ausführung & Archivierung (CSV)',
            [$this, 'renderSchedulerBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        // 4. Info & Shortcode / Block
        add_meta_box(
            'comyli_query_usage_box',
            'Verwendung & Integration',
            [$this, 'renderUsageBox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function renderSqlBox(\WP_Post $post): void {
        wp_nonce_field('comyli_save_query_meta', 'comyli_query_nonce');
        $sql = $post->post_content;
        ?>
        <div style="margin-bottom:12px;">
            <p class="description">
                Geben Sie hier Ihre SQL-Abfrage ein. Unterstützt benannte Parameter (z. B. <code>:unterkunft_id</code>) und dynamische Kontext-Token (z. B. <code>{current_user_id}</code>).
            </p>
            <div id="comyli_sql_editor" style="width:100%; min-height:220px; font-family:monospace; font-size:13px; background:#fff; border:1px solid #ccd0d4; padding:12px; box-sizing:border-box; border-radius:4px; line-height:1.5;"></div>
            <textarea id="comyli_sql_code" name="comyli_sql_code" style="display:none;"><?php echo esc_textarea($sql); ?></textarea>
        </div>

        <div style="background:#f0f6fc; border-left:4px solid #2271b1; padding:8px 12px; font-size:12px; color:#1d2327;">
            <strong>Parameter-Hilfe:</strong> Verwenden Sie <code>:param_name</code> in der SQL-Abfrage, um Werte dynamisch per Shortcode (<code>params="param_name=123"</code>) oder REST-API zu binden.
        </div>

        <script>
        jQuery(document).ready(function($) {
            const editor = document.getElementById('comyli_sql_editor');
            const hidden = document.getElementById('comyli_sql_code');
            if (editor && hidden) {
                editor.textContent = hidden.value;
                if (typeof CodeJar !== 'undefined') {
                    const highlight = el => {
                        if (typeof Prism !== 'undefined' && Prism.languages && Prism.languages.sql) {
                            el.innerHTML = Prism.highlight(el.textContent, Prism.languages.sql, 'sql');
                        }
                    };
                    const jar = CodeJar(editor, highlight);
                    jar.updateCode(hidden.value);
                    jar.onUpdate(code => {
                        hidden.value = code;
                    });
                } else {
                    editor.setAttribute('contenteditable', 'true');
                    editor.addEventListener('input', () => {
                        hidden.value = editor.textContent;
                    });
                }
            }
        });
        </script>
        <?php
    }

    public function renderPermissionsBox(\WP_Post $post): void {
        $currentCap = get_post_meta($post->ID, self::META_CAPABILITY, true);
        $desc = get_post_meta($post->ID, self::META_DESCRIPTION, true);
        
        $currentTableId = (int)get_post_meta($post->ID, self::META_TABLE_ID, true);
        if ($currentTableId === 0 && isset($_GET['comyli_table_id'])) {
            $currentTableId = (int)$_GET['comyli_table_id'];
        }

        // Dynamically fetch all capabilities registered in the WordPress system
        $systemCaps = self::get_all_system_capabilities();
        $tableOptions = ConnectionManager::get_all_tables_options();
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="comyli_table_id">Datenquelle / Datenbank</label></th>
                    <td>
                        <select id="comyli_table_id" name="comyli_table_id" style="min-width:320px; max-width:100%;">
                            <option value="0" <?php selected($currentTableId, 0); ?>>Lokale WordPress-Datenbank (Standard)</option>
                            <?php foreach ($tableOptions as $opt): ?>
                                <option value="<?php echo esc_attr($opt['id']); ?>" <?php selected($currentTableId, $opt['id']); ?>>
                                    <?php echo esc_html($opt['title'] . ' (DB: ' . $opt['db_info'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Wählen Sie eine definierte Tabelle, um deren Datenbankverbindung (Server, Port, Benutzer, Passwort) für diese Abfrage zu nutzen, oder wählen Sie die Standard-WordPress-Datenbank.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="comyli_capability">Erforderliche Berechtigung (Capability)</label></th>
                    <td>
                        <select id="comyli_capability" name="comyli_capability" class="comyli-cap-select2" style="width:100%; min-width:320px;">
                            <option value="">-- Keine spezielle Capability (Öffentlich für alle / read) --</option>
                            <?php if (!empty($currentCap) && !in_array($currentCap, $systemCaps, true)): ?>
                                <option value="<?php echo esc_attr($currentCap); ?>" selected><?php echo esc_html($currentCap); ?> (Benutzerdefiniert)</option>
                            <?php endif; ?>
                            <?php foreach ($systemCaps as $cap): ?>
                                <option value="<?php echo esc_attr($cap); ?>" <?php selected($currentCap, $cap); ?>>
                                    <?php echo esc_html($cap); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Wählen Sie eine im System vorhandene WordPress-Capability aus oder tippen Sie eine eigene ein. Benutzer ohne diese Berechtigung können die Abfrage weder ausführen noch einsehen.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="comyli_description">Kurzbeschreibung (für Abfrage-Listen)</label></th>
                    <td>
                        <input type="text" id="comyli_description" name="comyli_description" value="<?php echo esc_attr($desc); ?>" class="large-text" placeholder="z. B. Auslastungsquote aller Gemeinschaftsunterkünfte nach Kommunen">
                        <p class="description">Wird im Block <em>CoMyLi Abfrage-Liste</em> und in Übersichten angezeigt.</p>
                    </td>
                </tr>

            </tbody>
        </table>

        <script>
        jQuery(document).ready(function($) {
            if (typeof $.fn.select2 !== 'undefined') {
                $('#comyli_capability').select2({
                    placeholder: 'Capability suchen oder eingeben...',
                    allowClear: true,
                    tags: true,
                    width: 'resolve'
                });
            }
        });
        </script>
        <?php
    }

    public function renderSchedulerBox(\WP_Post $post): void {
        $interval = get_post_meta($post->ID, QueryScheduler::META_INTERVAL, true) ?: 'none';
        $lastRun  = (int)get_post_meta($post->ID, QueryScheduler::META_LAST_RUN, true);
        $nextRun  = (int)get_post_meta($post->ID, QueryScheduler::META_NEXT_RUN, true);
        $archives = get_post_meta($post->ID, QueryScheduler::META_ARCHIVES, true);
        if (!is_array($archives)) {
            $archives = [];
        }

        if (isset($_GET['comyli_archived'])) {
            $msg = isset($_GET['comyli_msg']) ? urldecode($_GET['comyli_msg']) : '';
            if ($_GET['comyli_archived'] === '1') {
                echo '<div class="notice notice-success is-dismissible" style="margin:10px 0;"><p>' . esc_html($msg ?: 'Abfrage erfolgreich ausgeführt und archiviert.') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible" style="margin:10px 0;"><p>' . esc_html($msg ?: 'Fehler bei der Archivierung.') . '</p></div>';
            }
        }
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="comyli_schedule_interval">Ausführungs-Intervall</label></th>
                    <td>
                        <select id="comyli_schedule_interval" name="comyli_schedule_interval" style="min-width:260px;">
                            <option value="none" <?php selected($interval, 'none'); ?>>Deaktiviert (Keine automatische Archivierung)</option>
                            <option value="daily" <?php selected($interval, 'daily'); ?>>Täglich</option>
                            <option value="weekly" <?php selected($interval, 'weekly'); ?>>Wöchentlich</option>
                            <option value="monthly" <?php selected($interval, 'monthly'); ?>>Monatlich (alle 30 Tage)</option>
                        </select>
                        <p class="description">
                            Wird per WP-Cron bei erstmöglicher Gelegenheit ausgeführt. Das Abfrageergebnis wird als CSV-Datei auf dem Server archiviert und steht zum Download bereit.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <div style="font-size:13px; line-height:1.8;">
                            <strong>Letzte Archivierung:</strong> 
                            <?php echo $lastRun ? esc_html(wp_date('d.m.Y H:i:s', $lastRun)) : '<em>Noch nie ausgeführt</em>'; ?><br>
                            
                            <strong>Nächste geplante Ausführung:</strong> 
                            <?php 
                            if ($interval === 'none') {
                                echo '<em>Inaktiv</em>';
                            } elseif ($nextRun > 0) {
                                echo esc_html(wp_date('d.m.Y H:i:s', $nextRun));
                                if ($nextRun <= time()) {
                                    echo ' <span style="color:#d97706; font-weight:bold;">(fällig bei nächstem Aufruf)</span>';
                                }
                            } else {
                                echo '<em>Ausstehend beim nächsten Lauf</em>';
                            }
                            ?>
                        </div>
                        <?php if ($post->post_status === 'publish'): ?>
                            <div style="margin-top:10px;">
                                <button type="button" class="button button-secondary" onclick="document.getElementById('comyli_manual_run_form').submit();">
                                    ▶ Jetzt manuell ausführen & archivieren
                                </button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h4 style="margin:20px 0 10px 0; border-top:1px solid #ccd0d4; padding-top:15px;">Archivierte CSV-Dateien (<?php echo count($archives); ?>)</h4>
        <?php if (empty($archives)): ?>
            <p style="color:#64748b; font-style:italic;">Noch keine Archive vorhanden. Aktivieren Sie ein Intervall oder führen Sie die Abfrage manuell aus.</p>
        <?php else: ?>
            <table class="widefat striped" style="max-width:100%; font-size:12px;">
                <thead>
                    <tr>
                        <th>Erstellt am</th>
                        <th>Dateiname</th>
                        <th>Zeilen</th>
                        <th>Größe</th>
                        <th style="text-align:right;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archives as $arc): 
                        $downloadUrl = add_query_arg([
                            'action'     => 'comyli_download_archive',
                            'query_id'   => $post->ID,
                            'archive_id' => $arc['id'],
                        ], admin_url('admin-post.php'));
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html(wp_date('d.m.Y H:i:s', (int)($arc['created_at'] ?? 0))); ?></strong></td>
                            <td><code><?php echo esc_html($arc['filename'] ?? ''); ?></code></td>
                            <td><?php echo number_format_i18n((int)($arc['row_count'] ?? 0)); ?></td>
                            <td><?php echo size_format((int)($arc['file_size'] ?? 0)); ?></td>
                            <td style="text-align:right;">
                                <a href="<?php echo esc_url($downloadUrl); ?>" class="button button-small button-primary" style="margin-right:5px;">
                                    ⬇ CSV herunterladen
                                </a>
                                <button type="button" class="button button-small" style="color:#dc2626; border-color:#dc2626;" onclick="if(confirm('Archiv wirklich löschen?')) { document.getElementById('comyli_delete_arc_id').value='<?php echo esc_attr($arc['id']); ?>'; document.getElementById('comyli_delete_form').submit(); }">
                                    Löschen
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Hidden forms for actions -->
        <form id="comyli_manual_run_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
            <?php wp_nonce_field('comyli_manual_archive_' . $post->ID); ?>
            <input type="hidden" name="action" value="comyli_run_archive_now">
            <input type="hidden" name="query_id" value="<?php echo esc_attr($post->ID); ?>">
        </form>

        <form id="comyli_delete_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
            <input type="hidden" name="action" value="comyli_delete_archive">
            <input type="hidden" name="query_id" value="<?php echo esc_attr($post->ID); ?>">
            <input type="hidden" id="comyli_delete_arc_id" name="archive_id" value="">
            <?php wp_nonce_field('comyli_delete_archive_' . ($archives[0]['id'] ?? 'none')); ?>
        </form>
        <?php
    }

    public function renderUsageBox(\WP_Post $post): void {
        $slug = $post->post_name ?: sanitize_title($post->post_title ?: 'abfrage-' . $post->ID);
        $shortcode = '[comyli_table query="' . $slug . '" layout="clean"]';
        ?>
        <p><strong>Query-Bezeichner:</strong><br>
        <code><?php echo esc_html($slug); ?></code></p>

        <p><strong>Shortcode:</strong><br>
        <input type="text" readonly value="<?php echo esc_attr($shortcode); ?>" style="width:100%; font-family:monospace; font-size:12px;" onclick="this.select();"></p>

        <p><strong>Gutenberg-Block:</strong><br>
        Fügen Sie den Block <em>CoMyLi Tabelle</em> ein und wählen Sie <code><?php echo esc_html($slug); ?></code> als Abfrage.</p>

        <p><strong>In Abfrage-Liste anzeigen:</strong><br>
        Mit dem Block <em>CoMyLi Abfrage-Liste</em> (oder <code>[comyli_query_list]</code>) wird diese Abfrage allen berechtigten Nutzern automatisch im Dashboard angeboten.</p>
        <?php
    }

    public function setCustomColumns(array $columns): array {
        $newCols = [];
        $newCols['cb'] = $columns['cb'] ?? '<input type="checkbox" />';
        $newCols['title'] = 'Abfragetitel';
        $newCols['slug'] = 'Bezeichner (Slug)';
        $newCols['table'] = 'Datenquelle / DB';
        $newCols['category'] = 'Kategorie';
        $newCols['capability'] = 'Erforderliche Capability';
        $newCols['archive'] = 'Archivierung';
        $newCols['shortcode'] = 'Shortcode';
        $newCols['date'] = 'Erstellt / Geändert';
        return $newCols;
    }

    public function renderCustomColumn(string $column, int $post_id): void {
        $post = get_post($post_id);
        switch ($column) {
            case 'slug':
                echo '<code>' . esc_html($post->post_name) . '</code>';
                break;
            case 'table':
                $tableId = (int)get_post_meta($post_id, self::META_TABLE_ID, true);
                if ($tableId > 0) {
                    $tbl = get_post($tableId);
                    if ($tbl) {
                        $tblName = esc_html($tbl->post_title);
                        $editUrl = get_edit_post_link($tableId);
                        echo '<a href="' . esc_url($editUrl) . '"><strong>' . $tblName . '</strong></a>';
                    } else {
                        echo '<span style="color:#64748b;">Tabelle #' . $tableId . '</span>';
                    }
                } else {
                    echo '<span style="color:#0284c7;">WordPress-DB</span>';
                }
                break;
            case 'category':
                $categories = get_the_category($post_id);
                if (!empty($categories)) {
                    $catLinks = [];
                    foreach ($categories as $cat) {
                        $catUrl = add_query_arg([
                            'post_type'     => self::POST_TYPE,
                            'category_name' => $cat->slug,
                        ], 'edit.php');
                        $catLinks[] = '<a href="' . esc_url($catUrl) . '" style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px; font-size:11px; text-decoration:none; font-weight:500;">' . esc_html($cat->name) . '</a>';
                    }
                    echo implode(' ', $catLinks);
                } else {
                    $legacyCat = get_post_meta($post_id, self::META_CATEGORY, true);
                    echo $legacyCat ? '<span style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:12px; font-size:11px;">' . esc_html($legacyCat) . '</span>' : '—';
                }
                break;
            case 'capability':
                $cap = get_post_meta($post_id, self::META_CAPABILITY, true);
                if (empty($cap) || $cap === 'read') {
                    echo '<span style="color:#059669; font-weight:600;">✓ Öffentlich (read)</span>';
                } elseif ($cap === 'manage_options') {
                    echo '<span style="color:#dc2626; font-weight:600;">🔒 Admin (manage_options)</span>';
                } else {
                    echo '<span style="background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:4px; font-family:monospace; font-size:11px; font-weight:600;">🔒 ' . esc_html($cap) . '</span>';
                }
                break;
            case 'archive':
                $interval = get_post_meta($post_id, QueryScheduler::META_INTERVAL, true) ?: 'none';
                $archives = get_post_meta($post_id, QueryScheduler::META_ARCHIVES, true);
                $count = is_array($archives) ? count($archives) : 0;
                
                $labels = [
                    'daily'   => 'Täglich',
                    'weekly'  => 'Wöchentlich',
                    'monthly' => 'Monatlich',
                    'none'    => '—',
                ];
                $intLabel = $labels[$interval] ?? '—';
                echo '<strong>' . esc_html($intLabel) . '</strong>';
                if ($count > 0) {
                    echo '<br><span style="color:#64748b; font-size:11px;">' . $count . ' Archive</span>';
                }
                break;
            case 'shortcode':
                $slug = $post->post_name ?: 'abfrage-' . $post_id;
                echo '<input type="text" readonly value="[comyli_table query=&quot;' . esc_attr($slug) . '&quot; layout=&quot;clean&quot;]" style="width:100%; font-size:11px; font-family:monospace;" onclick="this.select();">';
                break;
        }
    }

    public function savePost(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['comyli_query_nonce']) || !wp_verify_nonce($_POST['comyli_query_nonce'], 'comyli_save_query_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save SQL in post_content if not already persisted by filterPostData
        if (isset($_POST['comyli_sql_code'])) {
            $sql = wp_unslash($_POST['comyli_sql_code']);
            $currentPost = get_post($post_id);
            if ($currentPost && $currentPost->post_content !== $sql) {
                remove_action('save_post_' . self::POST_TYPE, [$this, 'savePost']);
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => $sql,
                ]);
                add_action('save_post_' . self::POST_TYPE, [$this, 'savePost']);
            }
        }

        // Meta fields
        if (isset($_POST['comyli_capability'])) {
            $cap = sanitize_text_field($_POST['comyli_capability']);
            update_post_meta($post_id, self::META_CAPABILITY, $cap);
        }
        if (isset($_POST['comyli_description'])) {
            update_post_meta($post_id, self::META_DESCRIPTION, sanitize_text_field($_POST['comyli_description']));
        }
        if (isset($_POST['comyli_category'])) {
            update_post_meta($post_id, self::META_CATEGORY, sanitize_text_field($_POST['comyli_category']));
        }
        
        // Associated table / external DB
        $tableId = intval($_POST['comyli_table_id'] ?? 0);
        update_post_meta($post_id, self::META_TABLE_ID, $tableId);

        // Schedule interval
        if (isset($_POST['comyli_schedule_interval'])) {
            $newInterval = sanitize_key($_POST['comyli_schedule_interval']);
            $oldInterval = get_post_meta($post_id, QueryScheduler::META_INTERVAL, true);
            update_post_meta($post_id, QueryScheduler::META_INTERVAL, $newInterval);

            if ($newInterval !== 'none' && ($newInterval !== $oldInterval || !get_post_meta($post_id, QueryScheduler::META_NEXT_RUN, true))) {
                $base = time();
                switch ($newInterval) {
                    case 'daily':   $next = $base + DAY_IN_SECONDS; break;
                    case 'weekly':  $next = $base + (7 * DAY_IN_SECONDS); break;
                    case 'monthly': $next = $base + (30 * DAY_IN_SECONDS); break;
                    default:        $next = 0; break;
                }
                update_post_meta($post_id, QueryScheduler::META_NEXT_RUN, $next);
            }
        }
    }

    /**
     * Extracts all capabilities present across all user roles in WordPress.
     *
     * @return array<int, string>
     */
    public static function get_all_system_capabilities(): array {
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        $caps = [];
        if (!empty($wp_roles->roles) && is_array($wp_roles->roles)) {
            foreach ($wp_roles->roles as $roleKey => $roleData) {
                if (!empty($roleData['capabilities']) && is_array($roleData['capabilities'])) {
                    foreach ($roleData['capabilities'] as $capName => $granted) {
                        $caps[$capName] = true;
                    }
                }
            }
        }

        // Standard WordPress Core capabilities
        $coreCaps = [
            'read', 'edit_posts', 'edit_others_posts', 'publish_posts', 'manage_options',
            'edit_pages', 'publish_pages', 'upload_files', 'moderate_comments',
        ];
        foreach ($coreCaps as $c) {
            $caps[$c] = true;
        }

        $list = array_keys($caps);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);
        return $list;
    }

    /**
     * Retrieves the raw SQL code of a query by its slug, title or post ID.
     */
    public static function get_query_sql(string $queryIdentifier): ?string {
        $post = self::find_query_post($queryIdentifier);
        if ($post && !empty($post->post_content)) {
            return $post->post_content;
        }
        return null;
    }

    /**
     * Retrieves the required capability for a given query identifier.
     */
    public static function get_query_capability(string $queryIdentifier): string {
        $post = self::find_query_post($queryIdentifier);
        if ($post) {
            $cap = get_post_meta($post->ID, self::META_CAPABILITY, true);
            if ($cap !== '') {
                return $cap;
            }
        }
        return '';
    }

    /**
     * Returns an array of all available queries that the given user has permission to view.
     * 
     * @param int|null $userId User ID to check against (defaults to current logged-in user).
     * @param string|null $category Optional category filter.
     * @return array<int, array<string, mixed>>
     */
    public static function get_available_queries(?int $userId = null, ?string $category = null): array {
        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $posts = get_posts($args);
        $available = [];

        foreach ($posts as $p) {
            $slug = $p->post_name ?: 'abfrage-' . $p->ID;
            $terms = get_the_category($p->ID);
            $catNames = !empty($terms) ? wp_list_pluck($terms, 'name') : [];
            $catSlugs = !empty($terms) ? wp_list_pluck($terms, 'slug') : [];
            $legacyCat = get_post_meta($p->ID, self::META_CATEGORY, true);
            if (!empty($legacyCat) && !in_array($legacyCat, $catNames, true)) {
                $catNames[] = $legacyCat;
            }

            if ($category) {
                $match = false;
                foreach ($catNames as $cn) {
                    if (strcasecmp($cn, $category) === 0) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    foreach ($catSlugs as $cs) {
                        if (strcasecmp($cs, $category) === 0) {
                            $match = true;
                            break;
                        }
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            $cap = get_post_meta($p->ID, self::META_CAPABILITY, true);
            $canView = true;

            if (!empty($cap) && function_exists('current_user_can')) {
                if ($userId) {
                    $canView = user_can($userId, 'manage_options') || user_can($userId, $cap);
                } else {
                    $canView = current_user_can('manage_options') || current_user_can($cap);
                }
            }

            if ($canView) {
                $available[] = [
                    'id'          => $p->ID,
                    'title'       => $p->post_title,
                    'slug'        => $slug,
                    'capability'  => $cap,
                    'description' => get_post_meta($p->ID, self::META_DESCRIPTION, true),
                    'category'    => implode(', ', $catNames),
                    'categories'  => $catNames,
                    'shortcode'   => '[comyli_table query="' . $slug . '" layout="clean"]',
                ];
            }
        }

        return $available;
    }

    /**
     * Intercept post save to write SQL code into post_content prior to revision creation.
     */
    public function filterPostData(array $data, array $postarr): array {
        if (($data['post_type'] ?? '') === self::POST_TYPE && isset($_POST['comyli_sql_code'])) {
            if (isset($_POST['comyli_query_nonce']) && wp_verify_nonce($_POST['comyli_query_nonce'], 'comyli_save_query_meta')) {
                $data['post_content'] = wp_unslash($_POST['comyli_sql_code']);
            }
        }
        return $data;
    }

    /**
     * Customize field labels on the revision diff screen.
     */
    public function filterRevisionFields(array $fields, $post): array {
        $postType = is_array($post) ? ($post['post_type'] ?? '') : ($post->post_type ?? '');
        if ($postType === self::POST_TYPE) {
            $fields['post_title'] = 'Abfragetitel';
            $fields['post_content'] = 'SQL-Abfragecode';
        }
        return $fields;
    }

    /**
     * Include QueryManager metadata keys in revisions.
     */
    public function filterRevisionMetaKeys(array $keys): array {
        $keys[] = self::META_TABLE_ID;
        $keys[] = self::META_CAPABILITY;
        $keys[] = self::META_DESCRIPTION;
        return $keys;
    }

    /**
     * Restore metadata when restoring a revision.
     */
    public function restoreRevisionMeta(int $postId, int $revisionId): void {
        $parent = get_post($postId);
        if (!$parent || $parent->post_type !== self::POST_TYPE) {
            return;
        }

        $metaKeys = [self::META_TABLE_ID, self::META_CAPABILITY, self::META_DESCRIPTION];
        foreach ($metaKeys as $metaKey) {
            $metaVal = get_metadata('post', $revisionId, $metaKey, true);
            if ($metaVal !== false && $metaVal !== '') {
                update_post_meta($postId, $metaKey, $metaVal);
            }
        }
    }

    /**
     * Finds a comyli_query post by slug, post name, title or ID.
     */
    public static function find_query_post(string $queryIdentifier): ?\WP_Post {
        $clean = preg_replace('/\.sql$/i', '', trim($queryIdentifier));
        
        // 1. By ID if numeric
        if (is_numeric($clean)) {
            $p = get_post((int)$clean);
            if ($p && $p->post_type === self::POST_TYPE) {
                return $p;
            }
        }

        // 2. By post_name (slug)
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'name'           => sanitize_title($clean),
            'posts_per_page' => 1,
            'post_status'    => ['publish', 'draft'],
        ]);
        if (!empty($posts)) {
            return $posts[0];
        }

        // 3. By exact title
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'title'          => $clean,
            'posts_per_page' => 1,
            'post_status'    => ['publish', 'draft'],
        ]);
        if (!empty($posts)) {
            return $posts[0];
        }

        return null;
    }
}
