<?php
/**
 * INTEGRATION: ADVANCED TOOLS FOR GRAVITY FORMS
 *
 * Shares the number of Gravity Forms spam entries and can delete them.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class IntegrationGfTools {

    /**
     * Request key.
     */
    public const COUNT_KEY = 'gftools_spam_count';


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
        $integrations[ 'gf_tools' ] = [
            'label'    => __( 'Advanced Tools for Gravity Forms', 'pluginrx-control-center' ),
            'plugin'   => 'gf-tools/gf-tools.php',
            'builtin'  => true,
            'requests' => [
                self::COUNT_KEY => [
                    'label'       => __( 'Form Spam', 'pluginrx-control-center' ),
                    'description' => __( 'Share the number of Gravity Forms entries marked as spam.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'count' ],
                    'link'        => 'admin.php?page=gf-tools&tab=spam_entries',
                    'format'      => 'number',
                    'warn'        => [ $this, 'warn' ],
                ],
            ],
            'actions'  => [
                'gftools_delete_spam' => [
                    'label'       => __( 'Delete Form Spam', 'pluginrx-control-center' ),
                    'description' => __( 'Allow deleting every Gravity Forms entry marked as spam.', 'pluginrx-control-center' ),
                    'waiting'     => __( 'Deleting spam entries...', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'delete_spam' ],
                    'available'   => [ $this, 'available' ],
                ],
            ],
        ];

        return $integrations;
    } // End register()


    /**
     * Get the entries table, if Gravity Forms is installed.
     *
     * @return string
     */
    private function table() : string {
        global $wpdb;

        return $wpdb->prefix . 'gf_entry';
    } // End table()


    /**
     * Count spam entries.
     *
     * @return int
     */
    public function count() : int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(id) FROM %i WHERE status = %s', $this->table(), 'spam' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Gravity Forms table; count must be live.
    } // End count()


    /**
     * Warn when there are spam entries.
     *
     * @param mixed $value
     * @return bool
     */
    public function warn( $value ) : bool {
        return is_numeric( $value ) && $value > 0;
    } // End warn()


    /**
     * The delete button is only useful when there is spam.
     *
     * @param object $site
     * @return bool
     */
    public function available( object $site ) : bool {
        $value = Registry::value( $site, self::COUNT_KEY );

        return null === $value || ( is_numeric( $value ) && $value > 0 );
    } // End available()


    /**
     * Delete spam entries.
     *
     * @return array
     */
    public function delete_spam() : array {
        global $wpdb;

        $deleted = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE status = %s', $this->table(), 'spam' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Gravity Forms table.

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %d: number of entries */
                _n( 'Deleted %d spam entry.', 'Deleted %d spam entries.', $deleted, 'pluginrx-control-center' ),
                $deleted
            ),
        ];
    } // End delete_spam()

} // End class IntegrationGfTools


new IntegrationGfTools();
