<?php
/**
 * Clear Cache Everywhere Integration
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class ClearCacheEverywhereController {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxcntrl_integration_requests', [ $this, 'integration_requests' ], 10, 3 );
        add_filter( 'prxcntrl_integration_actions', [ $this, 'integration_actions' ], 10, 3 );
    } // End __construct()


    /**
     * Add Developer Debug Tools integrations
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @param array $data Integration data for the site.
     * @return array Modified integrations.
     */
    public function integration_requests( $integrations, $site_id, $data ) : array {
        $key = 'clear_cache_everywhere_hosting_url';

        $has_key   = array_key_exists( $key, $data );
        $is_empty  = $has_key && empty( $data[ $key ] );

        $integrations[ $key ] = [
            'label' => __( 'Host Cache URL', 'pluginrx-control-center' ),
            'warn'  => $is_empty,
            'link'  => '',
        ];
        return $integrations;
    } // End integration_requests()


    /**
     * Add Clear Cache Everywhere integration action
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @return array Modified integrations.
     */
    public function integration_actions( $integrations, $site_id ) : array {
        $integrations[ 'clear_cache_everywhere' ] = [
            'button_label'    => __( 'Clear Cache', 'pluginrx-control-center' ),
            'waiting_message' => __( 'Clearing cache...', 'pluginrx-control-center' ),
        ];
        return $integrations;
    } // End integration_actions()

}


new ClearCacheEverywhereController();