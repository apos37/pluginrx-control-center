<?php
/**
 * SITE CARD
 *
 * Renders one site on the dashboard: header with status and actions, the widget board and the
 * plugins, themes and admins tables. The same markup is used for the host dashboard, an agent's
 * own dashboard (read-only) and the widget manager preview, so every view stays identical.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class SiteCard {

    /**
     * Render contexts.
     */
    public const HOST = 'host';
    public const AGENT = 'agent';
    public const PREVIEW = 'preview';


    /**
     * @var array|null Cached latest versions
     */
    private static ?array $latest = null;


    /**
     * Get the latest WordPress and PHP versions once per request.
     *
     * @return array
     */
    private static function latest() : array {
        return self::$latest ??= Helpers::latest_versions();
    } // End latest()


    /**
     * Get the current user's hidden display keys.
     *
     * @return array
     */
    public static function hidden_keys() : array {
        $hidden = get_user_meta( get_current_user_id(), 'prxctrl_hidden', true );

        return is_array( $hidden ) ? $hidden : [ 'unavailable' ];
    } // End hidden_keys()


    /**
     * Check if a widget's data was not shared by the agent.
     *
     * @param object $site
     * @param array  $widget
     * @return bool
     */
    private static function is_unshared( object $site, array $widget ) : bool {
        if ( empty( $widget[ 'request' ] ) || null === $site->permissions ) {
            return false;
        }

        return ! in_array( $widget[ 'request' ], (array) $site->permissions, true );
    } // End is_unshared()


    /**
     * Check if two domains belong together (same domain, or one is a subdomain of the other).
     *
     * @param string $site_host
     * @param string $email_domain
     * @return bool
     */
    private static function domains_match( string $site_host, string $email_domain ) : bool {
        $site_host = preg_replace( '/:\d+$/', '', $site_host );

        if ( '' === $site_host || '' === $email_domain || $site_host === $email_domain ) {
            return true;
        }

        return str_ends_with( $site_host, '.' . $email_domain ) || str_ends_with( $email_domain, '.' . $site_host );
    } // End domains_match()


    /**
     * Get the state of every widget for a site: display value, link, warning and flags.
     *
     * @param object $site
     * @return array key => state
     */
    public static function widget_states( object $site ) : array {
        $widgets = Registry::widgets();
        $latest = self::latest();
        $checked = ! empty( $site->last_checked );
        $legacy = ! empty( $site->id ) ? Registry::legacy_host_widgets( (int) $site->id, (array) ( $site->integrations ?? [] ) ) : [];
        $states = [];

        foreach ( $widgets as $key => $widget ) {
            $state = [
                'key'      => $key,
                'label'    => $widget[ 'label' ],
                'type'     => $widget[ 'type' ],
                'wide'     => ! empty( $widget[ 'wide' ] ),
                'value'    => '',
                'link'     => '',
                'warning'  => '',
                'note'     => '',
                'unshared' => self::is_unshared( $site, $widget ),
                'ignored'  => in_array( $key, (array) $site->ignored, true ),
            ];

            if ( $state[ 'unshared' ] || ( ! $checked && ! in_array( $widget[ 'type' ], [ 'notes', 'connection' ], true ) ) ) {
                $state[ 'unknown' ] = 'integration' === $widget[ 'type' ];
                $states[ $key ] = $state;
                continue;
            }

            $state = self::fill_state( $state, $widget, $site, $latest, $legacy[ $key ] ?? null );
            $states[ $key ] = (array) apply_filters( 'prxctrl_widget_state', $state, $site, $widget );
        }

        return $states;
    } // End widget_states()


    /**
     * Fill in a widget's value, link and warning.
     *
     * @param array      $state
     * @param array      $widget
     * @param object     $site
     * @param array      $latest
     * @param array|null $legacy
     * @return array
     */
    private static function fill_state( array $state, array $widget, object $site, array $latest, ?array $legacy ) : array {
        $admin_path = (string) ( $site->admin_path ?? '' );

        switch ( $widget[ 'type' ] ) {
            case 'wp_version':
                $state[ 'value' ] = (string) $site->wordpress_version;

                if ( Helpers::is_outdated( $state[ 'value' ], $latest[ 'wordpress' ], (string) Options::get( 'wp_warning' ) ) ) {
                    $state[ 'warning' ] = sprintf(
                        /* translators: %s: latest WordPress version */
                        __( 'Latest is %s.', 'pluginrx-control-center' ),
                        $latest[ 'wordpress' ]
                    );
                }

                if ( ! empty( $site->core_update ) ) {
                    $state[ 'note' ] = sprintf(
                        /* translators: %s: WordPress version */
                        __( '%s available', 'pluginrx-control-center' ),
                        $site->core_update
                    );
                }

                if ( '' !== $state[ 'warning' ] || '' !== $state[ 'note' ] ) {
                    $state[ 'link' ] = Helpers::admin_link( $admin_path, 'update-core.php' );
                }
                break;

            case 'php_version':
                $state[ 'value' ] = (string) $site->php_version;

                if ( Helpers::is_outdated( $state[ 'value' ], $latest[ 'php' ], (string) Options::get( 'php_warning' ) ) ) {
                    $state[ 'warning' ] = sprintf(
                        /* translators: %s: latest PHP version */
                        __( 'Latest is %s.', 'pluginrx-control-center' ),
                        $latest[ 'php' ]
                    );
                }
                break;

            case 'plugins':
            case 'themes':
                $items = (array) ( 'plugins' === $widget[ 'type' ] ? ( $site->plugins ?? [] ) : ( $site->themes ?? [] ) );
                $updates = count( array_filter( $items, [ self::class, 'has_update' ] ) );
                $state[ 'count' ] = count( $items );
                $state[ 'updates' ] = $updates;
                $state[ 'value' ] = (string) count( $items );

                if ( $updates ) {
                    $state[ 'warning' ] = sprintf(
                        /* translators: %d: number of updates */
                        _n( '%d update available', '%d updates available', $updates, 'pluginrx-control-center' ),
                        $updates
                    );
                }

                if ( 'themes' === $widget[ 'type' ] ) {
                    foreach ( $items as $theme ) {
                        if ( ! empty( $theme->is_active ) ) {
                            $state[ 'note' ] = trim( html_entity_decode( (string) $theme->name, ENT_QUOTES ) . ' ' . $theme->version );
                        }
                    }
                }
                break;

            case 'admins':
                $state[ 'count' ] = count( (array) ( $site->admin_users ?? [] ) );
                $state[ 'value' ] = (string) $state[ 'count' ];
                $mine = self::current_admin_id( $site );
                if ( $mine ) {
                    $state[ 'note' ] = sprintf(
                        /* translators: %d: user ID */
                        __( 'Your User ID: %d', 'pluginrx-control-center' ),
                        $mine
                    );
                }
                $stale = self::stale_admins( $site );

                if ( $stale ) {
                    $state[ 'warning' ] = sprintf(
                        /* translators: 1: number of admins, 2: number of days */
                        _n( '%1$d admin inactive for over %2$d days.', '%1$d admins inactive for over %2$d days.', $stale, 'pluginrx-control-center' ),
                        $stale,
                        (int) Options::get( 'admin_inactive_days' )
                    );
                }
                break;

            case 'response':
                $status = (int) ( $site->http_status ?? 0 );
                $ms = (int) $site->response_ms;
                $state[ 'value' ] = $status ? sprintf(
                    /* translators: 1: HTTP status code, 2: response time in milliseconds */
                    __( '%1$d in %2$s ms', 'pluginrx-control-center' ),
                    $status,
                    number_format_i18n( $ms )
                ) : __( 'No response', 'pluginrx-control-center' );

                if ( ! $status || $status >= 400 ) {
                    $state[ 'warning' ] = __( 'The home page did not load.', 'pluginrx-control-center' );
                } else {
                    $rating = self::response_rating( $ms );
                    $state[ 'note' ] = $rating[ 0 ];
                    $state[ 'warning' ] = $rating[ 1 ];
                }

                $state[ 'link' ] = (string) $site->site_url;
                break;

            case 'ssl':
                $state = self::ssl_state( $state, $site );
                break;

            case 'admin_email':
                $state[ 'value' ] = (string) $site->admin_email;
                $email_domain = strtolower( (string) substr( (string) strrchr( $state[ 'value' ], '@' ), 1 ) );

                if ( Options::get( 'warn_admin_email' ) && '' !== $state[ 'value' ] && ! self::domains_match( Helpers::host( (string) $site->site_url ), $email_domain ) ) {
                    $state[ 'warning' ] = __( 'The email domain does not match the site.', 'pluginrx-control-center' );
                }
                break;

            case 'wp_debug':
                $state[ 'value' ] = ! empty( $site->wp_debug ) ? __( 'Enabled', 'pluginrx-control-center' ) : __( 'Disabled', 'pluginrx-control-center' );

                if ( ! empty( $site->wp_debug ) ) {
                    $state[ 'warning' ] = __( 'WP_DEBUG is on.', 'pluginrx-control-center' );
                    $state[ 'quiet' ] = true;
                    $state[ 'link' ] = Helpers::is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' ) ? Helpers::admin_link( $admin_path, 'admin.php?page=dev-debug-tools&tool=wpconfig' ) : '';
                }
                break;

            case 'bool':
                $state[ 'value' ] = ! empty( $site->{ $widget[ 'request' ] } ) ? __( 'Yes', 'pluginrx-control-center' ) : __( 'No', 'pluginrx-control-center' );
                break;

            case 'connection':
                $state = self::connection_state( $state, $site );
                break;

            case 'notes':
                $state[ 'value' ] = (string) ( $site->notes ?? '' );
                break;

            case 'integration':
                $state = self::integration_state( $state, $widget, $site, $legacy );
                break;

            default:
                $value = $site->{ $widget[ 'request' ] } ?? '';
                $state[ 'value' ] = is_scalar( $value ) ? (string) $value : '';
        }

        return $state;
    } // End fill_state()


    /**
     * Get the ID of the admin on a site who has the same email as the current user.
     *
     * @param object $site
     * @return int 0 when there is none.
     */
    private static function current_admin_id( object $site ) : int {
        $email = strtolower( (string) wp_get_current_user()->user_email );

        if ( '' === $email ) {
            return 0;
        }

        foreach ( (array) ( $site->admin_users ?? [] ) as $admin ) {
            if ( strtolower( (string) ( $admin->user_email ?? '' ) ) === $email ) {
                return (int) $admin->user_id;
            }
        }

        return 0;
    } // End current_admin_id()


    /**
     * Check if a plugin or theme row has an update.
     *
     * @param object $item
     * @return bool
     */
    public static function has_update( $item ) : bool {
        return ! empty( $item->update_available );
    } // End has_update()


    /**
     * Rate a response time.
     *
     * @param int $ms
     * @return array [ rating label, warning ] The warning is empty when the time is acceptable.
     */
    public static function response_rating( int $ms ) : array {
        if ( $ms <= 400 ) {
            return [ __( 'Excellent', 'pluginrx-control-center' ), '' ];
        }

        if ( $ms <= 1000 ) {
            return [ __( 'Good', 'pluginrx-control-center' ), '' ];
        }

        if ( $ms <= 2000 ) {
            return [ __( 'Fair', 'pluginrx-control-center' ), '' ];
        }

        if ( $ms <= 3000 ) {
            return [ __( 'Slow', 'pluginrx-control-center' ), __( 'Takes more than 2 seconds.', 'pluginrx-control-center' ) ];
        }

        return [ __( 'Very slow', 'pluginrx-control-center' ), __( 'Takes more than 3 seconds.', 'pluginrx-control-center' ) ];
    } // End response_rating()


    /**
     * Fill the SSL widget.
     *
     * @param array  $state
     * @param object $site
     * @return array
     */
    private static function ssl_state( array $state, object $site ) : array {
        if ( 0 !== strpos( (string) $site->site_url, 'https://' ) ) {
            $state[ 'value' ] = __( 'Not using HTTPS', 'pluginrx-control-center' );
            $state[ 'warning' ] = __( 'Visitors connect without encryption.', 'pluginrx-control-center' );
            return $state;
        }

        if ( empty( $site->ssl_expires ) ) {
            $state[ 'value' ] = empty( $site->ssl_checked ) ? __( 'Not checked yet', 'pluginrx-control-center' ) : __( 'Invalid', 'pluginrx-control-center' );
            $state[ 'warning' ] = empty( $site->ssl_checked ) ? '' : __( 'The certificate could not be verified.', 'pluginrx-control-center' );
            return $state;
        }

        $expires = strtotime( (string) $site->ssl_expires . ' ' . wp_timezone_string() );
        $days = (int) floor( ( $expires - time() ) / DAY_IN_SECONDS );
        $state[ 'value' ] = Helpers::format_date( (string) $site->ssl_expires );

        if ( $days < 0 ) {
            $state[ 'warning' ] = __( 'Expired.', 'pluginrx-control-center' );
        } elseif ( $days <= (int) Options::get( 'ssl_warning_days' ) ) {
            $state[ 'warning' ] = sprintf(
                /* translators: %d: number of days */
                _n( 'Expires in %d day.', 'Expires in %d days.', $days, 'pluginrx-control-center' ),
                $days
            );
        } else {
            $state[ 'note' ] = sprintf(
                /* translators: %d: number of days */
                _n( 'In %d day', 'In %d days', $days, 'pluginrx-control-center' ),
                $days
            );
        }

        return $state;
    } // End ssl_state()


    /**
     * Fill the Connection widget.
     *
     * @param array  $state
     * @param object $site
     * @return array
     */
    private static function connection_state( array $state, object $site ) : array {
        $protocol = (string) ( $site->protocol ?? '' );

        if ( 'local' === $protocol || Client::is_local( $site ) ) {
            $state[ 'value' ] = __( 'Local (same install)', 'pluginrx-control-center' );
        } elseif ( 'v2' === $protocol ) {
            $state[ 'value' ] = __( 'Signed', 'pluginrx-control-center' );
        } elseif ( 'v1' === $protocol ) {
            $state[ 'value' ] = __( 'Legacy', 'pluginrx-control-center' );
            $state[ 'warning' ] = __( 'Runs the older PluginRx Agent. Install PluginRx Control Center there to use signed requests.', 'pluginrx-control-center' );
        } else {
            $state[ 'value' ] = __( 'Not checked yet', 'pluginrx-control-center' );
        }

        if ( ! empty( $site->agent_version ) ) {
            $state[ 'note' ] = sprintf(
                /* translators: %s: plugin version */
                __( 'Version %s', 'pluginrx-control-center' ),
                $site->agent_version
            );
        }

        return $state;
    } // End connection_state()


    /**
     * Fill an integration widget.
     *
     * @param array      $state
     * @param array      $widget
     * @param object     $site
     * @param array|null $legacy
     * @return array
     */
    private static function integration_state( array $state, array $widget, object $site, ?array $legacy ) : array {
        $raw = $site->integrations[ $widget[ 'request' ] ] ?? null;
        $value = Registry::value( $site, $widget[ 'request' ] );
        $link = is_array( $raw ) && ! empty( $raw[ 'link' ] ) ? (string) $raw[ 'link' ] : (string) ( $legacy[ 'link' ] ?? $widget[ 'link' ] ?? '' );
        $format = (string) ( $legacy[ 'format' ] ?? $widget[ 'format' ] ?? 'text' );
        $has_value = Registry::has_value( $value );

        if ( null === $value ) {
            $state[ 'value' ] = '';
            $state[ 'unknown' ] = true;
        } elseif ( Registry::NOT_ACTIVE === $value ) {
            $state[ 'value' ] = __( 'Plugin not active', 'pluginrx-control-center' );
            $state[ 'inactive' ] = true;
        } elseif ( 'filesize' === $format && is_numeric( $value ) ) {
            $state[ 'value' ] = (string) size_format( (int) $value );
        } elseif ( 'number' === $format && is_numeric( $value ) ) {
            $state[ 'value' ] = number_format_i18n( (float) $value );
        } elseif ( is_bool( $value ) ) {
            $state[ 'value' ] = $value ? __( 'Yes', 'pluginrx-control-center' ) : __( 'No', 'pluginrx-control-center' );
        } else {
            $state[ 'value' ] = is_scalar( $value ) ? (string) $value : '';
        }

        if ( $has_value ) {
            $state[ 'link' ] = Helpers::admin_link( (string) ( $site->admin_path ?? '' ), $link );
        }

        $warn = false;

        if ( null !== $legacy ) {
            $warn = ! empty( $legacy[ 'warn' ] ) && $has_value;
        } elseif ( is_callable( $widget[ 'warn' ] ?? null ) && Registry::NOT_ACTIVE !== $value && null !== $value ) {
            $warn = (bool) call_user_func( $widget[ 'warn' ], $value, $site );
        }

        if ( $warn ) {
            $state[ 'warning' ] = __( 'Needs attention.', 'pluginrx-control-center' );
        }

        return $state;
    } // End integration_state()


    /**
     * Count the warnings that are not ignored.
     *
     * @param array $states
     * @return int
     */
    public static function count_warnings( array $states ) : int {
        $count = 0;

        foreach ( Registry::widget_layout()[ 'enabled' ] as $key ) {
            if ( isset( $states[ $key ] ) && '' !== $states[ $key ][ 'warning' ] && ! $states[ $key ][ 'ignored' ] ) {
                $count++;
            }
        }

        return $count;
    } // End count_warnings()


    /**
     * Get the action buttons for a site with their availability.
     *
     * @param object $site
     * @return array key => [ label, waiting, enabled, reason ]
     */
    public static function buttons( object $site ) : array {
        $buttons = [];
        $checked = 'ok' === ( $site->status ?? '' );
        $fresh = self::is_fresh( $site );
        $active = self::active_integrations( $site );
        $actions = Registry::actions();
        $ordered = array_merge( array_diff( array_keys( $actions ), [ 'update_wordpress', 'update_themes', 'update_plugins' ] ), [ 'update_wordpress', 'update_themes', 'update_plugins' ] );

        foreach ( $ordered as $key ) {
            $action = $actions[ $key ];
            $allowed = null === $site->permissions || in_array( $key, (array) $site->permissions, true );
            $available = $checked && ( ! is_callable( $action[ 'available' ] ?? null ) ? self::default_available( $site, $action ) : (bool) call_user_func( $action[ 'available' ], $site ) );

            if ( ! $checked ) {
                $reason = __( 'Check the site first.', 'pluginrx-control-center' );
            } elseif ( ! $fresh ) {
                $reason = __( 'Check the site again to use actions, so they run on up-to-date information.', 'pluginrx-control-center' );
            } elseif ( ! empty( $action[ 'integration' ] ) && isset( $active[ $action[ 'integration' ] ] ) && ! $active[ $action[ 'integration' ] ] ) {
                $reason = __( 'The plugin for this action is not active on this site.', 'pluginrx-control-center' );
            } elseif ( ! $allowed ) {
                $reason = __( 'Not allowed in the agent\'s permissions.', 'pluginrx-control-center' );
            } elseif ( ! $available ) {
                $reason = __( 'Nothing to do right now.', 'pluginrx-control-center' );
            } else {
                $reason = '';
            }

            $buttons[ $key ] = [
                'hidden'  => $checked && ! empty( $action[ 'integration' ] ) && isset( $active[ $action[ 'integration' ] ] ) && ! $active[ $action[ 'integration' ] ],
                'label'   => (string) $action[ 'label' ],
                'waiting' => (string) ( $action[ 'waiting' ] ?? __( 'Working...', 'pluginrx-control-center' ) ),
                'enabled' => '' === $reason,
                'reason'  => $reason,
            ];
        }

        return $buttons;
    } // End buttons()


    /**
     * Check if a site was checked successfully recently enough to run actions.
     *
     * @param object $site
     * @return bool
     */
    public static function is_fresh( object $site ) : bool {
        $window = (int) apply_filters( 'prxctrl_action_window', HOUR_IN_SECONDS, $site );
        $checked = Helpers::timestamp( (string) ( $site->last_checked ?? '' ) );

        return 'ok' === ( $site->status ?? '' ) && $checked > 0 && ( time() - $checked ) <= $window;
    } // End is_fresh()


    /**
     * Count the admins who have not been active within the warning window.
     *
     * @param object $site
     * @return int
     */
    public static function stale_admins( object $site ) : int {
        $count = 0;

        foreach ( (array) ( $site->admin_users ?? [] ) as $admin ) {
            if ( self::is_stale_admin( $admin ) ) {
                $count++;
            }
        }

        return $count;
    } // End stale_admins()


    /**
     * Check if an admin has not been active within the warning window.
     *
     * @param object $admin
     * @return bool
     */
    private static function is_stale_admin( object $admin ) : bool {
        $days = (int) Options::get( 'admin_inactive_days' );
        $online = (string) ( $admin->online_status ?? '' );

        return $days > 0 && ctype_digit( $online ) && (int) $online < time() - ( $days * DAY_IN_SECONDS );
    } // End is_stale_admin()


    /**
     * Get which integrations are active on a site, when the agent reported it.
     *
     * @param object $site
     * @return array slug => bool. Agents before 2.0 do not report it, so it is worked out from the
     *               values they share; integrations with nothing shared are left out.
     */
    public static function active_integrations( object $site ) : array {
        $other = Helpers::json_array( $site->other ?? '' );

        if ( isset( $other[ 'integrations' ] ) && is_array( $other[ 'integrations' ] ) ) {
            return $other[ 'integrations' ];
        }

        $active = [];

        foreach ( Registry::integrations() as $slug => $integration ) {
            foreach ( array_keys( (array) $integration[ 'requests' ] ) as $request_key ) {
                $value = Registry::value( $site, $request_key );

                if ( null !== $value ) {
                    $active[ $slug ] = Registry::NOT_ACTIVE !== $value;
                    break;
                }
            }
        }

        return $active;
    } // End active_integrations()


    /**
     * Default availability for an integration action: its integration's first request has a value.
     *
     * @param object $site
     * @param array  $action
     * @return bool
     */
    private static function default_available( object $site, array $action ) : bool {
        if ( empty( $action[ 'integration' ] ) || 'legacy' === $action[ 'integration' ] ) {
            return true;
        }

        $integration = Registry::integrations()[ $action[ 'integration' ] ] ?? [];

        foreach ( array_keys( (array) ( $integration[ 'requests' ] ?? [] ) ) as $request_key ) {
            return Registry::NOT_ACTIVE !== Registry::value( $site, $request_key );
        }

        return true;
    } // End default_available()


    /**
     * Get the quick links for a site: Login plus any link fields with a value.
     *
     * @param object $site
     * @return array label => url
     */
    public static function links( object $site ) : array {
        $login = ! empty( $site->login_url ) ? (string) $site->login_url : trailingslashit( (string) $site->site_url ) . 'wp-login.php';
        $links = [ __( 'Login Page', 'pluginrx-control-center' ) => $login ];

        foreach ( (array) Options::get( 'link_fields' ) as $field ) {
            $url = (string) ( $site->links[ $field[ 'slug' ] ] ?? '' );

            if ( '' !== $url ) {
                $links[ $field[ 'label' ] ] = $url;
            }
        }

        return (array) apply_filters( 'prxctrl_site_links', $links, $site );
    } // End links()


    /**
     * Explain why Remote Login is not available for a site.
     *
     * @param object $site
     * @return string Empty when it is available.
     */
    public static function remote_login_reason( object $site ) : string {
        if ( Client::is_local( $site ) ) {
            return ! empty( $site->admin_path ) ? '' : __( 'Check the site first.', 'pluginrx-control-center' );
        }

        if ( 'ok' !== ( $site->status ?? '' ) ) {
            return __( 'Check the site first. Remote Login is available once the check succeeds.', 'pluginrx-control-center' );
        }

        if ( 'v1' === ( $site->protocol ?? '' ) ) {
            return __( 'This site runs the older PluginRx Agent plugin. Install PluginRx Control Center on it to use Remote Login.', 'pluginrx-control-center' );
        }

        $other = Helpers::json_array( $site->other ?? '' );

        if ( ! is_array( $site->permissions ) || ! in_array( 'remote_login', $site->permissions, true ) || empty( $other[ 'login_ready' ] ) ) {
            return __( 'Remote Login is off on this site. In its Settings > Agent, turn on Allow Remote Login and choose an account under Log In As, then check the site again.', 'pluginrx-control-center' );
        }

        return '';
    } // End remote_login_reason()


    /**
     * Render the status and warnings pills.
     *
     * @param array $status [ class, icon, label ]
     * @param int   $warnings
     * @return void
     */
    private static function render_badges( array $status, int $warnings ) : void {
        ?>
        <div class="prxctrl-site-badges">
            <span class="prxctrl-status-pill <?php echo esc_attr( $status[ 0 ] ); ?>"><span class="dashicons <?php echo esc_attr( $status[ 1 ] ); ?>" aria-hidden="true"></span> <span class="prxctrl-status-text"><?php echo esc_html( $status[ 2 ] ); ?></span></span>
            <?php if ( $warnings ) : ?>
                <span class="prxctrl-warning-pill"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php echo esc_html( sprintf( /* translators: %d: number of warnings */ _n( '%d warning', '%d warnings', $warnings, 'pluginrx-control-center' ), $warnings ) ); ?></span>
            <?php endif; ?>
        </div>
        <?php
    } // End render_badges()


    /**
     * Get a status label, icon and class.
     *
     * @param object $site
     * @return array [ class, icon, label ]
     */
    private static function status( object $site ) : array {
        switch ( $site->status ?? '' ) {
            case 'ok':
                return [ 'is-ok', 'dashicons-yes-alt', __( 'Online', 'pluginrx-control-center' ) ];
            case 'error':
                return [ 'is-error', 'dashicons-dismiss', __( 'Error', 'pluginrx-control-center' ) ];
            default:
                return [ 'is-never', 'dashicons-marker', __( 'Not checked yet', 'pluginrx-control-center' ) ];
        }
    } // End status()


    /**
     * Print the hidden attribute when a display key is hidden.
     *
     * @param string $key
     * @param array  $hidden
     * @return void
     */
    private static function hidden_attr( string $key, array $hidden ) : void {
        if ( in_array( $key, $hidden, true ) ) {
            echo ' hidden';
        }
    } // End hidden_attr()


    /**
     * Render a site card.
     *
     * @param object $site    Site with details attached.
     * @param string $context host|agent|preview
     * @return void
     */
    public static function render( object $site, string $context = self::HOST ) : void {
        $states = self::widget_states( $site );
        $layout = Registry::widget_layout();

        if ( self::AGENT === $context ) {
            foreach ( Registry::widgets() as $key => $widget ) {
                if ( empty( $widget[ 'request' ] ) && ! in_array( $widget[ 'type' ], [ 'response', 'ssl', 'connection' ], true ) ) {
                    unset( $states[ $key ] );
                }
            }
        }

        $hidden = self::PREVIEW === $context ? [] : self::hidden_keys();
        $warnings = self::count_warnings( $states );
        $status = self::status( $site );
        $screenshot = Client::screenshot_url( $site );
        $site_id = (int) $site->id;
        $name = '' !== $site->site_name ? $site->site_name : Helpers::host( (string) $site->site_url );
        ?>
        <section class="prxctrl-site <?php echo esc_attr( $status[ 0 ] ); ?>" id="prxctrl-site-<?php echo esc_attr( (string) $site_id ); ?>" data-site-id="<?php echo esc_attr( (string) $site_id ); ?>" data-group-id="<?php echo esc_attr( (string) $site->group_id ); ?>" data-name="<?php echo esc_attr( $name ); ?>" data-screenshot="<?php echo esc_url( $screenshot ); ?>">
            <header class="prxctrl-site-header">
                <button type="button" class="prxctrl-site-toggle" aria-expanded="true" aria-controls="prxctrl-site-body-<?php echo esc_attr( (string) $site_id ); ?>">
                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e( 'Show or hide details', 'pluginrx-control-center' ); ?></span>
                </button>

                <?php if ( '' !== $screenshot ) : ?>
                    <div class="prxctrl-site-thumbnail" data-display="screenshot"<?php self::hidden_attr( 'screenshot', $hidden ); ?>>
                        <img src="<?php echo esc_url( $screenshot ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: site name */ __( 'Screenshot of %s', 'pluginrx-control-center' ), $name ) ); ?>" referrerpolicy="no-referrer" loading="lazy" width="120" height="90">
                    </div>
                <?php endif; ?>

                <div class="prxctrl-site-heading">
                    <h2 class="prxctrl-site-title"><?php echo esc_html( $name ); ?></h2>
                    <a href="<?php echo esc_url( (string) $site->site_url ); ?>" class="prxctrl-site-url" target="_blank" rel="noopener noreferrer"><?php echo esc_html( Helpers::host( (string) $site->site_url ) ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
                    <?php if ( self::PREVIEW !== $context ) : ?>
                        <div class="prxctrl-site-links" data-display="links"<?php self::hidden_attr( 'links', $hidden ); ?>>
                            <?php foreach ( self::links( $site ) as $label => $url ) : ?>
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( self::PREVIEW === $context ) : ?>
                    <?php self::render_badges( $status, $warnings ); ?>
                <?php endif; ?>

                <?php if ( self::PREVIEW !== $context ) : ?>
                    <div class="prxctrl-site-actions">
                        <?php self::render_badges( $status, $warnings ); ?>
                        <div class="prxctrl-site-buttons">
                            <?php if ( self::HOST === $context ) : ?>
                                <?php foreach ( self::buttons( $site ) as $key => $button ) : ?>
                                    <?php if ( $button[ 'hidden' ] ) { continue; } ?>
                                    <button type="button" class="prxctrl-button prxctrl-button-secondary prxctrl-action-button" data-action="<?php echo esc_attr( $key ); ?>" data-wait="<?php echo esc_attr( $button[ 'waiting' ] ); ?>" data-display="<?php echo esc_attr( $key ); ?>"<?php echo $button[ 'enabled' ] ? '' : ' aria-disabled="true" data-reason="' . esc_attr( $button[ 'reason' ] ) . '"'; ?><?php self::hidden_attr( $key, $hidden ); ?>><?php echo esc_html( $button[ 'label' ] ); ?></button>
                                <?php endforeach; ?>
                                <?php $login_reason = self::remote_login_reason( $site ); ?>
                                <button type="button" class="prxctrl-button prxctrl-button-secondary prxctrl-login-button" data-display="login"<?php echo '' === $login_reason ? '' : ' aria-disabled="true" data-reason="' . esc_attr( $login_reason ) . '"'; ?><?php self::hidden_attr( 'login', $hidden ); ?>><span class="dashicons dashicons-admin-network" aria-hidden="true"></span> <?php esc_html_e( 'Remote Login', 'pluginrx-control-center' ); ?></button>
                            <?php endif; ?>
                            <button type="button" class="prxctrl-button prxctrl-check-site-button" data-wait="<?php esc_attr_e( 'Checking...', 'pluginrx-control-center' ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Check Site', 'pluginrx-control-center' ); ?></button>
                        </div>
                        <div class="prxctrl-last-checked">
                            <?php
                            if ( ! empty( $site->last_checked ) ) {
                                /* translators: %s: date and time */
                                echo esc_html( sprintf( __( 'Last checked %s', 'pluginrx-control-center' ), Helpers::format_date( (string) $site->last_checked ) ) );

                                if ( self::HOST === $context && 'ok' === ( $site->status ?? '' ) && ! self::is_fresh( $site ) ) {
                                    echo ' <span class="prxctrl-stale-note">' . esc_html__( 'Check the site again to use actions.', 'pluginrx-control-center' ) . '</span>';
                                }
                            } elseif ( 'error' === ( $site->status ?? '' ) ) {
                                esc_html_e( 'Never checked successfully', 'pluginrx-control-center' );
                            } else {
                                esc_html_e( 'Never checked', 'pluginrx-control-center' );
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </header>

            <div class="prxctrl-site-message<?php echo 'error' === ( $site->status ?? '' ) && ! empty( $site->last_error ) ? ' is-error' : ''; ?>" role="status"<?php echo 'error' === ( $site->status ?? '' ) && ! empty( $site->last_error ) ? '' : ' hidden'; ?>>
                <?php
                if ( 'error' === ( $site->status ?? '' ) && ! empty( $site->last_error ) ) {
                    echo esc_html( (string) $site->last_error );

                    if ( ! empty( $site->id ) && self::PREVIEW !== $context ) {
                        echo ' <a href="' . esc_url( Helpers::page_url( 'logs', [ 'site' => (int) $site->id, 'status' => 'error' ] ) ) . '">' . esc_html__( 'View logs', 'pluginrx-control-center' ) . '</a>';
                    }
                }
                ?>
            </div>

            <div class="prxctrl-site-body" id="prxctrl-site-body-<?php echo esc_attr( (string) $site_id ); ?>">
                <div class="prxctrl-widgets<?php echo self::PREVIEW === $context ? ' prxctrl-widget-board' : ''; ?>">
                    <?php
                    foreach ( $layout[ 'enabled' ] as $key ) {
                        if ( isset( $states[ $key ] ) ) {
                            self::render_widget( $states[ $key ], $site, $context, $hidden );
                        }
                    }
                    ?>
                </div>

                <?php if ( self::PREVIEW !== $context ) : ?>
                    <?php self::render_tables( $site ); ?>
                    <?php do_action( 'prxctrl_site_card_after', $site, $context ); ?>
                <?php endif; ?>
            </div>
        </section>
        <?php
    } // End render()


    /**
     * Render one widget.
     *
     * @param array  $state
     * @param object $site
     * @param string $context
     * @param array  $hidden
     * @return void
     */
    public static function render_widget( array $state, object $site, string $context, array $hidden = [] ) : void {
        $classes = [ 'prxctrl-widget', 'prxctrl-widget-' . sanitize_html_class( $state[ 'type' ] ) ];
        $warning = '' !== $state[ 'warning' ];

        if ( $warning && ! $state[ 'ignored' ] ) {
            $classes[] = 'is-warning';
        }

        if ( $warning && $state[ 'ignored' ] ) {
            $classes[] = 'is-ignored';
        }

        if ( $state[ 'unshared' ] ) {
            $classes[] = 'is-unshared';
        }

        if ( $state[ 'wide' ] ) {
            $classes[] = 'is-wide';
        }

        if ( ! empty( $state[ 'inactive' ] ) ) {
            $classes[] = 'is-inactive';
        }

        $unavailable = 'integration' === $state[ 'type' ] && ( $state[ 'unshared' ] || ! empty( $state[ 'inactive' ] ) || ! empty( $state[ 'unknown' ] ) );
        $is_hidden = in_array( $state[ 'key' ], $hidden, true ) || ( $unavailable && in_array( 'unavailable', $hidden, true ) );
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-widget="<?php echo esc_attr( $state[ 'key' ] ); ?>" data-display="<?php echo esc_attr( $state[ 'key' ] ); ?>"<?php echo $unavailable ? ' data-display-group="unavailable"' : ''; ?><?php echo $is_hidden ? ' hidden' : ''; ?>>
            <div class="prxctrl-widget-label">
                <?php if ( self::PREVIEW === $context ) : ?>
                    <span class="dashicons dashicons-move prxctrl-widget-handle" aria-hidden="true"></span>
                <?php elseif ( $warning && ! $state[ 'ignored' ] ) : ?>
                    <span class="dashicons dashicons-warning prxctrl-widget-icon" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="prxctrl-widget-title"><?php echo esc_html( $state[ 'label' ] ); ?></span>
                <?php if ( self::HOST === $context && $warning ) : ?>
                    <button type="button" class="prxctrl-ignore-warning" data-widget="<?php echo esc_attr( $state[ 'key' ] ); ?>" data-ignored="<?php echo $state[ 'ignored' ] ? '1' : '0'; ?>">
                        <span class="dashicons <?php echo $state[ 'ignored' ] ? 'dashicons-visibility' : 'dashicons-hidden'; ?>" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php echo $state[ 'ignored' ] ? esc_html__( 'Show this warning again for this site', 'pluginrx-control-center' ) : esc_html__( 'Ignore this warning for this site', 'pluginrx-control-center' ); ?></span>
                    </button>
                <?php endif; ?>
            </div>
            <div class="prxctrl-widget-value">
                <?php self::render_widget_value( $state, $site, $context ); ?>
            </div>
            <?php if ( $warning && ! $state[ 'unshared' ] && empty( $state[ 'quiet' ] ) ) : ?>
                <div class="prxctrl-widget-warning"><?php echo esc_html( $state[ 'warning' ] ); ?><?php echo $state[ 'ignored' ] ? ' ' . esc_html__( '(ignored)', 'pluginrx-control-center' ) : ''; ?></div>
            <?php endif; ?>
        </div>
        <?php
    } // End render_widget()


    /**
     * Render a widget's value.
     *
     * @param array  $state
     * @param object $site
     * @param string $context
     * @return void
     */
    private static function render_widget_value( array $state, object $site, string $context ) : void {
        if ( $state[ 'unshared' ] ) {
            echo '<span class="prxctrl-muted">' . esc_html__( 'Not shared', 'pluginrx-control-center' ) . '</span>';
            return;
        }

        if ( 'notes' === $state[ 'type' ] ) {
            self::render_notes( $state, $context );
            return;
        }

        if ( '' === $state[ 'value' ] && empty( $state[ 'count' ] ) ) {
            echo '<span class="prxctrl-muted">&mdash;</span>';
            return;
        }

        if ( in_array( $state[ 'type' ], [ 'plugins', 'themes', 'admins' ], true ) ) {
            $table = 'admins' === $state[ 'type' ] ? 'admin_users' : $state[ 'type' ];
            ?>
            <button type="button" class="prxctrl-table-toggle" data-table="<?php echo esc_attr( $table ); ?>" aria-expanded="false"<?php echo self::PREVIEW === $context ? ' disabled' : ''; ?>>
                <span class="prxctrl-count"><?php echo esc_html( number_format_i18n( (int) ( $state[ 'count' ] ?? 0 ) ) ); ?></span>
                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
            </button>
            <?php if ( ! empty( $state[ 'note' ] ) ) : ?>
                <span class="prxctrl-widget-note"><?php echo esc_html( $state[ 'note' ] ); ?></span>
            <?php endif; ?>
            <?php
            return;
        }

        $value = $state[ 'value' ];

        if ( '' !== $state[ 'link' ] && self::PREVIEW !== $context ) {
            echo '<a href="' . esc_url( $state[ 'link' ] ) . '" target="_blank" rel="noopener noreferrer" class="prxctrl-widget-link">' . esc_html( $value ) . '</a>';
        } else {
            echo '<span class="prxctrl-widget-text">' . esc_html( $value ) . '</span>';
        }

        if ( ! empty( $state[ 'note' ] ) ) {
            echo '<span class="prxctrl-widget-note">' . esc_html( $state[ 'note' ] ) . '</span>';
        }
    } // End render_widget_value()


    /**
     * Render the notes widget.
     *
     * @param array  $state
     * @param string $context
     * @return void
     */
    private static function render_notes( array $state, string $context ) : void {
        ?>
        <div class="prxctrl-notes">
            <div class="prxctrl-notes-text"><?php echo '' !== $state[ 'value' ] ? nl2br( esc_html( $state[ 'value' ] ) ) : '<span class="prxctrl-muted">' . esc_html__( 'No notes yet.', 'pluginrx-control-center' ) . '</span>'; ?></div>
            <?php if ( self::HOST === $context ) : ?>
                <button type="button" class="prxctrl-notes-edit"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Edit notes', 'pluginrx-control-center' ); ?></span></button>
                <div class="prxctrl-notes-editor" hidden>
                    <textarea rows="4" aria-label="<?php esc_attr_e( 'Notes', 'pluginrx-control-center' ); ?>"><?php echo esc_textarea( $state[ 'value' ] ); ?></textarea>
                    <div class="prxctrl-inline-actions">
                        <button type="button" class="prxctrl-button prxctrl-button-small prxctrl-notes-save"><?php esc_html_e( 'Save', 'pluginrx-control-center' ); ?></button>
                        <button type="button" class="prxctrl-button prxctrl-button-small prxctrl-button-secondary prxctrl-notes-cancel"><?php esc_html_e( 'Cancel', 'pluginrx-control-center' ); ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    } // End render_notes()


    /**
     * Render the plugins, themes and admins tables.
     *
     * @param object $site
     * @return void
     */
    private static function render_tables( object $site ) : void {
        $admin_path = (string) ( $site->admin_path ?? '' );
        $yes = __( 'Yes', 'pluginrx-control-center' );
        $no = __( 'No', 'pluginrx-control-center' );
        ?>
        <div class="prxctrl-site-table" data-table="plugins" hidden>
            <table class="widefat striped">
                <thead><tr><th scope="col"><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Version', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Author', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'File', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Active', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Update', 'pluginrx-control-center' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( (array) ( $site->plugins ?? [] ) as $plugin ) : ?>
                        <tr class="<?php echo esc_attr( ( $plugin->is_active ? 'is-active' : 'is-inactive' ) . ( $plugin->update_available ? ' has-update' : '' ) ); ?>">
                            <td><?php echo esc_html( html_entity_decode( (string) $plugin->name, ENT_QUOTES ) ); ?></td>
                            <td><?php echo esc_html( (string) $plugin->version ); ?></td>
                            <td><?php echo esc_html( html_entity_decode( (string) $plugin->author, ENT_QUOTES ) ); ?></td>
                            <td><code><?php echo esc_html( (string) $plugin->slug ); ?></code></td>
                            <td><?php echo esc_html( $plugin->is_active ? $yes : $no ); ?></td>
                            <td><?php echo $plugin->update_available ? '<span class="prxctrl-update-flag"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html( $yes ) . '</span>' : esc_html( $no ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="prxctrl-site-table" data-table="themes" hidden>
            <table class="widefat striped">
                <thead><tr><th scope="col"><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Version', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Author', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Active', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Update', 'pluginrx-control-center' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( (array) ( $site->themes ?? [] ) as $theme ) : ?>
                        <tr class="<?php echo esc_attr( ( $theme->is_active ? 'is-active' : 'is-inactive' ) . ( $theme->update_available ? ' has-update' : '' ) ); ?>">
                            <td><?php echo esc_html( html_entity_decode( (string) $theme->name, ENT_QUOTES ) ); ?></td>
                            <td><?php echo esc_html( (string) $theme->version ); ?></td>
                            <td><?php echo esc_html( html_entity_decode( (string) $theme->author, ENT_QUOTES ) ); ?></td>
                            <td><?php echo esc_html( $theme->is_active ? $yes : $no ); ?></td>
                            <td><?php echo $theme->update_available ? '<span class="prxctrl-update-flag"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html( $yes ) . '</span>' : esc_html( $no ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="prxctrl-site-table" data-table="admin_users" hidden>
            <table class="widefat striped">
                <thead><tr><th scope="col"><?php esc_html_e( 'Username', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Email', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Role', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Developer', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'ID', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Registered', 'pluginrx-control-center' ); ?></th><th scope="col"><?php esc_html_e( 'Last Active', 'pluginrx-control-center' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( (array) ( $site->admin_users ?? [] ) as $admin ) : ?>
                        <?php
                        $roles = Helpers::json_array( (string) $admin->role );
                        $online = (string) $admin->online_status;
                        $edit_link = Helpers::admin_link( $admin_path, 'user-edit.php?user_id=' . absint( $admin->user_id ) );

                        if ( 'online' === $online ) {
                            $online_label = __( 'Online now', 'pluginrx-control-center' );
                        } elseif ( ctype_digit( $online ) ) {
                            $online_label = Helpers::format_date( (int) $online );
                        } else {
                            $online_label = __( 'Unknown', 'pluginrx-control-center' );
                        }
                        ?>
                        <tr class="<?php echo esc_attr( ( 'online' === $online ? 'is-online' : 'is-offline' ) . ( self::is_stale_admin( $admin ) ? ' is-stale' : '' ) ); ?>">
                            <td><?php echo '' !== $edit_link ? '<a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $admin->user_login ) . '</a>' : esc_html( (string) $admin->user_login ); ?></td>
                            <td><?php echo esc_html( (string) $admin->display_name ); ?></td>
                            <td><?php echo esc_html( (string) $admin->user_email ); ?></td>
                            <td><?php echo esc_html( implode( ', ', array_map( [ Helpers::class, 'humanize' ], $roles ) ) ); ?></td>
                            <td><?php echo esc_html( $admin->is_dev ? $yes : $no ); ?></td>
                            <td><?php echo esc_html( (string) $admin->user_id ); ?></td>
                            <td><?php echo esc_html( Helpers::format_date( (string) $admin->user_registered ) ); ?></td>
                            <td><?php echo esc_html( $online_label ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    } // End render_tables()


    /**
     * Build a sample site for the widget manager preview.
     *
     * @return object
     */
    public static function sample_site() : object {
        $latest = self::latest();
        $plugin = (object) [ 'name' => 'Sample Plugin', 'version' => '1.0', 'author' => 'PluginRx', 'slug' => 'sample/sample.php', 'is_active' => 1, 'update_available' => 0 ];
        $theme = (object) [ 'name' => 'Sample Theme', 'version' => '2.1', 'author' => 'PluginRx', 'slug' => 'sample', 'is_active' => 1, 'update_available' => 0 ];
        $integrations = [];

        foreach ( Registry::widgets() as $key => $widget ) {
            if ( 'integration' === $widget[ 'type' ] ) {
                $integrations[ $key ] = 'filesize' === ( $widget[ 'format' ] ?? '' ) ? 524288 : 3;
            }
        }

        return (object) [
            'id'                => 0,
            'site_name'         => __( 'Example Site', 'pluginrx-control-center' ),
            'site_url'          => 'https://example.com',
            'group_id'          => 0,
            'status'            => 'ok',
            'protocol'          => 'v2',
            'agent_version'     => Bootstrap::version(),
            'permissions'       => null,
            'ignored'           => [],
            'links'             => [],
            'login_url'         => '',
            'admin_path'        => 'https://example.com/wp-admin/',
            'admin_email'       => 'admin@example.com',
            'server_ip'         => '203.0.113.10',
            'abspath'           => '/var/www/html/',
            'is_multisite'      => 0,
            'blog_id'           => 1,
            'wordpress_version' => '' !== $latest[ 'wordpress' ] ? $latest[ 'wordpress' ] : '7.1',
            'core_update'       => '',
            'php_version'       => '' !== $latest[ 'php' ] ? $latest[ 'php' ] : '8.4',
            'wp_debug'          => 0,
            'http_status'       => 200,
            'response_ms'       => 420,
            'ssl_expires'       => wp_date( 'Y-m-d H:i:s', time() + 90 * DAY_IN_SECONDS ),
            'ssl_checked'       => Helpers::now(),
            'screenshot_ver'    => 0,
            'notes'             => __( 'Notes you add to a site show here.', 'pluginrx-control-center' ),
            'last_checked'      => Helpers::now(),
            'last_error'        => '',
            'other'             => '',
            'plugins'           => array_fill( 0, 12, $plugin ),
            'themes'            => [ $theme ],
            'admin_users'       => [ (object) [] , (object) [] ],
            'integrations'      => $integrations,
        ];
    } // End sample_site()

} // End class SiteCard
