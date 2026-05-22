<?php
/**
 * Broken Link Notifier Integration
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class BrokenLinkNotifierController {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxcntrl_integration_requests', [ $this, 'integrations' ], 10, 3 );
    } // End __construct()


    /**
     * Add Broken Link Notifier flagged broken link count
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @param array $data Integration data for the site.
     * @return array Modified integrations.
     */
    public function integrations( $integrations, $site_id, $data ) : array {
        $key = 'broken_link_notifier_count';
        $integrations[ $key ] = [
            'label' => __( 'Broken Links', 'pluginrx-control-center' ),
            'warn'  => ! empty( $data[ $key ] ) && $data[ $key ] > 0,
            'link'  => 'admin.php?page=broken-link-notifier&tab=results',
        ];

        return $integrations;
    } // End integrations()

}


new BrokenLinkNotifierController();