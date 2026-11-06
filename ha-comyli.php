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
 * Description:		Implements an easy-to-use interface for MySQL-Databases
 * Version:			3.1.5
 * Author:			Alexander Hacker
 * Author URI:		https://edv-hacker.de
 * Text Domain:		comyli
 * License:			GPL-3.0
 * License URI:		https://www.gnu.org/licenses/gpl-3.0.txt
 */

defined('ABSPATH') or die('Nope.');

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

// Initialize plugin
function ha_comyli_init() {
    $plugin = new \ha\CoMyLi\Plugin(__FILE__);
    $plugin->init();
}

ha_comyli_init();
