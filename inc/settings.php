<?php 
/**
 * Plugin settings
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Settings {

    /**
     * @var string Text domain
     */
    private string $text_domain;


    /**
     * @var string Nonce
     */
    private $nonce = 'prxctrl_settings_nonce';
    private $nonce_action = 'prxctrl_save_settings';


    /**
     * @var Settings|null Singleton instance
     */
    private static ?Settings $instance = null;


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
    public function __construct() {

        // Set text domain
        $this->text_domain = Bootstrap::textdomain();

        // Save settings
        add_action( 'admin_init', [ $this, 'save' ] );

        // AJAX save site order
        add_action( 'wp_ajax_prxctrl_save_site_order', [ $this, 'save_site_order' ] );

		// Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End __construct()

    
    /**
     * The page
     *
     * @return void
     */
    public function render_page() {
        // Sites
        $sites = Database::get_sites_for_settings();

        // Other options
        $timeout_option = absint( get_option( 'prxctrl_timeout', 15 ) );
        $console_log_option = sanitize_key( get_option( 'prxctrl_console_log', 'no' ) );
        $delete_db_option = sanitize_key( get_option( 'prxctrl_delete_db', 'no' ) );
        $dashboard_sorting_option = sanitize_key( get_option( 'prxctrl_dashboard_sorting', 'same_as_settings' ) );
        $dashboard_site_path_option = sanitize_key( get_option( 'prxctrl_dashboard_site_path', 'admin' ) );
        $dashboard_warn_admin_email_option = sanitize_key( get_option( 'prxctrl_dashboard_warn_admin_email', 'yes' ) );
        $dashboard_allow_prev_wp_outdated_option = sanitize_key( get_option( 'prxctrl_dashboard_allow_prev_wp_outdated', 'no' ) );
        $dashboard_allow_prev_php_outdated_option = sanitize_key( get_option( 'prxctrl_dashboard_allow_prev_php_outdated', 'no' ) );
        $dashboard_toc_option = sanitize_key( get_option( 'prxctrl_dashboard_toc', 'no' ) );
        ?>
		<h1><?php echo esc_attr( get_admin_page_title() ) ?></h1>
        <form method="post">
            <?php wp_nonce_field( $this->nonce_action, $this->nonce ); ?>
            
            <h2><?php esc_html_e( 'Sites', 'pluginrx-control-center' ); ?></h2>
            <table class="form-table" role="presentation" id="prxctrl-sites-table">
                <tbody>
                    <tr class="prxctrl-sites">
                        <th scope="row"><?php echo esc_html__( 'Sites to Manage', 'pluginrx-control-center' ); ?></th>
                        <td>
                            <table class="widefat fixed striped" role="grid">
                                <thead>
                                    <tr>
                                        <th class="prxctrl-sort-col"></th>
                                        <th><?php esc_html_e( 'Site Name', 'pluginrx-control-center' ); ?></th>
                                        <th><?php esc_html_e( 'Site URL', 'pluginrx-control-center' ); ?></th>
                                        <th><?php esc_html_e( 'API Key', 'pluginrx-control-center' ); ?></th>
                                        <th class="prxctrl-status-col"><?php esc_html_e( 'Status', 'pluginrx-control-center' ); ?></th>
                                        <th class="prxctrl-remove-col"><?php esc_html_e( 'Remove', 'pluginrx-control-center' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="prxctrl-sites-body">
                                    <?php if ( ! empty( $sites ) ) : ?>
                                        <?php foreach ( $sites as $site ) : ?>
                                            <tr data-site-id="<?php echo (int) $site->id; ?>">
                                                <td class="prxctrl-sort-handle">
                                                    <span class="dashicons dashicons-menu"></span>
                                                </td>
                                                <td>
                                                    <input type="text" class="prxctrl-site-name" name="prxctrl_site_name[]" value="<?php echo esc_attr( $site->site_name ); ?>">
                                                </td>
                                                <td>
                                                    <input type="url" class="prxctrl-site-url" name="prxctrl_site_url[]" value="<?php echo esc_url( $site->site_url ); ?>">
                                                </td>
                                                <td>
                                                    <input type="text" class="prxctrl-site-api-key" name="prxctrl_site_api_key[]" value="<?php echo esc_attr( $site->api_key ); ?>">
                                                </td>
                                                <td class="prxctrl-status-col">
                                                    <?php
                                                    if ( $site->last_checked ) {
                                                        echo esc_html( $site->last_checked );
                                                    } elseif ( $site->last_error ) {
                                                        echo esc_html( $site->last_error );
                                                    } else {
                                                        esc_html_e( 'Never connected', 'pluginrx-control-center' );
                                                    }
                                                    ?>
                                                </td>
                                                <td class="prxctrl-remove-col">
                                                    <button class="button prxctrl-remove-site"><?php esc_html_e( 'Remove', 'pluginrx-control-center' ); ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <tr class="prxctrl-site-template">
                                        <td class="prxctrl-sort-handle"><span class="dashicons dashicons-menu"></span></td>
                                        <td><input type="text" class="prxctrl-site-name" name="prxctrl_site_name[]" value=""></td>
                                        <td><input type="url" class="prxctrl-site-url" name="prxctrl_site_url[]" value=""></td>
                                        <td><input type="text" class="prxctrl-site-api-key" name="prxctrl_site_api_key[]" value=""></td>
                                        <td class="prxctrl-status-col"><?php esc_html_e( 'Not saved', 'pluginrx-control-center' ); ?></td>
                                        <td class="prxctrl-remove-col"><button class="button prxctrl-remove-site"><?php esc_html_e( 'Remove', 'pluginrx-control-center' ); ?></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button class="button button-primary" id="prxctrl-add-site">
                <?php esc_html_e( 'Add Site', 'pluginrx-control-center' ); ?>
            </button>

            <h2><?php esc_html_e( 'Dashboard Options', 'pluginrx-control-center' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr class="prxctrl-dashboard-sorting">
                        <th scope="row"><?php esc_html_e( 'Sorting', 'pluginrx-control-center' ); ?></th>
                        <td>
                            <select name="prxctrl_dashboard_sorting">
                                <option value="same_as_settings" <?php selected( $dashboard_sorting_option, 'same_as_settings' ); ?>>
                                    <?php esc_html_e( 'Same as Above', 'pluginrx-control-center' ); ?>
                                </option>
                                <option value="alphabetical" <?php selected( $dashboard_sorting_option, 'alphabetical' ); ?>>
                                    <?php esc_html_e( 'Alphabetically', 'pluginrx-control-center' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr class="prxctrl-dashboard-site-path">
                        <th scope="row"><?php esc_html_e( 'Site URL Path', 'pluginrx-control-center' ); ?></th>
                        <td>
                            <select name="prxctrl_dashboard_site_path">
                                <option value="admin" <?php selected( $dashboard_site_path_option, 'admin' ); ?>>
                                    <?php esc_html_e( 'Admin Area', 'pluginrx-control-center' ); ?>
                                </option>
                                <option value="home" <?php selected( $dashboard_site_path_option, 'home' ); ?>>
                                    <?php esc_html_e( 'Home Page', 'pluginrx-control-center' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr class="prxctrl-dashboard-toc">
                        <th scope="row"><?php esc_html_e( 'Include Table of Contents', 'pluginrx-control-center' ); ?></th>
                        <td>
                            <input type="checkbox" name="prxctrl_dashboard_toc" value="yes" <?php checked( $dashboard_toc_option, 'yes' ); ?>>
                        </td>
                    </tr>

                    <tr class="prxctrl-dashboard-warn-admin-email">
                        <th scope="row"><?php esc_html_e( 'Mismatching Admin Email', 'pluginrx-control-center' ); ?></th>
                        <td>
                            <input type="checkbox" name="prxctrl_dashboard_warn_admin_email" value="yes" <?php checked( $dashboard_warn_admin_email_option, 'yes' ); ?>>
                            <p class="description">
                                <?php esc_html_e( 'Warn if the admin email domain does not match the site domain.', 'pluginrx-control-center' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="prxctrl-dashboard-allow-prev-wp-outdated">
                        <th scope="row"><?php esc_html_e( 'Allow Previous WordPress Version', 'pluginrx-control-center' ); ?></th>
                        <td>
                            <input type="checkbox" name="prxctrl_dashboard_allow_prev_wp_outdated" value="yes" <?php checked( $dashboard_allow_prev_wp_outdated_option, 'yes' ); ?>>
                            <p class="description">
                                <?php esc_html_e( 'When enabled, sites running the most recent previous major WordPress version will not be flagged as outdated.', 'pluginrx-control-center' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="prxctrl-dashboard-allow-prev-php-outdated">
                        <th scope="row"><?php esc_html_e( 'Allow Previous PHP Version', 'pluginrx-control-center' ); ?></th>
                        <td>
                            <input type="checkbox" name="prxctrl_dashboard_allow_prev_php_outdated" value="yes" <?php checked( $dashboard_allow_prev_php_outdated_option, 'yes' ); ?>>
                            <p class="description">
                                <?php esc_html_e( 'When enabled, sites running the most recent previous major PHP version will not be flagged as outdated.', 'pluginrx-control-center' ); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Access Control', 'pluginrx-control-center' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr class="prxctrl-access-info">
                        <th scope="row"><?php echo esc_html__( 'Who Can Use the Control Center?', 'pluginrx-control-center' ); ?></th>
                        <td><p class="description"><?php
                        /* translators: 1: Role label, 2: Role slug */
                        printf(
                            esc_html__( 'Give the "%1$s" (%2$s) role to users who should have access to the Control Center. If you are seeing this page, then you have the role.', 'pluginrx-control-center' ),
                            esc_html( __( 'Control Center Manager', 'pluginrx-control-center' ) ),
                            esc_html( 'prxctrl' )
                        );
                        ?></p></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Developers', 'pluginrx-control-center' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr class="prxctrl-timeout">
                        <th scope="row"><?php echo esc_html__( 'Timeout (in seconds)', 'pluginrx-control-center' ); ?></th>
                        <td><input type="number" id="prxctrl-timeout" name="prxctrl_timeout" value="<?php echo esc_attr( $timeout_option ); ?>" min="1" max="180"> <p class="description"><?php esc_html_e( 'Set the maximum time the agent will wait for a response. You may need to update your PHP max_execution_time setting for this to work properly.', 'pluginrx-control-center' ); ?></p></td>
                    </tr>
                    <tr class="prxctrl-console-log">
                        <th scope="row"><?php echo esc_html__( 'Console Log Remote Response', 'pluginrx-control-center' ); ?></th>
                        <td><input type="checkbox" id="prxctrl-console-log" name="prxctrl_console_log" value="yes" <?php checked( $console_log_option, 'yes' ); ?>></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Data', 'pluginrx-control-center' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr class="prxctrl-delete-db">
                        <th scope="row"><?php echo esc_html__( 'Delete All Site Data on Uninstall', 'pluginrx-control-center' ); ?></th>
                        <td><input type="checkbox" id="prxctrl-delete-db" name="prxctrl_delete_db" value="yes" <?php checked( $delete_db_option, 'yes' ); ?>> <p class="description"><?php echo wp_kses_post( sprintf( __( 'This option will delete our custom tables when you uninstall the plugin: %s', 'pluginrx-control-center' ),
                    '<code>' . implode( ', ', array_keys( Database::tables() ) ) . '</code>'
                ) ); ?></p></td>
                    </tr>
                </tbody>
            </table>

            <button class="button button-primary" id="prxctrl-save-settings-btn" type="submit">
                <?php esc_html_e( 'Save Settings', 'pluginrx-control-center' ); ?>
            </button>
        </form>
        <?php
    } // End render_page()


    /**
     * Save settings
     *
     * @return void
     */
    public function save() {
        // Verify nonce
        if ( ! isset( $_POST[ $this->nonce ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->nonce ] ) ), $this->nonce_action ) ) {
            return;
        }

        // Timeout
        $timeout = isset( $_POST[ 'prxctrl_timeout' ] ) ? absint( wp_unslash( $_POST[ 'prxctrl_timeout' ] ) ) : 15;
        if ( $timeout < 1 ) {
            $timeout = 1;
        } elseif ( $timeout > 180 ) {
            $timeout = 180;
        }
        update_option( 'prxctrl_timeout', $timeout, false );

        // Console log
        $console_log = ( isset( $_POST[ 'prxctrl_console_log' ] ) && sanitize_key( wp_unslash( $_POST[ 'prxctrl_console_log' ] ) ) === 'yes' ) ? 'yes' : 'no';
        update_option( 'prxctrl_console_log', $console_log, false );

        // Delete DB
        $delete_db = ( isset( $_POST[ 'prxctrl_delete_db' ] ) && sanitize_key( wp_unslash( $_POST[ 'prxctrl_delete_db' ] ) ) === 'yes' ) ? 'yes' : 'no';
        update_option( 'prxctrl_delete_db', $delete_db, false );

        // Dashboard options
        $dashboard_sorting = isset( $_POST[ 'prxctrl_dashboard_sorting' ] )
            ? sanitize_key( wp_unslash( $_POST[ 'prxctrl_dashboard_sorting' ] ) )
            : 'same_as_settings';
        update_option( 'prxctrl_dashboard_sorting', $dashboard_sorting, false );

        $dashboard_site_path = isset( $_POST[ 'prxctrl_dashboard_site_path' ] )
            ? sanitize_key( wp_unslash( $_POST[ 'prxctrl_dashboard_site_path' ] ) )
            : 'admin';
        update_option( 'prxctrl_dashboard_site_path', $dashboard_site_path, false );

        $dashboard_toc = ( isset( $_POST[ 'prxctrl_dashboard_toc' ] )
            && sanitize_key( wp_unslash( $_POST[ 'prxctrl_dashboard_toc' ] ) ) === 'yes' )
            ? 'yes'
            : 'no';
        update_option( 'prxctrl_dashboard_toc', $dashboard_toc, false );

        $dashboard_warn_admin_email = ( isset( $_POST[ 'prxctrl_dashboard_warn_admin_email' ] )
            && sanitize_key( wp_unslash( $_POST[ 'prxctrl_dashboard_warn_admin_email' ] ) ) === 'yes' )
            ? 'yes'
            : 'no';
        update_option( 'prxctrl_dashboard_warn_admin_email', $dashboard_warn_admin_email, false );

        $prxctrl_dashboard_allow_prev_wp_outdated = ( isset( $_POST[ 'prxctrl_dashboard_allow_prev_wp_outdated' ] )
            && sanitize_key( wp_unslash( $_POST[ 'prxctrl_dashboard_allow_prev_wp_outdated' ] ) ) === 'yes' )
            ? 'yes'
            : 'no';
        update_option( 'prxctrl_dashboard_allow_prev_wp_outdated', $prxctrl_dashboard_allow_prev_wp_outdated, false );

        $prxctrl_dashboard_allow_prev_php_outdated = ( isset( $_POST[ 'prxctrl_dashboard_allow_prev_php_outdated' ] )
            && sanitize_key( wp_unslash( $_POST[ 'prxctrl_dashboard_allow_prev_php_outdated' ] ) ) === 'yes' )
            ? 'yes'
            : 'no';
        update_option( 'prxctrl_dashboard_allow_prev_php_outdated', $prxctrl_dashboard_allow_prev_php_outdated, false );

        // Sites
        $site_names = isset( $_POST[ 'prxctrl_site_name' ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'prxctrl_site_name' ] ) ) : [ ];
        $site_urls  = isset( $_POST[ 'prxctrl_site_url' ] ) ? array_map( 'esc_url_raw', wp_unslash( $_POST[ 'prxctrl_site_url' ] ) ) : [ ];
        $site_keys  = isset( $_POST[ 'prxctrl_site_api_key' ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'prxctrl_site_api_key' ] ) ) : [ ];

        // Keep track of submitted site URLs
        $submitted_urls = [ ];

        foreach ( $site_urls as $index => $site_url ) {
            if ( empty( $site_url ) ) {
                continue;
            }

            $site_name = isset( $site_names[ $index ] ) ? $site_names[ $index ] : null;
            $api_key   = isset( $site_keys[ $index ] ) ? $site_keys[ $index ] : null;

            $existing_site = Database::get_site_by_url( $site_url );

            if ( $existing_site ) {
                Database::update_site( $existing_site->id, [
                    'site_name' => $site_name,
                    'api_key'   => $api_key,
                ] );
            } else {
                Database::add_site( $site_url, $site_name, $api_key );
            }

            $submitted_urls[] = $site_url;
        }

        // Delete any sites not included in submitted URLs
        $all_sites = Database::get_sites_for_settings();
        foreach ( $all_sites as $site ) {
            if ( ! in_array( $site->site_url, $submitted_urls, true ) ) {
                Database::delete_site( $site->id );
            }
        }

        // Redirect back with updated notice
        wp_safe_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
        exit;
    } // End save()


    /**
     * Save site order via AJAX
     *
     * @return void
     */
    public function save_site_order() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        if ( empty( $_POST[ 'site_order' ] ) || ! is_array( $_POST[ 'site_order' ] ) ) {
            wp_send_json_error();
        }

        $site_order = array_map( 'absint', $_POST[ 'site_order' ] );
        update_option( 'prxctrl_settings_sorting', $site_order, false );

        wp_send_json_success();
    } // End save_site_order()


	/**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        $slug = 'prxctrl-settings';
        if ( empty( $_GET[ 'page' ] ) || sanitize_text_field( wp_unslash( $_GET[ 'page' ] ) ) !== $slug ) {
            return;
        }

		// Register and enqueue your CSS
        $css_path = Bootstrap::url( 'inc/css/' );
        $js_path  = Bootstrap::url( 'inc/js/' );
        $script_version = Bootstrap::script_version();

        // CSS
        wp_enqueue_style( $this->text_domain . '-shared-styles', $css_path . 'shared.css', [], $script_version );
        wp_enqueue_style( $this->text_domain . '-settings', $css_path . 'settings.css', [], $script_version );

        // JS
        wp_enqueue_script(
            $this->text_domain . '-settings',
            $js_path . 'settings.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            $script_version,
            true
        );

        // Localize script
        wp_localize_script(
            $this->text_domain . '-settings',
            'prxctrl_settings',
            [
                'nonce' => wp_create_nonce( $this->nonce_action ),
            ]
        );
    } // End enqueue_scripts()

}


Settings::instance();