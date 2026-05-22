<?php
/**
 * License Manager
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class LicenseManager {

    private static ?self $instance = null;

    public Validator $validator;
    public Updater $updater;

    public static function init( array $args ): self {
        if ( self::$instance === null ) {
            self::$instance = new self( $args );
        }

        return self::$instance;
    }

    public static function instance(): ?self {
        return self::$instance;
    }

    private function __construct( array $args ) {
        $this->validator = new Validator( $args );
        $this->updater   = new Updater( $args, $this->validator );
    }

    public function has_been_validated(): bool {
        return $this->validator->has_been_validated();
    }

    public function get_license_key(): string {
        return $this->validator->license_key;
    }

    public function get_license_comments(): string {
        return $this->validator->get_license_comments();
    }
}
