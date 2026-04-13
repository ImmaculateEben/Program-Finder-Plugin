<?php
/**
 * Plugin Name: Smart Programme Finder
 * Plugin URI:  https://example.com/smart-programme-finder
 * Description: A no-code recommendation engine that helps visitors find the right programme through guided forms and conditional rules.
 * Version:     1.0.1
 * Author:      Smart Programme Finder
 * Author URI:  https://example.com
 * Text Domain: smart-programme-finder
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ──────────────────────────────────────────────
 * Constants
 * ──────────────────────────────────────────── */
define( 'SPF_VERSION', '1.0.1' );
define( 'SPF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/* ──────────────────────────────────────────────
 * Autoload classes
 * ──────────────────────────────────────────── */
require_once SPF_PLUGIN_DIR . 'includes/class-entries-store.php';
require_once SPF_PLUGIN_DIR . 'includes/class-admin.php';
require_once SPF_PLUGIN_DIR . 'includes/class-ajax.php';
require_once SPF_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once SPF_PLUGIN_DIR . 'includes/class-rules-engine.php';
require_once SPF_PLUGIN_DIR . 'includes/class-cache.php';

/* ──────────────────────────────────────────────
 * Activation — seed default options
 * ──────────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
    // Seed default form if no forms exist yet.
    if ( ! get_option( 'spf_forms' ) ) {
        update_option( 'spf_forms', array(
            array(
                'id'         => 1,
                'name'       => __( 'Default Form', 'smart-programme-finder' ),
                'created_at' => current_time( 'mysql' ),
                'settings'   => SPF_Admin::DEFAULT_APPEARANCE,
                'general'    => SPF_Admin::DEFAULT_GENERAL,
            ),
        ) );
    }
    if ( ! get_option( 'spf_fields' ) ) {
        update_option( 'spf_fields', array() );
    }
    if ( ! get_option( 'spf_rules' ) ) {
        update_option( 'spf_rules', array() );
    }
    if ( ! get_option( 'spf_confirmations' ) ) {
        update_option( 'spf_confirmations', array() );
    }
    if ( ! get_option( 'spf_settings' ) ) {
        update_option( 'spf_settings', array(
            'fallback_message' => __( 'We could not find an exact match. Please contact our admissions team for guidance.', 'smart-programme-finder' ),
        ) );
    }
    SPF_Entries_Store::activate();
    update_option( 'spf_version', SPF_VERSION );
} );

/* ──────────────────────────────────────────────
 * Deactivation — lightweight cleanup
 * ──────────────────────────────────────────── */
register_deactivation_hook( __FILE__, function () {
    // Intentionally left empty — data is preserved on deactivation.
} );

/* ──────────────────────────────────────────────
 * Bootstrap
 * ──────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'smart-programme-finder', false, dirname( SPF_PLUGIN_BASENAME ) . '/languages' );
} );

SPF_Entries_Store::maybe_upgrade();

// Admin screens
if ( is_admin() ) {
    new SPF_Admin();
}

// AJAX handler (admin-ajax.php runs inside is_admin context)
new SPF_Ajax();

// Shortcode
new SPF_Shortcode();

// Cache purge on admin changes
new SPF_Cache();

/* ──────────────────────────────────────────────
 * Enqueue frontend assets (only when shortcode is present)
 * ──────────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', function () {
    // Assets are enqueued by the shortcode renderer when needed.
    // Register them here so they are available for enqueue.
    wp_register_style(
        'spf-frontend',
        SPF_PLUGIN_URL . 'assets/css/style.css',
        array(),
        SPF_VERSION
    );

    wp_register_script(
        'spf-frontend',
        SPF_PLUGIN_URL . 'assets/js/script.js',
        array( 'jquery' ),
        SPF_VERSION,
        true
    );

    wp_localize_script( 'spf-frontend', 'spf_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
} );

/* ──────────────────────────────────────────────
 * Elementor integration (conditional)
 * ──────────────────────────────────────────── */
add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
    require_once SPF_PLUGIN_DIR . 'includes/class-elementor.php';
    $widgets_manager->register( new SPF_Elementor_Widget() );
} );
