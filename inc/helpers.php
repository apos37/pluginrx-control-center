<?php
/**
 * HELPERS
 *
 * Small, stateless utilities shared across the plugin.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Helpers {

    /**
     * Admin page slug prefix.
     */
    public const PAGE_PREFIX = 'prxctrl-';


    /**
     * Check if the current admin screen belongs to this plugin.
     *
     * @return bool
     */
    public static function is_plugin_screen() : bool {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();

        return $screen && false !== strpos( $screen->id, self::PAGE_PREFIX );
    } // End is_plugin_screen()


    /**
     * Get the current plugin page slug from the query string.
     *
     * @return string
     */
    public static function current_page() : string {
        return isset( $_GET[ 'page' ] ) ? sanitize_key( wp_unslash( $_GET[ 'page' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
    } // End current_page()


    /**
     * Build the URL to one of the plugin's admin pages, in the network admin when the host lives there.
     *
     * @param string $page Page slug without the prefix, e.g. 'dashboard'.
     * @param array  $args Extra query args.
     * @return string
     */
    public static function page_url( string $page, array $args = [] ) : string {
        $base = Mode::is_network_context() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );

        return add_query_arg( array_merge( [ 'page' => self::PAGE_PREFIX . $page ], $args ), $base );
    } // End page_url()


    /**
     * Print a help tooltip.
     *
     * @param string $text
     * @return void
     */
    public static function tooltip( string $text ) : void {
        if ( '' === $text ) {
            return;
        }
        ?>
        <span class="prxctrl-tooltip" tabindex="0">
            <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
            <span class="prxctrl-tooltip-text" role="tooltip"><?php echo wp_kses_post( $text ); ?></span>
        </span>
        <?php
    } // End tooltip()


    /**
     * Format a date stored in the site's local time using the plugin's date format and timezone settings.
     *
     * @param string|int $date MySQL datetime in site time, or a Unix timestamp.
     * @return string
     */
    public static function format_date( $date ) : string {
        if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
            return '';
        }

        try {
            if ( is_numeric( $date ) ) {
                $datetime = new \DateTimeImmutable( '@' . (int) $date );
            } else {
                $datetime = new \DateTimeImmutable( (string) $date, wp_timezone() );
            }

            $timezone = (string) Options::get( 'timezone' );
            $zone = ( '' !== $timezone && in_array( $timezone, timezone_identifiers_list(), true ) ) ? new \DateTimeZone( $timezone ) : wp_timezone();

            return wp_date( (string) Options::get( 'date_format' ), $datetime->getTimestamp(), $zone );
        } catch ( \Exception $e ) {
            return sanitize_text_field( (string) $date );
        }
    } // End format_date()


    /**
     * Get the current time as a MySQL datetime in the site's timezone.
     *
     * @return string
     */
    public static function now() : string {
        return current_time( 'mysql' );
    } // End now()


    /**
     * Convert a date saved with now() back to a Unix timestamp.
     *
     * @param string $date
     * @return int 0 when the date is empty or invalid.
     */
    public static function timestamp( string $date ) : int {
        if ( '' === $date ) {
            return 0;
        }

        $parsed = date_create( $date, wp_timezone() );

        return $parsed ? $parsed->getTimestamp() : 0;
    } // End timestamp()


    /**
     * Normalize a host name for comparisons: lowercase, no scheme, port, path or leading www.
     *
     * @param string $value URL or host.
     * @return string
     */
    public static function host( string $value ) : string {
        $value = strtolower( trim( $value ) );

        if ( '' === $value ) {
            return '';
        }

        if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#', $value ) ) {
            $value = 'http://' . $value;
        }

        $parts = wp_parse_url( $value );

        return isset( $parts[ 'host' ] ) ? (string) preg_replace( '/^www\./', '', $parts[ 'host' ] ) : '';
    } // End host()


    /**
     * Normalize a site URL: http or https only, no trailing slash, no query or fragment.
     *
     * @param string $url
     * @return string Empty string when invalid.
     */
    public static function normalize_url( string $url ) : string {
        $url = trim( $url );

        if ( '' === $url ) {
            return '';
        }

        if ( ! preg_match( '#^https?://#i', $url ) ) {
            $url = 'https://' . $url;
        }

        $parts = wp_parse_url( esc_url_raw( $url, [ 'http', 'https' ] ) );

        if ( empty( $parts[ 'host' ] ) || empty( $parts[ 'scheme' ] ) ) {
            return '';
        }

        $normalized = strtolower( $parts[ 'scheme' ] ) . '://' . strtolower( $parts[ 'host' ] );

        if ( ! empty( $parts[ 'port' ] ) ) {
            $normalized .= ':' . (int) $parts[ 'port' ];
        }

        if ( ! empty( $parts[ 'path' ] ) ) {
            $normalized .= untrailingslashit( $parts[ 'path' ] );
        }

        return $normalized;
    } // End normalize_url()


    /**
     * Get the sanitized IP address of the current request.
     *
     * @return string
     */
    public static function ip() : string {
        $ip = isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ 'REMOTE_ADDR' ] ) ) : '';

        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    } // End ip()


    /**
     * Check if a plugin is active on the current site (or network-activated).
     *
     * @param string $plugin_file
     * @return bool
     */
    public static function is_plugin_active( string $plugin_file ) : bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_file );
    } // End is_plugin_active()


    /**
     * Decode a JSON string into an array, returning an empty array on failure.
     *
     * @param mixed $json
     * @return array
     */
    public static function json_array( $json ) : array {
        if ( is_array( $json ) ) {
            return $json;
        }

        if ( ! is_string( $json ) || '' === $json ) {
            return [];
        }

        $decoded = json_decode( $json, true );

        return is_array( $decoded ) ? $decoded : [];
    } // End json_array()


    /**
     * Get the latest WordPress and PHP versions. Cached for 12 hours, with the last known values kept as a fallback.
     *
     * @return array [ 'wordpress' => string, 'php' => string ]
     */
    public static function latest_versions() : array {
        $cached = get_transient( 'prxctrl_latest_versions' );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $known = get_option( 'prxctrl_latest_versions_known', [ 'wordpress' => '', 'php' => '' ] );
        $known = is_array( $known ) ? $known : [ 'wordpress' => '', 'php' => '' ];
        $wordpress = self::fetch_latest_wp_version();
        $php = self::fetch_latest_php_version();
        $failed = ( '' === $wordpress || '' === $php );

        $versions = [
            'wordpress' => '' !== $wordpress ? $wordpress : (string) ( $known[ 'wordpress' ] ?? '' ),
            'php'       => '' !== $php ? $php : (string) ( $known[ 'php' ] ?? '' ),
        ];

        if ( ! $failed ) {
            update_option( 'prxctrl_latest_versions_known', $versions, false );
        }

        set_transient( 'prxctrl_latest_versions', $versions, $failed ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );

        return $versions;
    } // End latest_versions()


    /**
     * Fetch the latest WordPress version from api.wordpress.org.
     *
     * @return string
     */
    private static function fetch_latest_wp_version() : string {
        $response = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/', [ 'timeout' => 8 ] );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $version = $data[ 'offers' ][ 0 ][ 'version' ] ?? '';

        return preg_match( '/^\d+(\.\d+)*$/', (string) $version ) ? (string) $version : '';
    } // End fetch_latest_wp_version()


    /**
     * Fetch the latest PHP version from php.net.
     *
     * @return string
     */
    private static function fetch_latest_php_version() : string {
        $response = wp_remote_get( 'https://www.php.net/releases/?json', [ 'timeout' => 8 ] );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return '';
        }

        $releases = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $releases ) || empty( $releases ) ) {
            return '';
        }

        $major = max( array_map( 'intval', array_keys( $releases ) ) );
        $version = $releases[ $major ][ 'version' ] ?? '';

        return preg_match( '/^\d+(\.\d+)*$/', (string) $version ) ? (string) $version : '';
    } // End fetch_latest_php_version()


    /**
     * Split a version into integer parts, padded to three.
     *
     * @param string $version
     * @return array
     */
    public static function version_parts( string $version ) : array {
        preg_match_all( '/\d+/', $version, $matches );
        $parts = array_map( 'intval', array_slice( $matches[ 0 ], 0, 3 ) );

        return array_pad( $parts, 3, 0 );
    } // End version_parts()


    /**
     * Get the version warning levels with example labels built from the latest version.
     *
     * @param string $latest
     * @return array level => label
     */
    public static function version_levels( string $latest ) : array {
        [ $major, $minor, $patch ] = self::version_parts( '' !== $latest ? $latest : '0.0.0' );
        $previous = max( 0, $minor - 1 );

        return [
            'exact'    => sprintf(
                /* translators: %s: version number such as 7.1.3 */
                __( 'Older than %s (latest release)', 'pluginrx-control-center' ),
                $major . '.' . $minor . '.' . $patch
            ),
            'minor'    => sprintf(
                /* translators: %s: version pattern such as 7.1.x */
                __( 'Older than %s (latest minor version)', 'pluginrx-control-center' ),
                $major . '.' . $minor . '.x'
            ),
            'previous' => sprintf(
                /* translators: %s: version pattern such as 7.0.x */
                __( 'Older than %s (one minor version behind)', 'pluginrx-control-center' ),
                $major . '.' . $previous . '.x'
            ),
            'major'    => sprintf(
                /* translators: %s: version pattern such as 7.x.x */
                __( 'Older than %s (latest major version)', 'pluginrx-control-center' ),
                $major . '.x.x'
            ),
            'off'      => __( 'Never warn', 'pluginrx-control-center' ),
        ];
    } // End version_levels()


    /**
     * Check if a version is outdated relative to the latest version and a warning level.
     *
     * @param string $current
     * @param string $latest
     * @param string $level exact|minor|previous|major|off
     * @return bool
     */
    public static function is_outdated( string $current, string $latest, string $level ) : bool {
        if ( '' === $current || '' === $latest || 'off' === $level ) {
            return false;
        }

        [ $cur_major, $cur_minor, $cur_patch ] = self::version_parts( $current );
        [ $new_major, $new_minor, $new_patch ] = self::version_parts( $latest );

        switch ( $level ) {
            case 'exact':
                return version_compare( "$cur_major.$cur_minor.$cur_patch", "$new_major.$new_minor.$new_patch", '<' );
            case 'previous':
                return version_compare( "$cur_major.$cur_minor", $new_major . '.' . max( 0, $new_minor - 1 ), '<' );
            case 'major':
                return $cur_major < $new_major;
            default:
                return version_compare( "$cur_major.$cur_minor", "$new_major.$new_minor", '<' );
        }
    } // End is_outdated()


    /**
     * Turn a relative admin path into a full URL on a remote site's admin area.
     *
     * @param string $admin_path The remote site's admin URL.
     * @param string $link Relative path or absolute URL.
     * @return string
     */
    public static function admin_link( string $admin_path, string $link ) : string {
        if ( '' === $link ) {
            return '';
        }

        if ( preg_match( '#^https?://#i', $link ) ) {
            return $link;
        }

        if ( '' === $admin_path ) {
            return '';
        }

        return trailingslashit( $admin_path ) . ltrim( $link, '/' );
    } // End admin_link()


    /**
     * Get a short, human-readable label for an action or context key.
     *
     * @param string $key
     * @return string
     */
    public static function humanize( string $key ) : string {
        return ucwords( str_replace( [ '_', '-' ], ' ', $key ) );
    } // End humanize()

} // End class Helpers
