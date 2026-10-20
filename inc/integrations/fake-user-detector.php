<?php
/**
 * INTEGRATION: FAKE USER DETECTOR
 *
 * Shares the number of accounts flagged by Fake User Detector.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class IntegrationFakeUserDetector {

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
        $integrations[ 'fake_user_detector' ] = [
            'label'    => __( 'Fake User Detector', 'pluginrx-control-center' ),
            'plugin'   => 'fake-user-detector/fake-user-detector.php',
            'builtin'  => true,
            'requests' => [
                'fake_user_detector_count' => [
                    'label'       => __( 'Fake Users', 'pluginrx-control-center' ),
                    'description' => __( 'Share the number of accounts flagged by Fake User Detector.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'count' ],
                    'link'        => 'users.php',
                    'format'      => 'number',
                    'warn'        => [ $this, 'warn' ],
                ],
            ],
        ];

        return $integrations;
    } // End register()


    /**
     * Count flagged users.
     *
     * @return int
     */
    public function count() : int {
        if ( ! class_exists( '\PluginRx\FakeUserDetector\Indicator' ) || ! method_exists( '\PluginRx\FakeUserDetector\Indicator', 'count_flagged_users' ) ) {
            return 0;
        }

        return (int) ( new \PluginRx\FakeUserDetector\Indicator() )->count_flagged_users();
    } // End count()


    /**
     * Warn when any accounts are flagged.
     *
     * @param mixed $value
     * @return bool
     */
    public function warn( $value ) : bool {
        return is_numeric( $value ) && $value > 0;
    } // End warn()

} // End class IntegrationFakeUserDetector


new IntegrationFakeUserDetector();
