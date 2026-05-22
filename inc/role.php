<?php
/**
 * Role
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Role {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_prxctrl_role' ] );
    } // End __construct()


    /**
     * Register the PRX Control role
     */
    public function register_prxctrl_role() {
        if ( ! get_role( 'prxctrl' ) ) {
            add_role(
                'prxctrl',
                __( 'Control Center Manager', 'pluginrx-control-center' ),
                [
                    'read' => true,
                ]
            );
        }

        // Assign role to users
        if ( is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' ) ) {
            $devs = \Apos37\DevDebugTools\Helpers::get_devs();
            if ( is_array( $devs ) && ! empty( $devs ) ) {
                foreach ( $devs as $user_id ) {
                    $user = get_user_by( 'id', $user_id );
                    if ( $user && ! in_array( 'prxctrl', $user->roles, true ) ) {
                        $user->add_role( 'prxctrl' );
                    }
                }
            }
        } else {
            // Give role to all admins
            $admins = get_users( [ 'role' => 'administrator' ] );
            foreach ( $admins as $user ) {
                if ( ! in_array( 'prxctrl', $user->roles, true ) ) {
                    $user->add_role( 'prxctrl' );
                }
            }
        }
    } // End register_prxctrl_role()

}


new Role();