<?php
/**
 * OPTIONS
 *
 * One place that defines every setting: its default, how it is sanitized, where it is stored and
 * whether it is included in exports. Saving, importing, exporting and resetting all read from here.
 *
 * Scopes:
 * - global: stored per site, or network-wide when the Control Center lives in the network admin.
 * - host:   same storage as global, used by the host features.
 * - agent:  always stored per site, because every site is its own agent.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Options {

    /**
     * Option name prefix.
     */
    public const PREFIX = 'prxctrl_';


    /**
     * @var array|null Cached definitions
     */
    private static ?array $definitions = null;


    /**
     * Get all setting definitions.
     *
     * @return array
     */
    public static function definitions() : array {
        if ( null !== self::$definitions ) {
            return self::$definitions;
        }

        $definitions = [
            'access_roles'         => [ 'scope' => 'global', 'type' => 'roles', 'default' => [] ],
            'date_format'          => [ 'scope' => 'global', 'type' => 'text', 'default' => trim( get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' ) ) ],
            'timezone'             => [ 'scope' => 'global', 'type' => 'timezone', 'default' => '' ],
            'log_retention'        => [ 'scope' => 'global', 'type' => 'int', 'default' => 90, 'min' => 0, 'max' => 3650 ],
            'console_log'          => [ 'scope' => 'global', 'type' => 'bool', 'default' => 0 ],
            'delete_on_uninstall'  => [ 'scope' => 'global', 'type' => 'bool', 'default' => 0 ],
            'sorting'              => [ 'scope' => 'host', 'type' => 'choice', 'default' => 'alpha', 'choices' => [ 'alpha', 'custom' ] ],
            'jump_menu'            => [ 'scope' => 'host', 'type' => 'bool', 'default' => 1 ],
            'screenshots'          => [ 'scope' => 'host', 'type' => 'bool', 'default' => 1 ],
            'concurrency'          => [ 'scope' => 'host', 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 5 ],
            'warn_admin_email'     => [ 'scope' => 'host', 'type' => 'bool', 'default' => 1 ],
            'admin_inactive_days'  => [ 'scope' => 'host', 'type' => 'int', 'default' => 180, 'min' => 0, 'max' => 3650 ],
            'wp_warning'           => [ 'scope' => 'host', 'type' => 'choice', 'default' => 'minor', 'choices' => [ 'exact', 'minor', 'previous', 'major', 'off' ] ],
            'php_warning'          => [ 'scope' => 'host', 'type' => 'choice', 'default' => 'previous', 'choices' => [ 'exact', 'minor', 'previous', 'major', 'off' ] ],
            'ssl_warning_days'     => [ 'scope' => 'host', 'type' => 'int', 'default' => 14, 'min' => 0, 'max' => 365 ],
            'link_fields'          => [ 'scope' => 'host', 'type' => 'link_fields', 'default' => [] ],
            'timeout'              => [ 'scope' => 'host', 'type' => 'int', 'default' => 15, 'min' => 1, 'max' => 180 ],
            'log_checks'           => [ 'scope' => 'host', 'type' => 'bool', 'default' => 1 ],
            'widget_layout'        => [ 'scope' => 'host', 'type' => 'layout', 'default' => [] ],
            'agent_remote_access'  => [ 'scope' => 'agent', 'type' => 'bool', 'default' => 0 ],
            'agent_api_key'        => [ 'scope' => 'agent', 'type' => 'api_key', 'default' => '', 'export' => false ],
            'agent_domains'        => [ 'scope' => 'agent', 'type' => 'hosts', 'default' => [] ],
            'agent_require_signed' => [ 'scope' => 'agent', 'type' => 'bool', 'default' => 0 ],
            'agent_permissions'    => [ 'scope' => 'agent', 'type' => 'permissions', 'default' => null ],
            'agent_login_user'     => [ 'scope' => 'agent', 'type' => 'login_user', 'default' => 0, 'export' => false ],
            'agent_log_requests'   => [ 'scope' => 'agent', 'type' => 'bool', 'default' => 0 ],
        ];

        self::$definitions = (array) apply_filters( 'prxctrl_option_definitions', $definitions );

        return self::$definitions;
    } // End definitions()


    /**
     * Check if a setting is stored network-wide in the current context.
     *
     * @param string $key
     * @return bool
     */
    private static function is_network_stored( string $key ) : bool {
        $definition = self::definitions()[ $key ] ?? null;

        return $definition && 'agent' !== $definition[ 'scope' ] && Mode::is_network_context();
    } // End is_network_stored()


    /**
     * Get a setting value, falling back to its default.
     *
     * @param string $key Setting key without the prefix.
     * @return mixed
     */
    public static function get( string $key ) {
        $definitions = self::definitions();

        if ( ! isset( $definitions[ $key ] ) ) {
            return null;
        }

        $default = $definitions[ $key ][ 'default' ];
        $name = self::PREFIX . $key;
        $value = self::is_network_stored( $key ) ? get_site_option( $name, null ) : get_option( $name, null );

        if ( null === $value ) {
            return 'agent_permissions' === $key ? Registry::default_permissions() : $default;
        }

        return $value;
    } // End get()


    /**
     * Sanitize and save a setting.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public static function update( string $key, $value ) : void {
        if ( ! isset( self::definitions()[ $key ] ) ) {
            return;
        }

        $value = self::sanitize( $key, $value );
        $name = self::PREFIX . $key;

        if ( self::is_network_stored( $key ) ) {
            update_site_option( $name, $value );
            return;
        }

        update_option( $name, $value, false );
    } // End update()


    /**
     * Delete a setting so it falls back to its default.
     *
     * @param string $key
     * @return void
     */
    public static function delete( string $key ) : void {
        $name = self::PREFIX . $key;

        if ( self::is_network_stored( $key ) ) {
            delete_site_option( $name );
            return;
        }

        delete_option( $name );
    } // End delete()


    /**
     * Get the keys for one or more scopes.
     *
     * @param array $scopes
     * @param bool  $exportable_only
     * @return array
     */
    public static function keys( array $scopes, bool $exportable_only = false ) : array {
        $keys = [];

        foreach ( self::definitions() as $key => $definition ) {
            if ( ! in_array( $definition[ 'scope' ], $scopes, true ) ) {
                continue;
            }

            if ( $exportable_only && isset( $definition[ 'export' ] ) && ! $definition[ 'export' ] ) {
                continue;
            }

            $keys[] = $key;
        }

        return $keys;
    } // End keys()


    /**
     * Sanitize a value for a setting.
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    public static function sanitize( string $key, $value ) {
        $definition = self::definitions()[ $key ];

        switch ( $definition[ 'type' ] ) {
            case 'bool':
                return ( ! empty( $value ) && 'no' !== $value && 'false' !== $value ) ? 1 : 0;

            case 'int':
                return min( (int) $definition[ 'max' ], max( (int) $definition[ 'min' ], absint( $value ) ) );

            case 'choice':
                $value = sanitize_key( (string) $value );
                return in_array( $value, $definition[ 'choices' ], true ) ? $value : $definition[ 'default' ];

            case 'timezone':
                $value = sanitize_text_field( (string) $value );
                return in_array( $value, timezone_identifiers_list(), true ) ? $value : '';

            case 'roles':
                $roles = array_map( 'sanitize_key', (array) $value );
                return array_values( array_intersect( $roles, array_keys( Access::assignable_roles() ) ) );

            case 'hosts':
                $hosts = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
                $hosts = array_filter( array_map( [ Helpers::class, 'host' ], array_map( 'strval', (array) $hosts ) ) );
                return array_values( array_unique( $hosts ) );

            case 'api_key':
                $value = strtolower( sanitize_text_field( (string) $value ) );
                return preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';

            case 'permissions':
                $keys = array_map( 'sanitize_key', (array) $value );
                return array_values( array_intersect( $keys, array_keys( Registry::permissions() ) ) );

            case 'login_user':
                $user_id = absint( $value );
                return ( $user_id && user_can( $user_id, 'manage_options' ) ) ? $user_id : 0;

            case 'link_fields':
                return self::sanitize_link_fields( (array) $value );

            case 'layout':
                $value = (array) $value;
                return [
                    'enabled'  => array_values( array_unique( array_map( 'sanitize_key', (array) ( $value[ 'enabled' ] ?? [] ) ) ) ),
                    'disabled' => array_values( array_unique( array_map( 'sanitize_key', (array) ( $value[ 'disabled' ] ?? [] ) ) ) ),
                ];

            default:
                return sanitize_text_field( (string) $value );
        }
    } // End sanitize()


    /**
     * Sanitize the link field definitions. Slugs must be unique, and "login" is reserved.
     *
     * @param array $fields
     * @return array
     */
    private static function sanitize_link_fields( array $fields ) : array {
        $clean = [];
        $used = [ 'login' ];

        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $label = sanitize_text_field( (string) ( $field[ 'label' ] ?? '' ) );
            $slug = sanitize_key( (string) ( $field[ 'slug' ] ?? '' ) );

            if ( '' === $slug && '' !== $label ) {
                $slug = sanitize_key( str_replace( ' ', '_', $label ) );
            }

            if ( '' === $label || '' === $slug || in_array( $slug, $used, true ) ) {
                continue;
            }

            $used[] = $slug;
            $clean[] = [ 'label' => $label, 'slug' => $slug ];

            if ( count( $clean ) >= 20 ) {
                break;
            }
        }

        return $clean;
    } // End sanitize_link_fields()

} // End class Options
