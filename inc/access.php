<?php
/**
 * ACCESS CONTROL
 *
 * Administrators always have access. Additional roles can be given access to the Dashboard, Logs
 * and Sites pages in Settings. Settings pages are limited to administrators. In the network admin,
 * only super admins have access.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Access {

    /**
     * Check if the current user can use the Dashboard, Logs and Sites pages.
     *
     * @return bool
     */
    public static function can_access() : bool {
        $user = wp_get_current_user();

        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        if ( Mode::is_network_context() ) {
            $allowed = current_user_can( 'manage_network_options' );
        } else {
            $allowed = current_user_can( 'manage_options' ) || count( array_intersect( (array) Options::get( 'access_roles' ), (array) $user->roles ) ) > 0;
        }

        return (bool) apply_filters( 'prxctrl_can_access', $allowed, $user );
    } // End can_access()


    /**
     * Check if the current user can change settings.
     *
     * @return bool
     */
    public static function can_manage() : bool {
        $allowed = Mode::is_network_context() ? current_user_can( 'manage_network_options' ) : current_user_can( 'manage_options' );

        return (bool) apply_filters( 'prxctrl_can_manage', $allowed, wp_get_current_user() );
    } // End can_manage()


    /**
     * End an AJAX request when the user does not have access. Call it right after check_ajax_referer().
     *
     * @param bool $manage Require settings access instead of general access.
     * @return void
     */
    public static function require_ajax( bool $manage = false ) : void {
        if ( ! ( $manage ? self::can_manage() : self::can_access() ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'pluginrx-control-center' ) ], 403 );
        }
    } // End require_ajax()


    /**
     * Get the roles that can be given access (every role except administrator).
     *
     * @return array role slug => label
     */
    public static function assignable_roles() : array {
        $roles = [];

        foreach ( wp_roles()->roles as $slug => $role ) {
            if ( 'administrator' === $slug ) {
                continue;
            }

            $roles[ $slug ] = translate_user_role( $role[ 'name' ] );
        }

        return $roles;
    } // End assignable_roles()

} // End class Access
