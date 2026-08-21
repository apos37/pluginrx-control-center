<?php
/**
 * Plugin Name:         PluginRx Control Center
 * Plugin URI:          https://pluginrx.com/plugin/pluginrx-control-center/
 * Description:         Centralized management and monitoring for multiple WordPress sites using the PluginRx Agent.
 * Version:             1.1.0
 * Requires at least:   6.0
 * Tested up to:        7.1
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         pluginrx-control-center
 * License:             Proprietary
 * License URI:         https://pluginrx.com/proprietary-license-agreement/
 * Created on:          January 5, 2026
 * Premium:             true
 */


namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * BOOTSTRAP
 *
 * Loads plugin metadata, performs environment checks, and initializes the plugin.
 */
final class Bootstrap {

    /**
     * Plugin files to load.
     *
     * This array contains the paths to all plugin files that need to be included.
     */
    public const FILES = [
        'helpers.php',
        'menu.php',
        'plugin-page.php',
        'db.php',
        'role.php',
        'settings.php',
        'remote.php',
        'dashboard.php',
        'logs-list-table.php',
        'logs.php',
    ];


    /**
     * Plugin header keys for get_file_data()
     */
    public const HEADER_KEYS = [
        'name'         => 'Plugin Name',
        'description'  => 'Description',
        'version'      => 'Version',
        'plugin_uri'   => 'Plugin URI',
        'requires_php' => 'Requires PHP',
        'textdomain'   => 'Text Domain',
        'author'       => 'Author',
        'author_uri'   => 'Author URI',
        'discord_uri'  => 'Discord URI'
    ];


    /**
     * @var array Plugin metadata from file header
     */
    private array $meta;


