<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Manages the Custom Post Type 'comyli_query' for defining SQL queries and capability requirements.
 * Provides backend GUI for queries, capabilities, and block/shortcode integrations.
 */
class QueryManager {
    public const POST_TYPE = 'comyli_query';
    public const META_CAPABILITY = '_comyli_capability';
    public const META_DESCRIPTION = '_comyli_description';
    public const META_USE_WP_DB = '_comyli_use_wp_db';
    public const META_CATEGORY = '_comyli_category';

    private Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        add_action('init', [$this, 'registerPostType']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'savePost']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueEditorAssets']);
        
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
            'supports'           => ['title'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public function enqueueEditorAssets($hook): void {
        global $post_type;
        if ($post_type === self::POST_TYPE) {
            $url = $this->config->plugin['url'];
            wp_enqueue_style('prism-css', $url . 'sql-admin/assets/prism.min.css');
            wp_enqueue_script('codejar-js', $url . 'sql-admin/assets/codejar.js', [], null, true);
            wp_enqueue_script('prism-js', $url . 'sql-admin/assets/prism.min.js', [], null, true);
            wp_enqueue_script('prism-sql-js', $url . 'sql-admin/assets/prism-sql.min.js', ['prism-js'], null, true);
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

        // 2. Berechtigungs- und Capability-Konfiguration
        add_meta_box(
            'comyli_query_perms_box',
            'Berechtigung & Schutz (Capability)',
            [$this, 'renderPermissionsBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        // 3. Info & Shortcode / Block
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
        if ($currentCap === '') {
            $currentCap = 'mig_view_reports'; // Sinnvoller Default für Auswertungen
        }
        $desc = get_post_meta($post->ID, self::META_DESCRIPTION, true);
        $category = get_post_meta($post->ID, self::META_CATEGORY, true);
        $useWpDb = get_post_meta($post->ID, self::META_USE_WP_DB, true);
        if ($useWpDb === '') {
            $useWpDb = '1';
        }

        $commonCaps = [
            'mig_view_reports'  => 'mig_view_reports (Auswertungen & Berichte Migrantenverwaltung)',
            'mig_view_persons'  => 'mig_view_persons (Personen & Belegungsstatus einsehen)',
            'mig_view_facility' => 'mig_view_facility (Liegenschaften, Zimmer & Finanzen)',
            'mig_view_courses'  => 'mig_view_courses (Sprachkurse & Fehlzeiten)',
            'read'              => 'read (Jeder angemeldete WordPress-Benutzer)',
            'manage_options'    => 'manage_options (Ausschließlich Administratoren)',
        ];
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="comyli_cap_select">Erforderliche Berechtigung (Capability)</label></th>
                    <td>
                        <select id="comyli_cap_select" style="min-width:320px; max-width:100%;">
                            <option value="">-- Benutzerdefinierte Capability eingeben --</option>
                            <?php foreach ($commonCaps as $cap => $label): ?>
                                <option value="<?php echo esc_attr($cap); ?>" <?php selected($currentCap, $cap); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <br><br>
                        <input type="text" id="comyli_capability" name="comyli_capability" value="<?php echo esc_attr($currentCap); ?>" class="regular-text" style="font-family:monospace;" placeholder="z. B. mig_view_reports">
                        <p class="description">
                            Benutzer ohne diese WordPress-Berechtigung (Capability) können die Abfrage <strong>weder ausführen noch einsehen</strong>. Gutenberg-Blöcke und Abfrage-Listen verbergen diese Auswertung für unberechtigte Nutzer automatisch.
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
                <tr>
                    <th scope="row"><label for="comyli_category">Kategorie / Bereich</label></th>
                    <td>
                        <input type="text" id="comyli_category" name="comyli_category" value="<?php echo esc_attr($category); ?>" class="regular-text" placeholder="z. B. Migration, Liegenschaften, Kurse">
                        <p class="description">Erlaubt das Filtern von Abfragen im Abfrage-Listen-Block.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Datenbank</th>
                    <td>
                        <label>
                            <input type="checkbox" name="comyli_use_wp_db" value="1" <?php checked($useWpDb, '1'); ?>>
                            Lokale WordPress-Datenbank verwenden (empfohlen für <code>wp_mig_*</code>)
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('#comyli_cap_select').on('change', function() {
                var val = $(this).val();
                if (val !== '') {
                    $('#comyli_capability').val(val);
                }
            });
            $('#comyli_capability').on('input', function() {
                var typed = $(this).val();
                var found = false;
                $('#comyli_cap_select option').each(function() {
                    if ($(this).val() === typed) {
                        $('#comyli_cap_select').val(typed);
                        found = true;
                    }
                });
                if (!found) {
                    $('#comyli_cap_select').val('');
                }
            });
        });
        </script>
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
        $newCols['category'] = 'Kategorie';
        $newCols['capability'] = 'Erforderliche Capability';
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
            case 'category':
                $cat = get_post_meta($post_id, self::META_CATEGORY, true);
                echo $cat ? '<span class="badge" style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px; font-size:11px;">' . esc_html($cat) . '</span>' : '—';
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

        // Save SQL in post_content
        if (isset($_POST['comyli_sql_code'])) {
            $sql = wp_unslash($_POST['comyli_sql_code']);
            remove_action('save_post_' . self::POST_TYPE, [$this, 'savePost']);
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $sql,
            ]);
            add_action('save_post_' . self::POST_TYPE, [$this, 'savePost']);
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
        update_post_meta($post_id, self::META_USE_WP_DB, isset($_POST['comyli_use_wp_db']) ? '1' : '0');
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
            $cat = get_post_meta($p->ID, self::META_CATEGORY, true);
            if ($category && strcasecmp($cat, $category) !== 0) {
                continue;
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
                    'category'    => $cat,
                    'shortcode'   => '[comyli_table query="' . $slug . '" layout="clean"]',
                ];
            }
        }

        return $available;
    }

    /**
     * Finds a comyli_query post by slug, post name, title or ID.
     */
    private static function find_query_post(string $queryIdentifier): ?\WP_Post {
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
