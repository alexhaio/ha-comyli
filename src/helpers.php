<?php
/**
 * Global helper functions for CoMyLi.
 */

defined('ABSPATH') or die('Nope.');

if (!function_exists('comyli_render')) {
    /**
     * Rendert eine comyli-Tabelle oder Abfrage direkt im PHP-Template oder Fach-Plugin.
     *
     * @param array<string, mixed> $args Konfigurations-Parameter (table, query, params, layout, limit, edit, filter, csvExport, etc.)
     * @return string Das gerenderte HTML der Tabelle.
     */
    function comyli_render(array $args): string {
        $defaults = [
            'table'     => '',
            'query'     => '',
            'where'     => [],
            'params'    => [],
            'layout'    => 'clean',
            'limit'     => 25,
            'edit'      => false,
            'filter'    => true,
            'csvExport' => false,
        ];
        $config = wp_parse_args($args, $defaults);

        return \ha\CoMyLi\Shortcode::renderDirect($config);
    }
}
