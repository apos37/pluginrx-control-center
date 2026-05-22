<?php
/**
 * Validate license
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Validator {

    private array $args;
    private string $data_option;
    private string $result_option;
    private string $has_been_validated_option;
    public string $license_key;
    

    /**
     * Constructor
     */
    public function __construct( $args ) {

        $this->args = $args;
        $this->data_option = $args[ 'prefix' ] . '_license_data';
        $this->result_option = $args[ 'prefix' ] . '_license_result';
        $this->has_been_validated_option = $args[ 'prefix' ] . '_license_hbv';
        $this->license_key = sanitize_text_field( get_option( $args[ 'prefix' ] . '_license_id' ) );

        $this->maybe_check_license();
        add_action( 'admin_notices', [ $this, 'invalid_license_notice' ] );
        
    } // End __construct()


    /**
     * Maybe check the license based on timestamp
     *
     * @return void
     */
    public function maybe_check_license() {
        if ( ! $this->license_key ) {
            return;
        }

        $time = $this->get_license_check_time();
        if ( ! $time || ( time() - $time > DAY_IN_SECONDS ) ) {
            $this->check_license();
        }
    } // End maybe_check_license()


    /**
     * Get the time we last checked for a valid license
     *
     * @return string|false
     */
    public function get_license_check_time() {
        $result = get_transient( $this->data_option );
        return $result ? $this->unprocess( $result ) : false;
    } // End get_license_check_time()


    /**
     * Get the license results
     *
     * @return array|false
     */
    public function get_license_results() {
        $result = get_option( $this->result_option );
        return $result ? $this->unprocess( $result ) : false;
    } // End get_license_results()


    /**
     * Check the license
     *
     * @return void
     */
    public function check_license( $return_result = false ) {
        if ( ! $this->license_key ) {
            return;
        }

        $api_url = $this->args[ 'author_uri' ] . 'wp-json/wpe-licenses/v1/validation';

        $response = wp_remote_post( $api_url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Expect'       => '',
            ],
            'body' => wp_json_encode( [
                'license_key' => $this->license_key,
                'site_url'    => home_url(),
                'text_domain' => $this->args[ 'text_domain' ]
            ] ),
        ] );

        // Default fail-safe result
        $validation_result = [
            'status'  => 'error',
            'code'    => 0,
            'message' => __( 'License server unreachable. Last known valid state used.', 'pluginrx-control-center' )
        ];

        if ( ! is_wp_error( $response ) ) {
            $status_code = wp_remote_retrieve_response_code( $response );
            $validation_result[ 'code' ] = $status_code;

            if ( $status_code === 200 ) {
                $body = wp_remote_retrieve_body( $response );
                $decoded = json_decode( $body, true );

                if ( is_array( $decoded ) && isset( $decoded[ 'status' ] ) ) {
                    $validation_result = $decoded;
                    $validation_result[ 'code' ] = $status_code; // preserve actual code
                } else {
                    $validation_result[ 'message' ] = __( 'Invalid response from license server.', 'pluginrx-control-center' );
                }
            } else {
                $validation_result[ 'message' ] = __( 'Could not validate. Status code: ', 'pluginrx-control-center' ) . $status_code;
            }
        } else {
            $validation_result[ 'message' ] .= ' ' . $response->get_error_message();
        }

        // Store the validation result and timestamp
        $processed_result = $this->process( $validation_result );
        update_option( $this->result_option, $processed_result );

        $processed_time = $this->process( time() );
        set_transient( $this->data_option, $processed_time, DAY_IN_SECONDS );

        if ( isset( $validation_result[ 'status' ] ) && $validation_result[ 'status' ] === 'active' ) {
            if ( ! $this->has_been_validated() ) {
                update_option( $this->has_been_validated_option, time(), false );
            }
        }

        if ( $return_result ) {
            return $validation_result;
        }
    } // End check_license()


    /**
     * Check if the license has been validated at least once
     *
     * @return boolean|int
     */
    public function has_been_validated( $return_timestamp = false ) {
        $timestamp = absint( get_option( $this->has_been_validated_option ) );
        $has_timestamp = $timestamp > 0;
        return $has_timestamp && $return_timestamp ? $timestamp : $has_timestamp;
    } // End has_been_validated()


    /**
     * Check for validity everywhere else
     *
     * @return boolean
     */
    public function has_valid_license() {
        $validation_result = $this->get_license_results();

        // Check if the result is an array and process the status
        if ( is_array( $validation_result ) ) {
            $status = $validation_result[ 'status' ] ?? '';

            // Check for a valid license
            if ( $status === 'active' ) {
                if ( ! $this->has_been_validated() ) {
                    update_option( $this->has_been_validated_option, time(), false );
                }
                return true;
            }

            // Check for an expired license with grace period
            if ( $status === 'expired' ) {
                $expiration_date = ( isset( $validation_result[ 'expires' ] ) && strtotime( $validation_result[ 'expires' ] ) ) ? strtotime( $validation_result[ 'expires' ] ) : false;
                if ( ! $expiration_date ) {
                    return false;
                }

                $grace_period = 14 * DAY_IN_SECONDS;
                $current_time = time();

                // Check if within the grace period
                if ( ( $current_time - $expiration_date ) <= $grace_period ) {
                    return true;
                }
            }

            // If we got a response but it's an error, allow if we've had a valid license before
            if ( $status === 'error' ) {
                if ( $this->has_been_validated() ) {
                    return true;
                }
                return false;
            }
        }
    
        return false;
    } // End has_valid_license()


    /**
     * Notice for invalid license
     *
     * @return void
     */
    public function invalid_license_notice() {
        $current_screen = get_current_screen();
        if ( $current_screen->id !== $this->args[ 'settings_screen' ] ) {

            $validation_result = $this->get_license_results();
            $code = $validation_result[ 'code' ] ?? 0;

            // Only show notice if we actually got a response (code != 0) and license is invalid
            if ( $code !== 0 && ! $this->has_valid_license() ) {
                echo '<div class="notice notice-warning"><p>';

                /* translators: 1: Plugin name, 2: Settings page URL */
                echo wp_kses( sprintf( __( 'Your %1$s license is invalid or expired. Please enter a valid license key in the <a href="%2$s">settings</a>.', 'pluginrx-control-center' ),
                    esc_html( $this->args[ 'name' ] ),
                    esc_url( $this->args[ 'settings_url' ] )
                ), [ 'a' => [ 'href' => [], 'target' => [] ] ] );

                echo '</p></div>';
            }
        }
    } // End invalid_license_notice()


    /**
     * Get license comments for settings page
     *
     * @return string
     */
    public function get_license_comments() {
        $comments = '';
        if ( isset( $_GET[ 'page' ] ) && sanitize_key( wp_unslash( $_GET[ 'page' ] ) ) === $this->args[ 'settings_page' ] ) { // phpcs:ignore

            if ( isset( $_GET[ 'settings-updated' ] ) && sanitize_key( wp_unslash( $_GET[ 'settings-updated' ] ) ) === 'true' ) { // phpcs:ignore
                $license = $this->check_license( true );
                $just_updated = true;
            } else {
                $license = $this->get_license_results();
                $just_updated = false;
            }
            
            if ( $license && isset( $license[ 'status' ] ) ) {
                if ( ! $just_updated && $license[ 'status' ] == 'error' ) {
                    $license = $this->check_license( true );
                }

                $time = $this->get_license_check_time();
                $display_time = $time ? (new Helpers())->convert_timezone( $time, 'Y-m-d H:i:s T' ) : 'Unknown';

                $comments = '<span class="license-status ' . sanitize_key( $license[ 'status' ] ) . '" title="' . __( 'Last checked: ', 'pluginrx-control-center' ) . '' . $display_time . '">' . wp_kses_post( $license[ 'message' ] ) . '</span>';
            } else {
                /* translators: %s: Plugin author's website link */
                $comments = '<em>' . sprintf( __( 'Make sure to add your website to your account on %s, then paste your License ID here.', 'pluginrx-control-center' ),
                    '<a href="' . $this->args[ 'author_uri' ] . '" target="_blank">' . str_replace( [ 'https://', '/' ], '', $this->args[ 'author_uri' ] ) . '</a>',
                ) . '</em>';
            }
        }
        return $comments;
    } // End get_license_comments()


    /**
     * Calculate key
     *
     * @return string
     */
    private function calculate_key() {
        return hash( 'sha256', $this->license_key, true );
    } // calculate_key()


    /**
     * Process
     *
     * @param mixed $data
     * @return string
     */
    private function process( $data ) {
        $iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
        $enc_s = openssl_encrypt( serialize( $data ), 'aes-256-cbc', $this->calculate_key(), 0, $iv );
        return base64_encode( $iv . $enc_s );
    } // End process()
    

    /**
     * Unprocess
     *
     * @param string $data
     * @return mixed
     */
    private function unprocess( $data ) {
        $data = base64_decode( $data );
        $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
        $iv = substr( $data, 0, $iv_length );
        $enc_s = substr( $data, $iv_length );
        $decrypted = openssl_decrypt( $enc_s, 'aes-256-cbc', $this->calculate_key(), 0, $iv );
        return unserialize( $decrypted );
    } // End unprocess()

}