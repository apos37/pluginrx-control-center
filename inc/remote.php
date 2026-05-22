<?php
/**
 * Remote
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Remote {


    /**
     * Constructor
     */
    public function __construct() {

        // AJAX handler for fetching site data
        add_action( 'wp_ajax_prxctrl_fetch_site_data', [ $this, 'fetch_site_data' ] );

        // AJAX handler for actions
        add_action( 'wp_ajax_prxctrl_perform_action', [ $this, 'perform_action' ] );

    } // End __construct()


    /**
     * Fetch site data for remote requests
     */
    public function fetch_site_data() {
        // Check nonce for security
        check_ajax_referer( 'prxctrl_remote_nonce', 'nonce' );

        // Get site ID from request
        $site_id = isset( $_POST[ 'site_id' ] ) ? intval( $_POST[ 'site_id' ] ) : 0;
        if ( $site_id <= 0 ) {
            $error = __( 'Invalid site ID.', 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error ] );
            Database::log_site_action( $site_id, [
                'action'  => 'fetch_site_data',
                'context' => 'ajax_request_error',
                'details' => $error,
            ] );
            wp_send_json_error( [ 'message' => $error ] );
        }

        // Clear the cached screenshot
        delete_transient( 'prxctrl_ss_' . $site_id );

        // Fetch site data from database
        $site = Database::get_site_for_api( $site_id );
        if ( ! $site ) {
            $error = __( 'Site not set up in database.', 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error ] );
            Database::log_site_action( $site_id, [
                'action'  => 'fetch_site_data',
                'context' => 'ajax_request_error',
                'details' => $error,
            ] );
            wp_send_json_error( [ 'message' => $error ] );
        }

        $endpoint = rtrim( $site->site_url, '/' ) . '/wp-json/prx-agent/v1/request';

        $args = [
            'timeout' => absint( get_option( 'prxctrl_timeout', 15 ) ),
            'headers' => [
                'X-PRX-Agent-Key' => $site->api_key,
                'Accept'          => 'application/json',
                'Origin'          => parse_url( home_url(), PHP_URL_HOST ),
            ],
            'body' => [],
        ];

        $response = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            Database::update_site( $site_id, [ 'last_error' => $response->get_error_message() ] );
            Database::log_site_action( $site_id, [
                'action'  => 'fetch_site_data',
                'context' => 'ajax_request_error',
                'details' => $response->get_error_message(),
            ] );
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 || json_last_error() !== JSON_ERROR_NONE ) {
            $body_message = $data[ 'message' ] ?? $body;
            $error = __( 'Invalid response from site. ' . $body_message, 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error ] );
            Database::log_site_action( $site_id, [
                'action'  => 'fetch_site_data',
                'context' => 'ajax_request_error',
                'details' => $error,
            ] );
            wp_send_json_error( [ 'message' => $error ] );
        }

        $now = new \DateTime( 'now', wp_timezone() );
        $last_checked_mysql = $now->format( 'Y-m-d H:i:s' );

        // For display via Dev Debug Tools
        $is_dev_debug_tools_active = is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' );
        $last_checked_display = $last_checked_mysql;
        if ( $is_dev_debug_tools_active ) {
            $last_checked_display = \Apos37\DevDebugTools\Helpers::convert_date_format( $last_checked_mysql );
        }

        $data[ 'last_checked' ] = $last_checked_display;

        // Determine major.minor versions for comparison
        $latest_versions = Helpers::get_latest_versions();

        $allow_prev_wp = sanitize_text_field( get_option( 'prxctrl_dashboard_allow_prev_wp_outdated', '' ) ) === 'yes';
        $allow_prev_php = sanitize_text_field( get_option( 'prxctrl_dashboard_allow_prev_php_outdated', '' ) ) === 'yes';

        $latest_wp_parts = explode( '.', $latest_versions[ 'wordpress' ] );
        $latest_php_parts = explode( '.', $latest_versions[ 'php' ] );

        $latest_wp_minor  = isset( $latest_wp_parts[ 1 ] ) ? (int) $latest_wp_parts[ 1 ] : 0;
        $latest_php_minor = isset( $latest_php_parts[ 1 ] ) ? (int) $latest_php_parts[ 1 ] : 0;

        if ( $allow_prev_wp ) {
            $allowed_wp_min = $latest_wp_parts[ 0 ] . '.' . max( 0, $latest_wp_minor - 1 );
        } else {
            $allowed_wp_min = $latest_versions[ 'wordpress' ];
        }

        if ( $allow_prev_php ) {
            $allowed_php_min = $latest_php_parts[ 0 ] . '.' . max( 0, $latest_php_minor - 1 );
        } else {
            $allowed_php_min = $latest_versions[ 'php' ];
        }

        $wp_current  = ! empty( $data[ 'wordpress_version' ] ) ? sanitize_text_field( $data[ 'wordpress_version' ] ) : '';
        $php_current = ! empty( $data[ 'php_version' ] ) ? sanitize_text_field( $data[ 'php_version' ] ) : '';

        $data[ 'is_wp_outdated' ]  = ( $wp_current && version_compare( $wp_current, $allowed_wp_min, '<' ) );
        $data[ 'is_php_outdated' ] = ( $php_current && version_compare( $php_current, $allowed_php_min, '<' ) );

        // Save/update site in database
        Database::update_site( $site_id, [
            'admin_email'       => isset( $data[ 'admin_email' ] ) ? sanitize_email( $data[ 'admin_email' ] ) : '',
            'server_ip'         => isset( $data[ 'server_ip' ] ) ? sanitize_text_field( $data[ 'server_ip' ] ) : '',
            'abspath'           => isset( $data[ 'abspath' ] ) ? sanitize_text_field( $data[ 'abspath' ] ) : '',
            'is_multisite'      => isset( $data[ 'is_multisite' ] ) ? (int) $data[ 'is_multisite' ] : 0,
            'blog_id'           => isset( $data[ 'blog_id' ] ) ? (int) $data[ 'blog_id' ] : 0,
            'wordpress_version' => isset( $data[ 'wordpress_version' ] ) ? sanitize_text_field( $data[ 'wordpress_version' ] ) : '',
            'php_version'       => isset( $data[ 'php_version' ] ) ? sanitize_text_field( $data[ 'php_version' ] ) : '',
            'wp_debug'          => isset( $data[ 'wp_debug' ] ) ? (int) $data[ 'wp_debug' ] : 0,
            'admin_path'        => isset( $data[ 'admin_path' ] ) ? sanitize_text_field( $data[ 'admin_path' ] ) : '',
            'last_checked'      => $last_checked_mysql,
            'last_error'        => '',
            'other'             => isset( $data[ 'other' ] ) ? wp_json_encode( $data[ 'other' ] ) : '',
        ] );

        // Save other tables
        if ( ! empty( $data[ 'admin_users' ] ) ) {
            Database::update_admin_users( $site_id, $data[ 'admin_users' ] );

            // Process admin users for display
            if ( ! empty( $data[ 'admin_users' ] ) && is_array( $data[ 'admin_users' ] ) ) {
                foreach ( $data[ 'admin_users' ] as &$admin ) {
                    // Convert registered date
                    if ( ! empty( $admin[ 'user_registered' ] ) ) {
                        if ( $is_dev_debug_tools_active ) {
                            $admin[ 'user_registered' ] = \Apos37\DevDebugTools\Helpers::convert_date_format( $admin[ 'user_registered' ] );
                        } else {
                            $admin[ 'user_registered' ] = gmdate( 'Y-m-d H:i:s', strtotime( $admin[ 'user_registered' ] ) );
                        }
                    }

                    // Convert online status
                    $admin[ 'online_status_text' ] = '';

                    if ( ! empty( $admin[ 'online_status' ] ) ) {
                        if ( $admin[ 'online_status' ] === 'online' ) {
                            $admin[ 'online_status_text' ] = esc_html__( 'Online Now', 'pluginrx-control-center' );
                        } else if ( $admin[ 'online_status' ] === 'unknown' ) {
                            $admin[ 'online_status_text' ] = esc_html__( 'Unknown', 'pluginrx-control-center' );
                        } else {
                            if ( $is_dev_debug_tools_active ) {
                                $admin[ 'online_status_text' ] = \Apos37\DevDebugTools\Helpers::convert_date_format( $admin[ 'online_status' ] );
                            } else {
                                $admin[ 'online_status_text' ] = gmdate(
                                    'Y-m-d H:i:s',
                                    is_numeric( $admin[ 'online_status' ] )
                                        ? (int) $admin[ 'online_status' ]
                                        : strtotime( $admin[ 'online_status' ] )
                                );
                            }
                        }
                    }

                    // Normalize roles to comma-separated string
                    if ( ! empty( $admin[ 'role' ] ) ) {
                        $roles = maybe_unserialize( $admin[ 'role' ] );

                        if ( is_array( $roles ) ) {
                            $roles = array_map( 'sanitize_text_field', $roles );
                            $admin[ 'role' ] = implode( ', ', $roles );
                        } else {
                            $admin[ 'role' ] = sanitize_text_field( (string) $roles );
                        }
                    } else {
                        $admin[ 'role' ] = '';
                    }
                }
                unset( $admin );
            }
        }
        if ( ! empty( $data[ 'plugins' ] ) ) {
            Database::update_plugins( $site_id, $data[ 'plugins' ] );
        }
        if ( ! empty( $data[ 'themes' ] ) ) {
            Database::update_themes( $site_id, $data[ 'themes' ] );
        }
        if ( isset( $data[ 'integrations' ] ) && ! empty( $data[ 'integrations' ] ) ) {
            Database::update_integrations( $site_id, $data[ 'integrations' ] );

            // Process integrations data
            if ( is_array( $data[ 'integrations' ] ) ) {
                $integration_defs = apply_filters( 'prxcntrl_integration_requests', [], $site_id, $data[ 'integrations' ] );

                foreach ( $data[ 'integrations' ] as $key => $raw_value ) {
                    if ( ! isset( $integration_defs[ $key ] ) ) {
                        continue;
                    }

                    if ( is_string( $raw_value ) ) {
                        $maybe_array = maybe_unserialize( $raw_value );
                    } else {
                        $maybe_array = $raw_value;
                    }

                    if ( is_array( $maybe_array ) ) {
                        $value = isset( $maybe_array[ 'value' ] ) ? sanitize_text_field( (string) $maybe_array[ 'value' ] ) : '';
                        $link = isset( $maybe_array[ 'link' ] ) ? sanitize_text_field( (string) $maybe_array[ 'link' ] ) : '';
                    } else {
                        $value = sanitize_text_field( (string) $raw_value );
                        $link  = '';
                    }

                    $has_value = ( strtoupper( $value ) !== 'N/A' && $value !== '' && $value !== '0' );

                    $data[ 'integrations' ][ $key ] = [
                        'value'  => $value,
                        'warn'   => $has_value && ! empty( $integration_defs[ $key ][ 'warn' ] ),
                        'link'   => $link ? $link : ( isset( $integration_defs[ $key ][ 'link' ] ) ? sanitize_text_field( $integration_defs[ $key ][ 'link' ] ) : '' ),
                        'format' => $integration_defs[ $key ][ 'format' ] ?? 'none',
                    ];
                }
            }
        }

        // Add the screenshot URL
        $data[ 'screenshot_url' ] = Dashboard::get_site_screenshot( $site->site_url, $site_id );

        // Log the action
        Database::log_site_action( $site_id, [
            'action'  => 'fetch_site_data',
            'context' => 'ajax_request_success',
            'details' => __( 'Data fetched successfully.', 'pluginrx-control-center' ),
        ] );

        wp_send_json_success( $data );
    } // End fetch_site_data()


    /**
     * Perform action on remote site
     */
    public function perform_action() {
        // Check nonce for security
        check_ajax_referer( 'prxctrl_remote_nonce', 'nonce' );

        // Get site ID from request
        $site_id = isset( $_POST[ 'site_id' ] ) ? intval( $_POST[ 'site_id' ] ) : 0;
        if ( $site_id <= 0 ) {
            $error = __( 'Invalid site ID.', 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error ] );
            Database::log_site_action( $site_id, [
                'action'  => 'not_specified',
                'context' => 'ajax_action_error',
                'details' => $error,
            ] );
            wp_send_json_error( [ 'message' => $error ] );
        }

        // Get the action
        $action = isset( $_POST[ 'type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'type' ] ) ) : '';
        if ( empty( $action ) ) {
            $error = __( 'No action specified.', 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error ] );
            Database::log_site_action( $site_id, [
                'action'  => 'not_specified',
                'context' => 'ajax_action_error',
                'details' => $error,
            ] );
            wp_send_json_error( [ 'message' => $error ] );
        }

        // Fetch site data from database
        $site = Database::get_site_for_api( $site_id );
        if ( ! $site ) {
            $error = __( 'Site not set up in database.', 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error ] );
            Database::log_site_action( $site_id, [
                'action'  => $action,
                'context' => 'ajax_action_error',
                'details' => $error,
            ] );
            wp_send_json_error( [ 'message' => $error ] );
        }

        $endpoint = rtrim( $site->site_url, '/' ) . '/wp-json/prx-agent/v1/action';

        $args = [
            'timeout' => absint( get_option( 'prxctrl_timeout', 15 ) ),
            'headers' => [
                'X-PRX-Agent-Key' => $site->api_key,
                'Accept'          => 'application/json',
                'Origin'          => parse_url( home_url(), PHP_URL_HOST ),
            ],
            'body' => [
                'type' => $action,
            ],
        ];

        $response = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            Database::update_site( $site_id, [ 'last_error' => $response->get_error_message() ] );
            Database::log_site_action( $site_id, [
                'action'  => $action,
                'context' => 'ajax_action_error',
                'details' => $response->get_error_message(),
            ] );
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
        } elseif ( $code !== 200 ) {
            if ( json_last_error() === JSON_ERROR_NONE && isset( $data['message'] ) ) {
                $error_message = $data['message'];
            } elseif ( ! empty( $body ) ) {
                $error_message = $body;
            } else {
                $error_message = "HTTP $code error with empty response body.";
            }
        } elseif ( json_last_error() !== JSON_ERROR_NONE ) {
            $error_message = 'Invalid JSON response: ' . json_last_error_msg() . '. Raw body: ' . $body;
        }

        if ( ! empty( $error_message ) ) {
            $error = __( 'Invalid response from site. ' . $error_message, 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error ] );
            Database::log_site_action( $site_id, [
                'action'  => $action,
                'context' => 'ajax_action_error',
                'details' => $error,
            ] );
            wp_send_json_error( [ 'message' => $error ] );
        }

        if ( isset( $data[ 'success' ] ) && $data[ 'success' ] === false ) {
            $error_message = $data[ 'message' ] ?? __( 'Agent returned an error.', 'pluginrx-control-center' );
            Database::update_site( $site_id, [ 'last_error' => $error_message ] );
            Database::log_site_action( $site_id, [
                'action'  => $action,
                'context' => 'ajax_action_error',
                'details' => $error_message,
            ] );
            wp_send_json_error( [ 'message' => $error_message ] );
        }

        // Log the action
        Database::log_site_action( $site_id, [
            'action'  => $action,
            'context' => 'ajax_action_success',
            'details' => __( 'Action performed successfully.', 'pluginrx-control-center' ),
        ] );

        wp_send_json_success( $data );
    } // End perform_action()

}


new Remote();