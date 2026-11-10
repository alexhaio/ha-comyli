<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Registers and manages the Custom Post Type for CoMyLi tables.
 * Includes the backend UI for creating and configuring tables.
 */
class PostType {
    private Config $config;

    /**
     * @param Config $config Plugin configuration.
     */
    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        add_action('init', [$this, 'registerPostType']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . $this->config->plugin['type'], [$this, 'savePost']);
        add_filter('views_edit-' . $this->config->plugin['type'], [$this, 'addQueryMigrationButton']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        
        // Custom Columns for the post list
        add_filter('manage_' . $this->config->plugin['type'] . '_posts_columns', [$this, 'setCustomColumns']);
        add_action('manage_' . $this->config->plugin['type'] . '_posts_custom_column', [$this, 'renderCustomColumn'], 10, 2);
    }

    public function enqueueAdminScripts($hook): void {
        global $post_type;
        if ($post_type === $this->config->plugin['type']) {
            $url = plugin_dir_url(__DIR__);
            wp_enqueue_style('prism-css', $url . 'sql-admin/assets/prism.min.css');
            wp_enqueue_script('codejar-js', $url . 'sql-admin/assets/codejar.js', [], null, true);
            wp_enqueue_script('prism-js', $url . 'sql-admin/assets/prism.min.js', [], null, true);
            wp_enqueue_script('prism-sql-js', $url . 'sql-admin/assets/prism-sql.min.js', ['prism-js'], null, true);
        }
    }

    public function registerPostType(): void {
        $labels = [
            'name'               => 'Tabellen',
            'singular_name'      => 'Tabelle',
            'menu_name'          => 'CoMyLi Tabellen',
            'name_admin_bar'     => 'CoMyLi Tabelle',
            'add_new'            => 'Erstellen',
            'add_new_item'       => 'Neue Tabelle erstellen',
            'edit_item'          => 'Tabelle bearbeiten',
            'view_item'          => 'Tabelle ansehen',
            'all_items'          => 'Alle Tabellen',
            'search_items'       => 'Tabellen suchen',
            'parent_item_colon'  => '&Uuml;bergeordnete Tabellen:',
            'not_found'          => 'Keine Tabellen gefunden.',
            'not_found_in_trash' => 'Keine Tabellen im Papierkorb gefunden.'
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'tabelle'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 30,
            'menu_icon'          => 'dashicons-list-view',
            'supports'           => ['title'],
        ];

        register_post_type($this->config->plugin['type'], $args);
    }

    public function addMetaBoxes(): void {
        remove_meta_box('postcustom', $this->config->plugin['type'], 'normal');
        
        add_meta_box(
            'comyli_config_meta',
            'Tabellen-Konfiguration',
            [$this, 'renderMetaBox'],
            $this->config->plugin['type'],
            'normal',
            'default'
        );

        add_meta_box(
            'comyli_advanced_config_meta',
            'Erweiterte Tabellen-Konfiguration (JSON)',
            [$this, 'renderAdvancedConfigBox'],
            $this->config->plugin['type'],
            'normal',
            'default'
        );

        add_meta_box(
            'comyli_queries_meta',
            'Abfragen (Queries) für diese Tabelle',
            [$this, 'renderQueriesBox'],
            $this->config->plugin['type'],
            'normal',
            'default'
        );

        add_meta_box(
            'comyli_custom_css_meta',
            'Benutzerdefiniertes CSS (Custom CSS)',
            [$this, 'renderCustomCssBox'],
            $this->config->plugin['type'],
            'normal',
            'default'
        );

        add_meta_box(
            'comyli_custom_js_meta',
            'Benutzerdefiniertes JavaScript (Custom JS)',
            [$this, 'renderCustomJsBox'],
            $this->config->plugin['type'],
            'normal',
            'default'
        );

        add_meta_box(
            'comyli_info_meta',
            'CoMyLi Info',
            [$this, 'renderInfoBox'],
            $this->config->plugin['type'],
            'side',
            'default'
        );
    }

    public function renderInfoBox(\WP_Post $post): void {
        $shortcode = str_replace('{{id}}', (string)$post->ID, $this->config->plugin['replaceReplace']);
        $viewUrl = get_permalink($post->ID);
        echo "<p><strong>Shortcode:</strong><br><input type='text' readonly value='" . esc_attr($shortcode) . "' style='width:100%;' onclick='this.select();'></p>";
        if ($post->post_status === 'publish') {
            echo "<p><a href='" . esc_url($viewUrl) . "' class='button button-primary' target='_blank'>Tabelle im Frontend ansehen</a></p>";
        } else {
            echo "<p><em>Bitte erst ver&ouml;ffentlichen, um die Tabelle anzusehen.</em></p>";
        }
    }

