<?php
/**
 * Cache invalidation utility.
 *
 * Tracks option updates and invalidates only the public content known to embed
 * Smart Programme Finder forms instead of flushing the entire site's caches.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Cache {

    /**
     * SPF option keys that should trigger cache invalidation when updated.
     *
     * @var string[]
     */
    private const OPTION_KEYS = array(
        'spf_forms',
        'spf_fields',
        'spf_confirmations',
        'spf_rules',
        'spf_settings',
    );

    /**
     * Register hooks.
     */
    public function __construct() {
        foreach ( self::OPTION_KEYS as $key ) {
            add_action( "update_option_{$key}", array( $this, 'purge_targets' ), 10, 0 );
        }
    }

    /**
     * Purge only posts/pages that are known to render SPF forms.
     */
    public function purge_targets(): void {
        static $purged = false;

        if ( $purged ) {
            return;
        }
        $purged = true;

        $post_ids = $this->get_target_post_ids();
        $urls     = array();

        foreach ( $post_ids as $post_id ) {
            clean_post_cache( $post_id );

            $url = get_permalink( $post_id );
            if ( is_string( $url ) && '' !== $url ) {
                $urls[] = $url;
            }

            if ( function_exists( 'rocket_clean_post' ) ) {
                rocket_clean_post( $post_id );
            }
        }

        $urls = array_values( array_unique( array_filter( $urls ) ) );

        /**
         * Fires after SPF has purged caches for the affected targets.
         *
         * Hosts or cache plugins can hook here to purge page caches or CDN
         * entries for the specific post IDs / URLs that embed SPF output.
         *
         * @param int[]    $post_ids Target post IDs.
         * @param string[] $urls     Target URLs.
         */
        do_action( 'spf_cache_targets_purged', $post_ids, $urls );
    }

    /**
     * Find posts, pages, and Elementor documents that embed SPF output.
     *
     * @return int[]
     */
    private function get_target_post_ids(): array {
        global $wpdb;

        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID
                 FROM {$wpdb->posts}
                 WHERE post_status NOT IN ( 'auto-draft', 'trash' )
                   AND post_type NOT IN ( 'revision', 'nav_menu_item' )
                   AND post_content LIKE %s",
                '%[spf_form%'
            )
        );

        $elementor_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = %s
                   AND meta_value LIKE %s",
                '_elementor_data',
                '%spf_programme_finder%'
            )
        );

        $target_ids = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        array_merge(
                            is_array( $post_ids ) ? $post_ids : array(),
                            is_array( $elementor_ids ) ? $elementor_ids : array()
                        )
                    )
                )
            )
        );

        /**
         * Filters the post IDs that should be purged after SPF updates.
         *
         * @param int[] $target_ids Detected post IDs.
         */
        return (array) apply_filters( 'spf_cache_target_post_ids', $target_ids );
    }
}
