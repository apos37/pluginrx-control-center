<?php
/**
 * UPGRADE
 *
 * Creates tables, migrates data from version 1.x, and takes over from the separate PluginRx Agent
 * plugin: its settings are copied over (so the API key and permissions stay the same) and it is
 * deactivated so the two plugins never answer the same requests.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Upgrade {

    /**
     * Legacy agent plugin basename.
     */
    public const LEGACY_AGENT = 'pluginrx-agent/pluginrx-agent.php';


    /**
     * @var Upgrade|null Singleton instance
     */
    private static ?Upgrade $instance = null;


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
        add_action( 'admin_init', [ $this, 'maybe_upgrade' ], 1 );
        add_action( 'admin_notices', [ $this, 'legacy_notice' ] );
        add_action( 'network_admin_notices', [ $this, 'legacy_notice' ] );
        add_action( 'wp_initialize_site', [ $this, 'include_new_subsite' ], 20 );
    } // End __construct()


    /**
     * Add a new subsite to the network Control Center when "add new subsites automatically" is on.
     *
     * @param \WP_Site $site
     * @return void
     */
    public function include_new_subsite( $site ) : void {
        $settings = Mode::network();

        if ( ! Mode::is_network_active() || ! Mode::has_host() || 'network' !== $settings[ 'location' ] || empty( $settings[ 'auto_include' ] ) || ! Database::table_exists_network( 'sites' ) ) {
            return;
        }

        $url = Helpers::normalize_url( get_home_url( (int) $site->blog_id ) );

        if ( '' === $url ) {
            return;
        }

        Database::insert_site( [
            'site_url'        => $url,
            'site_name'       => (string) get_blog_option( (int) $site->blog_id, 'blogname' ),
            'connection_type' => 'local',
            'local_blog_id'   => (int) $site->blog_id,
            'protocol'        => 'local',
            'sort_order'      => 9999,
        ], true );
    } // End include_new_subsite()


    /**
     * Run on activation.
     *
     * @param bool $network_wide
     * @return void
     */
    public static function activate( bool $network_wide ) : void {
        self::take_over_legacy_agent();

        if ( ! $network_wide ) {
            self::upgrade_site();
            set_transient( 'prxctrl_activation_redirect', 1, 60 );
        }
    } // End activate()


    /**
     * Run any pending upgrade for the current context.
     *
     * @return void
     */
    public function maybe_upgrade() : void {
        self::take_over_legacy_agent();

        if ( Mode::is_network_context() ) {
            Database::maybe_install();
            return;
        }

        if ( is_network_admin() ) {
            return;
        }

        self::upgrade_site();
    } // End maybe_upgrade()


    /**
     * Upgrade the current site's tables and data.
     *
     * @return void
     */
    private static function upgrade_site() : void {
        $installed = (string) get_option( 'prxctrl_db_version', '' );

        if ( version_compare( $installed, Database::VERSION, '>=' ) ) {
            return;
        }

        $has_v1_data = '' === $installed && Database::table_exists( 'sites' );

        Database::install();

        if ( $has_v1_data ) {
            self::migrate_v1();
        }
    } // End upgrade_site()


    /**
     * Check if the legacy agent plugin is active on this site or the network.
     *
     * @return bool
     */
    public static function legacy_agent_active() : bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( self::LEGACY_AGENT );
    } // End legacy_agent_active()


    /**
     * Copy the legacy agent's settings and deactivate it.
     *
     * @return void
     */
    public static function take_over_legacy_agent() : void {
        if ( ! self::legacy_agent_active() ) {
            return;
        }

        self::import_legacy_agent_settings();

        $network_wide = is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( self::LEGACY_AGENT );

        deactivate_plugins( self::LEGACY_AGENT, false, $network_wide );

        update_option( 'prxctrl_legacy_notice', 1, false );
    } // End take_over_legacy_agent()


    /**
     * Copy the legacy agent's settings, unless this plugin already has its own.
     *
     * @return void
     */
    private static function import_legacy_agent_settings() : void {
        $legacy_key = get_option( 'prxagnt_api_key', null );

        if ( null === $legacy_key || null !== get_option( Options::PREFIX . 'agent_api_key', null ) ) {
            return;
        }

        Options::update( 'agent_api_key', (string) $legacy_key );
        Options::update( 'agent_remote_access', 'yes' === get_option( 'prxagnt_remote_access', 'no' ) );
        Options::update( 'agent_domains', (string) get_option( 'prxagnt_remote_domains', '' ) );

        $permissions = get_option( 'prxagnt_permissions', null );

        if ( is_array( $permissions ) ) {
            if ( in_array( 'wordpress_version', $permissions, true ) && ! in_array( 'core_update', $permissions, true ) ) {
                $permissions[] = 'core_update';
            }

            Options::update( 'agent_permissions', $permissions );
        }

        if ( ! Mode::is_network_active() && '' === Mode::get() ) {
            Mode::set( Database::table_exists( 'sites' ) && Database::count_sites() > 0 ? Mode::BOTH : Mode::AGENT );
        }
    } // End import_legacy_agent_settings()


    /**
     * Migrate a 1.x Control Center install.
     *
     * @return void
     */
    private static function migrate_v1() : void {
        global $wpdb;

        $sites_table = Database::table( 'sites' );
        $order = array_values( array_filter( array_map( 'absint', (array) get_option( 'prxctrl_settings_sorting', [] ) ) ) );

        foreach ( $order as $position => $site_id ) {
            $wpdb->update( $sites_table, [ 'sort_order' => $position + 1 ], [ 'id' => $site_id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration.
        }

        $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'error' WHERE last_error IS NOT NULL AND last_error <> ''", $sites_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration.
        $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'ok' WHERE status = '' AND last_checked IS NOT NULL", $sites_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration.

        $actions_table = Database::table( 'actions' );
        $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'error' WHERE context LIKE %s", $actions_table, '%error%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration.
        $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'success' WHERE status = ''", $actions_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration.

        foreach ( [ 'integrations', 'admin_users' ] as $table ) {
            $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Database::table( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration; these caches used an old format and refill on the next check.
        }

        $was_custom = 'same_as_settings' === get_option( 'prxctrl_dashboard_sorting', 'same_as_settings' ) && ! empty( $order );

        Options::update( 'sorting', $was_custom ? 'custom' : 'alpha' );
        Options::update( 'jump_menu', 'yes' === get_option( 'prxctrl_dashboard_toc', 'no' ) );
        Options::update( 'warn_admin_email', 'no' !== get_option( 'prxctrl_dashboard_warn_admin_email', 'yes' ) );
        Options::update( 'wp_warning', 'yes' === get_option( 'prxctrl_dashboard_allow_prev_wp_outdated', 'no' ) ? 'previous' : 'exact' );
        Options::update( 'php_warning', 'yes' === get_option( 'prxctrl_dashboard_allow_prev_php_outdated', 'no' ) ? 'previous' : 'minor' );
        Options::update( 'console_log', 'yes' === get_option( 'prxctrl_console_log', 'no' ) );
        Options::update( 'delete_on_uninstall', 'yes' === get_option( 'prxctrl_delete_db', 'no' ) );
        Options::update( 'timeout', get_option( 'prxctrl_timeout', 15 ) );
        Options::update( 'log_retention', 0 );

        $old_options = [
            'settings_sorting',
            'site_order',
            'dashboard_sorting',
            'dashboard_site_path',
            'dashboard_toc',
            'dashboard_warn_admin_email',
            'dashboard_allow_prev_wp_outdated',
            'dashboard_allow_prev_php_outdated',
            'delete_db',
        ];

        foreach ( $old_options as $option ) {
            delete_option( Options::PREFIX . $option );
        }

        delete_transient( 'prxcntrl_latest_versions' );
        remove_role( 'prxctrl' );

        if ( '' === Mode::get() ) {
            Mode::set( Mode::HOST );
        }

        Database::log( 0, 'migration', true, 'upgrade', __( 'Upgraded from version 1.x. Check all sites to refresh admin users and integration data.', 'pluginrx-control-center' ) );
    } // End migrate_v1()


    /**
     * Tell the user the legacy agent plugin was deactivated, once.
     *
     * @return void
     */
    public function legacy_notice() : void {
        if ( ! get_option( 'prxctrl_legacy_notice' ) || ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        delete_option( 'prxctrl_legacy_notice' );
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %s: plugin name */
                    esc_html__( 'PluginRx Agent has been deactivated because %s now includes the agent. Its API key, domains and permissions were copied over, so your Control Center connection keeps working. You can delete PluginRx Agent.', 'pluginrx-control-center' ),
                    esc_html( Bootstrap::name() )
                );
                ?>
            </p>
        </div>
        <?php
    } // End legacy_notice()

} // End class Upgrade


Upgrade::instance();
