<?php
/**
 * MENU
 *
 * Registers the admin pages that apply to this site's mode, and renders the shared header and
 * subheader. Help tabs and Screen Options are removed from every page.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Menu {

    /**
     * Top-level menu slug.
     */
    public const PARENT = 'prxctrl-dashboard';


    /**
     * @var Menu|null Singleton instance
     */
    private static ?Menu $instance = null;


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
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'network_admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_page_access_denied', [ $this, 'redirect_old_pages' ] );
        add_action( 'current_screen', [ $this, 'clean_screen' ] );
        add_action( 'in_admin_header', [ $this, 'maybe_render_header' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_shared_assets' ] );
        add_filter( 'admin_body_class', [ $this, 'body_class' ] );
    } // End __construct()


    /**
     * Get the pages available in the current context, in menu order.
     *
     * @return array slug => [ title, callback, manage ]
     */
    public static function pages() : array {
        if ( Mode::setup_here() ) {
            return [
                'setup' => [ __( 'Setup', 'pluginrx-control-center' ), [ Starter::class, 'render_page' ], true ],
            ];
        }

        $host = Mode::host_here();
        $agent = Mode::agent_here();

        if ( ! $host && ! $agent ) {
            return [];
        }

        $pages = [
            'dashboard' => [ __( 'Dashboard', 'pluginrx-control-center' ), [ Dashboard::class, 'render_page' ], false ],
        ];

        if ( $host ) {
            $pages[ 'sites' ] = [ __( 'Sites', 'pluginrx-control-center' ), [ Sites::class, 'render_page' ], false ];
        }

        $pages[ 'logs' ] = [ __( 'Logs', 'pluginrx-control-center' ), [ Logs::class, 'render_page' ], false ];
        $pages[ 'settings' ] = [ __( 'Settings', 'pluginrx-control-center' ), [ SettingsUi::class, 'render_page' ], true ];

        return (array) apply_filters( 'prxctrl_pages', $pages );
    } // End pages()


    /**
     * Get the menu title.
     *
     * @return string
     */
    public static function menu_name() : string {
        return (string) apply_filters( 'prxctrl_menu_name', __( 'Control Center', 'pluginrx-control-center' ) );
    } // End menu_name()


    /**
     * Get the page title shown in the header.
     *
     * @return string
     */
    public static function page_name() : string {
        return (string) apply_filters( 'prxctrl_page_name', Bootstrap::name() );
    } // End page_name()


    /**
     * Register the menu and its pages.
     *
     * @return void
     */
    public function register_menu() : void {
        if ( is_network_admin() && ! Mode::is_network_active() ) {
            return;
        }

        $pages = self::pages();

        if ( empty( $pages ) || ! Access::can_access() ) {
            return;
        }

        $parent = Helpers::PAGE_PREFIX . array_key_first( $pages );
        $manage_cap = is_network_admin() ? 'manage_network_options' : 'manage_options';
        $first = reset( $pages );

        add_menu_page( self::page_name(), self::menu_name(), $first[ 2 ] ? $manage_cap : 'read', $parent, $first[ 1 ], 'dashicons-networking', 3 );

        foreach ( $pages as $slug => $page ) {
            add_submenu_page( $parent, self::page_name() . ' &rsaquo; ' . $page[ 0 ], $page[ 0 ], $page[ 2 ] ? $manage_cap : 'read', Helpers::PAGE_PREFIX . $slug, $page[ 1 ] );
        }
    } // End register_menu()


    /**
     * Send the old settings page slugs to the matching Settings tab. Runs when WordPress is about to
     * deny access to a page that no longer exists.
     *
     * @return void
     */
    public function redirect_old_pages() : void {
        $old = [
            Helpers::PAGE_PREFIX . 'host-settings'  => 'host',
            Helpers::PAGE_PREFIX . 'agent-settings' => 'agent',
        ];
        $page = Helpers::current_page();

        if ( isset( $old[ $page ] ) ) {
            wp_safe_redirect( Helpers::page_url( 'settings', [ 'tab' => $old[ $page ] ] ) );
            exit;
        }
    } // End redirect_old_pages()


    /**
     * Get the current page key without the prefix.
     *
     * @return string
     */
    public static function current_key() : string {
        $page = Helpers::current_page();

        return 0 === strpos( $page, Helpers::PAGE_PREFIX ) ? substr( $page, strlen( Helpers::PAGE_PREFIX ) ) : '';
    } // End current_key()


    /**
     * Remove help tabs and Screen Options from the plugin's pages.
     *
     * @param \WP_Screen $screen
     * @return void
     */
    public function clean_screen( $screen ) : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $screen->remove_help_tabs();
        add_filter( 'screen_options_show_screen', '__return_false' );
    } // End clean_screen()


    /**
     * Add a body class on the plugin's pages.
     *
     * @param string $classes
     * @return string
     */
    public function body_class( $classes ) : string {
        if ( Helpers::is_plugin_screen() ) {
            $classes .= ' prxctrl-screen prxctrl-page-' . sanitize_html_class( self::current_key() );
        }

        return (string) $classes;
    } // End body_class()


    /**
     * Render the header and subheader before admin notices.
     *
     * @return void
     */
    public function maybe_render_header() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $pages = self::pages();
        $current = self::current_key();

        if ( ! isset( $pages[ $current ] ) ) {
            return;
        }

        self::render_header( $pages, $current );
        self::render_subheader( $pages[ $current ][ 0 ], $current );
    } // End maybe_render_header()


    /**
     * Render the header: logo, title and tabs.
     *
     * @param array  $pages
     * @param string $current
     * @return void
     */
    public static function render_header( array $pages, string $current ) : void {
        $manage = Access::can_manage();
        $logo = (string) apply_filters( 'prxctrl_logo', Bootstrap::url( 'inc/img/logo.png' ) );
        ?>
        <div id="prxctrl-header">
            <img src="<?php echo esc_url( $logo ); ?>" alt="" class="logo">
            <div class="title-cont">
                <h1 title="<?php echo esc_attr( __( 'Version', 'pluginrx-control-center' ) . ' ' . Bootstrap::version() ); ?>"><?php echo esc_html( self::page_name() ); ?></h1>
            </div>
            <nav class="tabs-wrapper" aria-label="<?php esc_attr_e( 'Control Center pages', 'pluginrx-control-center' ); ?>">
                <?php foreach ( $pages as $slug => $page ) : ?>
                    <?php
                    if ( $page[ 2 ] && ! $manage ) {
                        continue;
                    }
                    ?>
                    <a href="<?php echo esc_url( Helpers::page_url( $slug ) ); ?>" class="prxctrl-tab<?php echo $current === $slug ? ' prxctrl-tab-active' : ''; ?>"<?php echo $current === $slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $page[ 0 ] ); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="header-right">
                <?php do_action( 'prxctrl_header_right', $current ); ?>
            </div>
        </div>
        <?php
    } // End render_header()


    /**
     * Render the subheader: page title and actions on the left, tools on the right.
     *
     * @param string $title
     * @param string $current
     * @return void
     */
    public static function render_subheader( string $title, string $current ) : void {
        ?>
        <div id="prxctrl-subheader">
            <div class="subheader-left">
                <h2 class="tab-title"><?php echo esc_html( $title ); ?></h2>
                <?php do_action( 'prxctrl_subheader_left', $current ); ?>
            </div>
            <div class="subheader-right">
                <?php do_action( 'prxctrl_subheader_right', $current ); ?>
            </div>
        </div>
        <?php
    } // End render_subheader()


    /**
     * Enqueue the shared stylesheet and script on the plugin's pages.
     *
     * @return void
     */
    public function enqueue_shared_assets() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $version = Bootstrap::script_version();

        wp_enqueue_style( 'prxctrl-theme', Bootstrap::url( 'inc/css/theme.css' ), [], $version );
        DynamicCss::enqueue( [ 'prxctrl-theme' ] );

        wp_enqueue_script( 'prxctrl-common', Bootstrap::url( 'inc/js/common.js' ), [ 'jquery' ], $version, true );
        wp_localize_script( 'prxctrl-common', 'prxctrlCommon', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'network'    => Mode::is_network_context() || Mode::is_network_context_raw() ? '1' : '',
            'consoleLog' => Options::get( 'console_log' ) ? '1' : '',
            'i18n'       => [
                'saving'  => __( 'Saving', 'pluginrx-control-center' ),
                'saved'   => __( 'Saved.', 'pluginrx-control-center' ),
                'error'   => __( 'Something went wrong. Please try again.', 'pluginrx-control-center' ),
                'unsaved' => __( 'You have unsaved changes. Leave anyway?', 'pluginrx-control-center' ),
                'close'   => __( 'Close', 'pluginrx-control-center' ),
                'expired' => __( 'Your session has expired. Please reload the page and try again.', 'pluginrx-control-center' ),
            ],
        ] );
    } // End enqueue_shared_assets()

} // End class Menu


Menu::instance();
