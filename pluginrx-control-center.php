<?php
/**
 * Plugin Name:         PluginRx Control Center
 * Plugin URI:          https://pluginrx.com/plugin/pluginrx-control-center/
 * Description:         Manage and monitor all of your WordPress sites from one dashboard. Install it on every site, then choose whether each site is a Host, an Agent, or Both.
 * Version:             2.0.0.1
 * Requires at least:   6.2
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         pluginrx-control-center
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Created on:          January 5, 2026
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
     * Files loaded on every request. Each one only registers lightweight hooks.
     */
    public const CORE_FILES = [
        'helpers.php',
        'options.php',
        'access.php',
        'mode.php',
        'db.php',
        'registry.php',
        'signer.php',
        'upgrade.php',
        'cron.php',
        'agent/requests.php',
        'agent/actions.php',
        'agent/agent.php',
        'agent/login.php',
    ];


    /**
     * Files loaded only in the admin area (which includes admin-ajax.php and admin-post.php).
     */
    public const ADMIN_FILES = [
        'menu.php',
        'dynamic-css.php',
        'plugin-page.php',
        'settings-ui.php',
        'advanced.php',
        'starter.php',
        'site-card.php',
        'dashboard.php',
        'logs.php',
        'host/client.php',
        'host/sites.php',
        'host/host-settings.php',
        'agent/agent-settings.php',
    ];


    /**
     * Built-in integrations. Each file registers its definitions on the prxctrl_integrations filter.
     */
    public const INTEGRATION_FILES = [
        'broken-link-notifier.php',
        'clear-cache-everywhere.php',
        'dev-debug-tools.php',
        'fake-user-detector.php',
        'gf-tools.php',
        'wp-mail-logging.php',
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
        'discord_uri'  => 'Discord URI',
    ];


    /**
     * @var array Plugin metadata from file header
     */
    private array $meta;


    /**
     * @var bool Whether the core files have been loaded
     */
    private static bool $core_loaded = false;


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
        $this->meta = get_file_data( __FILE__, self::HEADER_KEYS );

        if ( version_compare( PHP_VERSION, $this->meta[ 'requires_php' ], '<' ) ) {
            add_action( 'admin_notices', [ $this, 'php_version_notice' ] );
            return;
        }

        add_action( 'plugins_loaded', [ $this, 'load_files' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    } // End __construct()


    /**
     * Show a notice when the server's PHP version is too old.
     *
     * @return void
     */
    public function php_version_notice() : void {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: 1: plugin name, 2: required PHP version */
                    esc_html__( '%1$s requires PHP %2$s or higher. The plugin is not running.', 'pluginrx-control-center' ),
                    esc_html( $this->meta[ 'name' ] ),
                    esc_html( $this->meta[ 'requires_php' ] )
                );
                ?>
            </p>
        </div>
        <?php
    } // End php_version_notice()


    /**
     * Load the core files once.
     *
     * @return void
     */
    public static function load_core() : void {
        if ( self::$core_loaded ) {
            return;
        }

        self::$core_loaded = true;

        foreach ( self::CORE_FILES as $file ) {
            require_once __DIR__ . '/inc/' . $file;
        }

        foreach ( self::INTEGRATION_FILES as $file ) {
            require_once __DIR__ . '/inc/integrations/' . $file;
        }
    } // End load_core()


    /**
     * Load all required plugin files.
     *
     * @return void
     */
    public function load_files() : void {
        self::load_core();

        if ( ! is_admin() ) {
            return;
        }

        foreach ( self::ADMIN_FILES as $file ) {
            require_once __DIR__ . '/inc/' . $file;
        }
    } // End load_files()


    /**
     * Activation: create tables, migrate older installs and hand off from the legacy Agent plugin.
     *
     * @param bool $network_wide
     * @return void
     */
    public function activate( $network_wide = false ) : void {
        self::load_core();
        Upgrade::activate( (bool) $network_wide );
    } // End activate()


    /**
     * Deactivation: clear scheduled events.
     *
     * @return void
     */
    public function deactivate() : void {
        self::load_core();
        Cron::unschedule();
    } // End deactivate()


    /**
     * Get the main plugin file path.
     *
     * @return string
     */
    public static function file() : string {
        return __FILE__;
    } // End file()


    /**
     * Get a meta value
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
     * Check if test mode is enabled (Developer Debug Tools' test mode).
     *
     * @return bool
     */
    public static function is_test_mode() : bool {
        return filter_var( apply_filters( 'prxctrl_test_mode', get_option( 'ddtt_test_mode' ) ), FILTER_VALIDATE_BOOLEAN );
    } // End is_test_mode()


    /**
     * Get the script and style version for cache busting. Uses a timestamp in test mode so changes
     * show right away.
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

} // End class Bootstrap


Bootstrap::instance();
