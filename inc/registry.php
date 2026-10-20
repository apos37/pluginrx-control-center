<?php
/**
 * REGISTRY
 *
 * The single list of everything an agent can share (requests), everything a host can ask an agent
 * to do (actions), and every dashboard widget. Core items are defined here; integrations add theirs
 * through the prxctrl_integrations filter, so each integration is defined once for both sides.
 *
 * Integration format:
 *
 * add_filter( 'prxctrl_integrations', [ $this, 'register' ] );
 *
 * public function register( array $integrations ) : array {
 *     $integrations[ 'my_plugin' ] = [
 *         'label'    => 'My Plugin',
 *         'plugin'   => 'my-plugin/my-plugin.php', // Requests return "N/A" when this plugin is inactive.
 *         'requests' => [
 *             'my_plugin_count' => [
 *                 'label'       => 'My Count',                 // Widget label.
 *                 'description' => 'Share the number of ...',  // Shown on the agent's permissions list.
 *                 'callback'    => [ $this, 'my_count' ],      // Runs on the agent. Return a scalar.
 *                 'link'        => 'admin.php?page=my-plugin', // Optional. Relative to the agent's admin URL.
 *                 'format'      => 'number',                   // Optional. number|filesize|text
 *                 'warn'        => [ $this, 'my_warn' ],       // Optional. Receives the value, returns bool.
 *             ],
 *         ],
 *         'actions'  => [
 *             'my_plugin_clear' => [
 *                 'label'       => 'Clear Things',
 *                 'description' => 'Allow clearing things from the Control Center.',
 *                 'waiting'     => 'Clearing...',
 *                 'callback'    => [ $this, 'my_clear' ],      // Runs on the agent. Return [ 'success' => bool, 'message' => string ].
 *                 'available'   => [ $this, 'my_available' ],  // Optional. Runs on the host with the site object; return bool.
 *             ],
 *         ],
 *     ];
 *     return $integrations;
 * } // End register()
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Registry {

    /**
     * Option where an agent keeps the widget layout its Control Center last sent.
     */
    public const AGENT_LAYOUT_OPTION = 'prxctrl_agent_layout';


    /**
     * Value returned for an integration whose plugin is not active on the agent.
     */
    public const NOT_ACTIVE = 'N/A';


    /**
     * Message for deprecated hooks.
     */
    private const DEPRECATED_DOCS = 'https://pluginrx.com/docs/plugin/pluginrx-control-center/';


    /**
     * @var array Per-request cache
     */
    private static array $cache = [];


    /**
     * Clear the per-request cache (used after settings change).
     *
     * @return void
     */
    public static function flush() : void {
        self::$cache = [];
    } // End flush()


    /**
     * Get the integration definitions.
     *
     * @return array
     */
    public static function integrations() : array {
        if ( isset( self::$cache[ 'integrations' ] ) ) {
            return self::$cache[ 'integrations' ];
        }

        $integrations = [];

        foreach ( (array) apply_filters( 'prxctrl_integrations', [] ) as $slug => $integration ) {
            if ( ! is_array( $integration ) ) {
                continue;
            }

            $integrations[ sanitize_key( $slug ) ] = wp_parse_args( $integration, [
                'label'    => Helpers::humanize( (string) $slug ),
                'plugin'   => '',
                'builtin'  => false,
                'requests' => [],
                'actions'  => [],
            ] );
        }

        self::$cache[ 'integrations' ] = $integrations;

        return $integrations;
    } // End integrations()


    /**
     * Build the deprecation message that points developers to the new hook.
     *
     * @return string
     */
    private static function deprecated_message() : string {
        return sprintf(
            /* translators: %s: documentation URL */
            __( 'Register requests, actions and widgets together with the prxctrl_integrations filter. See %s for the format.', 'pluginrx-control-center' ),
            self::DEPRECATED_DOCS
        );
    } // End deprecated_message()


    /**
     * Get the core requests.
     *
     * @return array
     */
    private static function core_requests() : array {
        $items = [
            'wordpress_version' => [ __( 'WordPress Version', 'pluginrx-control-center' ), __( 'Share the installed WordPress version.', 'pluginrx-control-center' ), true ],
            'core_update'       => [ __( 'WordPress Update', 'pluginrx-control-center' ), __( 'Share whether a WordPress update is available.', 'pluginrx-control-center' ), true ],
            'php_version'       => [ __( 'PHP Version', 'pluginrx-control-center' ), __( 'Share the PHP version the site runs on.', 'pluginrx-control-center' ), true ],
            'plugins'           => [ __( 'Installed Plugins', 'pluginrx-control-center' ), __( 'Share installed plugins with their versions and available updates.', 'pluginrx-control-center' ), true ],
            'themes'            => [ __( 'Installed Themes', 'pluginrx-control-center' ), __( 'Share installed themes with their versions and available updates.', 'pluginrx-control-center' ), true ],
            'wp_debug'          => [ __( 'WP_DEBUG Status', 'pluginrx-control-center' ), __( 'Share whether WP_DEBUG is enabled.', 'pluginrx-control-center' ), true ],
            'is_multisite'      => [ __( 'Multisite Status', 'pluginrx-control-center' ), __( 'Share whether this site is part of a multisite network.', 'pluginrx-control-center' ), true ],
            'blog_id'           => [ __( 'Blog ID', 'pluginrx-control-center' ), __( 'Share this site\'s blog ID.', 'pluginrx-control-center' ), true ],
            'admin_path'        => [ __( 'Admin Area Links', 'pluginrx-control-center' ), __( 'Share this site\'s admin URL so the Control Center can link straight to pages in its dashboard.', 'pluginrx-control-center' ), true ],
            'admin_email'       => [ __( 'Admin Email', 'pluginrx-control-center' ), __( 'Share the site admin email address.', 'pluginrx-control-center' ), false ],
            'admin_users'       => [ __( 'Admin Users', 'pluginrx-control-center' ), __( 'Share the list of administrator accounts (username, name, email, registration date and last activity).', 'pluginrx-control-center' ), false ],
            'server_ip'         => [ __( 'Server IP', 'pluginrx-control-center' ), __( 'Share the server IP address.', 'pluginrx-control-center' ), false ],
            'abspath'           => [ __( 'ABSPATH', 'pluginrx-control-center' ), __( 'Share the absolute path to the WordPress installation on the server.', 'pluginrx-control-center' ), false ],
        ];

        $requests = [];

        foreach ( $items as $key => $item ) {
            $requests[ $key ] = [
                'label'       => $item[ 0 ],
                'description' => $item[ 1 ],
                'default'     => $item[ 2 ],
                'callback'    => [ Requests::class, 'get_' . $key ],
                'group'       => 'core',
            ];
        }

        return $requests;
    } // End core_requests()


    /**
     * Get every request an agent can share.
     *
     * @return array
     */
    public static function requests() : array {
        if ( isset( self::$cache[ 'requests' ] ) ) {
            return self::$cache[ 'requests' ];
        }

        $requests = self::core_requests();

        foreach ( self::integrations() as $slug => $integration ) {
            foreach ( (array) $integration[ 'requests' ] as $key => $request ) {
                $requests[ sanitize_key( $key ) ] = array_merge( [ 'description' => '', 'default' => false ], (array) $request, [
                    'group'       => 'integration',
                    'integration' => $slug,
                    'plugin'      => $integration[ 'plugin' ],
                ] );
            }
        }

        $legacy = apply_filters_deprecated( 'prxagnt_integration_requests', [ [], self::NOT_ACTIVE ], '2.0.0', 'prxctrl_integrations', self::deprecated_message() );

        foreach ( (array) $legacy as $key => $request ) {
            if ( is_array( $request ) && ! isset( $requests[ $key ] ) && isset( $request[ 'callback' ] ) ) {
                $requests[ sanitize_key( $key ) ] = array_merge( [ 'label' => Helpers::humanize( (string) $key ), 'description' => '', 'default' => false ], $request, [ 'group' => 'integration', 'integration' => 'legacy', 'plugin' => '' ] );
            }
        }

        self::$cache[ 'requests' ] = (array) apply_filters( 'prxctrl_requests', $requests );

        return self::$cache[ 'requests' ];
    } // End requests()


    /**
     * Get every action a host can ask an agent to perform.
     *
     * @return array
     */
    public static function actions() : array {
        if ( isset( self::$cache[ 'actions' ] ) ) {
            return self::$cache[ 'actions' ];
        }

        $actions = [
            'update_wordpress' => [
                'label'       => __( 'Update WordPress', 'pluginrx-control-center' ),
                'description' => __( 'Allow updating WordPress core from the Control Center.', 'pluginrx-control-center' ),
                'waiting'     => __( 'Updating WordPress...', 'pluginrx-control-center' ),
                'callback'    => [ Actions::class, 'update_wordpress' ],
                'available'   => [ self::class, 'core_update_available' ],
                'group'       => 'core',
            ],
            'update_themes'    => [
                'label'       => __( 'Update Themes', 'pluginrx-control-center' ),
                'description' => __( 'Allow updating themes that have updates available.', 'pluginrx-control-center' ),
                'waiting'     => __( 'Updating themes...', 'pluginrx-control-center' ),
                'callback'    => [ Actions::class, 'update_themes' ],
                'available'   => [ self::class, 'theme_updates_available' ],
                'group'       => 'core',
            ],
            'update_plugins'   => [
                'label'       => __( 'Update Plugins', 'pluginrx-control-center' ),
                'description' => __( 'Allow updating plugins that have updates available.', 'pluginrx-control-center' ),
                'waiting'     => __( 'Updating plugins...', 'pluginrx-control-center' ),
                'callback'    => [ Actions::class, 'update_plugins' ],
                'available'   => [ self::class, 'plugin_updates_available' ],
                'group'       => 'core',
            ],
        ];

        foreach ( self::integrations() as $slug => $integration ) {
            foreach ( (array) $integration[ 'actions' ] as $key => $action ) {
                $actions[ sanitize_key( $key ) ] = array_merge( [ 'description' => '', 'waiting' => __( 'Working...', 'pluginrx-control-center' ) ], (array) $action, [
                    'group'       => 'integration',
                    'integration' => $slug,
                    'plugin'      => $integration[ 'plugin' ],
                ] );
            }
        }

        $legacy_agent = apply_filters_deprecated( 'prxagnt_integration_actions', [ [] ], '2.0.0', 'prxctrl_integrations', self::deprecated_message() );

        foreach ( (array) $legacy_agent as $key => $action ) {
            if ( is_array( $action ) && ! isset( $actions[ $key ] ) && isset( $action[ 'callback' ] ) ) {
                $actions[ sanitize_key( $key ) ] = array_merge( [ 'label' => Helpers::humanize( (string) $key ), 'description' => '', 'waiting' => __( 'Working...', 'pluginrx-control-center' ) ], $action, [ 'group' => 'integration', 'integration' => 'legacy', 'plugin' => '' ] );
            }
        }

        $legacy_host = apply_filters_deprecated( 'prxcntrl_integration_actions', [ [], 0 ], '2.0.0', 'prxctrl_integrations', self::deprecated_message() );

        foreach ( (array) $legacy_host as $key => $button ) {
            $key = sanitize_key( $key );

            if ( ! is_array( $button ) ) {
                continue;
            }

            $actions[ $key ] = array_merge( $actions[ $key ] ?? [ 'description' => '', 'group' => 'integration', 'integration' => 'legacy', 'plugin' => '' ], [
                'label'   => (string) ( $button[ 'button_label' ] ?? Helpers::humanize( $key ) ),
                'waiting' => (string) ( $button[ 'waiting_message' ] ?? __( 'Working...', 'pluginrx-control-center' ) ),
            ] );
        }

        self::$cache[ 'actions' ] = (array) apply_filters( 'prxctrl_actions', $actions );

        return self::$cache[ 'actions' ];
    } // End actions()


    /**
     * Get everything an agent can allow, for the permissions list.
     *
     * @return array key => [ label, description, group, kind ]
     */
    public static function permissions() : array {
        if ( isset( self::$cache[ 'permissions' ] ) ) {
            return self::$cache[ 'permissions' ];
        }

        $integrations = self::integrations();
        $permissions = [];

        foreach ( self::requests() as $key => $request ) {
            $permissions[ $key ] = [
                'label'       => (string) ( $request[ 'label' ] ?? $key ),
                'description' => (string) ( $request[ 'description' ] ?? '' ),
                'group'       => $request[ 'group' ],
                'kind'        => 'request',
                'source'      => isset( $request[ 'integration' ] ) ? (string) ( $integrations[ $request[ 'integration' ] ][ 'label' ] ?? '' ) : '',
            ];
        }

        foreach ( self::actions() as $key => $action ) {
            if ( empty( $action[ 'callback' ] ) ) {
                continue;
            }

            $permissions[ $key ] = [
                'label'       => (string) ( $action[ 'label' ] ?? $key ),
                'description' => (string) ( $action[ 'description' ] ?? '' ),
                'group'       => $action[ 'group' ],
                'kind'        => 'action',
                'source'      => isset( $action[ 'integration' ] ) ? (string) ( $integrations[ $action[ 'integration' ] ][ 'label' ] ?? '' ) : '',
            ];
        }

        $permissions[ 'remote_login' ] = [
            'label'       => __( 'Remote Login', 'pluginrx-control-center' ),
            'description' => __( 'Allow the Control Center to log in to this site with a single-use link.', 'pluginrx-control-center' ),
            'group'       => 'login',
            'kind'        => 'login',
            'source'      => '',
        ];

        self::$cache[ 'permissions' ] = $permissions;

        return $permissions;
    } // End permissions()


    /**
     * Get the permissions allowed on a fresh install: low-risk version and update information only.
     *
     * @return array
     */
    public static function default_permissions() : array {
        $defaults = [];

        foreach ( self::requests() as $key => $request ) {
            if ( 'core' === $request[ 'group' ] && ! empty( $request[ 'default' ] ) ) {
                $defaults[] = $key;
            }
        }

        return (array) apply_filters( 'prxctrl_default_permissions', $defaults );
    } // End default_permissions()


    /**
     * Get every dashboard widget.
     *
     * @return array
     */
    public static function widgets() : array {
        if ( isset( self::$cache[ 'widgets' ] ) ) {
            return self::$cache[ 'widgets' ];
        }

        $core = [
            'wordpress_version' => [ __( 'WordPress Version', 'pluginrx-control-center' ), 'wp_version', 'wordpress_version' ],
            'php_version'       => [ __( 'PHP Version', 'pluginrx-control-center' ), 'php_version', 'php_version' ],
            'plugins'           => [ __( 'Plugins', 'pluginrx-control-center' ), 'plugins', 'plugins' ],
            'themes'            => [ __( 'Themes', 'pluginrx-control-center' ), 'themes', 'themes' ],
            'admin_users'       => [ __( 'Admins', 'pluginrx-control-center' ), 'admins', 'admin_users' ],
            'response'          => [ __( 'Response', 'pluginrx-control-center' ), 'response', '' ],
            'ssl'               => [ __( 'SSL Certificate', 'pluginrx-control-center' ), 'ssl', '' ],
            'admin_email'       => [ __( 'Admin Email', 'pluginrx-control-center' ), 'admin_email', 'admin_email' ],
            'server_ip'         => [ __( 'Server IP', 'pluginrx-control-center' ), 'text', 'server_ip' ],
            'abspath'           => [ __( 'ABSPATH', 'pluginrx-control-center' ), 'text', 'abspath' ],
            'wp_debug'          => [ __( 'WP Debug', 'pluginrx-control-center' ), 'wp_debug', 'wp_debug' ],
            'is_multisite'      => [ __( 'Multisite', 'pluginrx-control-center' ), 'bool', 'is_multisite' ],
            'blog_id'           => [ __( 'Blog ID', 'pluginrx-control-center' ), 'text', 'blog_id' ],
            'connection'        => [ __( 'Connection', 'pluginrx-control-center' ), 'connection', '' ],
            'notes'             => [ __( 'Notes', 'pluginrx-control-center' ), 'notes', '' ],
        ];

        $widgets = [];

        foreach ( $core as $key => $item ) {
            $widgets[ $key ] = [
                'label'   => $item[ 0 ],
                'type'    => $item[ 1 ],
                'request' => $item[ 2 ],
                'builtin' => true,
                'wide'    => 'notes' === $key,
            ];
        }

        foreach ( self::integrations() as $slug => $integration ) {
            foreach ( (array) $integration[ 'requests' ] as $key => $request ) {
                if ( isset( $request[ 'widget' ] ) && false === $request[ 'widget' ] ) {
                    continue;
                }

                $widgets[ sanitize_key( $key ) ] = [
                    'label'       => (string) ( $request[ 'label' ] ?? Helpers::humanize( $key ) ),
                    'type'        => 'integration',
                    'request'     => sanitize_key( $key ),
                    'builtin'     => ! empty( $integration[ 'builtin' ] ),
                    'wide'        => false,
                    'integration' => $slug,
                    'link'        => (string) ( $request[ 'link' ] ?? '' ),
                    'format'      => (string) ( $request[ 'format' ] ?? 'text' ),
                    'warn'        => $request[ 'warn' ] ?? null,
                ];
            }
        }

        foreach ( self::legacy_host_widgets( 0, [] ) as $key => $legacy ) {
            if ( isset( $widgets[ $key ] ) ) {
                continue;
            }

            $widgets[ $key ] = [
                'label'   => (string) ( $legacy[ 'label' ] ?? Helpers::humanize( $key ) ),
                'type'    => 'integration',
                'request' => $key,
                'builtin' => false,
                'wide'    => false,
                'link'    => (string) ( $legacy[ 'link' ] ?? '' ),
                'format'  => (string) ( $legacy[ 'format' ] ?? 'text' ),
                'warn'    => null,
                'legacy'  => true,
            ];
        }

        self::$cache[ 'widgets' ] = (array) apply_filters( 'prxctrl_widgets', $widgets );

        return self::$cache[ 'widgets' ];
    } // End widgets()


    /**
     * Run the deprecated host-side widget filter for one site.
     *
     * @param int   $site_id
     * @param array $values Integration values.
     * @return array
     */
    public static function legacy_host_widgets( int $site_id, array $values ) : array {
        $legacy = apply_filters_deprecated( 'prxcntrl_integration_requests', [ [], $site_id, $values ], '2.0.0', 'prxctrl_integrations', self::deprecated_message() );

        return is_array( $legacy ) ? $legacy : [];
    } // End legacy_host_widgets()


    /**
     * Get the widget layout: enabled widgets in board order, and the disabled tray.
     * Widgets that are new since the layout was saved are added to the board when they ship with
     * the plugin, and to the disabled tray when they come from another plugin.
     *
     * @return array [ 'enabled' => array, 'disabled' => array ]
     */
    public static function widget_layout() : array {
        $widgets = self::widgets();
        $saved = (array) Options::get( 'widget_layout' );

        if ( ! Mode::host_here() ) {
            $from_host = get_option( self::AGENT_LAYOUT_OPTION, null );

            if ( is_array( $from_host ) ) {
                $saved = [ 'enabled' => $from_host, 'disabled' => array_diff( array_keys( $widgets ), $from_host ) ];
            }
        }
        $enabled = array_values( array_intersect( (array) ( $saved[ 'enabled' ] ?? [] ), array_keys( $widgets ) ) );
        $disabled = array_values( array_diff( array_intersect( (array) ( $saved[ 'disabled' ] ?? [] ), array_keys( $widgets ) ), $enabled ) );

        foreach ( $widgets as $key => $widget ) {
            if ( in_array( $key, $enabled, true ) || in_array( $key, $disabled, true ) ) {
                continue;
            }

            if ( ! empty( $widget[ 'builtin' ] ) ) {
                $enabled[] = $key;
            } else {
                $disabled[] = $key;
            }
        }

        return [ 'enabled' => $enabled, 'disabled' => $disabled ];
    } // End widget_layout()


    /**
     * Get the action buttons that can be shown or hidden in Display Options.
     *
     * @return array key => label
     */
    public static function buttons() : array {
        $buttons = [ 'login' => __( 'Remote Login', 'pluginrx-control-center' ) ];

        foreach ( self::actions() as $key => $action ) {
            $buttons[ $key ] = (string) ( $action[ 'label' ] ?? Helpers::humanize( $key ) );
        }

        return $buttons;
    } // End buttons()


    /**
     * Get a human-readable label for a log action.
     *
     * @param string $key
     * @return string
     */
    public static function action_label( string $key ) : string {
        $labels = [
            'check_site'       => __( 'Check Site', 'pluginrx-control-center' ),
            'fetch_site_data'  => __( 'Check Site', 'pluginrx-control-center' ),
            'check_connection' => __( 'Check Connection', 'pluginrx-control-center' ),
            'remote_login'     => __( 'Remote Login', 'pluginrx-control-center' ),
            'agent_request'    => __( 'Data Request', 'pluginrx-control-center' ),
            'agent_ping'       => __( 'Connection Check', 'pluginrx-control-center' ),
            'auth_failed'      => __( 'Authentication Failed', 'pluginrx-control-center' ),
            'site_added'       => __( 'Site Added', 'pluginrx-control-center' ),
            'site_removed'     => __( 'Site Removed', 'pluginrx-control-center' ),
            'migration'        => __( 'Upgrade', 'pluginrx-control-center' ),
        ];

        if ( isset( $labels[ $key ] ) ) {
            return $labels[ $key ];
        }

        $actions = self::actions();

        return isset( $actions[ $key ][ 'label' ] ) ? (string) $actions[ $key ][ 'label' ] : Helpers::humanize( $key );
    } // End action_label()


    /**
     * Availability check: a WordPress core update is available.
     *
     * @param object $site
     * @return bool
     */
    public static function core_update_available( object $site ) : bool {
        if ( ! empty( $site->core_update ) ) {
            return true;
        }

        if ( null === $site->permissions || ! in_array( 'core_update', (array) $site->permissions, true ) ) {
            $latest = Helpers::latest_versions()[ 'wordpress' ];
            return ! empty( $site->wordpress_version ) && version_compare( (string) $site->wordpress_version, $latest, '<' );
        }

        return false;
    } // End core_update_available()


    /**
     * Availability check: at least one plugin has an update.
     *
     * @param object $site
     * @return bool
     */
    public static function plugin_updates_available( object $site ) : bool {
        foreach ( (array) ( $site->plugins ?? [] ) as $plugin ) {
            if ( ! empty( $plugin->update_available ) ) {
                return true;
            }
        }

        return false;
    } // End plugin_updates_available()


    /**
     * Availability check: at least one theme has an update.
     *
     * @param object $site
     * @return bool
     */
    public static function theme_updates_available( object $site ) : bool {
        foreach ( (array) ( $site->themes ?? [] ) as $theme ) {
            if ( ! empty( $theme->update_available ) ) {
                return true;
            }
        }

        return false;
    } // End theme_updates_available()


    /**
     * Get a site's stored value for an integration request.
     *
     * @param object $site
     * @param string $key
     * @return mixed
     */
    public static function value( object $site, string $key ) {
        $value = $site->integrations[ $key ] ?? null;

        if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
            return $value[ 'value' ];
        }

        return $value;
    } // End value()


    /**
     * Check if an integration value means "has something": not empty, not zero and not N/A.
     *
     * @param mixed $value
     * @return bool
     */
    public static function has_value( $value ) : bool {
        if ( null === $value || false === $value || '' === $value || self::NOT_ACTIVE === $value ) {
            return false;
        }

        return ! ( is_numeric( $value ) && 0.0 === (float) $value );
    } // End has_value()

} // End class Registry
