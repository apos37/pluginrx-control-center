<?php
/**
 * SETTINGS UI
 *
 * The Settings page and its sub tabs (Host, Agent and Site Role), plus the shared pieces: panel and
 * field markup, the Save button with its unsaved-changes reminder, the AJAX save handler, and the
 * panels that apply to the whole plugin (site role, access, date and time, logs and advanced).
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class SettingsUi {

    /**
     * Nonce action shared by the settings pages.
     */
    public const NONCE = 'prxctrl_settings';


    /**
     * @var SettingsUi|null Singleton instance
     */
    private static ?SettingsUi $instance = null;


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
        add_action( 'wp_ajax_prxctrl_save_settings', [ $this, 'ajax_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_role_tab' ] );
        add_action( 'prxctrl_subheader_left', [ $this, 'render_subheader_left' ] );
        add_action( 'prxctrl_subheader_right', [ $this, 'render_subheader_right' ] );
    } // End __construct()


    /**
     * Get the Settings sub tabs available in the current context, in order.
     *
     * @return array slug => label
     */
    public static function tabs() : array {
        $tabs = [];

        if ( Mode::host_here() ) {
            $tabs[ 'host' ] = __( 'Host', 'pluginrx-control-center' );
        }

        if ( Mode::agent_here() ) {
            $tabs[ 'agent' ] = __( 'Agent', 'pluginrx-control-center' );
        }

        if ( self::can_change_mode() ) {
            $tabs[ 'role' ] = __( 'Site Role', 'pluginrx-control-center' );
        }

        return (array) apply_filters( 'prxctrl_settings_tabs', $tabs );
    } // End tabs()


    /**
     * Get the current Settings sub tab. Without one in the URL, it is Host on a Control Center and
     * Agent everywhere else.
     *
     * @return string
     */
    public static function current_tab() : string {
        $tabs = self::tabs();
        $tab = isset( $_GET[ 'tab' ] ) ? sanitize_key( wp_unslash( $_GET[ 'tab' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.

        if ( isset( $tabs[ $tab ] ) ) {
            return $tab;
        }

        return (string) array_key_first( $tabs );
    } // End current_tab()


    /**
     * Check if a Settings sub tab is showing.
     *
     * @param string $tab host|agent|role
     * @return bool
     */
    public static function is_tab( string $tab ) : bool {
        return Helpers::is_plugin_screen() && 'settings' === Menu::current_key() && $tab === self::current_tab();
    } // End is_tab()


    /**
     * Render the Settings page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'pluginrx-control-center' ) );
        }

        switch ( self::current_tab() ) {
            case 'host':
                HostSettings::render_page();
                break;
            case 'agent':
                AgentSettings::render_page();
                break;
            case 'role':
                self::render_role_page();
                break;
            default:
                do_action( 'prxctrl_settings_tab_' . self::current_tab() );
        }
    } // End render_page()


    /**
     * Render the Site Role tab.
     *
     * @return void
     */
    private static function render_role_page() : void {
        $network = Mode::is_network_active();
        ?>
        <div class="wrap prxctrl-wrap prxctrl-settings" id="prxctrl-settings-form">
            <div class="prxctrl-settings-grid">
                <?php
                self::open_box( __( 'Site Role', 'pluginrx-control-center' ), 'prxctrl-mode-box', 'prxctrl-box-wide' );
                ?>
                <p class="prxctrl-section-desc">
                    <?php
                    if ( $network ) {
                        esc_html_e( 'Choose what this network does. The role applies to every site on the network. Changing it shows or hides the Control Center and Agent pages after you save.', 'pluginrx-control-center' );
                    } else {
                        esc_html_e( 'Choose what this site does. Changing the role shows or hides the Control Center and Agent pages after you save.', 'pluginrx-control-center' );
                    }
                    ?>
                </p>
                <?php
                self::render_mode_options( Mode::get() );
                self::close_box();

                if ( $network ) {
                    self::open_box( __( 'Network', 'pluginrx-control-center' ), 'prxctrl-network-box', 'prxctrl-box-wide' );
                    Starter::render_network_steps( Mode::network() );
                    self::close_box();
                }
                ?>
            </div>
        </div>
        <?php
    } // End render_role_page()


    /**
     * Render the Host, Agent and Both cards.
     *
     * @param string $current
     * @return void
     */
    public static function render_mode_options( string $current ) : void {
        ?>
        <div class="prxctrl-mode-options prxctrl-mode-options-large" role="radiogroup" aria-label="<?php esc_attr_e( 'Site role', 'pluginrx-control-center' ); ?>">
            <?php foreach ( Mode::modes() as $mode => $info ) : ?>
                <label class="prxctrl-mode-option">
                    <input type="radio" name="mode" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $current, $mode ); ?>>
                    <span class="prxctrl-mode-card">
                        <span class="dashicons <?php echo esc_attr( $info[ 'icon' ] ); ?>" aria-hidden="true"></span>
                        <strong><?php echo esc_html( $info[ 'label' ] ); ?></strong>
                        <span class="prxctrl-mode-desc"><?php echo esc_html( $info[ 'description' ] ); ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    } // End render_mode_options()


    /**
     * Load the setup styles and script on the Site Role tab, which shares the role cards and the
     * network questions.
     *
     * @return void
     */
    public function enqueue_role_tab() : void {
        if ( ! self::is_tab( 'role' ) ) {
            return;
        }

        self::enqueue( 'role' );
        Starter::enqueue_script();
    } // End enqueue_role_tab()


    /**
     * Check if the shared panels (access, dates, logs, advanced) belong on a settings page.
     * They live on the Host tab when this context hosts, otherwise on the Agent tab.
     *
     * @param string $page host|agent
     * @return bool
     */
    public static function has_general_panels( string $page ) : bool {
        return 'host' === $page ? Mode::host_here() : ! Mode::host_here();
    } // End has_general_panels()


    /**
     * Render the sub tabs, Save button and unsaved-changes reminder.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_left( string $page ) : void {
        if ( 'settings' !== $page ) {
            return;
        }

        $current = self::current_tab();
        ?>
        <nav class="prxctrl-subtabs" aria-label="<?php esc_attr_e( 'Settings sections', 'pluginrx-control-center' ); ?>">
            <?php foreach ( self::tabs() as $tab => $label ) : ?>
                <a href="<?php echo esc_url( Helpers::page_url( 'settings', [ 'tab' => $tab ] ) ); ?>" class="prxctrl-button<?php echo $current === $tab ? '' : ' prxctrl-button-secondary'; ?>"<?php echo $current === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </nav>
        <button type="button" class="prxctrl-button" id="prxctrl-save-settings"><?php esc_html_e( 'Save', 'pluginrx-control-center' ); ?></button>
        <span id="prxctrl-save-reminder" class="prxctrl-save-reminder"><?php esc_html_e( 'Remember to click "Save" after making changes.', 'pluginrx-control-center' ); ?></span>
        <?php
    } // End render_subheader_left()


    /**
     * Render the version and help links.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_right( string $page ) : void {
        if ( 'settings' !== $page ) {
            return;
        }

        $base = Bootstrap::author_uri();
        $slug = Bootstrap::textdomain();
        $links = [
            [ __( 'How-To Guide', 'pluginrx-control-center' ), $base . 'guide/plugin/' . $slug . '/' ],
            [ __( 'Developer Docs', 'pluginrx-control-center' ), $base . 'docs/plugin/' . $slug . '/' ],
            [ __( 'Support Forum', 'pluginrx-control-center' ), $base . 'support/plugin/' . $slug . '/' ],
        ];
        ?>
        <span class="prxctrl-version"><?php esc_html_e( 'Version', 'pluginrx-control-center' ); ?> <?php echo esc_html( Bootstrap::version() ); ?></span>
        <?php foreach ( $links as $link ) : ?>
            <a href="<?php echo esc_url( $link[ 1 ] ); ?>" target="_blank" rel="noopener noreferrer" class="prxctrl-button prxctrl-external-link"><?php echo esc_html( $link[ 0 ] ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
        <?php endforeach; ?>
        <?php
    } // End render_subheader_right()


    /**
     * Open a settings panel.
     *
     * @param string $title
     * @param string $id
     * @param string $class
     * @return void
     */
    public static function open_box( string $title, string $id = '', string $class = '' ) : void {
        ?>
        <section class="prxctrl-box <?php echo esc_attr( $class ); ?>"<?php echo '' !== $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>>
            <div class="prxctrl-box-header"><h2><?php echo esc_html( $title ); ?></h2></div>
            <div class="prxctrl-box-body">
        <?php
    } // End open_box()


    /**
     * Close a settings panel.
     *
     * @return void
     */
    public static function close_box() : void {
        ?>
            </div>
        </section>
        <?php
    } // End close_box()


    /**
     * Render a checkbox field bound to a setting.
     *
     * @param string $key
     * @param string $label
     * @param string $tip
     * @return void
     */
    public static function checkbox( string $key, string $label, string $tip = '' ) : void {
        ?>
        <div class="prxctrl-field">
            <label class="prxctrl-inline-label">
                <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( (bool) Options::get( $key ) ); ?>>
                <?php echo esc_html( $label ); ?>
                <?php Helpers::tooltip( $tip ); ?>
            </label>
        </div>
        <?php
    } // End checkbox()


    /**
     * Render a select field bound to a setting.
     *
     * @param string $key
     * @param string $label
     * @param array  $choices value => label
     * @param string $tip
     * @return void
     */
    public static function select( string $key, string $label, array $choices, string $tip = '' ) : void {
        $value = (string) Options::get( $key );
        ?>
        <div class="prxctrl-field">
            <label for="prxctrl-field-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?> <?php Helpers::tooltip( $tip ); ?></label>
            <select id="prxctrl-field-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>">
                <?php foreach ( $choices as $choice => $choice_label ) : ?>
                    <option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, (string) $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    } // End select()


    /**
     * Render a number field bound to a setting.
     *
     * @param string $key
     * @param string $label
     * @param string $tip
     * @return void
     */
    public static function number( string $key, string $label, string $tip = '' ) : void {
        $definition = Options::definitions()[ $key ];
        ?>
        <div class="prxctrl-field">
            <label for="prxctrl-field-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?> <?php Helpers::tooltip( $tip ); ?></label>
            <input type="number" id="prxctrl-field-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) Options::get( $key ) ); ?>" min="<?php echo esc_attr( (string) $definition[ 'min' ] ); ?>" max="<?php echo esc_attr( (string) $definition[ 'max' ] ); ?>">
        </div>
        <?php
    } // End number()


    /**
     * Render a text field bound to a setting.
     *
     * @param string $key
     * @param string $label
     * @param string $tip
     * @param string $placeholder
     * @return void
     */
    public static function text( string $key, string $label, string $tip = '', string $placeholder = '' ) : void {
        $value = Options::get( $key );
        $value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
        ?>
        <div class="prxctrl-field">
            <label for="prxctrl-field-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?> <?php Helpers::tooltip( $tip ); ?></label>
            <input type="text" id="prxctrl-field-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
        </div>
        <?php
    } // End text()


    /**
     * Check if the current user can change the site role. On a network, the role applies to every
     * site, so only super admins can change it.
     *
     * @return bool
     */
    public static function can_change_mode() : bool {
        return ! Mode::is_network_active() || current_user_can( 'manage_network_options' );
    } // End can_change_mode()


    /**
     * Render the Access panel. Not shown in the network admin, where access is for super admins.
     *
     * @return void
     */
    public static function render_access_box() : void {
        if ( Mode::is_network_context() ) {
            return;
        }

        self::open_box( __( 'Access', 'pluginrx-control-center' ) );
        ?>
        <div class="prxctrl-field">
            <label>
                <?php esc_html_e( 'Additional Roles With Access', 'pluginrx-control-center' ); ?>
                <?php Helpers::tooltip( __( 'Administrators always have access. Checked roles can use the Dashboard, Sites and Logs pages. Settings are limited to administrators.', 'pluginrx-control-center' ) ); ?>
            </label>
            <div class="prxctrl-checkboxes">
                <?php foreach ( Access::assignable_roles() as $slug => $label ) : ?>
                    <label>
                        <input type="checkbox" name="access_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) Options::get( 'access_roles' ), true ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        self::close_box();
    } // End render_access_box()


    /**
     * Render the Date and Time panel.
     *
     * @return void
     */
    public static function render_date_box() : void {
        $timezones = [ '' => __( 'Site default', 'pluginrx-control-center' ) . ' (' . wp_timezone_string() . ')' ];

        foreach ( timezone_identifiers_list() as $zone ) {
            $timezones[ $zone ] = str_replace( '_', ' ', $zone );
        }

        $now = time();
        $formats = array_unique( [
            (string) Options::get( 'date_format' ),
            Options::definitions()[ 'date_format' ][ 'default' ],
            'n/j/Y g:i a T',
            'F j, Y g:i a',
            'Y-m-d H:i:s',
            'M j, Y @ g:i A',
            'D, M j, Y g:i a',
        ] );
        $format_choices = [];

        foreach ( $formats as $format ) {
            $format_choices[ $format ] = wp_date( $format, $now ) . '  —  ' . $format;
        }

        self::open_box( __( 'Date and Time', 'pluginrx-control-center' ) );
        self::select( 'date_format', __( 'Date Format', 'pluginrx-control-center' ), $format_choices, __( 'Used for "last checked" times, logs and other dates in the plugin. The first option is your site\'s format.', 'pluginrx-control-center' ) );
        self::select( 'timezone', __( 'Timezone', 'pluginrx-control-center' ), $timezones, __( 'Dates in the plugin are shown in this timezone.', 'pluginrx-control-center' ) );
        self::close_box();
    } // End render_date_box()


    /**
     * Render the log fields shared by the Host Logs panel and the Agent Activity panel.
     *
     * @return void
     */
    public static function render_log_fields() : void {
        self::number( 'log_retention', __( 'Clear Logs After (Days)', 'pluginrx-control-center' ), __( 'Log entries older than this are deleted once a day. Enter 0 to keep logs forever.', 'pluginrx-control-center' ) );

        if ( Mode::host_here() ) {
            self::checkbox( 'log_checks', __( 'Log Successful Site Checks', 'pluginrx-control-center' ), __( 'Failed checks and every action are always logged. Turn this off to keep the logs shorter.', 'pluginrx-control-center' ) );
        }

        self::checkbox( 'console_log', __( 'Log Responses to the Browser Console', 'pluginrx-control-center' ), __( 'For troubleshooting. Prints the data returned by AJAX requests to the browser console.', 'pluginrx-control-center' ) );
    } // End render_log_fields()


    /**
     * Render the Advanced panel.
     *
     * @return void
     */
    public static function render_advanced_box() : void {
        self::open_box( __( 'Advanced', 'pluginrx-control-center' ), 'prxctrl-advanced-box' );
        self::checkbox( 'delete_on_uninstall', __( 'Delete All Data on Uninstall', 'pluginrx-control-center' ), __( 'When the plugin is deleted, remove its settings, managed sites, groups, cached site data and logs.', 'pluginrx-control-center' ) );
        ?>
        <div class="prxctrl-field">
            <label>
                <?php esc_html_e( 'Download/Upload Settings', 'pluginrx-control-center' ); ?>
                <?php Helpers::tooltip( __( 'Export these settings as a JSON file for backup, or to copy them to another site. API keys, managed sites and the remote login account are never included. Uploading overwrites your current settings.', 'pluginrx-control-center' ) ); ?>
            </label>
            <div class="prxctrl-inline-actions">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( Advanced::NONCE ); ?>
                    <input type="hidden" name="action" value="prxctrl_export">
                    <?php if ( Mode::is_network_context() ) : ?>
                        <input type="hidden" name="prxctrl_network" value="1">
                    <?php endif; ?>
                    <button type="submit" class="prxctrl-button"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'Download Settings', 'pluginrx-control-center' ); ?></button>
                </form>
                <label for="prxctrl-upload-settings" class="prxctrl-button" tabindex="0"><span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e( 'Upload Settings', 'pluginrx-control-center' ); ?></label>
                <input type="file" id="prxctrl-upload-settings" accept="application/json,.json" hidden>
                <span id="prxctrl-upload-filename"></span>
            </div>
        </div>
        <div class="prxctrl-field">
            <label>
                <?php esc_html_e( 'Reset Settings', 'pluginrx-control-center' ); ?>
                <?php Helpers::tooltip( __( 'Resets every setting on this page to its default. Managed sites, groups, logs, the API key and the site role are not changed.', 'pluginrx-control-center' ) ); ?>
            </label>
            <div><button type="button" class="prxctrl-button prxctrl-button-danger" id="prxctrl-reset-settings"><?php esc_html_e( 'Reset Settings', 'pluginrx-control-center' ); ?></button></div>
        </div>
        <?php
        self::close_box();
    } // End render_advanced_box()


    /**
     * Get the setting keys a settings page may save.
     *
     * @param string $page host|agent|role
     * @return array
     */
    public static function page_keys( string $page ) : array {
        if ( 'role' === $page ) {
            return [];
        }

        $scopes = 'host' === $page ? [ 'host' ] : [ 'agent' ];

        if ( self::has_general_panels( $page ) ) {
            $scopes[] = 'global';
        }

        $keys = Options::keys( $scopes );

        return array_values( array_diff( $keys, [ 'agent_api_key' ] ) );
    } // End page_keys()


    /**
     * Check if a settings page is available in the current context.
     *
     * @param string $page host|agent|role
     * @return bool
     */
    public static function page_available( string $page ) : bool {
        return isset( self::tabs()[ $page ] );
    } // End page_available()


    /**
     * Enqueue the settings assets.
     *
     * @param string $page host|agent|role
     * @param array  $extra Extra localized data.
     * @param array  $deps Extra script dependencies.
     * @return void
     */
    public static function enqueue( string $page, array $extra = [], array $deps = [] ) : void {
        $version = Bootstrap::script_version();

        wp_enqueue_style( 'prxctrl-settings', Bootstrap::url( 'inc/css/settings.css' ), [ 'prxctrl-theme' ], $version );
        wp_enqueue_script( 'prxctrl-settings', Bootstrap::url( 'inc/js/settings.js' ), array_merge( [ 'jquery', 'prxctrl-common' ], $deps ), $version, true );

        wp_localize_script( 'prxctrl-settings', 'prxctrlSettings', array_merge( [
            'nonce' => wp_create_nonce( self::NONCE ),
            'page'  => $page,
            'i18n'  => [
                'saved'         => __( 'Settings saved.', 'pluginrx-control-center' ),
                'savedReload'   => __( 'Settings saved. Reloading...', 'pluginrx-control-center' ),
                'importOk'      => __( 'Settings uploaded. Reloading...', 'pluginrx-control-center' ),
                'importError'   => __( 'That file is not a valid settings export.', 'pluginrx-control-center' ),
                'confirmImport' => __( 'Uploading will overwrite your current settings. Continue?', 'pluginrx-control-center' ),
                'confirmReset'  => __( 'Reset every setting on this page to its default?', 'pluginrx-control-center' ),
                'resetOk'       => __( 'Settings reset. Reloading...', 'pluginrx-control-center' ),
            ],
        ], $extra ) );
    } // End enqueue()


    /**
     * AJAX: save a settings page.
     *
     * @return void
     */
    public function ajax_save() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax( true );

        $page = isset( $_POST[ 'page' ] ) ? sanitize_key( wp_unslash( $_POST[ 'page' ] ) ) : '';

        if ( ! in_array( $page, [ 'host', 'agent', 'role' ], true ) || ! self::page_available( $page ) ) {
            wp_send_json_error( [ 'message' => __( 'This settings page is not available.', 'pluginrx-control-center' ) ] );
        }

        $settings = isset( $_POST[ 'settings' ] ) ? json_decode( wp_unslash( $_POST[ 'settings' ] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded here and every value is sanitized by Options::update() or save_role().

        if ( ! is_array( $settings ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'pluginrx-control-center' ) ] );
        }

        $redirect = '';

        if ( 'role' === $page ) {
            $redirect = self::save_role( $settings );

            if ( is_wp_error( $redirect ) ) {
                wp_send_json_error( [ 'message' => $redirect->get_error_message() ] );
            }
        }

        foreach ( self::page_keys( $page ) as $key ) {
            if ( array_key_exists( $key, $settings ) ) {
                Options::update( $key, $settings[ $key ] );
            }
        }

        Registry::flush();

        do_action( 'prxctrl_settings_saved', $page, $settings );

        wp_send_json_success( [ 'redirect' => $redirect ] );
    } // End ajax_save()


    /**
     * Save the Site Role tab.
     *
     * @param array $settings
     * @return string|\WP_Error The page to reload, or an empty string when nothing changed.
     */
    private static function save_role( array $settings ) {
        $mode = Mode::sanitize( (string) ( $settings[ 'mode' ] ?? '' ) );

        if ( '' === $mode ) {
            return new \WP_Error( 'missing_mode', __( 'Choose Host, Agent or Both.', 'pluginrx-control-center' ) );
        }

        if ( Mode::is_network_active() ) {
            return Starter::save_network( $mode, (string) ( $settings[ 'location' ] ?? 'network' ), absint( $settings[ 'location_site' ] ?? 0 ), array_map( 'absint', (array) ( $settings[ 'subsites' ] ?? [] ) ), ! empty( $settings[ 'auto_include' ] ), 'settings' );
        }

        if ( $mode === Mode::get() ) {
            return '';
        }

        Mode::set( $mode );
        Database::maybe_install();

        if ( Mode::has_host() && Mode::has_agent() ) {
            Sites::add_this_site();
        } elseif ( Mode::has_host() ) {
            Sites::remove_this_site();
        }

        return Helpers::page_url( 'settings', [ 'tab' => 'role' ] );
    } // End save_role()

} // End class SettingsUi


SettingsUi::instance();
