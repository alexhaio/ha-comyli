<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Granular WordPress Capability Matrix for CoMyLi.
 * 
 * Manages access permissions for queries, tables, and CRUD operations.
 */
class Permissions {
    /**
     * Checks if current user can view a given query.
     *
     * @param string $queryName Query identifier.
     * @param int|null $tableId Optional table post ID for contextual lookup.
     * @return bool
     */
    public static function can_view_query(string $queryName, ?int $tableId = null): bool {
        if (!function_exists('current_user_can')) {
            return true;
        }

        // Administrators always have full access
        if (current_user_can('manage_options')) {
            return true;
        }

        $cleanName = trim($queryName);

        // 1. Check if dynamically configured in QueryManager (comyli_query post type)
        if (class_exists(__NAMESPACE__ . '\\QueryManager')) {
            $post = QueryManager::find_query_post($cleanName, $tableId);
            if ($post) {
                $requiredCap = get_post_meta($post->ID, QueryManager::META_CAPABILITY, true);
                if (empty($requiredCap)) {
                    // No special capability restriction configured -> public for all
                    return true;
                }
                return current_user_can($requiredCap);
            }

            // Deny if the query exists only as an unpublished draft and user cannot edit it
            $draftCheck = get_posts([
                'post_type'      => QueryManager::POST_TYPE,
                'name'           => sanitize_title($cleanName),
                'post_status'    => ['draft', 'pending', 'private', 'trash'],
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]);
            if (!empty($draftCheck) && !current_user_can('edit_post', $draftCheck[0])) {
                return false;
            }
        }

        // 2. Filter fallback
        $requiredCap = apply_filters("comyli_cap_query_{$cleanName}", 'read');
        return current_user_can($requiredCap);
    }

    /**
     * Checks if current user can view a given table.
     *
     * @param string $tableName Table name.
     * @return bool
     */
    public static function can_view_table(string $tableName): bool {
        if (!function_exists('current_user_can')) {
            return true;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $requiredCap = apply_filters("comyli_cap_table_view_{$tableName}", 'read');
        return current_user_can($requiredCap);
    }

    /**
     * Checks if current user can edit/modify a given table.
     *
     * @param string $tableName Table name.
     * @return bool
     */
    public static function can_edit_table(string $tableName): bool {
        if (!function_exists('current_user_can')) {
            return true;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $requiredCap = apply_filters("comyli_cap_table_edit_{$tableName}", 'edit_posts');
        return current_user_can($requiredCap);
    }
}

// Global class alias for backwards compatibility and ease of use in external plugins
if (!class_exists('Comyli_Permissions')) {
    class_alias(Permissions::class, 'Comyli_Permissions');
}
