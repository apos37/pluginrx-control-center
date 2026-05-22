<?php
/**
 * Fake User Detector Integration
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class FakeUserDetectorController {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxcntrl_integration_requests', [ $this, 'integrations' ], 10, 3 );
    } // End __construct()


    /**
     * Add Fake User Detector flagged user count
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @param array $data Integration data for the site.
     * @return array Modified integrations.
     */
    public function integrations( $integrations, $site_id, $data ) : array {
        $key = 'fake_user_detector_count';
        $integrations[ $key ] = [
            'label' => __( 'Fake Users', 'pluginrx-control-center' ),
            'warn'  => ! empty( $data[ $key ] ) && $data[ $key ] > 0,
            'link'  => 'users.php',
        ];

        return $integrations;
    } // End integrations()

}


new FakeUserDetectorController();