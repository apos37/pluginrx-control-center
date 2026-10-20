<?php
/**
 * INTEGRATION: CLEAR CACHE EVERYWHERE
 *
 * Shares the hosting purge URL set in Clear Cache Everywhere, and can clear every cache it knows about.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class IntegrationClearCacheEverywhere {

    /**
     * Request key.
     */
    public const URL_KEY = 'clear_cache_everywhere_hosting_url';


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
        $integrations[ 'clear_cache_everywhere' ] = [
            'label'    => __( 'Clear Cache Everywhere', 'pluginrx-control-center' ),
            'plugin'   => 'clear-cache-everywhere/clear-cache-everywhere.php',
            'builtin'  => true,
            'requests' => [
                self::URL_KEY => [
                    'label'       => __( 'Host Cache URL', 'pluginrx-control-center' ),
                    'description' => __( 'Share the hosting cache purge URL set in Clear Cache Everywhere.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'hosting_url' ],
                    'format'      => 'text',
                ],
            ],
            'actions'  => [
                'clear_cache_everywhere' => [
                    'label'       => __( 'Clear Cache', 'pluginrx-control-center' ),
                    'description' => __( 'Allow clearing every cache Clear Cache Everywhere knows about.', 'pluginrx-control-center' ),
                    'waiting'     => __( 'Clearing cache...', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'clear' ],
                ],
            ],
        ];

        return $integrations;
    } // End register()


    /**
     * Get the hosting purge URL.
     *
     * @return string
     */
    public function hosting_url() : string {
        return esc_url_raw( (string) get_option( 'clear_cache_everywhere_hosting_purge_url', '' ) );
    } // End hosting_url()


    /**
     * Clear every cache.
     *
     * @return array
     */
    public function clear() : array {
        $class_file = WP_PLUGIN_DIR . '/clear-cache-everywhere/includes/clear-cache.php';

        if ( ! class_exists( '\Apos37\ClearCache\Clear' ) && file_exists( $class_file ) ) {
            include_once $class_file;
        }

        if ( ! class_exists( '\Apos37\ClearCache\Clear' ) ) {
            return [ 'success' => false, 'message' => __( 'Clear Cache Everywhere is not installed on this site.', 'pluginrx-control-center' ) ];
        }

        $results = ( new \Apos37\ClearCache\Clear() )->clear_all( true, false );

        if ( ! is_array( $results ) ) {
            return [ 'success' => false, 'message' => __( 'Clear Cache Everywhere returned an unexpected response.', 'pluginrx-control-center' ) ];
        }

        $lines = [];

        foreach ( $results as $item ) {
            if ( is_array( $item ) && ! empty( $item[ 'title' ] ) && isset( $item[ 'result' ] ) ) {
                $lines[] = wp_strip_all_tags( (string) $item[ 'title' ] ) . ': ' . ( is_string( $item[ 'result' ] ) ? ucfirst( wp_strip_all_tags( $item[ 'result' ] ) ) : __( 'Unknown', 'pluginrx-control-center' ) );
            }
        }

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %s: list of caches and their results */
                __( 'Cache cleared: %s', 'pluginrx-control-center' ),
                implode( ', ', $lines )
            ),
        ];
    } // End clear()

} // End class IntegrationClearCacheEverywhere


new IntegrationClearCacheEverywhere();
