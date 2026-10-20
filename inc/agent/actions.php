<?php
/**
 * AGENT ACTIONS
 *
 * Core maintenance actions an agent can run when they are allowed in Settings > Agent.
 * Every action returns [ 'success' => bool, 'message' => string ].
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Actions {

    /**
     * Load the files the WordPress upgraders need outside of the admin screens.
     *
     * @return void
     */
    private static function load_upgrader() : void {
        require_once ABSPATH . 'wp-admin/includes/admin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        if ( ! defined( 'FS_METHOD' ) ) {
            add_filter( 'filesystem_method', [ self::class, 'direct_filesystem' ] );
        }
    } // End load_upgrader()


    /**
     * Use the direct filesystem method while running updates.
     *
     * @return string
     */
    public static function direct_filesystem() : string {
        return 'direct';
    } // End direct_filesystem()


    /**
     * Stop updates that would affect a whole network from running on a subsite.
     *
     * @return array|null Error response, or null when allowed.
     */
    private static function network_guard() : ?array {
        if ( is_multisite() && ! is_main_site() ) {
            return [
                'success' => false,
                'message' => __( 'Plugins, themes and WordPress are shared by the whole network, so they can only be updated through the main site.', 'pluginrx-control-center' ),
            ];
        }

        return null;
    } // End network_guard()


    /**
     * Summarize a bulk upgrade result.
     *
     * @param array|false $result
     * @param string      $type plugins|themes
     * @return array
     */
    private static function bulk_result( $result, string $type ) : array {
        if ( ! is_array( $result ) ) {
            return [
                'success' => false,
                'message' => __( 'The update process could not start. The site may not have permission to write files.', 'pluginrx-control-center' ),
            ];
        }

        $failed = [];

        foreach ( $result as $item => $status ) {
            if ( false === $status || is_wp_error( $status ) ) {
                $failed[] = is_wp_error( $status ) ? $item . ' (' . $status->get_error_message() . ')' : $item;
            }
        }

        if ( ! empty( $failed ) ) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: comma-separated list of plugins or themes that failed */
                    __( 'Some updates failed: %s', 'pluginrx-control-center' ),
                    implode( ', ', $failed )
                ),
            ];
        }

        return [
            'success' => true,
            'message' => 'plugins' === $type
                /* translators: %d: number of plugins */
                ? sprintf( _n( '%d plugin updated.', '%d plugins updated.', count( $result ), 'pluginrx-control-center' ), count( $result ) )
                /* translators: %d: number of themes */
                : sprintf( _n( '%d theme updated.', '%d themes updated.', count( $result ), 'pluginrx-control-center' ), count( $result ) ),
        ];
    } // End bulk_result()


    /**
     * Update every plugin that has an update available.
     *
     * @return array
     */
    public static function update_plugins() : array {
        $guard = self::network_guard();

        if ( $guard ) {
            return $guard;
        }

        self::load_upgrader();
        wp_update_plugins();

        $updates = get_site_transient( 'update_plugins' );
        $files = is_object( $updates ) && ! empty( $updates->response ) ? array_keys( (array) $updates->response ) : [];

        if ( empty( $files ) ) {
            return [ 'success' => true, 'message' => __( 'All plugins are already up to date.', 'pluginrx-control-center' ) ];
        }

        $upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
        $result = $upgrader->bulk_upgrade( $files );

        wp_clean_plugins_cache();

        return self::bulk_result( $result, 'plugins' );
    } // End update_plugins()


    /**
     * Update every theme that has an update available.
     *
     * @return array
     */
    public static function update_themes() : array {
        $guard = self::network_guard();

        if ( $guard ) {
            return $guard;
        }

        self::load_upgrader();
        wp_update_themes();

        $updates = get_site_transient( 'update_themes' );
        $slugs = is_object( $updates ) && ! empty( $updates->response ) ? array_keys( (array) $updates->response ) : [];

        if ( empty( $slugs ) ) {
            return [ 'success' => true, 'message' => __( 'All themes are already up to date.', 'pluginrx-control-center' ) ];
        }

        $upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
        $result = $upgrader->bulk_upgrade( $slugs );

        wp_clean_themes_cache();

        return self::bulk_result( $result, 'themes' );
    } // End update_themes()


    /**
     * Update WordPress core to the latest offered version.
     *
     * @return array
     */
    public static function update_wordpress() : array {
        $guard = self::network_guard();

        if ( $guard ) {
            return $guard;
        }

        self::load_upgrader();
        wp_version_check( [], true );

        $update = null;

        foreach ( (array) get_core_updates() as $offer ) {
            if ( isset( $offer->response ) && 'upgrade' === $offer->response ) {
                $update = $offer;
                break;
            }
        }

        if ( ! $update ) {
            return [ 'success' => true, 'message' => __( 'WordPress is already up to date.', 'pluginrx-control-center' ) ];
        }

        $upgrader = new \Core_Upgrader( new \Automatic_Upgrader_Skin() );
        $result = $upgrader->upgrade( $update );

        if ( is_wp_error( $result ) ) {
            return [ 'success' => false, 'message' => $result->get_error_message() ];
        }

        if ( ! $result ) {
            return [ 'success' => false, 'message' => __( 'WordPress could not be updated.', 'pluginrx-control-center' ) ];
        }

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %s: WordPress version number */
                __( 'WordPress updated to %s.', 'pluginrx-control-center' ),
                sanitize_text_field( (string) $result )
            ),
        ];
    } // End update_wordpress()

} // End class Actions
