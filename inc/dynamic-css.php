<?php
/**
 * DYNAMIC CSS
 *
 * Serves the header and button colors as a real stylesheet so nothing is printed inline. When
 * Admin Help Docs is active, its colors are used so both plugins look alike. The URL carries a hash
 * of the colors, so browsers cache it until they change.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class DynamicCss {

    /**
     * AJAX action name.
     */
    public const ACTION = 'prxctrl_css';


    /**
     * Default colors (keys match Site Perspectives and Admin Help Docs).
     */
    public const DEFAULTS = [
        'header-bg'       => '#1D2327',
        'header-font'     => '#FFFFFF',
        'header-tab'      => '#475467',
        'header-tab-link' => '#98A2B3',
        'accent'          => '#0783BE',
        'button'          => '#0783BE',
        'button-font'     => '#FFFFFF',
        'button-hover'    => '#066998',
    ];


    /**
     * @var DynamicCss|null Singleton instance
     */
    private static ?DynamicCss $instance = null;


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
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'serve' ] );
        add_filter( 'prxctrl_theme_colors', [ $this, 'admin_help_docs_colors' ], 5 );
    } // End __construct()


    /**
     * Use Admin Help Docs' colors when it is active.
     *
     * @param array $colors
     * @return array
     */
    public function admin_help_docs_colors( array $colors ) : array {
        if ( ! Helpers::is_plugin_active( 'admin-help-docs/admin-help-docs.php' ) ) {
            return $colors;
        }

        $ahd = [];

        if ( class_exists( '\PluginRx\AdminHelpDocs\Colors' ) && method_exists( '\PluginRx\AdminHelpDocs\Colors', 'get' ) ) {
            $ahd = \PluginRx\AdminHelpDocs\Colors::get();
        }

        if ( ! is_array( $ahd ) || empty( $ahd ) ) {
            $ahd = get_option( 'helpdocs_colors', [] );
        }

        if ( ! is_array( $ahd ) ) {
            return $colors;
        }

        $map = [
            'header_bg'       => 'header-bg',
            'header_font'     => 'header-font',
            'header_tab'      => 'header-tab',
            'header_tab_link' => 'header-tab-link',
            'doc_accent'      => 'accent',
            'button'          => 'button',
            'button_font'     => 'button-font',
            'button_hover'    => 'button-hover',
        ];

        foreach ( $map as $ahd_key => $key ) {
            $hex = sanitize_hex_color( (string) ( $ahd[ $ahd_key ] ?? '' ) );

            if ( $hex ) {
                $colors[ $key ] = $hex;
            }
        }

        return $colors;
    } // End admin_help_docs_colors()


    /**
     * Get the resolved colors.
     *
     * @return array
     */
    public static function colors() : array {
        $colors = array_merge( self::DEFAULTS, (array) apply_filters( 'prxctrl_theme_colors', [] ) );

        foreach ( $colors as $key => $value ) {
            $hex = sanitize_hex_color( (string) $value );
            $colors[ $key ] = $hex ? $hex : ( self::DEFAULTS[ $key ] ?? '#000000' );
        }

        return $colors;
    } // End colors()


    /**
     * Build the CSS.
     *
     * @return string
     */
    public static function build() : string {
        $declarations = '';

        foreach ( self::colors() as $key => $hex ) {
            $declarations .= '--prxctrl-color-' . sanitize_key( $key ) . ':' . $hex . ';';
        }

        return ':root{' . $declarations . '}';
    } // End build()


    /**
     * Enqueue the stylesheet.
     *
     * @param array $deps
     * @return void
     */
    public static function enqueue( array $deps = [] ) : void {
        $url = add_query_arg( [ 'action' => self::ACTION ], admin_url( 'admin-ajax.php' ) );

        wp_enqueue_style( 'prxctrl-colors', $url, $deps, substr( md5( self::build() ), 0, 12 ) );
    } // End enqueue()


    /**
     * AJAX: output the stylesheet.
     *
     * @return void
     */
    public function serve() : void {
        header( 'Content-Type: text/css; charset=UTF-8' );
        header( 'Cache-Control: private, max-age=31536000' );
        header_remove( 'Pragma' );
        header_remove( 'Expires' );

        echo wp_strip_all_tags( self::build() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from sanitized hex colors with tags stripped.
        exit;
    } // End serve()

} // End class DynamicCss


DynamicCss::instance();
