<?php
/**
 * SIGNER
 *
 * Signs and verifies v2 requests between a host and an agent. The API key never travels over the
 * network: the host signs each request with it, and the agent checks the signature with its own copy.
 *
 * Signature: hex HMAC-SHA256 of METHOD \n ROUTE \n TIMESTAMP \n NONCE \n HOST \n SHA256(BODY), keyed with the API key.
 * Requests older than 5 minutes, or with a nonce the agent has already seen, are rejected.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Signer {

    /**
     * Allowed clock difference in seconds.
     */
    public const MAX_SKEW = 300;


    /**
     * Header names.
     */
    public const HEADER_SIGNATURE = 'X-PRX-Signature';
    public const HEADER_TIMESTAMP = 'X-PRX-Timestamp';
    public const HEADER_NONCE = 'X-PRX-Nonce';
    public const HEADER_HOST = 'X-PRX-Host';


    /**
     * Nonces verified during this PHP request. WordPress checks permissions again when it builds the
     * Allow header, so a request that already passed must not be treated as a replay.
     *
     * @var array
     */
    private static array $verified = [];


    /**
     * Build the string that gets signed.
     *
     * @param string $method
     * @param string $route
     * @param string $timestamp
     * @param string $nonce
     * @param string $host
     * @param string $body
     * @return string
     */
    private static function string_to_sign( string $method, string $route, string $timestamp, string $nonce, string $host, string $body ) : string {
        return implode( "\n", [ strtoupper( $method ), '/' . ltrim( $route, '/' ), $timestamp, $nonce, $host, hash( 'sha256', $body ) ] );
    } // End string_to_sign()


    /**
     * Get the signing headers for a request.
     *
     * @param string $key    Shared API key.
     * @param string $method HTTP method.
     * @param string $route  REST route, e.g. /prx-agent/v2/request.
     * @param string $body   Raw request body.
     * @param string $host   This Control Center's host name.
     * @return array
     */
    public static function headers( string $key, string $method, string $route, string $body, string $host ) : array {
        $timestamp = (string) time();
        $nonce = bin2hex( random_bytes( 16 ) );
        $signature = hash_hmac( 'sha256', self::string_to_sign( $method, $route, $timestamp, $nonce, $host, $body ), $key );

        return [
            self::HEADER_SIGNATURE => $signature,
            self::HEADER_TIMESTAMP => $timestamp,
            self::HEADER_NONCE     => $nonce,
            self::HEADER_HOST      => $host,
        ];
    } // End headers()


    /**
     * Verify a signed REST request.
     *
     * @param \WP_REST_Request $request
     * @param string           $key
     * @return true|\WP_Error
     */
    public static function verify( \WP_REST_Request $request, string $key ) {
        $signature = (string) $request->get_header( self::HEADER_SIGNATURE );
        $timestamp = (string) $request->get_header( self::HEADER_TIMESTAMP );
        $nonce = (string) $request->get_header( self::HEADER_NONCE );
        $host = Helpers::host( (string) $request->get_header( self::HEADER_HOST ) );

        if ( '' === $signature || '' === $timestamp || '' === $nonce || '' === $host ) {
            return new \WP_Error( 'missing_signature', __( 'The request is not signed.', 'pluginrx-control-center' ), [ 'status' => 401 ] );
        }

        if ( '' === $key ) {
            return new \WP_Error( 'missing_api_key', __( 'This site does not have an API key yet. Generate one in Settings > Agent.', 'pluginrx-control-center' ), [ 'status' => 401 ] );
        }

        if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > self::MAX_SKEW ) {
            return new \WP_Error( 'signature_expired', __( 'The request timestamp is outside the allowed window. Check that both servers have the correct time.', 'pluginrx-control-center' ), [ 'status' => 401 ] );
        }

        if ( ! preg_match( '/^[a-f0-9]{32}$/', $nonce ) ) {
            return new \WP_Error( 'invalid_signature', __( 'The request signature is invalid.', 'pluginrx-control-center' ), [ 'status' => 401 ] );
        }

        $expected = hash_hmac( 'sha256', self::string_to_sign( $request->get_method(), $request->get_route(), $timestamp, $nonce, $host, (string) $request->get_body() ), $key );

        if ( ! hash_equals( $expected, $signature ) ) {
            return new \WP_Error( 'invalid_signature', __( 'The request signature does not match. The API key is probably different on the two sites.', 'pluginrx-control-center' ), [ 'status' => 401 ] );
        }

        if ( isset( self::$verified[ $nonce ] ) ) {
            return true;
        }

        $nonce_key = 'prxctrl_nonce_' . $nonce;

        if ( false !== get_transient( $nonce_key ) ) {
            return new \WP_Error( 'replayed_request', __( 'This request has already been used.', 'pluginrx-control-center' ), [ 'status' => 401 ] );
        }

        set_transient( $nonce_key, 1, self::MAX_SKEW * 2 );
        self::$verified[ $nonce ] = true;

        return true;
    } // End verify()

} // End class Signer
