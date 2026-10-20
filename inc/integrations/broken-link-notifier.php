<?php
/**
 * INTEGRATION: BROKEN LINK NOTIFIER
 *
 * Shares the number of broken links found by Broken Link Notifier.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class IntegrationBrokenLinkNotifier {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxctrl_integrations', [ $this, 'register' ] );
    } // End __construct()


    /**
     * Register the integration.
     *
     * @param array $integrations
     * @return array
     */
    public function register( array $integrations ) : array {
        $integrations[ 'broken_link_notifier' ] = [
            'label'    => __( 'Broken Link Notifier', 'pluginrx-control-center' ),
            'plugin'   => 'broken-link-notifier/broken-link-notifier.php',
            'builtin'  => true,
            'requests' => [
                'broken_link_notifier_count' => [
                    'label'       => __( 'Broken Links', 'pluginrx-control-center' ),
                    'description' => __( 'Share the number of broken links found by Broken Link Notifier.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'count' ],
                    'link'        => 'admin.php?page=broken-link-notifier&tab=results',
                    'format'      => 'number',
                    'warn'        => [ $this, 'warn' ],
                ],
            ],
        ];

        return $integrations;
    } // End register()


    /**
     * Count broken links.
     *
     * @return int
     */
    public function count() : int {
        if ( ! class_exists( '\BLNOTIFIER_HELPERS' ) || ! method_exists( '\BLNOTIFIER_HELPERS', 'count_broken_links' ) ) {
            return 0;
        }

        return (int) ( new \BLNOTIFIER_HELPERS() )->count_broken_links();
    } // End count()


    /**
     * Warn when any broken links are found.
     *
     * @param mixed $value
     * @return bool
     */
    public function warn( $value ) : bool {
        return is_numeric( $value ) && $value > 0;
    } // End warn()

} // End class IntegrationBrokenLinkNotifier


new IntegrationBrokenLinkNotifier();
