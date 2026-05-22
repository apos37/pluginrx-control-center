<?php
/**
 * Plugin Page
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class PluginPage {

    /**
     * Plugin file
     * 
     * @var string
     */
    private $plugin_file = 'pluginrx-control-center/pluginrx-control-center.php';


    /**
     * Text domain
     * 
     * @var string
     */
    private $text_domain;


	/**
     * Constructor
     */
    public function __construct() {

        // Set text domain
        $this->text_domain = Bootstrap::textdomain();

        // Add links to the website and discord
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

        // Add settings link
        add_filter( 'plugin_action_links_' . $this->plugin_file, [ $this, 'add_settings_link' ] );

    } // End __construct()


    /**
     * Add links to plugin row meta.
     *
     * @param array  $links Existing plugin meta links.
     * @param string $file  Plugin file.
     * @return array Modified plugin meta links.
     */
    public function plugin_row_meta( $links, $file ) {
        if ( $this->plugin_file !== $file ) {
            return (array) $links;
        }

        $plugin_name = Bootstrap::name();
        $base_url    = Bootstrap::author_uri();
        $our_links   = [
            // 'guide' => [ // TODO:
            //     'label' => __( 'How-To Guide', 'pluginrx-control-center' ),
            //     'url'   => "{$base_url}guide/plugin/{$this->text_domain}",
            // ],
            // 'docs' => [
            //     'label' => __( 'Developer Docs', 'pluginrx-control-center' ),
            //     'url'   => "{$base_url}docs/plugin/{$this->text_domain}",
            // ],
            'support' => [
                'label' => __( 'Support', 'pluginrx-control-center' ),
                'url'   => "{$base_url}support/plugin/{$this->text_domain}",
            ],
        ];

        foreach ( $our_links as $key => $link ) {
            $aria_label = sprintf(
                // translators: %1$s: Link label, %2$s: Plugin name
                __( '%1$s for %2$s', 'pluginrx-control-center' ),
                $link[ 'label' ],
                $plugin_name
            );
            $links[ $key ] = '<a href="' . esc_url( $link[ 'url' ] ) . '" target="_blank" aria-label="' . esc_attr( $aria_label ) . '">' . esc_html( $link[ 'label' ] ) . '</a>';
        }

        return $links;
    } // End plugin_row_meta()


    /**
     * Add settings link to plugin action links.
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_settings_link( $links ) {
        $url = Bootstrap::settings_url();

        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $url ),
            esc_html__( 'Settings', 'pluginrx-control-center' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    } // End add_settings_link()

}


new PluginPage();