    public function renderQueriesBox(\WP_Post $post): void {
        // 1. CPT-Queries from QueryManager linked to this table
        $linkedQueries = get_posts([
            'post_type'      => QueryManager::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'meta_key'       => QueryManager::META_TABLE_ID,
            'meta_value'     => $post->ID,
        ]);

        $newQueryUrl = admin_url('post-new.php?post_type=' . QueryManager::POST_TYPE . '&comyli_table_id=' . $post->ID);
        ?>
        <div style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h4 style="margin:0;">Zugeordnete Abfragen im Abfrage-Editor (nutzen diese Datenbank)</h4>
                <a href="<?php echo esc_url($newQueryUrl); ?>" class="button button-primary" target="_blank">
                    + Neue Abfrage für diese Tabelle anlegen
                </a>
            </div>

            <?php if (empty($linkedQueries)): ?>
                <p style="color:#64748b; font-style:italic; margin:8px 0;">
                    Bisher sind keine Abfragen aus dem Abfrage-Editor direkt dieser Tabelle zugewiesen. Klicken Sie oben rechts auf den Button, um eine Abfrage anzulegen, die diese Datenbankverbindung verwendet.
                </p>
            <?php else: ?>
                <table class="widefat striped" style="margin-top:5px; font-size:12px;">
                    <thead>
                        <tr>
                            <th>Titel</th>
                            <th>Slug (Bezeichner)</th>
                            <th>Berechtigung (Capability)</th>
                            <th>Shortcode</th>
                            <th style="text-align:right;">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linkedQueries as $lq): 
                            $slug = $lq->post_name ?: 'abfrage-' . $lq->ID;
                            $cap = get_post_meta($lq->ID, QueryManager::META_CAPABILITY, true);
                            $editUrl = get_edit_post_link($lq->ID);
                        ?>
                            <tr>
                                <td><strong><a href="<?php echo esc_url($editUrl); ?>" target="_blank"><?php echo esc_html($lq->post_title); ?></a></strong></td>
                                <td><code><?php echo esc_html($slug); ?></code></td>
                                <td>
                                    <?php if (empty($cap) || $cap === 'read'): ?>
                                        <span style="color:#059669;">Öffentlich (read)</span>
                                    <?php else: ?>
                                        <span style="background:#fef3c7; color:#92400e; padding:2px 6px; border-radius:3px; font-family:monospace;">🔒 <?php echo esc_html($cap); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><input type="text" readonly value="[comyli_table query=&quot;<?php echo esc_attr($slug); ?>&quot; layout=&quot;clean&quot;]" style="width:100%; font-size:11px; font-family:monospace;" onclick="this.select();"></td>
                                <td style="text-align:right;">
                                    <a href="<?php echo esc_url($editUrl); ?>" class="button button-small" target="_blank">
                                        Bearbeiten
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <hr style="border:0; border-top:1px solid #ccd0d4; margin:20px 0;">

        <!-- 2. File-based queries section -->
        <?php
        $unserialized = maybe_unserialize($post->post_content);
        $queriesFolder = '';
        if (is_array($unserialized) && !empty($unserialized)) {
            $queriesFolder = $unserialized['queries'] ?? '';
        } else {
            $meta = get_post_meta($post->ID, '_comyli_queries', true);
            $queriesFolder = maybe_unserialize($meta) ?: '';
        }

        $slug = $post->post_name ?: 'config-' . $post->ID;
        $absFolder = QueryManager::resolve_queries_dir($queriesFolder, $slug);
        $defaultRel = QueryManager::get_default_queries_dir($slug, true);
        $displayPath = !empty($queriesFolder) ? $queriesFolder : $defaultRel;
        $filesMap = is_dir($absFolder) ? QueryManager::find_sql_files($absFolder) : [];
        $uniqueCount = count(array_unique($filesMap));
        ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h4 style="margin:0;">Datei-basierte Abfragen (.sql auf dem Server)</h4>
            <?php if (!empty($filesMap) && current_user_can('manage_options')): 
                $tableImportUrl = wp_nonce_url(
                    admin_url('edit.php?post_type=' . QueryManager::POST_TYPE . '&comyli_action=migrate_file_queries&comyli_table_id=' . $post->ID),
                    'comyli_migrate_file_queries_nonce'
                );
            ?>
                <a href="<?php echo esc_url($tableImportUrl); ?>" class="button button-secondary" onclick="return confirm('Möchten Sie alle <?php echo $uniqueCount; ?> Datei-Abfragen dieser Tabelle als eigenständige Abfragen in den Abfrage-Editor importieren?');">
                    📥 Alle <?php echo $uniqueCount; ?> Datei-Abfragen dieser Tabelle importieren
                </a>
            <?php endif; ?>
        </div>
        <?php
        if (!is_dir($absFolder)) {
            echo "<p style='color:#64748b;'>Der Abfragen-Ordner <code>" . esc_html($displayPath) . "</code> existiert noch nicht auf dem Server.<br>Klicken Sie unten auf <strong>Ordner anlegen</strong> oder kopieren Sie Ihre <code>.sql</code>-Dateien in diesen Pfad.</p>";
            return;
        }
        $queryContents = [];
        foreach ($filesMap as $name => $fPath) {
            $queryContents[$name] = file_get_contents($fPath) ?: '';
        }

