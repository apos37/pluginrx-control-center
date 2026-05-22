<?php
/**
 * WP Mail Logging Integration
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class WPMailLoggingController {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxcntrl_integration_requests', [ $this, 'integration_requests' ], 10, 3 );
        add_filter( 'prxcntrl_integration_actions', [ $this, 'integration_actions' ], 10, 3 );
    } // End __construct()


    /**
     * Add WP Mail Logging error count
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @param array $data Integration data for the site.
     * @return array Modified integrations.
     */
    public function integration_requests( $integrations, $site_id, $data ) : array {
        $key = 'wp_mail_logging_error_count';
        $integrations[ $key ] = [
            'label' => __( 'WP Mail Errors', 'pluginrx-control-center' ),
            'warn'  => ! empty( $data[ $key ] ) && $data[ $key ] > 0,
            'link'  => 'admin.php?page=wpml_plugin_log&status=2',
        ];

        return $integrations;
    } // End integration_requests()


    /**
     * Add WP Mail Logging action to purge errors
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @return array Modified integrations.
     */
    public function integration_actions( $integrations, $site_id ) : array {
        $integrations[ 'wp_mail_logging_clear_errors' ] = [
            'button_label'    => __( 'Purge Mail Errors', 'pluginrx-control-center' ),
            'waiting_message' => __( 'Purging errors...', 'pluginrx-control-center' ),
        ];
        return $integrations;
    } // End integration_actions()

}


new WPMailLoggingController();