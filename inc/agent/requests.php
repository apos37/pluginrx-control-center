<?php
/**
 * AGENT REQUESTS
 *
 * Collects the core data an agent can share. Each method matches a request key in the registry
 * (get_{key}) and only runs when that request is allowed in Settings > Agent.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Requests {

    /**
     * Get the admin email.
     *
     * @return string
     */
    public static function get_admin_email() : string {
        return sanitize_email( (string) get_option( 'admin_email', '' ) );
    } // End get_admin_email()


    /**
     * Get the administrator accounts.
     *
     * @return array
     */
    public static function get_admin_users() : array {
        $admins = get_users( [ 'role__in' => [ 'administrator' ], 'number' => 200 ] );

        if ( is_multisite() ) {
            foreach ( get_super_admins() as $login ) {
                $super = get_user_by( 'login', $login );

                if ( $super && ! in_array( $super->ID, wp_list_pluck( $admins, 'ID' ), true ) ) {
                    $admins[] = $super;
                }
            }
        }

        $has_dev_tools = Helpers::is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' ) && method_exists( '\Apos37\DevDebugTools\Helpers', 'is_dev' );
        $users = [];

        foreach ( $admins as $user ) {
            $user_id = absint( $user->ID );

            $users[] = [
                'user_id'         => $user_id,
                'user_login'      => sanitize_user( $user->user_login ),
                'display_name'    => sanitize_text_field( $user->display_name ),
                'user_email'      => sanitize_email( $user->user_email ),
                'role'            => array_values( array_map( 'sanitize_key', (array) $user->roles ) ),
                'is_dev'          => $has_dev_tools && \Apos37\DevDebugTools\Helpers::is_dev( $user_id ),
                'user_registered' => sanitize_text_field( (string) $user->user_registered ),
                'online_status'   => self::online_status( $user_id ),
            ];
        }

        return $users;
    } // End get_admin_users()


    /**
     * Get a user's online status: "online", a Unix timestamp of the last activity, or "unknown".
     *
     * @param int $user_id
     * @return string
     */
    private static function online_status( int $user_id ) : string {
        $last_activity = absint( get_user_meta( $user_id, 'ddtt_last_online', true ) );

        if ( $last_activity ) {
            $minutes = absint( get_option( 'ddtt_online_users_last_seen', get_option( 'online_users_last_seen', 5 ) ) );
            return ( $last_activity >= time() - ( max( 1, $minutes ) * MINUTE_IN_SECONDS ) ) ? 'online' : (string) $last_activity;
        }

        $sessions = get_user_meta( $user_id, 'session_tokens', true );

        if ( is_array( $sessions ) && ! empty( $sessions ) ) {
            $logins = array_filter( array_map( 'absint', wp_list_pluck( $sessions, 'login' ) ) );

            if ( ! empty( $logins ) ) {
                return (string) max( $logins );
            }
        }

        return 'unknown';
    } // End online_status()


    /**
     * Get the server IP address.
     *
     * @return string
     */
    public static function get_server_ip() : string {
        $ip = isset( $_SERVER[ 'SERVER_ADDR' ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ 'SERVER_ADDR' ] ) ) : '';

        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    } // End get_server_ip()


    /**
     * Get the absolute path to WordPress.
     *
     * @return string
     */
    public static function get_abspath() : string {
        return sanitize_text_field( ABSPATH );
    } // End get_abspath()


    /**
     * Get whether this is a multisite network.
     *
     * @return bool
     */
    public static function get_is_multisite() : bool {
        return is_multisite();
    } // End get_is_multisite()


    /**
     * Get the blog ID.
     *
     * @return int
     */
    public static function get_blog_id() : int {
        return get_current_blog_id();
    } // End get_blog_id()


    /**
     * Get the WordPress version.
     *
     * @return string
     */
    public static function get_wordpress_version() : string {
        return sanitize_text_field( (string) get_bloginfo( 'version' ) );
    } // End get_wordpress_version()


    /**
     * Get the version of an available WordPress update, or an empty string.
     *
     * @return string
     */
    public static function get_core_update() : string {
        $updates = get_site_transient( 'update_core' );

        if ( ! is_object( $updates ) || empty( $updates->updates ) ) {
            return '';
        }

        foreach ( (array) $updates->updates as $update ) {
            if ( isset( $update->response ) && 'upgrade' === $update->response && ! empty( $update->current ) ) {
                return sanitize_text_field( (string) $update->current );
            }
        }

        return '';
    } // End get_core_update()


    /**
     * Get the PHP version.
     *
     * @return string
     */
    public static function get_php_version() : string {
        return sanitize_text_field( PHP_VERSION );
    } // End get_php_version()


    /**
     * Get whether WP_DEBUG is enabled.
     *
     * @return bool
     */
    public static function get_wp_debug() : bool {
        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    } // End get_wp_debug()


    /**
     * Get installed plugins with versions and available updates.
     *
     * @return array
     */
    public static function get_plugins() : array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $updates = get_site_transient( 'update_plugins' );
        $responses = is_object( $updates ) && isset( $updates->response ) ? (array) $updates->response : [];
        $plugins = [];

        foreach ( get_plugins() as $file => $plugin ) {
            $plugins[] = [
                'name'             => wp_strip_all_tags( $plugin[ 'Name' ] ),
                'version'          => wp_strip_all_tags( $plugin[ 'Version' ] ),
                'author'           => wp_strip_all_tags( $plugin[ 'Author' ] ),
                'slug'             => $file,
                'plugin_file'      => $file,
                'active'           => is_plugin_active( $file ),
                'update_available' => isset( $responses[ $file ]->new_version ) ? wp_strip_all_tags( (string) $responses[ $file ]->new_version ) : null,
            ];
        }

        return $plugins;
    } // End get_plugins()


    /**
     * Get installed themes with versions and available updates.
     *
     * @return array
     */
    public static function get_themes() : array {
        $updates = get_site_transient( 'update_themes' );
        $responses = is_object( $updates ) && isset( $updates->response ) ? (array) $updates->response : [];
        $active = get_stylesheet();
        $themes = [];

        foreach ( wp_get_themes() as $slug => $theme ) {
            $themes[] = [
                'name'             => wp_strip_all_tags( $theme->get( 'Name' ) ),
                'version'          => wp_strip_all_tags( $theme->get( 'Version' ) ),
                'author'           => wp_strip_all_tags( $theme->get( 'Author' ) ),
                'slug'             => $slug,
                'active'           => ( $active === $slug ),
                'update_available' => isset( $responses[ $slug ][ 'new_version' ] ) ? wp_strip_all_tags( (string) $responses[ $slug ][ 'new_version' ] ) : null,
            ];
        }

        return $themes;
    } // End get_themes()


    /**
     * Get the admin URL.
     *
     * @return string
     */
    public static function get_admin_path() : string {
        return esc_url_raw( admin_url() );
    } // End get_admin_path()

} // End class Requests
