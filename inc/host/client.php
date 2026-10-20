<?php
/**
 * CLIENT
 *
 * Talks to agents. Uses signed v2 requests, falls back to v1 for agents that have not been updated
 * yet, and turns failures into plain-language diagnostics. Sites on the same install (this site, or
 * a subsite managed from the network admin) are read directly instead of over HTTP.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Client {

    /**
     * How long to skip the v2 attempt for an agent that only speaks v1.
     */
    public const V1_CACHE = 6 * HOUR_IN_SECONDS;


    /**
     * mShots screenshot service base URL.
     */
    public const MSHOTS = 'https://s0.wordpress.com/mshots/v1/';


    /**
     * Get this Control Center's host name, as sent to agents.
     *
     * @return string
     */
    public static function host_name() : string {
        return Helpers::host( Mode::is_network_context() ? network_home_url() : home_url() );
    } // End host_name()


    /**
     * Get the raw host of this Control Center, as v1 agents expect in the Origin header.
     *
     * @return string
     */
    private static function origin() : string {
        return (string) wp_parse_url( Mode::is_network_context() ? network_home_url() : home_url(), PHP_URL_HOST );
    } // End origin()


    /**
     * Check if a site is this site acting as its own agent.
     *
     * @param object $site
     * @return bool
     */
    public static function is_self( object $site ) : bool {
        return Mode::has_agent() && ! Mode::is_network_context() && Helpers::normalize_url( (string) $site->site_url ) === Helpers::normalize_url( home_url() );
    } // End is_self()


    /**
     * Check if a site is a subsite of this network managed from the network admin.
     *
     * @param object $site
     * @return bool
     */
    public static function is_network_local( object $site ) : bool {
        return 'local' === ( $site->connection_type ?? '' ) && is_multisite() && $site->local_blog_id > 0 && get_site( $site->local_blog_id );
    } // End is_network_local()


    /**
     * Check if a site is read directly instead of over HTTP.
     *
     * @param object $site
     * @return bool
     */
    public static function is_local( object $site ) : bool {
        return self::is_network_local( $site ) || self::is_self( $site );
    } // End is_local()


    /**
     * Get the body sent with data requests: the dashboard widget layout, so the agent's own dashboard
     * matches this one.
     *
     * @return array
     */
    public static function request_body() : array {
        return (array) apply_filters( 'prxctrl_request_body', [ 'layout' => Registry::widget_layout()[ 'enabled' ] ] );
    } // End request_body()


    /**
     * Add the current user's name and email to a request, so the agent's logs show who asked.
     * The body is signed, so the agent can trust it on signed requests.
     *
     * @param array $body
     * @return array
     */
    private static function with_requester( array $body ) : array {
        $user = wp_get_current_user();

        if ( $user->exists() ) {
            $body[ '_requester' ] = [ 'name' => $user->display_name, 'email' => $user->user_email ];
        }

        return $body;
    } // End with_requester()


    /**
     * Call an agent endpoint.
     *
     * @param object $site     Site row (with api_key).
     * @param string $endpoint request|action|ping|login
     * @param array  $body
     * @return array|\WP_Error
     */
    public static function call( object $site, string $endpoint, array $body = [] ) {
        if ( self::is_local( $site ) ) {
            return self::call_local( $site, $endpoint, $body );
        }

        $body = self::with_requester( $body );

        if ( empty( $site->api_key ) ) {
            return new \WP_Error( 'missing_api_key', __( 'This site does not have an API key yet.', 'pluginrx-control-center' ), [ 'hint' => __( 'Generate a key in Settings > Agent on that site, then paste it here.', 'pluginrx-control-center' ) ] );
        }

        $skip_v2 = 'v1' === ( $site->protocol ?? '' ) && ! empty( $site->id ) && get_transient( 'prxctrl_v1_' . $site->id );

        if ( ! $skip_v2 ) {
            $result = self::call_v2( $site, $endpoint, $body );

            if ( ! is_wp_error( $result ) || 'rest_no_route' !== $result->get_error_code() ) {
                self::remember_protocol( $site, is_wp_error( $result ) ? '' : 'v2' );
                return $result;
            }
        }

        if ( ! in_array( $endpoint, [ 'request', 'action', 'ping' ], true ) ) {
            return new \WP_Error( 'legacy_agent', __( 'This site runs the older PluginRx Agent plugin, which does not support this feature.', 'pluginrx-control-center' ), [ 'hint' => __( 'Install PluginRx Control Center on that site. It replaces the older plugin and keeps the same API key.', 'pluginrx-control-center' ) ] );
        }

        $result = self::call_v1( $site, 'ping' === $endpoint ? 'request' : $endpoint, $body );

        if ( is_wp_error( $result ) && 'rest_no_route' === $result->get_error_code() ) {
            return new \WP_Error( 'agent_not_found', __( 'The PluginRx agent was not found at this URL.', 'pluginrx-control-center' ), [ 'hint' => __( 'Make sure PluginRx Control Center is active on that site and its role is Agent or Both.', 'pluginrx-control-center' ) ] );
        }

        if ( ! is_wp_error( $result ) ) {
            if ( $skip_v2 && (int) ( $result[ '_meta' ][ 'protocol' ] ?? 1 ) >= 2 ) {
                delete_transient( 'prxctrl_v1_' . $site->id );

                if ( 'action' !== $endpoint ) {
                    $signed = self::call_v2( $site, $endpoint, $body );

                    if ( ! is_wp_error( $signed ) ) {
                        self::remember_protocol( $site, 'v2' );
                        return $signed;
                    }
                }

                return 'ping' === $endpoint ? [ 'ok' => true, '_meta' => $result[ '_meta' ] ] : $result;
            }

            self::remember_protocol( $site, 'v1' );

            if ( 'ping' === $endpoint ) {
                return [ 'ok' => true, '_meta' => $result[ '_meta' ] ?? null ];
            }
        }

        return $result;
    } // End call()


    /**
     * Remember which protocol a site speaks.
     *
     * @param object $site
     * @param string $protocol v1|v2|''
     * @return void
     */
    private static function remember_protocol( object $site, string $protocol ) : void {
        if ( '' === $protocol ) {
            return;
        }

        $previous = (string) ( $site->protocol ?? '' );
        $site->protocol = $protocol;

        if ( empty( $site->id ) ) {
            return;
        }

        if ( 'v1' === $protocol ) {
            set_transient( 'prxctrl_v1_' . $site->id, 1, self::V1_CACHE );
        } else {
            delete_transient( 'prxctrl_v1_' . $site->id );
        }

        if ( $previous !== $protocol ) {
            Database::update_site( (int) $site->id, [ 'protocol' => $protocol ] );
        }
    } // End remember_protocol()


    /**
     * Read a site on this install directly.
     *
     * @param object $site
     * @param string $endpoint
     * @param array  $body
     * @return array|\WP_Error
     */
    private static function call_local( object $site, string $endpoint, array $body ) {
        $network_local = self::is_network_local( $site );
        $allowed = $network_local ? null : Agent::allowed();

        if ( $network_local ) {
            switch_to_blog( $site->local_blog_id );
            Registry::flush();
        }

        switch ( $endpoint ) {
            case 'request':
                if ( $network_local ) {
                    Agent::remember_layout( $body );
                }

                $result = Agent::collect( $allowed );
                break;
            case 'action':
                $result = Agent::run_action( sanitize_key( (string) ( $body[ 'type' ] ?? '' ) ), $allowed );
                break;
            case 'login':
                $result = [ 'url' => admin_url(), 'expires' => 0 ];
                break;
            default:
                $result = [ 'ok' => true, '_meta' => Agent::meta( $allowed ) ];
        }

        if ( $network_local ) {
            restore_current_blog();
            Registry::flush();
        }

        if ( ! is_wp_error( $result ) && ( $site->protocol ?? '' ) !== 'local' && ! empty( $site->id ) ) {
            Database::update_site( (int) $site->id, [ 'protocol' => 'local' ] );
        }

        return $result;
    } // End call_local()


    /**
     * Build a REST URL on a site.
     *
     * @param string $site_url
     * @param string $route
     * @param bool   $plain Use ?rest_route= for sites without pretty permalinks.
     * @return string
     */
    private static function rest_url( string $site_url, string $route, bool $plain ) : string {
        if ( $plain ) {
            return add_query_arg( 'rest_route', $route, trailingslashit( $site_url ) );
        }

        return trailingslashit( $site_url ) . 'wp-json' . $route;
    } // End rest_url()


    /**
     * Get the request arguments shared by every call.
     *
     * @param array  $headers
     * @param string $body
     * @return array
     */
    private static function args( array $headers, $body ) : array {
        return (array) apply_filters( 'prxctrl_remote_args', [
            'timeout'     => (int) Options::get( 'timeout' ),
            'redirection' => 0,
            'headers'     => $headers,
            'body'        => $body,
        ] );
    } // End args()


    /**
     * Send a signed v2 request.
     *
     * @param object $site
     * @param string $endpoint
     * @param array  $body
     * @return array|\WP_Error
     */
    private static function call_v2( object $site, string $endpoint, array $body ) {
        $route = '/' . Agent::NAMESPACE_V2 . '/' . $endpoint;
        $json = wp_json_encode( empty( $body ) ? new \stdClass() : $body );
        $result = null;

        foreach ( [ false, true ] as $plain ) {
            $headers = Signer::headers( (string) $site->api_key, 'POST', $route, $json, self::host_name() );
            $headers[ 'Content-Type' ] = 'application/json';
            $headers[ 'Accept' ] = 'application/json';

            $result = self::send( self::rest_url( (string) $site->site_url, $route, $plain ), self::args( $headers, $json ) );

            if ( ! is_wp_error( $result ) || 'not_json_404' !== $result->get_error_code() ) {
                return $result;
            }
        }

        return new \WP_Error( 'rest_no_route', __( 'The REST route was not found.', 'pluginrx-control-center' ) );
    } // End call_v2()


    /**
     * Send a legacy v1 request.
     *
     * @param object $site
     * @param string $endpoint
     * @param array  $body
     * @return array|\WP_Error
     */
    private static function call_v1( object $site, string $endpoint, array $body ) {
        $route = '/' . Agent::NAMESPACE_V1 . '/' . $endpoint;

        $headers = [
            'X-PRX-Agent-Key' => (string) $site->api_key,
            'Accept'          => 'application/json',
            'Origin'          => self::origin(),
        ];

        foreach ( [ false, true ] as $plain ) {
            $result = self::send( self::rest_url( (string) $site->site_url, $route, $plain ), self::args( $headers, $body ) );

            if ( ! is_wp_error( $result ) || 'not_json_404' !== $result->get_error_code() ) {
                return $result;
            }
        }

        return new \WP_Error( 'rest_no_route', __( 'The REST route was not found.', 'pluginrx-control-center' ) );
    } // End call_v1()


    /**
     * Send a request and parse the response.
     *
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    private static function send( string $url, array $args ) {
        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $raw = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code >= 300 && $code < 400 ) {
            $location = (string) wp_remote_retrieve_header( $response, 'location' );

            return new \WP_Error( 'site_redirect', sprintf(
                /* translators: %s: URL the site redirects to */
                __( 'The site redirected the request to %s.', 'pluginrx-control-center' ),
                esc_url_raw( $location )
            ), [ 'status' => $code, 'location' => $location ] );
        }

        if ( ! is_array( $data ) ) {
            if ( 404 === $code ) {
                return new \WP_Error( 'not_json_404', __( 'Not found.', 'pluginrx-control-center' ), [ 'status' => $code ] );
            }

            return new \WP_Error( 'invalid_response', sprintf(
                /* translators: %d: HTTP status code */
                __( 'The site returned an unexpected response (HTTP %d).', 'pluginrx-control-center' ),
                $code
            ), [ 'status' => $code, 'body' => substr( wp_strip_all_tags( $raw ), 0, 300 ) ] );
        }

        if ( $code >= 400 ) {
            return new \WP_Error( sanitize_key( (string) ( $data[ 'code' ] ?? 'http_' . $code ) ), wp_strip_all_tags( (string) ( $data[ 'message' ] ?? '' ) ), [ 'status' => $code ] );
        }

        return $data;
    } // End send()


    /**
     * Turn an error into a message with a suggestion.
     *
     * @param \WP_Error $error
     * @return string
     */
    public static function diagnose( \WP_Error $error ) : string {
        $code = $error->get_error_code();
        $message = $error->get_error_message();
        $data = (array) $error->get_error_data();
        $hint = (string) ( $data[ 'hint' ] ?? '' );

        $hints = [
            'remote_access_disabled' => __( 'Turn on Allow Remote Access in Settings > Agent on that site.', 'pluginrx-control-center' ),
            'invalid_signature'      => __( 'Copy the API key from Settings > Agent on that site and paste it into this site\'s settings for it.', 'pluginrx-control-center' ),
            'invalid_api_key'        => __( 'Copy the API key from Settings > Agent on that site and paste it into this site\'s settings for it.', 'pluginrx-control-center' ),
            'missing_api_key'        => __( 'Generate an API key in Settings > Agent on that site, then paste it here.', 'pluginrx-control-center' ),
            'invalid_domain'         => sprintf(
                /* translators: %s: this Control Center's domain */
                __( 'Add %s to Control Center Domains in Settings > Agent on that site.', 'pluginrx-control-center' ),
                self::host_name()
            ),
            'signature_expired'      => __( 'Check that the clocks on both servers are correct.', 'pluginrx-control-center' ),
            'rate_limited'           => __( 'Wait 15 minutes, then fix the API key or domain before trying again.', 'pluginrx-control-center' ),
            'forbidden_action'       => __( 'Allow it under Permissions in Settings > Agent on that site.', 'pluginrx-control-center' ),
            'site_redirect'          => __( 'Update this site\'s URL to the address it redirects to (for example https:// or www).', 'pluginrx-control-center' ),
            'invalid_response'       => __( 'A security plugin, firewall or maintenance mode may be blocking the REST API on that site.', 'pluginrx-control-center' ),
            'rest_forbidden'         => __( 'A security plugin may be blocking the REST API on that site.', 'pluginrx-control-center' ),
            'login_user_missing'     => __( 'Choose a login account under Remote Login in Settings > Agent on that site.', 'pluginrx-control-center' ),
        ];

        if ( '' === $hint && isset( $hints[ $code ] ) ) {
            $hint = $hints[ $code ];
        }

        if ( '' === $hint && 'http_request_failed' === $code ) {
            if ( preg_match( '/cURL error 6\b|resolve host/i', $message ) ) {
                $hint = __( 'The domain could not be found. Check the site URL.', 'pluginrx-control-center' );
            } elseif ( preg_match( '/cURL error 28\b|timed out/i', $message ) ) {
                $hint = __( 'The site took too long to respond. Try again, or raise the Timeout in Settings > Host.', 'pluginrx-control-center' );
            } elseif ( preg_match( '/cURL error (35|51|58|60)\b|SSL|certificate/i', $message ) ) {
                $hint = __( 'There is a problem with the site\'s SSL certificate.', 'pluginrx-control-center' );
            } elseif ( preg_match( '/cURL error 7\b|connect/i', $message ) ) {
                $hint = __( 'The server refused the connection. Check that the site is online.', 'pluginrx-control-center' );
            }
        }

        return trim( $message . ( '' !== $hint ? ' ' . $hint : '' ) );
    } // End diagnose()


    /**
     * Check that a site's front end responds, and how fast.
     *
     * @param string $url
     * @return array [ 'status' => int, 'ms' => int, 'error' => string ]
     */
    public static function ping( string $url ) : array {
        $args = [ 'timeout' => min( 15, (int) Options::get( 'timeout' ) ), 'redirection' => 5, 'user-agent' => 'PluginRx Control Center; ' . home_url() ];
        $start = microtime( true );
        $response = wp_remote_head( $url, $args );

        if ( ! is_wp_error( $response ) && in_array( (int) wp_remote_retrieve_response_code( $response ), [ 403, 405, 501 ], true ) ) {
            $start = microtime( true );
            $response = wp_remote_get( $url, $args + [ 'limit_response_size' => 1024 ] );
        }

        $ms = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            return [ 'status' => 0, 'ms' => $ms, 'error' => $response->get_error_message() ];
        }

        return [ 'status' => (int) wp_remote_retrieve_response_code( $response ), 'ms' => $ms, 'error' => '' ];
    } // End ping()


    /**
     * Get when a site's SSL certificate expires.
     *
     * @param string $url
     * @return array [ 'expires' => string|null (site time), 'error' => string ]
     */
    public static function ssl_expiry( string $url ) : array {
        $parts = wp_parse_url( $url );

        if ( 'https' !== ( $parts[ 'scheme' ] ?? '' ) || empty( $parts[ 'host' ] ) ) {
            return [ 'expires' => null, 'error' => 'not_https' ];
        }

        if ( ! function_exists( 'stream_socket_client' ) || ! function_exists( 'openssl_x509_parse' ) ) {
            return [ 'expires' => null, 'error' => 'unsupported' ];
        }

        $context = stream_context_create( [
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'peer_name'         => $parts[ 'host' ],
                'SNI_enabled'       => true,
                'cafile'            => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
            ],
        ] );

        $port = ! empty( $parts[ 'port' ] ) ? (int) $parts[ 'port' ] : 443;
        $client = @stream_socket_client( 'ssl://' . $parts[ 'host' ] . ':' . $port, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed handshake is reported below.

        if ( ! $client ) {
            return [ 'expires' => null, 'error' => 'invalid' ];
        }

        $params = stream_context_get_params( $client );
        fclose( $client ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- network socket, not a file.

        $cert = isset( $params[ 'options' ][ 'ssl' ][ 'peer_certificate' ] ) ? openssl_x509_parse( $params[ 'options' ][ 'ssl' ][ 'peer_certificate' ] ) : false;

        if ( empty( $cert[ 'validTo_time_t' ] ) ) {
            return [ 'expires' => null, 'error' => 'invalid' ];
        }

        return [ 'expires' => wp_date( 'Y-m-d H:i:s', (int) $cert[ 'validTo_time_t' ], wp_timezone() ), 'error' => '' ];
    } // End ssl_expiry()


    /**
     * Get a site's screenshot URL. The URL only changes when the site is checked again, so the
     * screenshot service is never asked to start over while it is still rendering.
     *
     * @param object $site
     * @return string
     */
    public static function screenshot_url( object $site ) : string {
        if ( ! Options::get( 'screenshots' ) || empty( $site->site_url ) ) {
            return '';
        }

        $target = trailingslashit( (string) $site->site_url );

        if ( ! empty( $site->screenshot_ver ) ) {
            $target = add_query_arg( 'prxctrl', (int) $site->screenshot_ver, $target );
        }

        $url = self::MSHOTS . rawurlencode( $target ) . '?w=400&h=300';

        return (string) apply_filters( 'prxctrl_screenshot_url', $url, $site );
    } // End screenshot_url()


    /**
     * Check if a screenshot has finished rendering. mShots redirects to a placeholder while it works.
     *
     * @param string $url
     * @return bool
     */
    public static function screenshot_ready( string $url ) : bool {
        if ( 0 !== strpos( $url, self::MSHOTS ) ) {
            return true;
        }

        $response = wp_remote_head( $url, [ 'timeout' => 8, 'redirection' => 0 ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $type = (string) wp_remote_retrieve_header( $response, 'content-type' );

        return 200 === (int) wp_remote_retrieve_response_code( $response ) && 0 === strpos( $type, 'image/' );
    } // End screenshot_ready()

} // End class Client
