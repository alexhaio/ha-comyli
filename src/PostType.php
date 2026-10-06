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
        add_filter('views_edit-' . $this->config->plugin['type'], [$this, 'addMigrationButton']);
        add_action('admin_init', [$this, 'handleMigration']);
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
            'new_item'           => 'Neue Tabelle',
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
            'comyli_queries_meta',
            'Abfragen (Queries) bearbeiten',
            [$this, 'renderQueriesBox'],
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
        echo "<p><strong>Shortcode:</strong><br><input type='text' readonly value='" . esc_attr($shortcode) . "' style='width:100%;'></p>";
        if ($post->post_status === 'publish') {
            echo "<p><a href='" . esc_url($viewUrl) . "' class='button button-primary' target='_blank'>Tabelle im Frontend ansehen</a></p>";
        } else {
            echo "<p><em>Bitte erst ver&ouml;ffentlichen, um die Tabelle anzusehen.</em></p>";
        }
    }

    public function renderQueriesBox(\WP_Post $post): void {
        $unserialized = maybe_unserialize($post->post_content);
        $queriesFolder = '';
        if (is_array($unserialized) && !empty($unserialized)) {
            $queriesFolder = $unserialized['queries'] ?? '';
        } else {
            $meta = get_post_meta($post->ID, '_comyli_queries', true);
            $queriesFolder = maybe_unserialize($meta) ?: '';
        }

        if (empty($queriesFolder)) {
            echo "<p>Bitte erst einen Abfragen-Ordner in der Konfiguration festlegen und speichern!</p>";
            return;
        }

        $absFolder = rtrim(ABSPATH, '/\\') . '/' . ltrim($queriesFolder, './\\');
        
        if (!is_dir($absFolder)) {
            echo "<p>Der Ordner <code>" . esc_html($queriesFolder) . "</code> existiert nicht.</p>";
            return;
        }

        $files = glob($absFolder . '/*.sql');
        $queryContents = [];
        if ($files) {
            foreach ($files as $f) {
                $base = basename($f, '.sql');
                $queryContents[$base] = file_get_contents($f);
            }
        }
        
        echo '<div style="margin-bottom:15px;">';
        echo '<label for="comyli_query_select"><strong>Abfrage w&auml;hlen:</strong></label> ';
        echo '<select id="comyli_query_select" style="min-width:200px;">';
        echo '<option value="">-- Neue Abfrage --</option>';
        foreach ($queryContents as $name => $code) {
            echo '<option value="' . esc_attr($name) . '">' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div style="margin-bottom:15px;">';
        echo '<label for="comyli_query_name"><strong>Name:</strong></label><br>';
        echo '<input type="text" id="comyli_query_name" name="comyli_query_name" class="regular-text">';
        echo '<p class="description">Ohne .sql Endung, z.B. <code>meine_abfrage</code></p>';
        echo '</div>';

        echo '<div style="margin-bottom:15px;">';
        echo '<label for="comyli_query_code"><strong>SQL Code:</strong></label><br>';
        echo '<div id="comyli_query_editor" style="width:100%; height:200px; font-family:monospace; background:#fff; border:1px solid #ccc; padding:10px; box-sizing:border-box;"></div>';
        echo '<textarea id="comyli_query_code" name="comyli_query_code" style="display:none;"></textarea>';
        echo '</div>';

        echo '<div>';
        echo '<button type="submit" name="comyli_query_save" value="1" class="button button-primary" style="margin-right:10px;">Abfrage speichern</button>';
        echo '<button type="submit" name="comyli_query_delete" value="1" class="button" onclick="return confirm(\'Wirklich l&ouml;schen?\');" style="color:red; border-color:red;">L&ouml;schen</button>';
        echo '</div>';
        
        $jsonContents = json_encode($queryContents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        echo "<script>
            jQuery(document).ready(function($) {
                var queries = $jsonContents;
                
                const editor = document.getElementById('comyli_query_editor');
                const hidden = document.getElementById('comyli_query_code');
                let jar = null;
                
                if (typeof CodeJar !== 'undefined') {
                    const highlight = editor => {
                        if (typeof Prism !== 'undefined' && Prism.languages && Prism.languages.sql) {
                            editor.innerHTML = Prism.highlight(editor.textContent, Prism.languages.sql, 'sql');
                        } else {
                            editor.textContent = editor.textContent;
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
                        else hidden.value = '';
                    } else {
                        $('#comyli_query_name').val(name);
                        let val = queries[name] || '';
                        if (jar) jar.updateCode(val);
                        else hidden.value = val;
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
        } else {
            $meta = get_post_meta($post->ID);
            foreach ($meta as $k => $v) {
                if (str_starts_with($k, '_comyli_')) {
                    $key = substr($k, 8);
                    $data[$key] = maybe_unserialize($v[0]);
                }
            }
        }

        echo "<table class='form-table'><tbody>";
        $this->renderRow('use_wp_db', 'Lokale WordPress-Datenbank verwenden', 'checkbox', $data, 'Ignoriert die unteren DB-Felder und nutzt die aktuelle WordPress-Datenbank.');
        $this->renderRow('db', 'Datenbank', 'text', $data, '', 'db-field');
        $this->renderRow('dbuser', 'DB Benutzer', 'text', $data, '', 'db-field');
        $this->renderRow('dbpass', 'DB Passwort', 'text', $data, '', 'db-field');
        $this->renderRow('dbserver', 'DB Server', 'text', $data, '', 'db-field');
        $this->renderRow('tables', 'Tabellen', 'text', $data, 'Mehrere Tabellen mit Komma trennen');
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
        $examplePath = './wp-content/plugins/ha-comyli/queries/' . $slug . '/';
        
        echo "<tr><th scope='row'><label for='comyli_queries'>Abfragen-Ordner (Pfad)</label></th><td>";
        echo "<input type='text' id='comyli_queries' name='comyli_queries' value='" . esc_attr($queriesVal) . "' placeholder='" . esc_attr($examplePath) . "' class='regular-text' style='margin-right:10px;'>";
        echo "<button type='submit' name='comyli_create_queries' value='1' class='button'>Ordner anlegen</button>";
        echo "<p class='description'>Pfad f&uuml;r .sql Abfragen.</p>";
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
            $checked = $val ? 'checked' : '';
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
            
            $dir = plugin_dir_path(__DIR__) . 'queries/' . $slug . '/';
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            $_POST['comyli_queries'] = './wp-content/plugins/ha-comyli/queries/' . $slug . '/';
        }

        if (!empty($_POST['comyli_query_save']) || !empty($_POST['comyli_query_delete'])) {
            $qName = sanitize_file_name($_POST['comyli_query_name'] ?? '');
            $qCode = wp_unslash($_POST['comyli_query_code'] ?? '');
            $qFolder = sanitize_text_field($_POST['comyli_queries'] ?? '');
            if (!empty($qName) && !empty($qFolder)) {
                $absFolder = rtrim(ABSPATH, '/\\') . '/' . ltrim($qFolder, './\\');
                if (file_exists($absFolder) && is_dir($absFolder)) {
                    $fPath = $absFolder . '/' . $qName . '.sql';
                    if (!empty($_POST['comyli_query_save'])) {
                        file_put_contents($fPath, $qCode);
                    } elseif (!empty($_POST['comyli_query_delete']) && file_exists($fPath)) {
                        unlink($fPath);
                    }
                }
            }
        }

        $data = [
            'use_wp_db'   => isset($_POST['comyli_use_wp_db']) ? 1 : 0,
            'db'          => sanitize_text_field($_POST['comyli_db'] ?? ''),
            'dbuser'      => sanitize_text_field($_POST['comyli_dbuser'] ?? ''),
            'dbpass'      => sanitize_text_field($_POST['comyli_dbpass'] ?? ''),
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

        remove_action('save_post_' . $this->config->plugin['type'], [$this, 'savePost']);
        wp_update_post([
            'ID' => $post_id,
            'post_content' => serialize($data)
        ]);
        add_action('save_post_' . $this->config->plugin['type'], [$this, 'savePost']);
    }

    public function addMigrationButton($views) {
        $views['comyli-migrate'] = '<a href="' . esc_url(add_query_arg(['comyli_migrate' => '1'])) . '" class="button">Alte Konfigurationen migrieren</a>';
        return $views;
    }

    public function handleMigration() {
        if (isset($_GET['comyli_migrate']) && $_GET['comyli_migrate'] === '1' && current_user_can('manage_options')) {
            $migrator = new LegacySpecifics($this->config);
            $migrator->migrateConfigs();
            wp_redirect(remove_query_arg('comyli_migrate'));
            exit;
        }
    }

    public function setCustomColumns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['comyli_shortcode'] = 'Shortcode';
            }
        }
        return $new_columns;
    }

    public function renderCustomColumn($column, $post_id) {
        if ($column === 'comyli_shortcode') {
            $shortcode = str_replace('{{id}}', (string)$post_id, $this->config->plugin['replaceReplace']);
            echo '<code>' . esc_html($shortcode) . '</code>';
        }
    }
}