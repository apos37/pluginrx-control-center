<?php
/**
 * INTEGRATION: WP MAIL LOGGING
 *
 * Shares the number of failed emails logged by WP Mail Logging and can purge them.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class IntegrationWpMailLogging {

    /**
     * Request key.
     */
    public const COUNT_KEY = 'wp_mail_logging_error_count';


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
        $integrations[ 'wp_mail_logging' ] = [
            'label'    => __( 'WP Mail Logging', 'pluginrx-control-center' ),
            'plugin'   => 'wp-mail-logging/wp-mail-logging.php',
            'builtin'  => true,
            'requests' => [
                self::COUNT_KEY => [
                    'label'       => __( 'Mail Errors', 'pluginrx-control-center' ),
                    'description' => __( 'Share the number of failed emails logged by WP Mail Logging.', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'count' ],
                    'link'        => 'admin.php?page=wpml_plugin_log&status=2',
                    'format'      => 'number',
                    'warn'        => [ $this, 'warn' ],
                ],
            ],
            'actions'  => [
                'wp_mail_logging_clear_errors' => [
                    'label'       => __( 'Purge Mail Errors', 'pluginrx-control-center' ),
                    'description' => __( 'Allow deleting the failed email records logged by WP Mail Logging.', 'pluginrx-control-center' ),
                    'waiting'     => __( 'Purging errors...', 'pluginrx-control-center' ),
                    'callback'    => [ $this, 'purge' ],
                    'available'   => [ $this, 'available' ],
                ],
            ],
        ];

        return $integrations;
    } // End register()


    /**
     * Get the log table.
     *
     * @return string
     */
    private function table() : string {
        global $wpdb;

        return $wpdb->prefix . 'wpml_mails';
    } // End table()


    /**
     * Count failed emails.
     *
     * @return int
     */
    public function count() : int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE error IS NOT NULL AND error <> %s', $this->table(), '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP Mail Logging table; count must be live.
    } // End count()


    /**
     * Warn when there are failed emails.
     *
     * @param mixed $value
     * @return bool
     */
    public function warn( $value ) : bool {
        return is_numeric( $value ) && $value > 0;
    } // End warn()


    /**
     * The purge button is only useful when there are errors.
     *
     * @param object $site
     * @return bool
     */
    public function available( object $site ) : bool {
        $value = Registry::value( $site, self::COUNT_KEY );

        return null === $value || ( is_numeric( $value ) && $value > 0 );
    } // End available()


    /**
     * Delete failed email records.
     *
     * @return array
     */
    public function purge() : array {
        global $wpdb;

        $deleted = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE error IS NOT NULL AND error <> %s', $this->table(), '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP Mail Logging table.

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %d: number of records */
                _n( 'Deleted %d failed email record.', 'Deleted %d failed email records.', $deleted, 'pluginrx-control-center' ),
                $deleted
            ),
        ];
    } // End purge()

} // End class IntegrationWpMailLogging


new IntegrationWpMailLogging();
