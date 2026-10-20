<?php
/**
 * CRON
 *
 * One daily event that clears logs older than the retention setting.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Cron {

    /**
     * Event hook name.
     */
    public const EVENT = 'prxctrl_daily';


    /**
     * @var Cron|null Singleton instance
     */
    private static ?Cron $instance = null;


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
        add_action( self::EVENT, [ $this, 'run' ] );
        add_action( 'admin_init', [ $this, 'maybe_schedule' ] );
    } // End __construct()


    /**
     * Schedule the daily event if it is missing.
     *
     * @return void
     */
    public function maybe_schedule() : void {
        if ( '' === Mode::get() || wp_next_scheduled( self::EVENT ) ) {
            return;
        }

        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::EVENT );
    } // End maybe_schedule()


    /**
     * Remove the scheduled event.
     *
     * @return void
     */
    public static function unschedule() : void {
        wp_clear_scheduled_hook( self::EVENT );
    } // End unschedule()


    /**
     * Clear old logs on this site, and in the network tables when the Control Center lives in the
     * network admin and this is the main site.
     *
     * @return void
     */
    public function run() : void {
        if ( Database::table_exists( 'actions' ) ) {
            Database::purge_logs( (int) Options::get( 'log_retention' ) );
        }

        if ( ! Mode::host_in_network_admin() || ! is_main_site() ) {
            return;
        }

        $retention = (int) get_site_option( Options::PREFIX . 'log_retention', Options::definitions()[ 'log_retention' ][ 'default' ] );

        Database::purge_logs( $retention, true );
    } // End run()

} // End class Cron


Cron::instance();
