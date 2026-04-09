<?php
/**
 * Cache purge utility.
 *
 * Automatically clears page caches from popular caching plugins
 * whenever any SPF option is updated in the admin.
 *
 * Supported plugins / hosts:
 *   WP Super Cache, W3 Total Cache, WP Rocket, LiteSpeed Cache,
 *   WP Fastest Cache, Autoptimize, SG Optimizer (SiteGround),
 *   Hummingbird, Cache Enabler, Breeze (Cloudways),
 *   Kinsta, WP Engine, Pantheon, Cloudflare (via plugin),
 *   and the native WordPress object cache.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Cache {

    /**
     * SPF option keys that should trigger a cache purge when updated.
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
            add_action( "update_option_{$key}", array( $this, 'purge_all' ), 10, 0 );
        }
    }

    /**
     * Purge caches from all detected caching layers.
     *
     * Safe to call multiple times per request — each plugin call
     * is guarded so it only runs if that plugin is active.
     */
    public function purge_all(): void {

        // Prevent running more than once per request.
        static $purged = false;
        if ( $purged ) {
            return;
        }
        $purged = true;

        /* ── WordPress object cache ──────────── */
        wp_cache_flush();

        /* ── WP Super Cache ──────────────────── */
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }

        /* ── W3 Total Cache ──────────────────── */
        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
        }

        /* ── WP Rocket ───────────────────────── */
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }

        /* ── LiteSpeed Cache ─────────────────── */
        if ( class_exists( 'LiteSpeed\Purge' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        /* ── WP Fastest Cache ────────────────── */
        if ( function_exists( 'wpfc_clear_all_cache' ) ) {
            wpfc_clear_all_cache( true );
        } elseif ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
            $GLOBALS['wp_fastest_cache']->deleteCache();
        }

        /* ── Autoptimize ─────────────────────── */
        if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
            autoptimizeCache::clearall();
        }

        /* ── SG Optimizer (SiteGround) ───────── */
        if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
            sg_cachepress_purge_cache();
        }

        /* ── Hummingbird ─────────────────────── */
        if ( class_exists( 'Hummingbird\WP_Hummingbird' ) ) {
            do_action( 'wphb_clear_page_cache' );
        }

        /* ── Cache Enabler ───────────────────── */
        if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_total_cache' ) ) {
            Cache_Enabler::clear_total_cache();
        }

        /* ── Breeze (Cloudways) ──────────────── */
        if ( class_exists( 'Breeze_PurgeCache' ) ) {
            do_action( 'breeze_clear_all_cache' );
        }

        /* ── Kinsta ──────────────────────────── */
        if ( class_exists( 'Kinsta\Cache' ) && wp_cache_flush() ) {
            // Kinsta uses the object cache flush + their own MU plugin.
            // wp_cache_flush() already called above handles it.
        }

        /* ── WP Engine ───────────────────────── */
        if ( class_exists( 'WpeCommon' ) ) {
            if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
                WpeCommon::purge_memcached();
            }
            if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
                WpeCommon::purge_varnish_cache();
            }
        }

        /* ── Pantheon ────────────────────────── */
        if ( function_exists( 'pantheon_wp_clear_edge_all' ) ) {
            pantheon_wp_clear_edge_all();
        }

        /* ── Cloudflare (official plugin) ────── */
        if ( class_exists( 'CF\WordPress\Hooks' ) ) {
            do_action( 'cloudflare_purge_everything' );
        }

        /**
         * Fires after SPF has purged all known caches.
         *
         * Third-party integrations can hook here to purge
         * additional caching layers (Varnish, Redis, CDN, etc.).
         */
        do_action( 'spf_cache_purged' );
    }
}
