<?php
/**
 * Uninstall handler for CoMyLi.
 *
 * Removes plugin CPT posts, user metadata, transients, scheduled cron events,
 * and archived CSV exports when the plugin is deleted via WordPress.
 *
 * @package ha\CoMyLi
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// 1. Delete all CoMyLi CPT posts (tables and queries) and their postmeta/revisions.
$ha_comyli_posts = get_posts(
	[
		'post_type'      => [ 'comyli', 'comyli_query' ],
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]
);

if ( is_array( $ha_comyli_posts ) ) {
	foreach ( $ha_comyli_posts as $ha_comyli_post_id ) {
		wp_delete_post( (int) $ha_comyli_post_id, true );
	}
}

// 2. Delete per-user hidden column preferences from wp_usermeta.
delete_metadata( 'user', 0, '_comyli_hidden_cols', '', true );

// 3. Clear scheduled cron hook.
if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'comyli_scheduled_queries_cron' );
}

// 4. Clean up JSON validation transients.
global $wpdb;
if ( isset( $wpdb ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_comyli_json_error_%',
			'_transient_timeout_comyli_json_error_%'
		)
	);
}

// 5. Remove archived CSV directory in uploads if present.
$ha_comyli_uploads = wp_upload_dir();
if ( ! empty( $ha_comyli_uploads['basedir'] ) ) {
	$ha_comyli_archive_dir = rtrim( $ha_comyli_uploads['basedir'], '/\\' ) . '/ha-comyli-archives';
	if ( is_dir( $ha_comyli_archive_dir ) ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
			global $wp_filesystem;
			if ( $wp_filesystem ) {
				$wp_filesystem->delete( $ha_comyli_archive_dir, true );
			}
		}
	}
}
