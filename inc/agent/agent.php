<?php
/**
 * AGENT
 *
 * The REST endpoints a Control Center talks to. Requests must come from an allowed Control Center
 * domain, and are checked against the API key: v2 requests are signed with it (the key is never
 * sent), while v1 requests send it in a header for older Control Centers. Only data and actions
 * allowed in Settings > Agent are ever shared or run.
 *
 * Routes:
 * - POST prx-agent/v2/request  Share allowed data.
 * - POST prx-agent/v2/action   Run an allowed action ({ "type": "update_plugins" }).
 * - POST prx-agent/v2/ping     Confirm the connection and report what is allowed.
 * - POST prx-agent/v2/login    Create a single-use login link (when Remote Login is allowed).
 * - POST prx-agent/v1/request  Legacy: same data, key header instead of a signature.
 * - POST prx-agent/v1/action   Legacy.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Agent {

    /**
     * Who is making the current request, for log entries.
     *
     * @var string
     */
    private static string $requester = '';


    /**
     * REST namespaces.
     */
    public const NAMESPACE_V2 = 'prx-agent/v2';
    public const NAMESPACE_V1 = 'prx-agent/v1';


    /**
     * Failed attempts allowed per IP before it is blocked for 15 minutes.
     */
    public const MAX_FAILURES = 10;


    /**
     * @var Agent|null Singleton instance
     */
    private static ?Agent $instance = null;


    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    } // End __construct()


    /**
     * Check if the legacy PluginRx Agent plugin is loaded in this request.
     *
     * @return bool
     */
    public static function legacy_plugin_loaded() : bool {
        return class_exists( '\PluginRx\Agent\Bootstrap', false );
    } // End legacy_plugin_loaded()


    /**
     * Register the REST routes when this site is an agent.
     *
     * @return void
     */
    public function register_routes() : void {
        if ( ! Mode::has_agent() ) {
            return;
        }

        $routes = [
            'request' => 'handle_request',
            'action'  => 'handle_action',
            'ping'    => 'handle_ping',
            'login'   => 'handle_login',
        ];

        foreach ( $routes as $route => $callback ) {
            register_rest_route( self::NAMESPACE_V2, '/' . $route, [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, $callback ],
                'permission_callback' => [ $this, 'authorize_v2' ],
            ] );
        }

        if ( self::legacy_plugin_loaded() || Options::get( 'agent_require_signed' ) ) {
            return;
        }

        foreach ( [ 'request' => 'handle_request', 'action' => 'handle_action' ] as $route => $callback ) {
            register_rest_route( self::NAMESPACE_V1, '/' . $route, [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, $callback ],
                'permission_callback' => [ $this, 'authorize_v1' ],
            ] );
        }
    } // End register_routes()


    /**
     * Get the transient key that counts failed attempts for the current IP.
     *
     * @return string
     */
    private static function failure_key() : string {
        return 'prxctrl_fail_' . md5( Helpers::ip() );
    } // End failure_key()


    /**
     * Run the checks shared by both protocol versions.
     *
     * @return true|\WP_Error
     */
    private function preflight() {
        if ( (int) get_transient( self::failure_key() ) >= self::MAX_FAILURES ) {
            return new \WP_Error( 'rate_limited', __( 'Too many failed attempts. Requests from this server are blocked for 15 minutes.', 'pluginrx-control-center' ), [ 'status' => 429 ] );
        }

        if ( ! Options::get( 'agent_remote_access' ) ) {
            return new \WP_Error( 'remote_access_disabled', __( 'Remote access is turned off on this site. Turn it on in Settings > Agent.', 'pluginrx-control-center' ), [ 'status' => 403 ] );
        }

        return true;
    } // End preflight()


    /**
     * Check that the Control Center's domain is allowed.
     *
     * @param string $host
     * @return true|\WP_Error
     */
    private function check_domain( string $host ) {
        $allowed = (array) Options::get( 'agent_domains' );

        if ( '' === $host || ! in_array( Helpers::host( $host ), $allowed, true ) ) {
            return new \WP_Error( 'invalid_domain', sprintf(
                /* translators: %s: the Control Center's domain */
                __( 'The Control Center domain "%s" is not allowed. Add it to Control Center Domains in Settings > Agent.', 'pluginrx-control-center' ),
                sanitize_text_field( $host )
            ), [ 'status' => 403 ] );
        }

        return true;
    } // End check_domain()


    /**
     * Record a failed attempt and pass the error through.
     *
     * @param \WP_Error $error
     * @return \WP_Error
     */
    private function fail( \WP_Error $error ) : \WP_Error {
        if ( 'remote_access_disabled' !== $error->get_error_code() && 'rate_limited' !== $error->get_error_code() ) {
            $key = self::failure_key();
            set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );

            self::log( 'auth_failed', false, $error->get_error_message() );
        }

        return $error;
    } // End fail()


    /**
     * Authorize a signed v2 request.
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function authorize_v2( \WP_REST_Request $request ) {
        self::$requester = self::describe_requester( $request, false );
        $check = $this->preflight();

        if ( true !== $check ) {
            return $this->fail( $check );
        }

        $check = $this->check_domain( (string) $request->get_header( Signer::HEADER_HOST ) );

        if ( true !== $check ) {
            return $this->fail( $check );
        }

        $check = Signer::verify( $request, (string) Options::get( 'agent_api_key' ) );

        if ( true !== $check ) {
            return $this->fail( $check );
        }

        self::$requester = self::describe_requester( $request, true );

        return true;
    } // End authorize_v2()


    /**
     * Authorize a legacy v1 request (API key header plus Origin).
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function authorize_v1( \WP_REST_Request $request ) {
        self::$requester = self::describe_requester( $request, false );
        $check = $this->preflight();

        if ( true !== $check ) {
            return $this->fail( $check );
        }

        $provided = (string) $request->get_header( 'X-PRX-Agent-Key' );
        $stored = (string) Options::get( 'agent_api_key' );

        if ( '' === $provided ) {
            return $this->fail( new \WP_Error( 'missing_api_key', __( 'Missing API key.', 'pluginrx-control-center' ), [ 'status' => 401 ] ) );
        }

        if ( '' === $stored || ! hash_equals( $stored, $provided ) ) {
            return $this->fail( new \WP_Error( 'invalid_api_key', __( 'Invalid API key.', 'pluginrx-control-center' ), [ 'status' => 401 ] ) );
        }

        $check = $this->check_domain( (string) $request->get_header( 'Origin' ) );

        if ( true !== $check ) {
            return $this->fail( $check );
        }

        return true;
    } // End authorize_v1()


    /**
     * Get the allowed permission keys.
     *
     * @return array
     */
    public static function allowed() : array {
        return (array) Options::get( 'agent_permissions' );
    } // End allowed()


    /**
     * Build the metadata included with every response.
     *
     * @param array|null $allowed
     * @return array
     */
    public static function meta( ?array $allowed ) : array {
        $login_user = (int) Options::get( 'agent_login_user' );
        $integrations = [];

        foreach ( Registry::integrations() as $slug => $integration ) {
            $integrations[ $slug ] = '' === $integration[ 'plugin' ] || Helpers::is_plugin_active( $integration[ 'plugin' ] );
        }

        return [
            'agent'          => 'pluginrx-control-center',
            'version'        => Bootstrap::version(),
            'protocol'       => 2,
            'site_name'      => wp_strip_all_tags( get_bloginfo( 'name' ) ),
            'home'           => esc_url_raw( home_url() ),
            'permissions'    => null === $allowed ? array_keys( Registry::permissions() ) : array_values( $allowed ),
            'login_ready'    => null === $allowed || ( in_array( 'remote_login', $allowed, true ) && $login_user > 0 ),
            'require_signed' => (bool) Options::get( 'agent_require_signed' ),
            'integrations'   => $integrations,
        ];
    } // End meta()


    /**
     * Collect the data this site shares. Pass null to collect everything (used for network subsites
     * managed from the network admin).
     *
     * @param array|null $allowed
     * @return array
     */
    public static function collect( ?array $allowed ) : array {
        $data = [];

        foreach ( Registry::requests() as $key => $request ) {
            if ( null !== $allowed && ! in_array( $key, $allowed, true ) ) {
                continue;
            }

            $is_integration = 'integration' === $request[ 'group' ];

            if ( $is_integration && ! empty( $request[ 'plugin' ] ) && ! Helpers::is_plugin_active( $request[ 'plugin' ] ) ) {
                $data[ 'integrations' ][ $key ] = Registry::NOT_ACTIVE;
                continue;
            }

            $value = is_callable( $request[ 'callback' ] ?? null ) ? call_user_func( $request[ 'callback' ] ) : null;

            if ( $is_integration ) {
                $data[ 'integrations' ][ $key ] = $value;
            } else {
                $data[ $key ] = $value;
            }
        }

        $data[ '_meta' ] = self::meta( $allowed );

        return (array) apply_filters( 'prxctrl_agent_response', $data, $allowed );
    } // End collect()


    /**
     * Run an action. Pass null to skip the permission check (network subsites managed from the network admin).
     *
     * @param string     $type
     * @param array|null $allowed
     * @return array|\WP_Error
     */
    public static function run_action( string $type, ?array $allowed ) {
        $actions = Registry::actions();

        if ( ! isset( $actions[ $type ] ) || empty( $actions[ $type ][ 'callback' ] ) ) {
            return new \WP_Error( 'unknown_action', __( 'Unknown action.', 'pluginrx-control-center' ), [ 'status' => 400 ] );
        }

        if ( null !== $allowed && ! in_array( $type, $allowed, true ) ) {
            return new \WP_Error( 'forbidden_action', __( 'This action is not allowed on this site. Allow it in Settings > Agent.', 'pluginrx-control-center' ), [ 'status' => 403 ] );
        }

        $action = $actions[ $type ];

        if ( ! empty( $action[ 'plugin' ] ) && ! Helpers::is_plugin_active( $action[ 'plugin' ] ) ) {
            return [ 'success' => false, 'message' => __( 'The plugin this action needs is not active on this site.', 'pluginrx-control-center' ) ];
        }

        if ( ! is_callable( $action[ 'callback' ] ) ) {
            return new \WP_Error( 'missing_action_handler', __( 'No handler found for this action.', 'pluginrx-control-center' ), [ 'status' => 500 ] );
        }

        $result = call_user_func( $action[ 'callback' ] );
        $result = is_array( $result ) ? $result : [ 'success' => (bool) $result, 'message' => '' ];

        $result = [
            'success' => ! empty( $result[ 'success' ] ),
            'message' => wp_kses_post( (string) ( $result[ 'message' ] ?? '' ) ),
        ];

        do_action( 'prxctrl_agent_action', $type, $result );

        return $result;
    } // End run_action()


    /**
     * Log an event on this site.
     *
     * @param string $action
     * @param bool   $success
     * @param string $details
     * @return void
     */
    public static function log( string $action, bool $success, string $details ) : void {
        Database::maybe_install();
        Database::log( 0, $action, $success, 'agent', $details, self::$requester );
    } // End log()


    /**
     * Set who is making the current request, for log entries.
     *
     * @param string $requester
     * @return void
     */
    public static function set_requester( string $requester ) : void {
        self::$requester = $requester;
    } // End set_requester()


    /**
     * Get who is making the current request.
     *
     * @return string
     */
    public static function current_requester() : string {
        return self::$requester;
    } // End current_requester()


    /**
     * Describe who is making a request: the Control Center's domain and, on signed requests, the
     * person using it. The name and email are only trusted once the signature checks out, because
     * they are part of the signed body.
     *
     * @param \WP_REST_Request $request
     * @param bool             $verified
     * @return string
     */
    private static function describe_requester( \WP_REST_Request $request, bool $verified ) : string {
        $host = self::requester( $request );
        $person = $verified ? (array) ( ( (array) $request->get_json_params() )[ '_requester' ] ?? [] ) : [];
        $name = sanitize_text_field( (string) ( $person[ 'name' ] ?? '' ) );
        $email = sanitize_email( (string) ( $person[ 'email' ] ?? '' ) );

        if ( '' === $name && '' === $email ) {
            return '' !== $host ? $host : '';
        }

        $who = '' !== $name && '' !== $email ? $name . ' (' . $email . ')' : ( '' !== $name ? $name : $email );

        return '' !== $host ? sprintf(
            /* translators: 1: person's name and email, 2: Control Center domain */
            __( '%1$s via %2$s', 'pluginrx-control-center' ),
            $who,
            $host
        ) : $who;
    } // End describe_requester()


    /**
     * Describe the requesting Control Center for logs.
     *
     * @param \WP_REST_Request $request
     * @return string
     */
    private static function requester( \WP_REST_Request $request ) : string {
        $host = (string) $request->get_header( Signer::HEADER_HOST );

        if ( '' === $host ) {
            $host = (string) $request->get_header( 'Origin' );
        }

        return sanitize_text_field( Helpers::host( $host ) );
    } // End requester()


    /**
     * REST: share allowed data.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request( \WP_REST_Request $request ) : \WP_REST_Response {
        self::remember_layout( (array) $request->get_json_params() );

        if ( Options::get( 'agent_log_requests' ) ) {
            self::log( 'agent_request', true, __( 'Data requested.', 'pluginrx-control-center' ) );
        }

        return rest_ensure_response( self::collect( self::allowed() ) );
    } // End handle_request()


    /**
     * Remember the widget layout a Control Center sent, so this site's dashboard shows the same
     * widgets in the same order.
     *
     * @param array $body
     * @return void
     */
    public static function remember_layout( array $body ) : void {
        if ( ! isset( $body[ 'layout' ] ) || ! is_array( $body[ 'layout' ] ) ) {
            return;
        }

        $layout = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $body[ 'layout' ] ) ) ) ), 0, 200 );

        if ( get_option( Registry::AGENT_LAYOUT_OPTION ) !== $layout ) {
            update_option( Registry::AGENT_LAYOUT_OPTION, $layout, false );
        }
    } // End remember_layout()


    /**
     * REST: run an allowed action.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_action( \WP_REST_Request $request ) {
        $type = sanitize_key( (string) $request->get_param( 'type' ) );
        $result = self::run_action( $type, self::allowed() );

        if ( is_wp_error( $result ) ) {
            self::log( '' !== $type ? $type : 'unknown_action', false, $result->get_error_message() );
            return $result;
        }

        self::log( $type, $result[ 'success' ], wp_strip_all_tags( $result[ 'message' ] ) );

        return rest_ensure_response( $result );
    } // End handle_action()


    /**
     * REST: confirm the connection.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_ping( \WP_REST_Request $request ) : \WP_REST_Response {
        if ( Options::get( 'agent_log_requests' ) ) {
            self::log( 'agent_ping', true, __( 'Connection checked.', 'pluginrx-control-center' ) );
        }

        return rest_ensure_response( [ 'ok' => true, '_meta' => self::meta( self::allowed() ) ] );
    } // End handle_ping()


    /**
     * REST: create a single-use login link.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_login( \WP_REST_Request $request ) {
        if ( ! in_array( 'remote_login', self::allowed(), true ) ) {
            self::log( 'remote_login', false, __( 'Remote login is not allowed.', 'pluginrx-control-center' ) );
            return new \WP_Error( 'forbidden_action', __( 'Remote login is not allowed on this site. Allow it in Settings > Agent.', 'pluginrx-control-center' ), [ 'status' => 403 ] );
        }

        $link = Login::create_link();

        if ( is_wp_error( $link ) ) {
            self::log( 'remote_login', false, $link->get_error_message() );
            return $link;
        }

        self::log( 'remote_login', true, __( 'Single-use login link created.', 'pluginrx-control-center' ) );

        return rest_ensure_response( $link );
    } // End handle_login()

} // End class Agent


Agent::instance();
