<?php
/**
 * Uninstall handler — removes all plugin data.
 *
 * Runs only when the user explicitly deletes the plugin
 * through the WordPress admin interface.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options
delete_option( 'spf_forms' );
delete_option( 'spf_fields' );
delete_option( 'spf_rules' );
delete_option( 'spf_confirmations' );
delete_option( 'spf_entries' );
delete_option( 'spf_settings' );
delete_option( 'spf_version' );
