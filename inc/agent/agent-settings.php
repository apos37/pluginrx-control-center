<?php
/**
 * AGENT SETTINGS
 *
 * Controls what a Control Center can see and do on this site: remote access, allowed domains,
 * the API key, permissions and remote login.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class AgentSettings {

    /**
     * @var AgentSettings|null Singleton instance
     */
    private static ?AgentSettings $instance = null;


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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_prxctrl_generate_api_key', [ $this, 'ajax_generate_api_key' ] );
    } // End __construct()


    /**
     * Enqueue assets.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! SettingsUi::is_tab( 'agent' ) ) {
            return;
        }

        SettingsUi::enqueue( 'agent', [
            'agent' => [
                'confirmRegenerate' => __( 'Generate a new API key? Any Control Center using the current key loses access until you paste the new key there.', 'pluginrx-control-center' ),
                'generated'         => __( 'New API key generated and saved.', 'pluginrx-control-center' ),
                'copied'            => __( 'Copied.', 'pluginrx-control-center' ),
                'show'              => __( 'Show', 'pluginrx-control-center' ),
                'hide'              => __( 'Hide', 'pluginrx-control-center' ),
            ],
        ] );
    } // End enqueue()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        $allowed = Agent::allowed();
        $key = (string) Options::get( 'agent_api_key' );
        ?>
        <div class="wrap prxctrl-wrap prxctrl-settings" id="prxctrl-settings-form">
            <div class="prxctrl-settings-grid">
                <?php
                SettingsUi::open_box( __( 'Remote Access', 'pluginrx-control-center' ) );
                SettingsUi::checkbox( 'agent_remote_access', __( 'Allow Remote Access', 'pluginrx-control-center' ), __( 'Lets the Control Center domains below connect to this site. Off by default.', 'pluginrx-control-center' ) );
                SettingsUi::text( 'agent_domains', __( 'Control Center Domains', 'pluginrx-control-center' ), __( 'The domain of each Control Center allowed to connect, separated by commas. Find it under Connection in that site\'s Host settings.', 'pluginrx-control-center' ), 'controlcenter.example.com' );
                SettingsUi::checkbox( 'agent_require_signed', __( 'Require Signed Requests', 'pluginrx-control-center' ), __( 'Only accept signed requests. Signed requests never send the API key over the network. Turn this on once every Control Center that manages this site runs version 2.0 or newer; older ones send the key in a header.', 'pluginrx-control-center' ) );
                SettingsUi::close_box();

                SettingsUi::open_box( __( 'API Key', 'pluginrx-control-center' ), 'prxctrl-api-key-box' );
                ?>
                <p class="prxctrl-section-desc"><?php esc_html_e( 'Paste this key into the Control Center when you add this site. Generating a new key immediately cuts off any Control Center using the old one.', 'pluginrx-control-center' ); ?></p>
                <div class="prxctrl-field">
                    <div class="prxctrl-input-group">
                        <input type="password" id="prxctrl-api-key" value="<?php echo esc_attr( $key ); ?>" readonly aria-label="<?php esc_attr_e( 'API key', 'pluginrx-control-center' ); ?>" placeholder="<?php esc_attr_e( 'No key yet', 'pluginrx-control-center' ); ?>">
                        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-show-api-key"<?php echo '' === $key ? ' disabled' : ''; ?>><?php esc_html_e( 'Show', 'pluginrx-control-center' ); ?></button>
                        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-copy-api-key"<?php echo '' === $key ? ' disabled' : ''; ?>><?php esc_html_e( 'Copy', 'pluginrx-control-center' ); ?></button>
                    </div>
                    <div><button type="button" class="prxctrl-button" id="prxctrl-generate-api-key"><?php echo '' === $key ? esc_html__( 'Generate Key', 'pluginrx-control-center' ) : esc_html__( 'Generate New Key', 'pluginrx-control-center' ); ?></button></div>
                </div>
                <?php
                SettingsUi::close_box();
                ?>

                <?php
                self::render_login_box( $allowed );

                $general = SettingsUi::has_general_panels( 'agent' );

                SettingsUi::open_box( __( 'Activity', 'pluginrx-control-center' ) );
                SettingsUi::checkbox( 'agent_log_requests', __( 'Log Data Requests', 'pluginrx-control-center' ), __( 'Actions, remote logins and failed connection attempts are always logged. Turn this on to also log every time a Control Center reads this site\'s data.', 'pluginrx-control-center' ) );

                if ( $general ) {
                    SettingsUi::render_log_fields();
                }

                SettingsUi::close_box();

                if ( $general ) {
                    SettingsUi::render_date_box();
                    SettingsUi::render_access_box();
                }

                self::render_permissions_box( $allowed );

                SettingsUi::render_advanced_box();
                ?>
            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * Render the Remote Login panel.
     *
     * @param array $allowed
     * @return void
     */
    private static function render_login_box( array $allowed ) : void {
        $user_id = (int) Options::get( 'agent_login_user' );
        $admins = get_users( [ 'role__in' => [ 'administrator' ], 'number' => 200, 'fields' => [ 'ID', 'user_login', 'display_name' ] ] );

        SettingsUi::open_box( __( 'Remote Login', 'pluginrx-control-center' ), 'prxctrl-login-box' );
        ?>
        <div class="prxctrl-field">
            <label class="prxctrl-inline-label">
                <input type="checkbox" name="agent_permissions[]" value="remote_login" <?php checked( in_array( 'remote_login', $allowed, true ) ); ?>>
                <?php esc_html_e( 'Allow Remote Login', 'pluginrx-control-center' ); ?>
                <?php Helpers::tooltip( __( 'Adds a Remote Login button for this site on the Control Center dashboard. Each click creates a link that works once and expires after 60 seconds. Every use is logged on both sites.', 'pluginrx-control-center' ) ); ?>
            </label>
        </div>
        <div class="prxctrl-field">
            <label for="prxctrl-field-agent_login_user"><?php esc_html_e( 'Log In As', 'pluginrx-control-center' ); ?></label>
            <select id="prxctrl-field-agent_login_user" name="agent_login_user">
                <option value="0"><?php esc_html_e( 'Choose an administrator...', 'pluginrx-control-center' ); ?></option>
                <?php foreach ( $admins as $admin ) : ?>
                    <option value="<?php echo esc_attr( (string) $admin->ID ); ?>" <?php selected( $user_id, (int) $admin->ID ); ?>><?php echo esc_html( $admin->display_name . ' (' . $admin->user_login . ')' ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="prxctrl-note prxctrl-note-warning">
            <span class="dashicons dashicons-shield" aria-hidden="true"></span>
            <p><?php esc_html_e( 'Remote login skips this site\'s login form, including any two-factor authentication. Anyone with access to your Control Center can use it. Only turn it on if you trust everyone who can open the Control Center dashboard.', 'pluginrx-control-center' ); ?></p>
        </div>
        <?php
        SettingsUi::close_box();
    } // End render_login_box()


    /**
     * Render the Permissions panel, split into Defaults and Integrations.
     *
     * @param array $allowed
     * @return void
     */
    private static function render_permissions_box( array $allowed ) : void {
        $groups = [
            'core'        => [ __( 'Defaults', 'pluginrx-control-center' ), __( 'Data from WordPress itself, and the maintenance actions a Control Center can run.', 'pluginrx-control-center' ) ],
            'integration' => [ __( 'Integrations', 'pluginrx-control-center' ), __( 'Data and actions from other plugins. Items for plugins that are not active on this site share "N/A".', 'pluginrx-control-center' ) ],
        ];
        $items = [];

        foreach ( Registry::permissions() as $permission_key => $permission ) {
            $items[ $permission[ 'group' ] ][ $permission_key ] = $permission;
        }
        ?>
        <section class="prxctrl-box prxctrl-box-wide prxctrl-permissions-box">
            <div class="prxctrl-box-header"><h2><?php esc_html_e( 'Permissions', 'pluginrx-control-center' ); ?></h2></div>
            <div class="prxctrl-box-body">
                <p class="prxctrl-section-desc"><?php esc_html_e( 'Choose what the Control Center can see and do. Nothing that is unchecked is ever shared or run. New installs start with version and update information only.', 'pluginrx-control-center' ); ?></p>
                <?php if ( is_multisite() && ! is_main_site() ) : ?>
                    <div class="prxctrl-note prxctrl-note-warning"><span class="dashicons dashicons-info" aria-hidden="true"></span><p><?php esc_html_e( 'This is a subsite. Plugins, themes and WordPress are shared by the whole network, so update actions only run on the main site.', 'pluginrx-control-center' ); ?></p></div>
                <?php endif; ?>
                <div class="prxctrl-permission-groups">
                <?php foreach ( $groups as $group => $info ) : ?>
                    <?php
                    if ( empty( $items[ $group ] ) ) {
                        continue;
                    }
                    ?>
                    <fieldset class="prxctrl-permission-group" data-group="<?php echo esc_attr( $group ); ?>">
                        <legend><?php echo esc_html( $info[ 0 ] ); ?></legend>
                        <p class="prxctrl-section-desc"><?php echo esc_html( $info[ 1 ] ); ?></p>
                        <label class="prxctrl-select-all"><input type="checkbox" class="prxctrl-select-all-toggle"> <?php esc_html_e( 'Select all', 'pluginrx-control-center' ); ?></label>
                        <div class="prxctrl-permission-list">
                            <?php foreach ( $items[ $group ] as $permission_key => $permission ) : ?>
                                <label class="prxctrl-permission">
                                    <input type="checkbox" name="agent_permissions[]" value="<?php echo esc_attr( $permission_key ); ?>" <?php checked( in_array( $permission_key, $allowed, true ) ); ?>>
                                    <span>
                                        <strong><?php echo esc_html( $permission[ 'label' ] ); ?></strong>
                                        <?php if ( 'action' === $permission[ 'kind' ] ) : ?>
                                            <span class="prxctrl-tag"><?php esc_html_e( 'Action', 'pluginrx-control-center' ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( '' !== $permission[ 'source' ] ) : ?>
                                            <span class="prxctrl-tag prxctrl-tag-muted"><?php echo esc_html( $permission[ 'source' ] ); ?></span>
                                        <?php endif; ?>
                                        <?php Helpers::tooltip( (string) $permission[ 'description' ] ); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    } // End render_permissions_box()


    /**
     * AJAX: generate and save a new API key.
     *
     * @return void
     */
    public function ajax_generate_api_key() : void {
        check_ajax_referer( SettingsUi::NONCE, 'nonce' );
        Access::require_ajax( true );

        if ( ! Mode::agent_here() ) {
            wp_send_json_error( [ 'message' => __( 'This site is not an agent.', 'pluginrx-control-center' ) ] );
        }

        try {
            $key = bin2hex( random_bytes( 32 ) );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => __( 'A secure key could not be generated on this server.', 'pluginrx-control-center' ) ] );
        }

        Options::update( 'agent_api_key', $key );
        Agent::log( 'api_key_generated', true, __( 'A new API key was generated.', 'pluginrx-control-center' ) );

        wp_send_json_success( [ 'key' => $key ] );
    } // End ajax_generate_api_key()

} // End class AgentSettings


AgentSettings::instance();
