<?php
/**
 * UNINSTALL
 *
 * Runs only when the plugin is deleted from the Plugins screen. Removes all plugin data only when
 * "Delete All Data on Uninstall" is checked (per site, or for the network admin Control Center).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;


/**
 * Delete the plugin's tables, options, transients and scheduled event on the current site.
 *
 * @param string $table_prefix Table prefix to drop, e.g. wp_prxctrl_.
 * @return void
 */
function prxctrl_uninstall_site( string $table_prefix ) : void {
    global $wpdb;

    foreach ( [ 'sites', 'groups', 'admin_users', 'themes', 'plugins', 'integrations', 'actions' ] as $prxctrl_table ) {
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_prefix . $prxctrl_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing this plugin's tables on uninstall.
    }

    $prxctrl_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'prxctrl_' ) . '%', $wpdb->esc_like( '_transient_prxctrl_' ) . '%', $wpdb->esc_like( '_transient_timeout_prxctrl_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup on uninstall.

    foreach ( (array) $prxctrl_options as $prxctrl_option ) {
        if ( 0 === strpos( $prxctrl_option, '_transient_timeout_' ) ) {
            continue;
        }

        if ( 0 === strpos( $prxctrl_option, '_transient_' ) ) {
            delete_transient( substr( $prxctrl_option, strlen( '_transient_' ) ) );
            continue;
        }

        delete_option( $prxctrl_option );
    }

    wp_clear_scheduled_hook( 'prxctrl_daily' );
} // End prxctrl_uninstall_site()


global $wpdb;

if ( is_multisite() ) {
    $prxctrl_network_delete = (bool) get_site_option( 'prxctrl_delete_on_uninstall', 0 );

    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $prxctrl_blog_id ) {
        switch_to_blog( (int) $prxctrl_blog_id );

        if ( $prxctrl_network_delete || get_option( 'prxctrl_delete_on_uninstall' ) ) {
            prxctrl_uninstall_site( $wpdb->prefix . 'prxctrl_' );
        }

        restore_current_blog();
    }

    if ( $prxctrl_network_delete ) {
        prxctrl_uninstall_site( $wpdb->base_prefix . 'prxctrl_net_' );

        foreach ( [ 'prxctrl_network', 'prxctrl_net_db_version' ] as $prxctrl_site_option ) {
            delete_site_option( $prxctrl_site_option );
        }

        $prxctrl_site_options = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'prxctrl_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup on uninstall.

        foreach ( (array) $prxctrl_site_options as $prxctrl_site_option ) {
            delete_site_option( $prxctrl_site_option );
        }

        delete_metadata( 'user', 0, 'prxctrl_hidden', '', true );
        delete_metadata( 'user', 0, 'prxctrl_logs_per_page', '', true );
    }

    return;
}

if ( ! get_option( 'prxctrl_delete_on_uninstall' ) ) {
    return;
}

prxctrl_uninstall_site( $wpdb->prefix . 'prxctrl_' );

delete_metadata( 'user', 0, 'prxctrl_hidden', '', true );
delete_metadata( 'user', 0, 'prxctrl_logs_per_page', '', true );
