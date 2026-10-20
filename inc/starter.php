<?php
/**
 * STARTER
 *
 * The setup page shown the first time the plugin (or version 2.0) is activated. It asks whether
 * this site is a Host, an Agent or Both. On a network, it also asks where the Control Center
 * should live and which subsites it should manage.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Starter {

    /**
     * Nonce action.
     */
    public const NONCE = 'prxctrl_setup';


    /**
     * @var Starter|null Singleton instance
     */
    private static ?Starter $instance = null;


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
        add_action( 'admin_init', [ $this, 'maybe_redirect' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_notices', [ $this, 'setup_notice' ] );
        add_action( 'network_admin_notices', [ $this, 'setup_notice' ] );
        add_action( 'wp_ajax_prxctrl_complete_setup', [ $this, 'ajax_complete' ] );
    } // End __construct()


    /**
     * Send the user to the setup page right after a single activation.
     *
     * @return void
     */
    public function maybe_redirect() : void {
        if ( ! get_transient( 'prxctrl_activation_redirect' ) ) {
            return;
        }

        delete_transient( 'prxctrl_activation_redirect' );

        if ( wp_doing_ajax() || isset( $_GET[ 'activate-multi' ] ) || ! Mode::setup_here() || ! Access::can_manage() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the core activation screen.
            return;
        }

        wp_safe_redirect( Helpers::page_url( 'setup' ) );
        exit;
    } // End maybe_redirect()


    /**
     * Remind admins to finish setup.
     *
     * @return void
     */
    public function setup_notice() : void {
        if ( ! Mode::setup_here() || ! Access::can_manage() || 'setup' === Menu::current_key() ) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p>
                <?php
                printf(
                    /* translators: 1: plugin name, 2: link to the setup page */
                    esc_html__( '%1$s needs one quick answer before it starts working. %2$s', 'pluginrx-control-center' ),
                    '<strong>' . esc_html( Bootstrap::name() ) . '</strong>',
                    '<a href="' . esc_url( Helpers::page_url( 'setup' ) ) . '">' . esc_html__( 'Finish setup', 'pluginrx-control-center' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    } // End setup_notice()


    /**
     * Enqueue assets.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! Helpers::is_plugin_screen() || 'setup' !== Menu::current_key() ) {
            return;
        }

        self::enqueue_script();
    } // End enqueue()


    /**
     * Enqueue the setup stylesheet and script. Also used by the Site Role settings tab, which shares
     * the network questions.
     *
     * @return void
     */
    public static function enqueue_script() : void {
        $version = Bootstrap::script_version();

        wp_enqueue_style( 'prxctrl-starter', Bootstrap::url( 'inc/css/starter.css' ), [ 'prxctrl-theme' ], $version );
        wp_enqueue_script( 'prxctrl-starter', Bootstrap::url( 'inc/js/starter.js' ), [ 'jquery', 'prxctrl-common' ], $version, true );

        wp_localize_script( 'prxctrl-starter', 'prxctrlStarter', [
            'nonce' => wp_create_nonce( self::NONCE ),
            'i18n'  => [
                'chooseMode' => __( 'Choose Host, Agent or Both.', 'pluginrx-control-center' ),
                'chooseSite' => __( 'Choose the site the Control Center should live on.', 'pluginrx-control-center' ),
                'finishing'  => __( 'Finishing setup', 'pluginrx-control-center' ),
            ],
        ] );
    } // End enqueue_script()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'pluginrx-control-center' ) );
        }

        $network = Mode::is_network_active() && is_network_admin();
        $current = Mode::get();
        $settings = Mode::network();
        $upgraded = '' !== $current;
        ?>
        <div class="wrap prxctrl-wrap prxctrl-starter">
            <div class="prxctrl-box prxctrl-starter-box">
                <div class="prxctrl-box-body">
                    <h2 class="prxctrl-starter-title">
                        <?php
                        /* translators: %s: plugin name */
                        echo esc_html( sprintf( __( 'Welcome to %s', 'pluginrx-control-center' ), Bootstrap::name() ) );
                        ?>
                    </h2>
                    <p class="prxctrl-starter-intro">
                        <?php
                        if ( $upgraded ) {
                            esc_html_e( 'The Control Center and the Agent are now one plugin. We picked the role below from your previous setup. Confirm it to finish upgrading; your sites, settings and API key were kept.', 'pluginrx-control-center' );
                        } else {
                            esc_html_e( 'Install this plugin on every site you manage. One site runs the Control Center (the Host); every other site shares only what you allow (an Agent).', 'pluginrx-control-center' );
                        }
                        ?>
                    </p>

                    <fieldset class="prxctrl-starter-step" id="prxctrl-step-mode">
                        <legend><?php echo $network ? esc_html__( 'Will this network be the Host, Agent, or Both?', 'pluginrx-control-center' ) : esc_html__( 'Will this website be the Host, Agent, or Both?', 'pluginrx-control-center' ); ?></legend>
                        <?php SettingsUi::render_mode_options( $current ); ?>
                    </fieldset>

                    <?php if ( $network ) : ?>
                        <?php self::render_network_steps( $settings ); ?>
                    <?php endif; ?>

                    <p class="prxctrl-modal-error" role="alert" hidden></p>

                    <div class="prxctrl-starter-footer">
                        <button type="button" class="prxctrl-button prxctrl-button-large" id="prxctrl-complete-setup"><?php esc_html_e( 'Complete Setup', 'pluginrx-control-center' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * Render the network questions.
     *
     * @param array $settings
     * @return void
     */
    public static function render_network_steps( array $settings ) : void {
        $sites = get_sites( [ 'number' => 500, 'deleted' => 0 ] );
        ?>
        <fieldset class="prxctrl-starter-step prxctrl-host-step" id="prxctrl-step-location">
            <legend><?php esc_html_e( 'Where should the Control Center live?', 'pluginrx-control-center' ); ?></legend>
            <label class="prxctrl-inline-label"><input type="radio" name="location" value="network" <?php checked( 'network' === $settings[ 'location' ] ); ?>> <?php esc_html_e( 'In the Network Admin', 'pluginrx-control-center' ); ?></label>
            <label class="prxctrl-inline-label"><input type="radio" name="location" value="site" <?php checked( 'network' !== $settings[ 'location' ] ); ?>> <?php esc_html_e( 'On one site:', 'pluginrx-control-center' ); ?>
                <select name="location_site" aria-label="<?php esc_attr_e( 'Site', 'pluginrx-control-center' ); ?>">
                    <option value=""><?php esc_html_e( 'Choose a site...', 'pluginrx-control-center' ); ?></option>
                    <?php foreach ( $sites as $site ) : ?>
                        <option value="<?php echo esc_attr( (string) $site->blog_id ); ?>" <?php selected( (int) $settings[ 'location' ], (int) $site->blog_id ); ?>><?php echo esc_html( get_blog_option( (int) $site->blog_id, 'blogname' ) . ' (' . $site->domain . $site->path . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </fieldset>

        <fieldset class="prxctrl-starter-step prxctrl-subsites-step" id="prxctrl-step-subsites">
            <legend><?php esc_html_e( 'Which subsites should the Control Center manage?', 'pluginrx-control-center' ); ?></legend>
            <p class="prxctrl-section-desc"><?php esc_html_e( 'Subsites on this network are read directly, so they do not need an API key. You can add sites on other servers later on the Sites page.', 'pluginrx-control-center' ); ?></p>
            <label class="prxctrl-select-all"><input type="checkbox" class="prxctrl-select-all-toggle"> <?php esc_html_e( 'Select all', 'pluginrx-control-center' ); ?></label>
            <div class="prxctrl-checkboxes">
                <?php foreach ( $sites as $site ) : ?>
                    <label>
                        <input type="checkbox" name="subsites[]" value="<?php echo esc_attr( (string) $site->blog_id ); ?>" <?php checked( in_array( (int) $site->blog_id, $settings[ 'subsites' ], true ) ); ?>>
                        <?php echo esc_html( get_blog_option( (int) $site->blog_id, 'blogname' ) ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="prxctrl-inline-label"><input type="checkbox" name="auto_include" value="1" <?php checked( ! empty( $settings[ 'auto_include' ] ) ); ?>> <?php esc_html_e( 'Also add new subsites automatically when they are created', 'pluginrx-control-center' ); ?></label>
        </fieldset>
        <?php
    } // End render_network_steps()


    /**
     * Add a network subsite to the network Control Center.
     *
     * @param int $blog_id
     * @return void
     */
    public static function add_subsite( int $blog_id ) : void {
        $site = get_site( $blog_id );

        if ( ! $site ) {
            return;
        }

        $url = Helpers::normalize_url( get_home_url( $blog_id ) );

        if ( '' === $url || Database::get_site_by_url( $url ) ) {
            return;
        }

        Database::insert_site( [
            'site_url'        => $url,
            'site_name'       => (string) get_blog_option( $blog_id, 'blogname' ),
            'connection_type' => 'local',
            'local_blog_id'   => $blog_id,
            'protocol'        => 'local',
            'sort_order'      => Database::count_sites() + 1,
        ], true );
    } // End add_subsite()


    /**
     * AJAX: save the answers and finish setup.
     *
     * @return void
     */
    public function ajax_complete() : void {
        if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Your session has expired. Please reload the page and try again.', 'pluginrx-control-center' ) ], 403 );
        }

        $network = Mode::is_network_context_raw();

        if ( ! ( $network ? current_user_can( 'manage_network_options' ) : current_user_can( 'manage_options' ) ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'pluginrx-control-center' ) ], 403 );
        }

        $mode = isset( $_POST[ 'mode' ] ) ? Mode::sanitize( sanitize_key( wp_unslash( $_POST[ 'mode' ] ) ) ) : '';

        if ( '' === $mode ) {
            wp_send_json_error( [ 'message' => __( 'Choose Host, Agent or Both.', 'pluginrx-control-center' ) ] );
        }

        if ( ! $network ) {
            Mode::set( $mode );
            Mode::complete_setup();
            Database::maybe_install();

            do_action( 'prxctrl_setup_complete', $mode, false );

            wp_send_json_success( [ 'redirect' => Mode::has_host() ? Helpers::page_url( 'sites' ) : Helpers::page_url( 'settings', [ 'tab' => 'agent' ] ) ] );
        }

        $location = isset( $_POST[ 'location' ] ) ? sanitize_key( wp_unslash( $_POST[ 'location' ] ) ) : 'network';
        $location_site = isset( $_POST[ 'location_site' ] ) ? absint( wp_unslash( $_POST[ 'location_site' ] ) ) : 0;
        $subsites = isset( $_POST[ 'subsites' ] ) ? array_map( 'absint', (array) wp_unslash( $_POST[ 'subsites' ] ) ) : [];
        $redirect = self::save_network( $mode, $location, $location_site, $subsites, ! empty( $_POST[ 'auto_include' ] ), 'dashboard' );

        if ( is_wp_error( $redirect ) ) {
            wp_send_json_error( [ 'message' => $redirect->get_error_message() ] );
        }

        do_action( 'prxctrl_setup_complete', $mode, true );

        wp_send_json_success( [ 'redirect' => $redirect ] );
    } // End ajax_complete()


    /**
     * Save the network role and Control Center location, and add the chosen subsites.
     *
     * @param string $mode
     * @param string $location network|site
     * @param int    $location_site
     * @param array  $subsites Blog IDs.
     * @param bool   $auto_include
     * @param string $page Page to open afterward: dashboard (setup) or settings (Site Role tab).
     * @return string|\WP_Error The URL to open next.
     */
    public static function save_network( string $mode, string $location, int $location_site, array $subsites, bool $auto_include, string $page ) {
        $has_host = in_array( $mode, [ Mode::HOST, Mode::BOTH ], true );
        $on_site = 'site' === $location;

        if ( $has_host && $on_site && ( ! $location_site || ! get_site( $location_site ) ) ) {
            return new \WP_Error( 'missing_site', __( 'Choose the site the Control Center should live on.', 'pluginrx-control-center' ) );
        }

        Mode::update_network( [
            'mode'          => $mode,
            'location'      => $on_site ? $location_site : 'network',
            'subsites'      => $on_site ? [] : $subsites,
            'auto_include'  => ! $on_site && $auto_include,
            'setup_version' => Bootstrap::version(),
        ] );

        if ( $has_host && ! $on_site ) {
            Database::maybe_install();

            foreach ( $subsites as $blog_id ) {
                self::add_subsite( (int) $blog_id );
            }
        }

        $args = 'settings' === $page ? '&tab=role' : '';

        if ( ! $has_host ) {
            return 'settings' === $page ? admin_url( 'admin.php?page=' . Helpers::PAGE_PREFIX . 'settings&tab=role' ) : network_admin_url( 'plugins.php' );
        }

        if ( $on_site ) {
            return get_admin_url( $location_site, 'admin.php?page=' . Helpers::PAGE_PREFIX . ( 'settings' === $page ? 'settings' : 'sites' ) . $args );
        }

        return network_admin_url( 'admin.php?page=' . Helpers::PAGE_PREFIX . $page . $args );
    } // End save_network()

} // End class Starter


Starter::instance();