        if (empty($queryContents)) {
            echo "<p style='color:#64748b;'>Aktiver Ordner: <code>" . esc_html($displayPath) . "</code> (Ordner ist bereit, enthält aber noch keine <code>.sql</code>-Dateien).</p>";
        }
        
        echo '<div style="margin-bottom:15px;">';
        echo '<label for="comyli_query_select"><strong>Datei-Abfrage w&auml;hlen:</strong></label> ';
        echo '<select id="comyli_query_select" style="min-width:200px;">';
        echo '<option value="">-- Neue Datei-Abfrage anlegen --</option>';
        foreach ($queryContents as $name => $code) {
            echo '<option value="' . esc_attr($name) . '">' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div style="margin-bottom:15px;">';
        echo '<label for="comyli_query_name"><strong>Dateiname:</strong></label><br>';
        echo '<input type="text" id="comyli_query_name" name="comyli_query_name" class="regular-text">';
        echo '<p class="description">Ohne .sql Endung, z.B. <code>meine_abfrage</code></p>';
        echo '</div>';

        echo '<div style="margin-bottom:15px;">';
        echo '<label for="comyli_query_code"><strong>SQL Code:</strong></label><br>';
        echo '<div id="comyli_query_editor" style="width:100%; height:200px; font-family:monospace; background:#fff; border:1px solid #ccc; padding:10px; box-sizing:border-box;"></div>';
        echo '<textarea id="comyli_query_code" name="comyli_query_code" style="display:none;"></textarea>';
        echo '</div>';

        echo '<div>';
        echo '<button type="submit" name="comyli_query_save" value="1" class="button button-secondary" style="margin-right:10px;">Datei speichern</button>';
        echo '<button type="submit" name="comyli_query_delete" value="1" class="button" onclick="return confirm(\'Wirklich l&ouml;schen?\');" style="color:red; border-color:red;">L&ouml;schen</button>';
        echo '</div>';
        
        $jsonContents = json_encode($queryContents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        echo "<script>
            jQuery(document).ready(function($) {
                var queries = $jsonContents;
                
                const editor = document.getElementById('comyli_query_editor');
                const hidden = document.getElementById('comyli_query_code');
                let jar = null;
                
                if (typeof CodeJar !== 'undefined' && editor) {
                    const highlight = editorEl => {
                        if (typeof Prism !== 'undefined' && Prism.languages && Prism.languages.sql) {
                            editorEl.innerHTML = Prism.highlight(editorEl.textContent, Prism.languages.sql, 'sql');
                        } else {
                            editorEl.textContent = editorEl.textContent;
                        }
                    };
                    jar = CodeJar(editor, highlight);
                    jar.onUpdate(code => {
                        hidden.value = code;
                    });
                }
                
                $('#comyli_query_select').on('change', function() {
                    var name = $(this).val();
                    if (name === '') {
                        $('#comyli_query_name').val('');
                        if (jar) jar.updateCode('');
                        else if (hidden) hidden.value = '';
                    } else {
                        $('#comyli_query_name').val(name);
                        let val = queries[name] || '';
                        if (jar) jar.updateCode(val);
                        else if (hidden) hidden.value = val;
                    }
                });
            });
        </script>";
    }

    public function renderMetaBox(\WP_Post $post): void {
        wp_nonce_field('comyli_save_meta', 'comyli_meta_nonce');

        $data = [];
        $unserialized = maybe_unserialize($post->post_content);
        if (is_array($unserialized) && !empty($unserialized)) {
            $data = $unserialized;
        }

        $meta = get_post_meta($post->ID);
        foreach ($meta as $k => $v) {
            if (str_starts_with($k, '_comyli_')) {
                $key = substr($k, 8);
                $data[$key] = maybe_unserialize($v[0]);
            }
        }

        echo "<table class='form-table'><tbody>";
        $this->renderRow('use_wp_db', 'Lokale WordPress-Datenbank verwenden', 'checkbox', $data, 'Ignoriert die unteren externen DB-Felder und nutzt die lokale WordPress-Datenbank.');
        $this->renderRow('db', 'Datenbank (DB Name)', 'text', $data, 'Name der Zieldatenbank auf dem Datenbankserver', 'db-field');
        $this->renderRow('dbuser', 'DB Benutzer', 'text', $data, 'Benutzername für die MySQL-Verbindung', 'db-field');
        $this->renderRow('dbpass', 'DB Passwort', 'password', $data, 'Passwort für die MySQL-Verbindung', 'db-field');
        $this->renderRow('dbserver', 'DB Server (Host)', 'text', $data, 'Server-Adresse (z. B. localhost, 127.0.0.1 oder externer Host; optional mit Port z. B. 127.0.0.1:3307)', 'db-field');
        $this->renderRow('tables', 'Tabellen', 'text', $data, 'Name der Tabelle in der Datenbank (mehrere Tabellen durch Komma trennen)');
        $this->renderRow('limit', 'Eintr&auml;ge pro Seite', 'number', $data);
        $this->renderRow('edit', 'Bearbeitbar', 'checkbox', $data);
        $this->renderRow('primaryEdit', 'Prim&auml;rschl&uuml;ssel bearbeitbar', 'checkbox', $data);
        $this->renderRow('eval', 'Abfragen mit Code', 'checkbox', $data);
        $this->renderRow('listQueries', 'Verf&uuml;gbare Abfragen auflisten', 'checkbox', $data);
        $this->renderRow('listTables', 'Verf&uuml;gbare Tabellen auflisten', 'checkbox', $data);
        $this->renderRow('csvExport', 'CSV-Export m&ouml;glich', 'checkbox', $data);
        $this->renderRow('showTypes', 'Typen anzeigen', 'checkbox', $data);
        $this->renderRow('editQueriesFrontend', 'Abfragen im Frontend bearbeiten', 'checkbox', $data, 'Erlaubt Nutzern (wenn Bearbeitbar aktiv ist), Abfragen direkt im Frontend zu speichern.');
        
        $queriesVal = $data['queries'] ?? '';
        $slug = $post->post_name ?: 'config-' . $post->ID;
        $defaultRelPath = QueryManager::get_default_queries_dir($slug, true);
        
        echo "<tr><th scope='row'><label for='comyli_queries'>Abfragen-Ordner (Pfad)</label></th><td>";
        echo "<input type='text' id='comyli_queries' name='comyli_queries' value='" . esc_attr($queriesVal) . "' placeholder='" . esc_attr($defaultRelPath) . "' class='regular-text' style='margin-right:10px;'>";
        echo "<button type='submit' name='comyli_create_queries' value='1' class='button'>Ordner anlegen</button>";
        echo "<p class='description'>Standardmä&szlig;ig in <code>wp-content</code>: <code>" . esc_html($defaultRelPath) . "</code> (bleibt bei Plugin-Updates erhalten). Bei leerem Feld wird dieser Ordner automatisch verwendet.</p>";
        echo "</td></tr>";
        
        echo "</tbody></table>";

        echo "<script>
            jQuery(document).ready(function($) {
                function toggleDbFields() {
                    if ($('#comyli_use_wp_db').is(':checked')) {
                        $('.db-field').hide();
                    } else {
                        $('.db-field').show();
                    }
                }
                $('#comyli_use_wp_db').on('change', toggleDbFields);
                toggleDbFields();
            });
        </script>";
    }

    private function renderRow(string $key, string $label, string $type, array $data, string $desc = '', string $rowClass = ''): void {
        $val = $data[$key] ?? '';
        $name = 'comyli_' . $key;
        echo "<tr class='$rowClass'><th scope='row'><label for='$name'>$label</label></th><td>";
        if ($type === 'checkbox') {
            $checked = (!empty($val) && $val !== '0') ? 'checked' : '';
            echo "<input type='checkbox' id='$name' name='$name' value='1' $checked>";
        } else {
            echo "<input type='$type' id='$name' name='$name' value='" . esc_attr($val) . "' class='regular-text'>";
        }
        if ($desc) {
            echo "<p class='description'>$desc</p>";
        }
        echo "</td></tr>";
    }

    public function savePost(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['comyli_meta_nonce']) || !wp_verify_nonce($_POST['comyli_meta_nonce'], 'comyli_save_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!empty($_POST['comyli_create_queries'])) {
            $post = get_post($post_id);
            $slug = $post->post_name;
            if (empty($slug)) $slug = 'config-' . $post_id;
            
            $targetRel = QueryManager::get_default_queries_dir($slug, true);
            $targetAbs = QueryManager::resolve_queries_dir($targetRel, $slug);
            QueryManager::ensure_queries_dir($targetAbs);
            $_POST['comyli_queries'] = $targetRel;
        }

        if (!empty($_POST['comyli_query_save']) || !empty($_POST['comyli_query_delete'])) {
            $qName = sanitize_file_name($_POST['comyli_query_name'] ?? '');
            $qCode = wp_unslash($_POST['comyli_query_code'] ?? '');
            $qFolder = sanitize_text_field($_POST['comyli_queries'] ?? '');
            $post = get_post($post_id);
            $slug = $post ? $post->post_name : 'config-' . $post_id;
            $absFolder = QueryManager::resolve_queries_dir($qFolder, $slug);
            
            if (!empty($qName) && is_dir($absFolder)) {
                $fPath = rtrim($absFolder, '/') . '/' . $qName . '.sql';
                if (!empty($_POST['comyli_query_save'])) {
                    QueryManager::ensure_queries_dir($absFolder);
                    file_put_contents($fPath, $qCode);
                } elseif (!empty($_POST['comyli_query_delete']) && file_exists($fPath)) {
                    unlink($fPath);
                }
            }
        }

        $dbpass = isset($_POST['comyli_dbpass']) ? wp_unslash($_POST['comyli_dbpass']) : '';

        $data = [
            'use_wp_db'   => isset($_POST['comyli_use_wp_db']) ? 1 : 0,
            'db'          => sanitize_text_field($_POST['comyli_db'] ?? ''),
            'dbuser'      => sanitize_text_field($_POST['comyli_dbuser'] ?? ''),
            'dbpass'      => $dbpass,
            'dbserver'    => sanitize_text_field($_POST['comyli_dbserver'] ?? ''),
            'tables'      => sanitize_text_field($_POST['comyli_tables'] ?? ''),
            'queries'     => sanitize_text_field($_POST['comyli_queries'] ?? ''),
            'limit'       => intval($_POST['comyli_limit'] ?? 100),
            'edit'        => isset($_POST['comyli_edit']) ? 1 : 0,
            'primaryEdit' => isset($_POST['comyli_primaryEdit']) ? 1 : 0,
            'eval'        => isset($_POST['comyli_eval']) ? 1 : 0,
            'listQueries' => isset($_POST['comyli_listQueries']) ? 1 : 0,
            'listTables'  => isset($_POST['comyli_listTables']) ? 1 : 0,
            'csvExport'   => isset($_POST['comyli_csvExport']) ? 1 : 0,
            'showTypes'   => isset($_POST['comyli_showTypes']) ? 1 : 0,
            'editQueriesFrontend' => isset($_POST['comyli_editQueriesFrontend']) ? 1 : 0,
        ];

        $rawCss = isset($_POST['comyli_custom_css']) ? wp_unslash($_POST['comyli_custom_css']) : '';
        $customCss = trim(preg_replace('/^<\s*style[^>]*>|<\s*\/\s*style\s*>$/i', '', $rawCss));
        $data['custom_css'] = $customCss;

        $rawJs = isset($_POST['comyli_custom_js']) ? wp_unslash($_POST['comyli_custom_js']) : '';
        $customJs = trim(preg_replace('/^<\s*script[^>]*>|<\s*\/\s*script\s*>$/i', '', $rawJs));
        $data['custom_js'] = $customJs;

        $rawAdvanced = isset($_POST['comyli_advanced_config']) ? wp_unslash($_POST['comyli_advanced_config']) : '';
        $data['advanced_config'] = trim($rawAdvanced);

        // Save to individual post meta fields
        foreach ($data as $key => $val) {
            update_post_meta($post_id, '_comyli_' . $key, $val);
        }

        // Save serialized copy to post_content (properly slashed for wp_insert_post)
        remove_action('save_post_' . $this->config->plugin['type'], [$this, 'savePost']);
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => wp_slash(serialize($data))
        ]);
        add_action('save_post_' . $this->config->plugin['type'], [$this, 'savePost']);
    }

    public function renderCustomCssBox(\WP_Post $post): void {
        $customCss = (string)get_post_meta($post->ID, '_comyli_custom_css', true);
        if ($customCss === '') {
            $unserialized = maybe_unserialize($post->post_content);
            if (is_array($unserialized) && !empty($unserialized['custom_css'])) {
                $customCss = (string)$unserialized['custom_css'];
            }
        }
        ?>
        <div style="margin-bottom:10px;">
            <p class="description">
                Geben Sie hier spezifisches CSS für diese Tabelle ein. Das CSS wird automatisch eingebunden, wenn die Tabelle (oder ein zugehöriger Shortcode/Block) angezeigt wird.
            </p>
            <div id="comyli_table_css_editor" style="width:100%; min-height:180px; font-family:monospace; font-size:13px; background:#fff; border:1px solid #ccd0d4; padding:12px; box-sizing:border-box; border-radius:4px; line-height:1.5;"></div>
            <textarea id="comyli_custom_css" name="comyli_custom_css" style="display:none;"><?php echo esc_textarea($customCss); ?></textarea>
        </div>
        <div style="background:#f8fafc; border-left:4px solid #0284c7; padding:8px 12px; font-size:12px; color:#334155;">
            <strong>Hilfreiche CSS-Selektoren für Tabellendesign:</strong><br>
            <code>.comyli .td:nth-of-type(2) { width: 150px; }</code> (Spaltenbreite anpassen)<br>
            <code>.comyli .td:nth-of-type(1) { position: sticky; left: 0; background: inherit; z-index: 6; }</code> (Spalte horizontal fixieren)<br>
            <code>.comyli .tr:hover { background-color: #f1f5f9; }</code> (Zeilen-Hover hervorheben)<br>
            <code>#colNames > .td { font-weight: bold; background: #e2e8f0; }</code> (Spaltenüberschriften formatieren)
        </div>
        <script>
        jQuery(document).ready(function($) {
            const editorEl = document.getElementById('comyli_table_css_editor');
            const hiddenEl = document.getElementById('comyli_custom_css');
            if (typeof CodeJar !== 'undefined' && editorEl && hiddenEl) {
                const highlight = el => {
                    if (typeof Prism !== 'undefined' && Prism.languages && Prism.languages.css) {
                        el.innerHTML = Prism.highlight(el.textContent, Prism.languages.css, 'css');
                    } else {
                        el.textContent = el.textContent;
                    }
                };
                const jar = CodeJar(editorEl, highlight);
                jar.updateCode(hiddenEl.value);
                jar.onUpdate(code => {
                    hiddenEl.value = code;
                });
            } else if (editorEl && hiddenEl) {
                editorEl.style.display = 'none';
                hiddenEl.style.display = 'block';
                hiddenEl.style.width = '100%';
                hiddenEl.style.minHeight = '180px';
                hiddenEl.style.fontFamily = 'monospace';
            }
        });
        </script>
        <?php
    }

    public function renderCustomJsBox(\WP_Post $post): void {
        $customJs = (string)get_post_meta($post->ID, '_comyli_custom_js', true);
        if ($customJs === '') {
            $unserialized = maybe_unserialize($post->post_content);
            if (is_array($unserialized) && !empty($unserialized['custom_js'])) {
                $customJs = (string)$unserialized['custom_js'];
            }
        }
        ?>
        <div style="margin-bottom:10px;">
            <p class="description">
                Geben Sie hier spezifisches JavaScript für diese Tabelle ein. Das Script wird automatisch ausgeführt, wenn die Tabelle gerendert wird.
            </p>
            <div id="comyli_table_js_editor" style="width:100%; min-height:180px; font-family:monospace; font-size:13px; background:#fff; border:1px solid #ccd0d4; padding:12px; box-sizing:border-box; border-radius:4px; line-height:1.5;"></div>
            <textarea id="comyli_custom_js" name="comyli_custom_js" style="display:none;"><?php echo esc_textarea($customJs); ?></textarea>
        </div>
        <div style="background:#f8fafc; border-left:4px solid #f59e0b; padding:8px 12px; font-size:12px; color:#334155;">
            <strong>JavaScript-Hinweis:</strong><br>
            Sie können Standard-JavaScript oder jQuery (<code>jQuery(document).ready(function($) { ... })</code>) verwenden.<br>
            Die Tabellendaten stehen im DOM unter <code>.comyli</code> zur Verfügung.
        </div>
        <script>
        jQuery(document).ready(function($) {
            const editorEl = document.getElementById('comyli_table_js_editor');
            const hiddenEl = document.getElementById('comyli_custom_js');
            if (typeof CodeJar !== 'undefined' && editorEl && hiddenEl) {
                const highlight = el => {
                    if (typeof Prism !== 'undefined' && Prism.languages && (Prism.languages.javascript || Prism.languages.js)) {
                        const lang = Prism.languages.javascript || Prism.languages.js;
                        el.innerHTML = Prism.highlight(el.textContent, lang, 'javascript');
                    } else {
                        el.textContent = el.textContent;
                    }
                };
                const jar = CodeJar(editorEl, highlight);
                jar.updateCode(hiddenEl.value);
                jar.onUpdate(code => {
                    hiddenEl.value = code;
                });
            } else if (editorEl && hiddenEl) {
                editorEl.style.display = 'none';
                hiddenEl.style.display = 'block';
                hiddenEl.style.width = '100%';
                hiddenEl.style.minHeight = '180px';
                hiddenEl.style.fontFamily = 'monospace';
            }
        });
        </script>
        <?php
    }

    public function renderAdvancedConfigBox(\WP_Post $post): void {
        $advancedConfig = (string)get_post_meta($post->ID, '_comyli_advanced_config', true);
        if ($advancedConfig === '') {
            $unserialized = maybe_unserialize($post->post_content);
            if (is_array($unserialized) && !empty($unserialized['advanced_config'])) {
                $advancedConfig = (string)$unserialized['advanced_config'];
            }
        }

        // If empty, check if this table matches any built-in preset
        if ($advancedConfig === '') {
            $metaTables = (string)get_post_meta($post->ID, '_comyli_tables', true);
            if (empty($metaTables)) {
                $unserialized = maybe_unserialize($post->post_content);
                if (is_array($unserialized) && !empty($unserialized['tables'])) {
                    $metaTables = (string)$unserialized['tables'];
                }
            }
            $table = trim(explode(',', $metaTables)[0] ?? '');
            if (!empty($table)) {
                $preset = ConfigResolver::getBuiltinPreset($table);
                if ($preset !== null) {
                    $advancedConfig = wp_json_encode($preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
        }
        ?>
        <div style="margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <p class="description" style="margin:0;">
                    Definieren Sie hier deklarativ spezifische Tabellenregeln (Spalten-Defaults, Berechtigungen, Fremdschlüssel, UI-Verhalten).
                </p>
                <span id="comyli_json_status" style="font-size:12px; font-weight:600; padding:2px 8px; border-radius:3px; background:#f1f5f9; color:#475569;">JSON-Prüfung...</span>
            </div>
            <div id="comyli_advanced_config_editor" style="width:100%; min-height:260px; font-family:monospace; font-size:13px; background:#fff; border:1px solid #ccd0d4; padding:12px; box-sizing:border-box; border-radius:4px; line-height:1.5;"></div>
            <textarea id="comyli_advanced_config" name="comyli_advanced_config" style="display:none;"><?php echo esc_textarea($advancedConfig); ?></textarea>
        </div>

        <details style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:8px 12px; font-size:12px; color:#334155; margin-top:8px;">
            <summary style="cursor:pointer; font-weight:600; color:#0284c7;">Dokumentation &amp; Platzhalter anzeigen (Klick)</summary>
            <div style="margin-top:10px; line-height:1.6;">
                <strong>Verfügbare dynamische Tokens (ohne eval):</strong><br>
                <code>{current_date}</code> (z. B. 2026-11-10) &bull; <code>{current_datetime}</code> &bull; <code>{current_year}</code><br>
                <code>{current_user_login}</code> &bull; <code>{current_user_id}</code> &bull; <code>{current_user_fullname}</code> (Nachname, Vorname)<br>
                <code>{year_letter:2024}</code> (zyklischer Buchstabe A, B, C...) &bull; <code>{column:Spaltenname}</code> (Referenz auf eine andere Spalte)<br>
                <code>{sql:SELECT ...}</code> (Liest skalaren Wert) &bull; <code>{sql_pad4:SELECT ...}</code> (4-stellig mit Nullen gepaddet)<br><br>

                <strong>Hauptbereiche im JSON-Schema:</strong>
                <ul style="margin:4px 0 0 18px; list-style-type:disc;">
                    <li><code>ui</code>: <code>stdQry</code>, <code>insert_top</code> (true/false), <code>default_order</code>, <code>default_dir</code>, <code>auto_audit_columns</code></li>
                    <li><code>columns</code>: <code>[Spalte]: { default, forced, post_override, readonly, min, max, step, pattern, attr }</code></li>
                    <li><code>foreign_keys</code>: <code>[Spalte]: { source: "sql"|"wp_option"|"wp_users", query, option, property, mode: "input"|"suggestion" }</code></li>
                    <li><code>access_rules</code>: Array von Regeln <code>{ capabilities: ["..."], edit: true|false, visible_columns: [...], custom_css: "..." }</code></li>
                    <li><code>readonly_unless_capability</code>: <code>{ capability: "...", columns: [...] }</code></li>
                    <li><code>maintenance</code>: <code>{ ensure_quarterly_dates: { table, years_ahead, months, day } }</code></li>
                </ul>
            </div>
        </details>

        <script>
        jQuery(document).ready(function($) {
            const editorEl = document.getElementById('comyli_advanced_config_editor');
            const hiddenEl = document.getElementById('comyli_advanced_config');
            const statusEl = document.getElementById('comyli_json_status');

            function validateJson(code) {
                if (!statusEl) return;
                const trimmed = (code || '').trim();
                if (trimmed === '') {
                    statusEl.innerText = 'Leer (Standard)';
                    statusEl.style.background = '#f1f5f9';
                    statusEl.style.color = '#475569';
                    return;
                }
                try {
                    JSON.parse(trimmed);
                    statusEl.innerText = '✓ Gültiges JSON';
                    statusEl.style.background = '#dcfce7';
                    statusEl.style.color = '#15803d';
                } catch(e) {
                    statusEl.innerText = '⚠️ Syntaxfehler im JSON';
                    statusEl.style.background = '#fee2e2';
                    statusEl.style.color = '#b91c1c';
                }
            }

            if (typeof CodeJar !== 'undefined' && editorEl && hiddenEl) {
                const highlight = el => {
                    if (typeof Prism !== 'undefined' && Prism.languages && (Prism.languages.javascript || Prism.languages.js)) {
                        const lang = Prism.languages.javascript || Prism.languages.js;
                        el.innerHTML = Prism.highlight(el.textContent, lang, 'javascript');
                    } else {
                        el.textContent = el.textContent;
                    }
                };
                const jar = CodeJar(editorEl, highlight);
                jar.updateCode(hiddenEl.value);
                validateJson(hiddenEl.value);
                jar.onUpdate(code => {
                    hiddenEl.value = code;
                    validateJson(code);
                });
            } else if (editorEl && hiddenEl) {
                editorEl.style.display = 'none';
                hiddenEl.style.display = 'block';
                hiddenEl.style.width = '100%';
                hiddenEl.style.minHeight = '240px';
                hiddenEl.style.fontFamily = 'monospace';
                hiddenEl.addEventListener('input', () => validateJson(hiddenEl.value));
                validateJson(hiddenEl.value);
            }
        });
        </script>
        <?php
    }

    public function addQueryMigrationButton($views) {
        if (!current_user_can('manage_options')) {
            return $views;
        }
        $url = wp_nonce_url(
            admin_url('edit.php?post_type=' . QueryManager::POST_TYPE . '&comyli_action=migrate_file_queries'),
            'comyli_migrate_file_queries_nonce'
        );
        $views['comyli-migrate-queries'] = '<a href="' . esc_url($url) . '" class="button button-secondary" onclick="return confirm(\'' . esc_js(__('Dateibasierte SQL-Abfragen (.sql) vom Server in die Datenbank importieren?', 'comyli')) . '\');" style="margin-left:8px;">' . esc_html__('Dateibasierte Abfragen importieren', 'comyli') . '</a>';
        return $views;
    }

    public function setCustomColumns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['comyli_db'] = 'Datenbank';
                $new_columns['comyli_queries_count'] = 'Abfragen';
                $new_columns['comyli_shortcode'] = 'Shortcode';
            }
        }
        return $new_columns;
    }

    public function renderCustomColumn($column, $post_id) {
        if ($column === 'comyli_db') {
            $cfg = ConnectionManager::resolve_table_config($post_id);
            $dbServer = (string)($cfg['dbserver'] ?? '');
            $useWpDb = empty($dbServer) || !empty($cfg['use_wp_db']);
            if ($useWpDb) {
                echo '<span style="color:#0284c7; font-weight:600;">WordPress-DB</span>';
            } else {
                $db = (string)($cfg['db'] ?? '');
                echo '<strong>' . esc_html($db ?: 'extern') . '</strong><br><span style="color:#64748b; font-size:11px;">@ ' . esc_html($dbServer) . '</span>';
            }
        } elseif ($column === 'comyli_queries_count') {
            $count = count(get_posts([
                'post_type'      => QueryManager::POST_TYPE,
                'posts_per_page' => -1,
                'post_status'    => ['publish', 'draft'],
                'meta_key'       => QueryManager::META_TABLE_ID,
                'meta_value'     => $post_id,
                'fields'         => 'ids',
            ]));
            echo $count > 0 ? '<strong>' . $count . ' Abfrage(n)</strong>' : '<span style="color:#94a3b8;">—</span>';
        } elseif ($column === 'comyli_shortcode') {
            $shortcode = str_replace('{{id}}', (string)$post_id, $this->config->plugin['replaceReplace']);
            echo '<input type="text" readonly value="' . esc_attr($shortcode) . '" style="width:100%; font-size:11px; font-family:monospace;" onclick="this.select();">';
        }
    }
}
