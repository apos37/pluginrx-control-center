<?php
/**
 * Advanced Tools for Gravity Forms Integration
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class AdvancedToolsForGravityFormsController {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxcntrl_integration_requests', [ $this, 'integration_requests' ], 10, 3 );
        add_filter( 'prxcntrl_integration_actions', [ $this, 'integration_actions' ], 10, 3 );
    } // End __construct()


    /**
     * Add Gravity Forms spam count
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @param array $data Integration data for the site.
     * @return array Modified integrations.
     */
    public function integration_requests( $integrations, $site_id, $data ) : array {
        $key = 'gftools_spam_count';
        $integrations[ $key ] = [
            'label' => __( 'Form Spam', 'pluginrx-control-center' ),
            'warn'  => ! empty( $data[ $key ] ) && $data[ $key ] > 0,
            'link'  => 'admin.php?page=gf-tools&tab=spam_entries',
        ];

        return $integrations;
    } // End integration_requests()


    /**
     * Add Gravity Forms action to delete spam entries
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @return array Modified integrations.
     */
    public function integration_actions( $integrations, $site_id ) : array {
        $integrations[ 'gftools_delete_spam' ] = [
            'button_label'    => __( 'Delete Form Spam Entries', 'pluginrx-control-center' ),
            'waiting_message' => __( 'Deleting spam entries...', 'pluginrx-control-center' ),
        ];
        return $integrations;
    } // End integration_actions()

}


new AdvancedToolsForGravityFormsController();