<?php
/**
 * PLUGIN PAGE
 *
 * Adds Guide, Docs and Support links to the plugin's row on the Plugins screen, plus a link to the
 * right settings page for this site's mode.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class PluginPage {

    /**
     * @var PluginPage|null Singleton instance
     */
    private static ?PluginPage $instance = null;


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
        add_filter( 'plugin_row_meta', [ $this, 'add_row_meta' ], 10, 2 );
        add_filter( 'plugin_action_links_' . Bootstrap::basename(), [ $this, 'add_action_links' ] );
        add_filter( 'network_admin_plugin_action_links_' . Bootstrap::basename(), [ $this, 'add_action_links' ] );
    } // End __construct()


    /**
     * Add Guide, Docs and Support links.
     *
     * @param array  $links
     * @param string $plugin_file
     * @return array
     */
    public function add_row_meta( $links, $plugin_file ) : array {
        if ( Bootstrap::basename() !== $plugin_file ) {
            return (array) $links;
        }

        $base = Bootstrap::author_uri();
        $slug = Bootstrap::textdomain();

        $items = [
            'guide'   => [ __( 'How-To Guide', 'pluginrx-control-center' ), $base . 'guide/plugin/' . $slug . '/' ],
            'docs'    => [ __( 'Developer Docs', 'pluginrx-control-center' ), $base . 'docs/plugin/' . $slug . '/' ],
            'support' => [ __( 'Support', 'pluginrx-control-center' ), $base . 'support/plugin/' . $slug . '/' ],
        ];

        foreach ( $items as $key => $item ) {
            $links[ $key ] = '<a href="' . esc_url( $item[ 1 ] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $item[ 0 ] ) . '</a>';
        }

        return (array) $links;
    } // End add_row_meta()


    /**
     * Add a Settings (or Setup) link.
     *
     * @param array $links
     * @return array
     */
    public function add_action_links( $links ) : array {
        if ( Mode::setup_here() ) {
            $link = [ __( 'Setup', 'pluginrx-control-center' ), Helpers::page_url( 'setup' ) ];
        } elseif ( Mode::host_here() || Mode::agent_here() ) {
            $link = [ __( 'Settings', 'pluginrx-control-center' ), Helpers::page_url( 'settings' ) ];
        } else {
            return (array) $links;
        }

        array_unshift( $links, '<a href="' . esc_url( $link[ 1 ] ) . '">' . esc_html( $link[ 0 ] ) . '</a>' );

        return (array) $links;
    } // End add_action_links()

} // End class PluginPage


PluginPage::instance();