    /**
     * @var Bootstrap|null Singleton instance
     */
    private static ?Bootstrap $instance = null;


    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        $this->meta = $this->load_meta();
        $this->check_environment();
        add_action( 'plugins_loaded', [ $this, 'load_files' ] );
        add_action( 'plugins_loaded', [ $this, 'check_for_updates' ] );
    } // End __construct()


    /**
     * Check if current user has access
     *
     * @return bool
     */
    public static function has_access() : bool {
        $prxctrl_users = array_map( 'intval', get_users( [ 'role' => 'prxctrl', 'fields' => 'ID' ] ) );
        if ( ! empty( $prxctrl_users ) ) {
            return in_array( get_current_user_id(), $prxctrl_users, true );
        } elseif ( is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' ) ) {
            return \Apos37\DevDebugTools\Helpers::has_access();
        } else {
            return current_user_can( 'administrator' );
        }
    } // End has_access()


    /**
     * Check if test mode is enabled
     *
     * @return bool
     */
    public static function is_test_mode() : bool {
        return filter_var( get_option( 'ddtt_test_mode' ), FILTER_VALIDATE_BOOLEAN );
    } // End is_test_mode()


    /**
     * Load plugin metadata
     *
     * @return array
     */
    private function load_meta() : array {
        return get_file_data( __FILE__, self::HEADER_KEYS );
    } // End load_meta()


    /**
     * Check environment requirements
     *
     * @return void
     */
    private function check_environment() : void {
        if ( version_compare( PHP_VERSION, $this->meta[ 'requires_php' ], '<' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( sprintf(
                /* translators: %1$s is plugin name, %2$s is required PHP version */
                esc_html( __( '%1$s requires PHP %2$s or higher.', 'dev-debug-tools' ) ),
                esc_html( $this->meta[ 'name' ] ),
                esc_html( $this->meta[ 'requires_php' ] )
            ) );
        }
    } // End check_environment()


    /**
     * Check for plugin updates and initialize the updater
     */
    public function check_for_updates() : void {
        require_once __DIR__ . '/inc/updater.php';

        $args = [
            'name'        => $this->meta[ 'name' ],
            'text_domain' => $this->meta[ 'textdomain' ],
            'basename'    => self::basename(),
            'version'     => $this->meta[ 'version' ],
            'author_uri'  => $this->meta[ 'author_uri' ],
            'plugin_uri'  => $this->meta[ 'plugin_uri' ],
            'prefix'      => 'prxctrl',
        ];

        new Updater( $args );
    } // End check_for_updates()


    /**
     * Load all required plugin files
     *
     * @return void
     */
    public function load_files() : void {
        // Automatically load all PHP files in /inc/_integrations/
        $integration_files = glob( __DIR__ . '/inc/_integrations/*.php' );
        if ( ! empty( $integration_files ) ) {
            foreach ( $integration_files as $file_path ) {
                require_once $file_path;
            }
        }

        // Load other required files
        foreach ( self::FILES as $file ) {

            $file_path = __DIR__ . '/inc/' . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            } else {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf( 'File not found: %s', esc_html( $file_path ) ),
                    esc_html( $this->version() )
                );
            }
        }
    } // End load_files()


    /**
     * Get admin URL
     *
     * @param string $path
     * @param string $scheme
     * @return string
     */
    public static function admin_url( $path = '', $scheme = 'admin' ) {
         return is_network_admin() ? network_admin_url( $path, $scheme ) : admin_url( $path, $scheme );
    } // End admin_url()


    /**
     * Get metadata value
     *
     * @param string $key
     * @return string
     */
    public static function meta( string $key ) : string {
        return self::$instance->meta[ $key ] ?? '';
    } // End meta()


    /**
     * Get plugin basename
     *
     * @return string
     */
    public static function basename() : string {
        return plugin_basename( __FILE__ );
    } // End basename()


    /**
     * Get plugin URL
     *
     * @param string $append
     * @return string
     */
    public static function url( string $append = '' ) : string {
        return plugin_dir_url( __FILE__ ) . ltrim( $append, '/' );
    } // End url()


    /**
     * Get plugin path
     *
     * @param string $append
     * @return string
     */
    public static function path( string $append = '' ) : string {
        return plugin_dir_path( __FILE__ ) . ltrim( $append, '/' );
    } // End path()


    /**
     * Get the dashboard URL
     *
     * @return string
     */
    public static function dashboard_url() : string {
        return self::admin_url( 'admin.php?page=prxctrl-dashboard' );
    } // End dashboard_url()


    /**
     * Get the settings URL
     *
     * @return string
     */
    public static function settings_url() : string {
        return self::admin_url( 'admin.php?page=prxctrl-settings' );
    } // End settings_url()


    /**
     * Get plugin name
     *
     * @return string
     */
    public static function name() : string {
        return self::meta( 'name' );
    } // End name()


    /**
     * Get plugin version
     *
     * @return string
     */
    public static function version() : string {
        return self::meta( 'version' );
    } // End version()


    /**
     * Get script/style version for cache busting.
     * Returns timestamp if TEST_MODE is enabled, otherwise plugin version.
     *
     * @return string
     */
    public static function script_version() : string {
        if ( self::is_test_mode() ) {
            return 'TEST-' . time();
        }
        return self::version();
    } // End script_version()


    /**
     * Get plugin text domain
     *
     * @return string
     */
    public static function textdomain() : string {
        return self::meta( 'textdomain' );
    } // End textdomain()


    /**
     * Get plugin author
     *
     * @return string
     */
    public static function author() : string {
        return self::meta( 'author' );
    } // End author()


    /**
     * Get plugin URI
     *
     * @return string
     */
    public static function plugin_uri() : string {
        return self::meta( 'plugin_uri' );
    } // End plugin_uri()


    /**
     * Get author URI
     *
     * @return string
     */
    public static function author_uri() : string {
        return self::meta( 'author_uri' );
    } // End author_uri()


    /**
     * Get Discord URI
     *
     * @return string
     */
    public static function discord_uri() : string {
        return self::meta( 'discord_uri' );
    } // End discord_uri()


    /**
     * Prevent cloning and unserializing
     */
    public function __clone() {}
    public function __wakeup() {}

} // End Bootstrap


Bootstrap::instance();