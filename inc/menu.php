<?php
/**
 * Menu
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Menu {
    
    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?Menu $instance = null;


    /**
     * Get the singleton instance
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

        // Register menu
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        
    } // End __construct()


    /**
     * Register admin menu and submenus
     */
    public function register_menu() : void {
        // Die if not admin or dev
        if ( ! Bootstrap::has_access()) {
            return;
        }

        $parent_name = apply_filters( 'prxctrl_menu_name', Bootstrap::name() );
        $capability = 'manage_options';
        $icon = 'dashicons-networking';
        $position = 2;

        $pages = [
            'dashboard' => __( 'Dashboard', 'pluginrx-control-center' ),
            'settings'  => __( 'Settings', 'pluginrx-control-center' ),
            'logs'      => __( 'Logs', 'pluginrx-control-center' ),
        ];

        $parent = false;

        foreach ( $pages as $key => $title ) {
            if ( ! $parent ) {
                add_menu_page(
                    $parent_name,
                    $parent_name,
                    $capability,
                    'prxctrl-' . $key,
                    [ $this, 'render_' . $key ],
                    $icon,
                    $position
                );

                $parent = 'prxctrl-' . $key;
            }

            add_submenu_page(
                $parent,
                $parent_name . ' » ' . $title,
                $title,
                $capability,
                'prxctrl-' . $key,
                [ $this, 'render_' . $key ]
            );
        }
    } // End register_menu()


    /**
     * Render dashboard page
     */
    public function render_dashboard() : void {
        $slug = 'prxctrl-dashboard';
        if ( empty( $_GET[ 'page' ] ) || sanitize_text_field( wp_unslash( $_GET[ 'page' ] ) ) !== $slug ) {
            return;
        }
        ?><div class="wrap prxctrl-wrap <?php echo esc_attr( $slug ); ?>"><?php
            (new Dashboard())->render_page();
        ?></div><?php
    } // End render_dashboard()


    /**
     * Render settings page
     */
    public function render_settings() : void {
        $slug = 'prxctrl-settings';
        if ( empty( $_GET[ 'page' ] ) || sanitize_text_field( wp_unslash( $_GET[ 'page' ] ) ) !== $slug ) {
            return;
        }
        ?><div class="wrap prxctrl-wrap <?php echo esc_attr( $slug ); ?>"><?php
            (new Settings())->render_page();
        ?></div><?php
    } // End render_settings()


    /**
     * Render logs page
     */
    public function render_logs() : void {
        $slug = 'prxctrl-logs';
        if ( empty( $_GET[ 'page' ] ) || sanitize_text_field( wp_unslash( $_GET[ 'page' ] ) ) !== $slug ) {
            return;
        }
        ?><div class="wrap prxctrl-wrap <?php echo esc_attr( $slug ); ?>"><?php
            (new Logs())->render_page();
        ?></div><?php
    } // End render_logs()
    
}


Menu::instance();