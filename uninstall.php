<?php
/**
 * Uninstall handler
 *
 * Deletes all plugin options and transients when uninstalled via
 * the WordPress plugin uninstaller.
 */

// Exit if not called by WP uninstall routine
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if the cleanup setting is enabled
$prxcntrl_cleanup_enabled = get_option( 'prxctrl_delete_db', false );
if ( ! $prxcntrl_cleanup_enabled ) {
    return; // Do nothing if the option is not enabled
}


/**
 * Delete tables
 */
\PluginRx\ControlCenter\Database::delete_tables();



global $wpdb;
$prxcntrl_option_prefix = 'prxctrl_';

/**
 * Delete all options
 */
$prxcntrl_like = $wpdb->esc_like( $prxcntrl_option_prefix ) . '%';
$prxcntrl_option_rows = $wpdb->get_col( // phpcs:ignore
    $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prxcntrl_like ) // phpcs:ignore
);
if ( is_array( $prxcntrl_option_rows ) ) {
    foreach ( $prxcntrl_option_rows as $prxcntrl_option_name ) {
        delete_option( $prxcntrl_option_name );
        if ( is_multisite() ) {
            delete_site_option( $prxcntrl_option_name );
        }
    }
}

/**
 * Delete all transients
 */
$prxcntrl_like = $wpdb->esc_like( '_transient_' . $prxcntrl_option_prefix ) . '%';
$prxcntrl_transient_rows = $wpdb->get_col( // phpcs:ignore
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $prxcntrl_like
    )
);

if ( is_array( $prxcntrl_transient_rows ) ) {
    foreach ( $prxcntrl_transient_rows as $prxcntrl_option_name ) {
        // Remove the transient name (WordPress strips _transient_ prefix automatically)
        $prxcntrl_name = preg_replace( '/^_transient_/', '', $prxcntrl_option_name );
        delete_transient( $prxcntrl_name );
    }
}


// Done.
return;