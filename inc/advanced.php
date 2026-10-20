<?php
/**
 * ADVANCED
 *
 * Download, upload and reset handlers for the Advanced panel. Exports never include API keys,
 * managed sites or the remote login account.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Advanced {

    /**
     * Export format version. Bump when the shape changes.
     */
    public const EXPORT_VERSION = 1;


    /**
     * Nonce action for the export form.
     */
    public const NONCE = 'prxctrl_export';


    /**
     * @var Advanced|null Singleton instance
     */
    private static ?Advanced $instance = null;


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
        add_action( 'admin_post_prxctrl_export', [ $this, 'handle_export' ] );
        add_action( 'wp_ajax_prxctrl_import_settings', [ $this, 'ajax_import' ] );
        add_action( 'wp_ajax_prxctrl_reset_settings', [ $this, 'ajax_reset' ] );
    } // End __construct()


    /**
     * Get the exportable keys for everything available in this context.
     *
     * @return array
     */
    private static function exportable_keys() : array {
        $keys = [];

        foreach ( [ 'host', 'agent' ] as $page ) {
            if ( SettingsUi::page_available( $page ) ) {
                $keys = array_merge( $keys, SettingsUi::page_keys( $page ) );
            }
        }

        return array_values( array_intersect( array_unique( $keys ), Options::keys( [ 'global', 'host', 'agent' ], true ) ) );
    } // End exportable_keys()


    /**
     * admin-post: download the settings as JSON.
     *
     * @return void
     */
    public function handle_export() : void {
        check_admin_referer( self::NONCE );

        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'pluginrx-control-center' ), 403 );
        }

        $settings = [];

        foreach ( self::exportable_keys() as $key ) {
            $settings[ $key ] = Options::get( $key );
        }

        $payload = [
            'export_version' => self::EXPORT_VERSION,
            'plugin'         => Bootstrap::textdomain(),
            'plugin_version' => Bootstrap::version(),
            'exported_at'    => gmdate( 'c' ),
            'settings'       => $settings,
        ];

        nocache_headers();
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'prxctrl-settings-' . Helpers::host( home_url() ) . '.json' ) . '"' );

        echo wp_json_encode( $payload, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download.
        exit;
    } // End handle_export()


    /**
     * AJAX: upload settings.
     *
     * @return void
     */
    public function ajax_import() : void {
        check_ajax_referer( SettingsUi::NONCE, 'nonce' );
        Access::require_ajax( true );

        $raw = isset( $_POST[ 'payload' ] ) ? wp_unslash( $_POST[ 'payload' ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and every value is sanitized by Options::update().
        $data = ( is_string( $raw ) && strlen( $raw ) < MB_IN_BYTES ) ? json_decode( $raw, true ) : null;

        if ( ! is_array( $data ) || ! isset( $data[ 'export_version' ], $data[ 'settings' ] ) || Bootstrap::textdomain() !== ( $data[ 'plugin' ] ?? '' ) ) {
            wp_send_json_error( [ 'message' => __( 'That file is not a valid settings export.', 'pluginrx-control-center' ) ] );
        }

        if ( (int) $data[ 'export_version' ] > self::EXPORT_VERSION ) {
            wp_send_json_error( [ 'message' => __( 'That file was exported by a newer version of the plugin. Update this site first.', 'pluginrx-control-center' ) ] );
        }

        $count = 0;

        foreach ( self::exportable_keys() as $key ) {
            if ( array_key_exists( $key, (array) $data[ 'settings' ] ) ) {
                Options::update( $key, $data[ 'settings' ][ $key ] );
                $count++;
            }
        }

        Registry::flush();

        wp_send_json_success( [ 'count' => $count ] );
    } // End ajax_import()


    /**
     * AJAX: reset the settings on one page.
     *
     * @return void
     */
    public function ajax_reset() : void {
        check_ajax_referer( SettingsUi::NONCE, 'nonce' );
        Access::require_ajax( true );

        $page = isset( $_POST[ 'page' ] ) ? sanitize_key( wp_unslash( $_POST[ 'page' ] ) ) : '';

        if ( ! in_array( $page, [ 'host', 'agent' ], true ) || ! SettingsUi::page_available( $page ) ) {
            wp_send_json_error( [ 'message' => __( 'This settings page is not available.', 'pluginrx-control-center' ) ] );
        }

        $keep = [ 'agent_api_key', 'agent_login_user' ];

        foreach ( SettingsUi::page_keys( $page ) as $key ) {
            if ( ! in_array( $key, $keep, true ) ) {
                Options::delete( $key );
            }
        }

        Registry::flush();

        wp_send_json_success();
    } // End ajax_reset()

} // End class Advanced


Advanced::instance();
