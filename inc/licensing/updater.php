<?php
/**
 * Checking for updates
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Updater {

    private $args;
    private $validator;
    private $svn_path;
    private $license_key;
    private $cache_key;
    

    /**
     * Constructor
     */
    public function __construct( $args, $validator ) {

        $this->args = $args;
        $this->validator = $validator;
        $this->svn_path = $args[ 'author_uri' ] . 'wp-content/svn/' . $this->args[ 'text_domain' ] . '/';
        $this->license_key = $validator->license_key;
        $this->cache_key = $args[ 'prefix' ] . '_update_check';

        // Hooks for update checking
        add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'pre_check' ] ); // phpcs:ignore
        add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
        add_filter( 'site_transient_update_plugins', [ $this, 'filter_plugin_updates' ] );

    } // End __construct()


    /**
     * Make the request to the update server
     *
     * @return object|false
     */
    private function request() {
        $transient = get_transient( $this->cache_key );
        $now = time();

        $cache_expire = 15 * MINUTE_IN_SECONDS; // short-lived cache
        $cache_stale  = 10 * MINUTE_IN_SECONDS; // treat stale if older than this

        if ( false === $transient || ! isset( $transient->fetched_at ) || ( $now - $transient->fetched_at ) > $cache_stale ) {

            $info_path = $this->args[ 'author_uri' ] . 'wp-content/svn/info.php?plugin=' . $this->args[ 'text_domain' ] . '&license=' . $this->license_key . '&site=' . home_url();
            $remote = wp_remote_get( $info_path, [
                'timeout' => 15,
                'headers' => [ 'Accept' => 'application/json' ]
            ] );

            if ( is_wp_error( $remote ) ) {
                error_log( $this->args[ 'name' ] . ': WP_Error fetching update info: ' . $remote->get_error_message(), 0 ); // phpcs:ignore
                return false;
            }

            $status_code = wp_remote_retrieve_response_code( $remote );
            if ( 200 !== $status_code ) {
                error_log( $this->args[ 'name' ] . ': HTTP ' . $status_code . ' fetching update info', 0 ); // phpcs:ignore
                return false;
            }

            $body = wp_remote_retrieve_body( $remote );
            $decoded = json_decode( $body );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                error_log( $this->args[ 'name' ] . ': JSON decode error: ' . json_last_error_msg(), 0 ); // phpcs:ignore
                return false;
            }

            // Add fetched timestamp
            $decoded->fetched_at = $now;

            // Cache for 15 minutes
            set_transient( $this->cache_key, $decoded, $cache_expire );

            return $decoded;
        }

        return $transient;
    } // End request()


    /**
     * Get plugin info (for the "View Details" link on the update page)
     *
     * @param false|object|array $res
     * @param string $action
     * @param object $args
     * @return object
     */
    public function info( $res, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $res;
        }

        if ( $this->args[ 'text_domain' ] !== $args->slug ) {
            return $res;
        }

        $remote = $this->request();
        if ( ! $remote ) {
            return $res;
        }

        return $this->prepare( $remote );
    } // End info()


    /**
     * Prepare the object
     *
     * @param object|false $remote
     * @return object
     */
    public function prepare( $remote ) {
        if ( ! $remote ) {
            return false;
        }

        $tags_path = $this->svn_path . 'tags/';
        $assets_path = $this->svn_path . 'assets/';

        $res = new \stdClass();
        $res->id = 'wpe/plugins/' . $this->args[ 'text_domain' ];
        $res->name = $remote->name;
        $res->slug = $this->args[ 'text_domain' ];
        $res->plugin = $this->args[ 'basename' ];
        $res->new_version = $remote->version;
        $res->url = $this->args[ 'plugin_uri' ];
        $res->package = $tags_path . $this->args[ 'text_domain' ] . '.' . $remote->version . '.zip';
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->requires_php = $remote->requires_php;
        $res->last_updated = $remote->last_updated;

        $res->sections = [
            'description' => $remote->sections->description,
            'changelog' => $remote->sections->changelog,
            'installation' => $remote->sections->installation,
        ];

        $res->icons = [
            '1x' => $assets_path . 'icon-128x128.png',
            '2x' => $assets_path . 'icon-256x256.png'
        ];
        $res->banners = [
            'low' => $assets_path . 'banner-772x250.png',
            'high' => $assets_path . 'banner-1544x500.png'
        ];

        return $res;
    } // End prepare()


    /**
     * Check for plugin updates
     *
     * @param object $transient
     * @return object
     */
    public function pre_check( $transient ) {
        // Block updates if license is invalid
        if ( ! $this->validator->has_valid_license() ) {
            if ( $this->license_key ) {
                add_action( 'admin_notices', [ $this, 'license_update_notice' ] );
            }

            if ( isset( $transient->response[ $this->args[ 'basename' ] ] ) ) {
                unset( $transient->response[ $this->args[ 'basename' ] ] );
            }

            return $transient;
        }

        $remote = $this->request();
        if ( ! $remote ) {
            error_log( $this->args[ 'name' ] . ': No remote update info received.', 0 ); // phpcs:ignore
            return $transient;
        }

        // Compare versions and requirements
        $current_version = $this->args[ 'version' ];
        $remote_version  = $remote->version ?? '';
        $requires_wp     = $remote->requires ?? '0.0';
        $requires_php    = $remote->requires_php ?? '0.0';

        if ( version_compare( $current_version, $remote_version, '<' ) &&
            version_compare( get_bloginfo( 'version' ), $requires_wp, '>=' ) &&
            version_compare( PHP_VERSION, $requires_php, '>=' ) ) {

            $transient->response[ $this->args[ 'basename' ] ] = $this->prepare( $remote );
            // error_log( $this->args[ 'name' ] . ': Update available: ' . $remote_version, 0 ); // phpcs:ignore

        }
        return $transient;
    } // End pre_check()


    /**
     * License update notice
     *
     * @return void
     */
    public function license_update_notice() {
        echo '<div class="notice notice-error"><p>' . wp_kses( sprintf( __( 'Plugin updates are disabled because your %1$s license is invalid. Please enter a valid license key in the <a href="%2$s">settings</a>.', 'pluginrx-control-center' ),
                esc_html( $this->args[ 'name' ] ),
                esc_url( $this->args[ 'settings_url' ] )
            ), [ 'a' => [ 'href' => [], 'target' => [] ] ] ) . '</p></div>';
    } // license_update_notice()


    /**
     * Purge the cache after a successful plugin update
     *
     * @param [type] $upgrader
     * @param array $options
     * @return void
     */
    public function purge( $upgrader, $options ) {
        if ( ! isset( $options[ 'action' ], $options[ 'type' ] ) ) {
            return;
        }

        if ( $options[ 'action' ] !== 'update' || $options[ 'type' ] !== 'plugin' ) {
            return;
        }

        delete_transient( $this->cache_key );
    } // End purge()


    /**
     * Filter plugin updates to prevent stale update notifications
     *
     * @param object $transient
     * @return object
     */
    public function filter_plugin_updates( $transient ) {
        if ( isset( $transient->response[ $this->args['basename'] ] ) ) {
            $remote_version = $transient->response[ $this->args['basename'] ]->new_version ?? '0.0';
            $current_version = $this->args['version'];

            // Only remove if current version is >= remote version
            if ( version_compare( $current_version, $remote_version, '>=' ) ) {
                unset( $transient->response[ $this->args['basename'] ] );
            }
        }
        return $transient;
    } // End filter_plugin_updates()

}