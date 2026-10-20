<?php
/**
 * INTEGRATION: DEVELOPER DEBUG TOOLS
 *
 * Shares user counts and debug log details from Developer Debug Tools, and can clear the debug log.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class IntegrationDevDebugTools {

    /**
     * Debug log size that triggers a warning (3 MB).
     */
    public const LOG_SIZE_WARNING = 3145728;


    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxctrl_integrations', [ $this, 'register' ] );
    } // End __construct()


    /**
     * Register the integration.
     *
     * @param array $integrations
     * @return array
     */
    public function register( array $integrations ) : array {
        $integrations[ 'dev_debug_tools' ] = [
            'label'    => __( 'Developer Debug Tools', 'pluginrx-control-center' ),
            'plugin'   => 'dev-debug-tools/dev-debug-tools.php',
            'builtin'  => true,
            'requests' => [
                'dev_debug_tools_log_count'    => [
                    'label'       => __( 'Log Count', 'pluginrx-control-center' ),
                    'description' => __( 'Share the number of entries in the debug log.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'log_count' ],
                    'link'        => 'admin.php?page=dev-debug-tools&tool=logs',
                    'format'      => 'number',
                ],
                'dev_debug_tools_log_size'     => [
                    'label'       => __( 'Debug Log Size', 'pluginrx-control-center' ),
                    'description' => __( 'Share the size of the debug log file.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'log_size' ],
                    'link'        => 'admin.php?page=dev-debug-tools&tool=logs',
                    'format'      => 'filesize',
                    'warn'        => [ $this, 'warn_log_size' ],
                ],
                'dev_debug_tools_total_users'  => [
                    'label'       => __( 'Total Users', 'pluginrx-control-center' ),
                    'description' => __( 'Share the total number of user accounts.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'total_users' ],
                    'link'        => 'users.php',
                    'format'      => 'number',
                ],
                'dev_debug_tools_online_users' => [
                    'label'       => __( 'Online Users', 'pluginrx-control-center' ),
                    'description' => __( 'Share the number of users online now, as tracked by Developer Debug Tools.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'online_users' ],
                    'link'        => 'users.php',
                    'format'      => 'number',
                ],
            ],
            'actions'  => [
                'dev_debug_tools_clear_debug_log' => [
                    'label'       => __( 'Clear Debug Log', 'pluginrx-control-center' ),
                    'description' => __( 'Allow clearing the debug log.', 'pluginrx-control-center' ),
                    'waiting'     => __( 'Clearing log...', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'clear_debug_log' ],
                    'available'   => [ $this, 'can_clear' ],
                ],
            ],
        ];

        return $integrations;
    } // End register()


    /**
     * Count users.
     *
     * @return int
     */
    public function total_users() : int {
        $counts = count_users();

        return (int) ( $counts[ 'total_users' ] ?? 0 );
    } // End total_users()


    /**
     * Count online users.
     *
     * @return int
     */
    public function online_users() : int {
        if ( ! class_exists( '\Apos37\DevDebugTools\OnlineUsers' ) ) {
            return 0;
        }

        $users = ( new \Apos37\DevDebugTools\OnlineUsers() )->get_online_users();

        return is_array( $users ) ? count( $users ) : 0;
    } // End online_users()


    /**
     * Get the error count tracked by Developer Debug Tools.
     *
     * @return int
     */
    public function log_count() : int {
        return absint( get_option( 'ddtt_total_error_count', 0 ) );
    } // End log_count()


    /**
     * Get the debug log path.
     *
     * @return string
     */
    private function log_path() : string {
        $override = get_option( 'ddtt_debug_log_path' );

        if ( ! empty( $override ) && is_string( $override ) ) {
            return 0 === strpos( $override, '/' ) ? $override : ABSPATH . ltrim( $override, '/' );
        }

        if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
            return 0 === strpos( WP_DEBUG_LOG, '/' ) ? WP_DEBUG_LOG : ABSPATH . ltrim( WP_DEBUG_LOG, '/' );
        }

        return WP_CONTENT_DIR . '/debug.log';
    } // End log_path()


    /**
     * Get the debug log size in bytes.
     *
     * @return int
     */
    public function log_size() : int {
        $path = $this->log_path();

        return file_exists( $path ) ? (int) filesize( $path ) : 0;
    } // End log_size()


    /**
     * Warn when the debug log is large.
     *
     * @param mixed $value
     * @return bool
     */
    public function warn_log_size( $value ) : bool {
        return is_numeric( $value ) && $value > self::LOG_SIZE_WARNING;
    } // End warn_log_size()


    /**
     * The clear button is only useful when the log has something in it.
     *
     * @param object $site
     * @return bool
     */
    public function can_clear( object $site ) : bool {
        $size = Registry::value( $site, 'dev_debug_tools_log_size' );
        $count = Registry::value( $site, 'dev_debug_tools_log_count' );

        if ( null === $size && null === $count ) {
            return true;
        }

        return ( is_numeric( $size ) && $size > 0 ) || ( is_numeric( $count ) && $count > 0 );
    } // End can_clear()


    /**
     * Clear the debug log.
     *
     * @return array
     */
    public function clear_debug_log() : array {
        global $wp_filesystem;

        $path = $this->log_path();

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem ) {
            return [ 'success' => false, 'message' => __( 'The filesystem could not be accessed.', 'pluginrx-control-center' ) ];
        }

        if ( ! $wp_filesystem->exists( $path ) ) {
            return [ 'success' => true, 'message' => __( 'The debug log is already empty.', 'pluginrx-control-center' ) ];
        }

        if ( ! $wp_filesystem->put_contents( $path, '' ) ) {
            return [ 'success' => false, 'message' => __( 'The debug log could not be cleared.', 'pluginrx-control-center' ) ];
        }

        delete_option( 'ddtt_total_error_count' );
        set_transient( 'ddtt_log_cleared', true, 30 );

        return [ 'success' => true, 'message' => __( 'Debug log cleared.', 'pluginrx-control-center' ) ];
    } // End clear_debug_log()

} // End class IntegrationDevDebugTools


new IntegrationDevDebugTools();
