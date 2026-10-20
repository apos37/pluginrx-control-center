<?php
/**
 * LOGS
 *
 * Lists everything the plugin has done: site checks, actions, connection tests, remote logins and,
 * on an agent, every action and login requested by a Control Center.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Logs {

    /**
     * Nonce action.
     */
    public const NONCE = 'prxctrl_logs';


    /**
     * Allowed per-page values.
     */
    public const PER_PAGE = [ 20, 50, 100, 250 ];


    /**
     * @var Logs|null Singleton instance
     */
    private static ?Logs $instance = null;


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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'prxctrl_subheader_left', [ $this, 'render_subheader_left' ] );
        add_action( 'prxctrl_subheader_right', [ $this, 'render_subheader_right' ] );
        add_action( 'wp_ajax_prxctrl_clear_logs', [ $this, 'ajax_clear_logs' ] );
        add_action( 'admin_init', [ $this, 'process_bulk_action' ] );
    } // End __construct()


    /**
     * Delete the selected log entries, then reload without the bulk action in the URL.
     *
     * @return void
     */
    public function process_bulk_action() : void {
        if ( 'logs' !== Menu::current_key() || ! isset( $_GET[ '_wp_http_referer' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is checked below before anything changes.
            return;
        }

        $action = isset( $_GET[ 'action' ] ) ? sanitize_key( wp_unslash( $_GET[ 'action' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is checked below.

        if ( 'delete' !== $action && isset( $_GET[ 'action2' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is checked below.
            $action = sanitize_key( wp_unslash( $_GET[ 'action2' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is checked below.
        }

        if ( 'delete' === $action && ! isset( $_GET[ 'filter_action' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is checked below.
            check_admin_referer( 'bulk-logs' );

            if ( ! Access::can_manage() ) {
                wp_die( esc_html__( 'You do not have permission to do this.', 'pluginrx-control-center' ), 403 );
            }

            $ids = isset( $_GET[ 'log' ] ) ? array_map( 'absint', (array) wp_unslash( $_GET[ 'log' ] ) ) : [];

            Database::delete_logs( $ids );
        }

        $keep = [];

        foreach ( [ 'per_page', 'site', 'log_action', 'context', 'status', 's', 'orderby', 'order', 'paged' ] as $key ) {
            $value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters carried into a clean URL.

            if ( '' !== $value ) {
                $keep[ $key ] = $value;
            }
        }

        wp_safe_redirect( Helpers::page_url( 'logs', $keep ) );
        exit;
    } // End process_bulk_action()


    /**
     * Enqueue assets.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! Helpers::is_plugin_screen() || 'logs' !== Menu::current_key() ) {
            return;
        }

        $version = Bootstrap::script_version();

        wp_enqueue_style( 'prxctrl-logs', Bootstrap::url( 'inc/css/logs.css' ), [ 'prxctrl-theme' ], $version );
        wp_enqueue_script( 'prxctrl-logs', Bootstrap::url( 'inc/js/logs.js' ), [ 'jquery', 'prxctrl-common' ], $version, true );

        wp_localize_script( 'prxctrl-logs', 'prxctrlLogs', [
            'nonce' => wp_create_nonce( self::NONCE ),
            'i18n'  => [
                'confirmAll'     => __( 'Delete every log entry? This cannot be undone.', 'pluginrx-control-center' ),
                'confirmSuccess' => __( 'Delete every successful log entry? Errors are kept.', 'pluginrx-control-center' ),
                /* translators: %d: number of entries */
                'cleared'        => __( 'Deleted %d entries. Reloading...', 'pluginrx-control-center' ),
            ],
        ] );
    } // End enqueue()


    /**
     * Render the clear buttons.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_left( string $page ) : void {
        if ( 'logs' !== $page || ! Access::can_manage() ) {
            return;
        }
        ?>
        <button type="button" class="prxctrl-button prxctrl-clear-logs" data-status=""><?php esc_html_e( 'Clear All', 'pluginrx-control-center' ); ?></button>
        <button type="button" class="prxctrl-button prxctrl-clear-logs" data-status="success"><?php esc_html_e( 'Clear All Success', 'pluginrx-control-center' ); ?></button>
        <?php
    } // End render_subheader_left()


    /**
     * Render the search box.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_right( string $page ) : void {
        if ( 'logs' !== $page ) {
            return;
        }

        $search = isset( $_GET[ 's' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 's' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
        ?>
        <form method="get" class="prxctrl-log-search" role="search">
            <input type="hidden" name="page" value="<?php echo esc_attr( Helpers::PAGE_PREFIX . 'logs' ); ?>">
            <div class="prxctrl-search">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search logs...', 'pluginrx-control-center' ); ?>" aria-label="<?php esc_attr_e( 'Search logs', 'pluginrx-control-center' ); ?>">
            </div>
            <button type="submit" class="prxctrl-button"><?php esc_html_e( 'Search', 'pluginrx-control-center' ); ?></button>
            <?php if ( '' !== $search ) : ?>
                <a href="<?php echo esc_url( Helpers::page_url( 'logs' ) ); ?>" class="prxctrl-button prxctrl-button-secondary"><?php esc_html_e( 'Clear', 'pluginrx-control-center' ); ?></a>
            <?php endif; ?>
        </form>
        <?php
    } // End render_subheader_right()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_access() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'pluginrx-control-center' ) );
        }

        require_once Bootstrap::path( 'inc/logs-list-table.php' );

        $table = new LogsListTable();
        $table->prepare_items();
        ?>
        <div class="wrap prxctrl-wrap prxctrl-logs">
            <form method="get" class="prxctrl-list-container" id="prxctrl-logs-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( Helpers::PAGE_PREFIX . 'logs' ); ?>">
                <?php
                $table->views();
                $table->display();
                ?>
            </form>
        </div>
        <?php
    } // End render_page()


    /**
     * AJAX: clear logs.
     *
     * @return void
     */
    public function ajax_clear_logs() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax( true );

        $status = isset( $_POST[ 'status' ] ) ? sanitize_key( wp_unslash( $_POST[ 'status' ] ) ) : '';
        $status = in_array( $status, [ 'success', 'error' ], true ) ? $status : '';

        wp_send_json_success( [ 'deleted' => Database::clear_logs( $status ) ] );
    } // End ajax_clear_logs()

} // End class Logs


Logs::instance();
