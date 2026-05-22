<?php
/**
 * Helpers
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Helpers {

    /**
     * Convert date/time to specified timezone and format, use DDTT settings if provided
     *
     * @param string|int $date     Date string or timestamp.
     * @param string|null $format  Date format. If null, uses the format from settings.
     * @param string|null $timezone Timezone string. If null, uses the timezone from settings or WP timezone.
     * @return string Formatted date/time string.
     */
	public static function convert_timezone( $date, $format = null, $timezone = null ) : string {
        if ( empty( $date ) || $date === '0000-00-00 00:00:00' || $date === 0 || $date === '0' ) {
            return __( 'Undefined', 'pluginrx-control-center' );
        }

        $timestamp = is_numeric( $date ) ? (int) $date : strtotime( $date );
        $format    = $format ?: sanitize_text_field( get_option( 'ddtt_dev_timeformat', 'n/j/Y g:i a T' ) );

        // Use provided timezone, then dev timezone, then WP timezone
        $timezone_string = $timezone ?: sanitize_text_field( get_option( 'ddtt_dev_timezone' ) );
        $tz = $timezone_string ? new \DateTimeZone( $timezone_string ) : wp_timezone();

        return wp_date( $format, $timestamp, $tz );
    } // End convert_timezone()

    
    /**
     * Get latest versions of WordPress and PHP
     *
     * @return array Associative array with 'wordpress' and 'php' keys and their latest version strings
     */
    public static function get_latest_versions() : array {
        $cached_versions = get_transient( 'prxcntrl_latest_versions' );
        if ( $cached_versions !== false ) {
            return $cached_versions;
        }

        $latest_versions = [
            'wordpress' => null,
            'php'       => null,
        ];

        // --- Fetch latest WordPress version ---
        $latest_versions[ 'wordpress' ] = self::get_latest_wp_version();

        // --- Fetch latest PHP version ---
        $latest_versions[ 'php' ] = self::get_latest_php_version();

         // --- Cache for 24 hours ---
        set_transient( 'prxcntrl_latest_versions', $latest_versions, DAY_IN_SECONDS );

        return $latest_versions;
    } // End get_latest_versions()


    /**
     * Get latest WordPress version
     *
     * @return string Latest WordPress version string
     */
    public static function get_latest_wp_version() : string {
        $wp_api = 'https://api.wordpress.org/core/version-check/1.7/';
        $wp_response = wp_remote_get( $wp_api, [ 'timeout' => 10 ] );
        if ( ! is_wp_error( $wp_response ) && wp_remote_retrieve_response_code( $wp_response ) === 200 ) {
            $body = wp_remote_retrieve_body( $wp_response );
            $data = json_decode( $body, true );
            if ( isset( $data[ 'offers' ][ 0 ][ 'version' ] ) ) {
                return $data[ 'offers' ][ 0 ][ 'version' ];
            }
        }
        return false;
    } // End get_latest_wp_version()


    /**
     * Get latest PHP version
     *
     * @param bool $major_only If true, returns only the major version number.
     * @return string|int Latest PHP version string or major version number
     */
    public static function get_latest_php_version( $major_only = false ) : string|int {
        $response = wp_remote_get( 'https://www.php.net/releases/?json' );
        if ( is_wp_error( $response ) || empty( $response[ 'body' ] ) ) {
            return 0;
        }

        $releases = json_decode( $response[ 'body' ] );
        if ( ! is_object( $releases ) || empty( $releases ) ) {
            return 0;
        }

        $latest_major = max( array_map( 'intval', array_keys( (array) $releases ) ) );

        return $major_only ? (int) $latest_major : sanitize_text_field( $releases->$latest_major->version ?? '0.0.0' );
    } // End get_latest_php_version()


    /**
     * Format seconds into a human-readable uptime string.
     *
     * @param int $seconds Number of seconds to format.
     * @return string
     */
    public static function format_bytes( $bytes, $precision = 2 ) : string {
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $bytes = max( $bytes, 0 );
        $power = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        return round( $bytes / ( 1024 ** $power ), $precision ) . ' ' . $units[$power];
    } // End format_bytes()

}