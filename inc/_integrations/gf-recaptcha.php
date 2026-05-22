<?php
/**
 * Gravity Forms reCAPTCHA Integration
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class GravityFormsRecaptchaController {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'prxcntrl_integration_requests', [ $this, 'integration_requests' ], 10, 3 );
    } // End __construct()


    /**
     * Add Gravity Forms spam count
     *
     * @param array $integrations Existing integrations.
     * @param int $site_id Site ID.
     * @param array $data Integration data for the site.
     * @return array Modified integrations.
     */
    public function integration_requests( $integrations, $site_id, $data ) : array {
        $key = 'gf_recaptcha_reauth';
        $integrations[ $key ] = [
            'label' => __( 'reCAPTCHA Reauth', 'pluginrx-control-center' ),
            'warn'  => ! empty( $data[ $key ] ) && $data[ $key ] > 0,
            'link'  => 'admin.php?page=gf_settings&subview=gravityformsrecaptcha',
        ];

        return $integrations;
    } // End integration_requests()

}


// TODO: NOT WORKING, but may not need it. Just leave it for now, though.
// new GravityFormsRecaptchaController();