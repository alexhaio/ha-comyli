<?php
/**
 * CoMyLi Wordpress-Plugin
 * 
 * 
 * @author		Alexander Hacker <info@edv-hacker.de>
 * @license		GPL-3.0
 * @link		https://edv-hacker.de
 * @copyright	2017 Alexander Hacker
 * 
 * @wordpress-plugin
 * Plugin Name:		CoMyLi - Configurable-MySQL-Light-Interface
 * Plugin URI:		https://github.com/alexhaio/ha-comyli
 * Description:		Implements an easy-to-use interface for MySQL-Databases
 * Version:			3.6.3
 * Author:			Alexander Hacker
 * Author URI:		https://edv-hacker.de
 * Requires at least:	6.6
 * Requires PHP:		8.0
 * Text Domain:		ha-comyli
 * Domain Path:		/languages
 * License:			GPL-3.0
 * License URI:		https://www.gnu.org/licenses/gpl-3.0.txt
 */

defined('ABSPATH') or die('Nope.');

if (!defined('HA_COMYLI_VERSION')) {
    define('HA_COMYLI_VERSION', '3.6.3');
}

// Point WordPress 6.6+ JIT translation loader to bundled /languages/ directory.
add_filter('lang_dir_for_domain', function ($path, $domain) {
    if ('ha-comyli' === $domain) {
        return plugin_dir_path(__FILE__) . 'languages/';
    }
    return $path;
}, 10, 2);

// Direct admin links on Plugins overview page.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    if (!current_user_can('edit_posts')) {
        return $links;
    }
    $tablesLink = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('edit.php?post_type=comyli')),
        esc_html__('Tabellen', 'ha-comyli')
    );
    $queriesLink = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('edit.php?post_type=comyli_query')),
        esc_html__('Abfragen', 'ha-comyli')
    );
    array_unshift($links, $tablesLink, $queriesLink);
    return $links;
});

/**
 * Initialize Plugin Update Checker for automatic updates from GitHub repository.
 *
 * @return \YahnisElsts\PluginUpdateChecker\v5\Vcs\PluginUpdateChecker|null
 */
function ha_comyli_get_updater() {
    static $update_checker = null;
    if ( null !== $update_checker ) {
        return $update_checker;
    }

    $puc_file = plugin_dir_path( __FILE__ ) . 'vendor/plugin-update-checker/plugin-update-checker.php';
    if ( ! file_exists( $puc_file ) ) {
        return null;
    }

    require_once $puc_file;

    $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/alexhaio/ha-comyli/',
        __FILE__,
        'ha-comyli'
    );

    // Set default branch to main
    $update_checker->setBranch( 'main' );

    // Set GitHub Access Token (if defined/available)
    $token = defined( 'HA_GITHUB_UPDATE_TOKEN' ) ? HA_GITHUB_UPDATE_TOKEN : '';
    if ( empty( $token ) && defined( 'GITHUB_ACCESS_TOKEN' ) ) {
        $token = GITHUB_ACCESS_TOKEN;
    }
    $token = apply_filters( 'ha_github_update_token', $token, 'ha-comyli' );

    if ( ! empty( $token ) ) {
        $update_checker->setAuthentication( $token );
    }

    return $update_checker;
}
ha_comyli_get_updater();


// Simple PSR-4 Autoloader for the src/ directory
spl_autoload_register(function ($class) {
    $prefix = 'ha\\CoMyLi\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load helpers and permissions
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Permissions.php';

// Activation & Deactivation hooks
register_activation_hook(__FILE__, function () {
    $config = new \ha\CoMyLi\Config(__FILE__);
    (new \ha\CoMyLi\PostType($config))->registerPostType();
    (new \ha\CoMyLi\QueryManager($config))->registerPostType();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook(\ha\CoMyLi\QueryScheduler::CRON_HOOK);
    }
    flush_rewrite_rules();
});

// Initialize plugin
function ha_comyli_init() {
    $plugin = new \ha\CoMyLi\Plugin(__FILE__);
    $plugin->init();
}

ha_comyli_init();
