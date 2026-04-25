<?php
/**
 * Runs when the plugin is deleted from the WordPress admin (Plugins > Delete).
 * Removes all plugin data: DB tables and wp_options entries.
 *
 * This file is only executed when the user explicitly deletes the plugin and
 * has opted in via the standard WordPress uninstall process.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpk_credentials' );    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpk_rate_limits' );    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpk_logs' );           // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove all plugin options
$options = array(
    'wpk_enabled',
    'wpk_eligible_roles',
    'wpk_max_passkeys_per_user',
    'wpk_user_verification',
    'wpk_rate_window',
    'wpk_rate_max_attempts',
    'wpk_rate_lockout',
    'wpk_rp_name',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove any transients left behind
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpk_%' OR option_name LIKE '_transient_timeout_wpk_%'"
);
