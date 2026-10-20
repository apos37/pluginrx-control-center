<?php
/**
 * REMOTE LOGIN
 *
 * Single-use login links. When Remote Login is allowed, a Control Center can ask for a link that
 * logs in as the account chosen in Settings > Agent. The link expires after 60 seconds and works once.
 * Only a hash of the token is stored.
 *
 * Links point to the front end (?prxctrl_login=token) and are handled early on init, so plugins that
 * hide or protect wp-admin never see them. The core wp_login action is not fired, because two-factor
 * plugins treat it as a password login that skipped their check and log the user straight back out.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Login {

    /**
     * Query variable that carries the token.
     */
    public const QUERY_VAR = 'prxctrl_login';


    /**
     * Seconds a link stays valid.
     */
    public const LIFETIME = 60;


    /**
     * @var Login|null Singleton instance
     */
    private static ?Login $instance = null;


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
        add_action( 'init', [ $this, 'maybe_handle' ], 1 );
    } // End __construct()


    /**
     * Get the account remote logins use, if it is still an administrator.
     *
     * @return \WP_User|null
     */
    private static function login_user() : ?\WP_User {
        $user_id = (int) Options::get( 'agent_login_user' );
        $user = $user_id ? get_userdata( $user_id ) : false;

        return ( $user && user_can( $user, 'manage_options' ) ) ? $user : null;
    } // End login_user()


    /**
     * Create a single-use login link.
     *
     * @return array|\WP_Error [ 'url' => string, 'expires' => int ]
     */
    public static function create_link() {
        if ( ! self::login_user() ) {
            return new \WP_Error( 'login_user_missing', __( 'No account is selected for remote logins. Choose one under Remote Login in Settings > Agent.', 'pluginrx-control-center' ), [ 'status' => 409 ] );
        }

        $token = bin2hex( random_bytes( 32 ) );

        set_transient( 'prxctrl_login_' . hash( 'sha256', $token ), [ 'user' => (int) Options::get( 'agent_login_user' ), 'requester' => Agent::current_requester() ], self::LIFETIME );

        return [
            'url'     => add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) ),
            'expires' => time() + self::LIFETIME,
        ];
    } // End create_link()


    /**
     * Show a failure page.
     *
     * @param string $message
     * @return void
     */
    private function deny( string $message ) : void {
        Agent::log( 'remote_login', false, $message );

        wp_die(
            esc_html( $message ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Log in normally', 'pluginrx-control-center' ) . '</a>',
            esc_html__( 'Login link not valid', 'pluginrx-control-center' ),
            [ 'response' => 403 ]
        );
    } // End deny()


    /**
     * Handle a login link when the request carries one.
     *
     * @return void
     */
    public function maybe_handle() : void {
        if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the single-use token is the credential.
            return;
        }

        $this->handle( sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the single-use token is the credential.
    } // End maybe_handle()


    /**
     * Use a login link.
     *
     * @param string $token
     * @return void
     */
    private function handle( string $token ) : void {
        nocache_headers();

        if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
            $this->deny( __( 'This login link is not valid.', 'pluginrx-control-center' ) );
            return;
        }

        $key = 'prxctrl_login_' . hash( 'sha256', $token );
        $stored = get_transient( $key );
        $user_id = is_array( $stored ) ? (int) ( $stored[ 'user' ] ?? 0 ) : 0;

        delete_transient( $key );

        if ( is_array( $stored ) && ! empty( $stored[ 'requester' ] ) ) {
            Agent::set_requester( (string) $stored[ 'requester' ] );
        }

        if ( ! $user_id ) {
            $this->deny( __( 'This login link has expired or was already used.', 'pluginrx-control-center' ) );
            return;
        }

        $user = self::login_user();

        if ( ! Mode::has_agent() || ! Options::get( 'agent_remote_access' ) || ! in_array( 'remote_login', Agent::allowed(), true ) || ! $user || $user->ID !== $user_id ) {
            $this->deny( __( 'Remote login is no longer allowed on this site.', 'pluginrx-control-center' ) );
            return;
        }

        $current = get_current_user_id();

        Agent::log( 'remote_login', true, sprintf(
            /* translators: 1: username, 2: IP address */
            __( 'Logged in as %1$s with a single-use link from %2$s.', 'pluginrx-control-center' ),
            $user->user_login,
            Helpers::ip()
        ) );

        if ( ! $current ) {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, false );

            if ( apply_filters( 'prxctrl_remote_login_fire_wp_login', false, $user ) ) {
                do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, only when a developer opts in.
            }
        }

        do_action( 'prxctrl_remote_login', $user->ID );

        wp_safe_redirect( admin_url() );
        exit;
    } // End handle()

} // End class Login


Login::instance();
