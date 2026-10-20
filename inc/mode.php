<?php
/**
 * MODE
 *
 * Every site is a Host (runs the Control Center), an Agent (shares data with a Control Center),
 * or Both. On a network where the plugin is network-activated, the mode is set once for the
 * whole network, and the Control Center lives either in the network admin or on one chosen site.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Mode {

    /**
     * Mode keys.
     */
    public const HOST = 'host';
    public const AGENT = 'agent';
    public const BOTH = 'both';


    /**
     * Version that last required the starter page.
     */
    public const SETUP_VERSION = '2.0.0';


    /**
     * @var bool|null Cached network activation state
     */
    private static ?bool $network_active = null;


    /**
     * Get the modes with their labels and descriptions.
     *
     * @return array
     */
    public static function modes() : array {
        return [
            self::HOST  => [
                'label'       => __( 'Host', 'pluginrx-control-center' ),
                'icon'        => 'dashicons-networking',
                'description' => __( 'This site runs the Control Center. You will add your other sites here, check on them and run updates from one dashboard.', 'pluginrx-control-center' ),
            ],
            self::AGENT => [
                'label'       => __( 'Agent', 'pluginrx-control-center' ),
                'icon'        => 'dashicons-admin-site-alt3',
                'description' => __( 'This site is managed by a Control Center on another site. You choose exactly what it can see and do.', 'pluginrx-control-center' ),
            ],
            self::BOTH  => [
                'label'       => __( 'Both', 'pluginrx-control-center' ),
                'icon'        => 'dashicons-update-alt',
                'description' => __( 'This site runs the Control Center and is also managed by it (or by another Control Center).', 'pluginrx-control-center' ),
            ],
        ];
    } // End modes()


    /**
     * Check if the plugin is network-activated on a multisite network.
     *
     * @return bool
     */
    public static function is_network_active() : bool {
        if ( null !== self::$network_active ) {
            return self::$network_active;
        }

        if ( ! is_multisite() ) {
            self::$network_active = false;
            return false;
        }

        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        self::$network_active = is_plugin_active_for_network( Bootstrap::basename() );

        return self::$network_active;
    } // End is_network_active()


    /**
     * Get the network settings.
     *
     * @return array
     */
    public static function network() : array {
        $defaults = [
            'mode'          => '',
            'location'      => 'network',
            'subsites'      => [],
            'auto_include'  => 0,
            'setup_version' => '',
        ];

        $settings = get_site_option( 'prxctrl_network', [] );

        return array_merge( $defaults, is_array( $settings ) ? $settings : [] );
    } // End network()


    /**
     * Save the network settings.
     *
     * @param array $settings
     * @return void
     */
    public static function update_network( array $settings ) : void {
        $current = self::network();
        $location = $settings[ 'location' ] ?? $current[ 'location' ];

        $clean = [
            'mode'          => self::sanitize( (string) ( $settings[ 'mode' ] ?? $current[ 'mode' ] ) ),
            'location'      => 'network' === $location ? 'network' : absint( $location ),
            'subsites'      => array_values( array_filter( array_map( 'absint', (array) ( $settings[ 'subsites' ] ?? $current[ 'subsites' ] ) ) ) ),
            'auto_include'  => ! empty( $settings[ 'auto_include' ] ?? $current[ 'auto_include' ] ) ? 1 : 0,
            'setup_version' => sanitize_text_field( (string) ( $settings[ 'setup_version' ] ?? $current[ 'setup_version' ] ) ),
        ];

        update_site_option( 'prxctrl_network', $clean );
    } // End update_network()


    /**
     * Sanitize a mode value.
     *
     * @param string $mode
     * @return string
     */
    public static function sanitize( string $mode ) : string {
        $mode = sanitize_key( $mode );

        return in_array( $mode, [ self::HOST, self::AGENT, self::BOTH ], true ) ? $mode : '';
    } // End sanitize()


    /**
     * Get the current mode. Empty until setup is complete.
     *
     * @return string
     */
    public static function get() : string {
        if ( self::is_network_active() ) {
            return self::network()[ 'mode' ];
        }

        return self::sanitize( (string) get_option( 'prxctrl_mode', '' ) );
    } // End get()


    /**
     * Save the mode.
     *
     * @param string $mode
     * @return void
     */
    public static function set( string $mode ) : void {
        $mode = self::sanitize( $mode );

        if ( self::is_network_active() ) {
            self::update_network( [ 'mode' => $mode ] );
            return;
        }

        update_option( 'prxctrl_mode', $mode );
    } // End set()


    /**
     * Check if the mode includes the Control Center.
     *
     * @return bool
     */
    public static function has_host() : bool {
        return in_array( self::get(), [ self::HOST, self::BOTH ], true );
    } // End has_host()


    /**
     * Check if the mode includes the agent.
     *
     * @return bool
     */
    public static function has_agent() : bool {
        return in_array( self::get(), [ self::AGENT, self::BOTH ], true );
    } // End has_agent()


    /**
     * Check if the Control Center is located in the network admin.
     *
     * @return bool
     */
    public static function host_in_network_admin() : bool {
        return self::is_network_active() && 'network' === self::network()[ 'location' ];
    } // End host_in_network_admin()


    /**
     * Check if the current request works with network-level data: the network admin itself, or an
     * AJAX or admin-post request sent from it. The flag only chooses where data is stored; every
     * handler still checks the nonce and manage_network_options.
     *
     * @return bool
     */
    public static function is_network_context() : bool {
        if ( ! self::host_in_network_admin() ) {
            return false;
        }

        if ( is_network_admin() ) {
            return true;
        }

        $is_async = wp_doing_ajax() || ( isset( $GLOBALS[ 'pagenow' ] ) && 'admin-post.php' === $GLOBALS[ 'pagenow' ] );

        return $is_async && ! empty( $_REQUEST[ 'prxctrl_network' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- selects storage scope only; handlers verify nonces and capabilities.
    } // End is_network_context()


    /**
     * Check if the Control Center (host pages) should run in the current admin context.
     *
     * @return bool
     */
    public static function host_here() : bool {
        if ( ! self::has_host() ) {
            return false;
        }

        if ( ! self::is_network_active() ) {
            return true;
        }

        $location = self::network()[ 'location' ];

        if ( 'network' === $location ) {
            return self::is_network_context();
        }

        return ! is_network_admin() && get_current_blog_id() === (int) $location;
    } // End host_here()


    /**
     * Check if the current site acts as an agent in the current context.
     *
     * @return bool
     */
    public static function agent_here() : bool {
        return self::has_agent() && ! is_network_admin() && ! self::is_network_context();
    } // End agent_here()


    /**
     * Check if setup has been completed for the current version.
     *
     * @return bool
     */
    public static function setup_complete() : bool {
        $version = self::is_network_active() ? self::network()[ 'setup_version' ] : (string) get_option( 'prxctrl_setup_version', '' );

        return '' !== self::get() && '' !== $version && version_compare( $version, self::SETUP_VERSION, '>=' );
    } // End setup_complete()


    /**
     * Mark setup as complete.
     *
     * @return void
     */
    public static function complete_setup() : void {
        if ( self::is_network_active() ) {
            self::update_network( [ 'setup_version' => Bootstrap::version() ] );
            return;
        }

        update_option( 'prxctrl_setup_version', Bootstrap::version() );
    } // End complete_setup()


    /**
     * Check if the starter page belongs in the current admin context.
     *
     * @return bool
     */
    public static function setup_here() : bool {
        if ( self::setup_complete() ) {
            return false;
        }

        return self::is_network_active() ? is_network_admin() || self::is_network_context_raw() : true;
    } // End setup_here()


    /**
     * Network context check that does not depend on the host location, used before setup is complete.
     *
     * @return bool
     */
    public static function is_network_context_raw() : bool {
        if ( ! self::is_network_active() ) {
            return false;
        }

        if ( is_network_admin() ) {
            return true;
        }

        return wp_doing_ajax() && ! empty( $_REQUEST[ 'prxctrl_network' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- selects storage scope only; handlers verify nonces and capabilities.
    } // End is_network_context_raw()

} // End class Mode
