<?php
/**
 * Developer Debug Tools Integration
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class DevDebugToolsController {

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
        $key = 'dev_debug_tools_total_users';
        $integrations[ $key ] = [
            'label' => __( 'Total Users', 'pluginrx-control-center' ),
            'warn'  => false,
            'link'  => 'users.php',
        ];

        $key = 'dev_debug_tools_online_users';
        $integrations[ $key ] = [
            'label' => __( 'Online Users', 'pluginrx-control-center' ),
            'warn'  => false,
            'link'  => 'users.php',
        ];

        $key = 'dev_debug_tools_log_count';
        $integrations[ $key ] = [
            'label' => __( 'Log Count', 'pluginrx-control-center' ),
            'warn'  => false,
            'link'  => 'admin.php?page=dev-debug-tools&tool=logs',
        ];

        $key = 'dev_debug_tools_log_size';
        $integrations[ $key ] = [
            'label'  => __( 'Debug Log Size', 'pluginrx-control-center' ),
            'warn'   => $data[ $key ] > 3 * 1024 * 1024,
            'link'   => 'admin.php?page=dev-debug-tools&tool=logs',
            'format' => 'filesize',
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
        $integrations[ 'dev_debug_tools_clear_debug_log' ] = [
            'button_label'    => __( 'Clear Debug Log', 'pluginrx-control-center' ),
            'waiting_message' => __( 'Clearing log...', 'pluginrx-control-center' ),
        ];
        return $integrations;
    } // End integration_actions()

}


new DevDebugToolsController();