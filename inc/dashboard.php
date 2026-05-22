<?php
/**
 * Dashboard
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Dashboard {

    /**
     * @var Dashboard|null Singleton instance
     */
    private static ?Dashboard $instance = null;


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

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    
    } // End __construct()


    /**
     * Gets a cached screenshot URL or generates a new one.
     * 
     * @param string $site_url The URL of the site to screenshot.
     * @param int $site_id The ID of the site (used for caching).
     * @param bool $force_refresh Whether to force refresh the screenshot (default: false).
     * @return string The URL of the screenshot.
     */
    public static function get_site_screenshot( $site_url, $site_id, $force_refresh = false ) {
        $transient_key = 'prxctrl_ss_' . $site_id;

        if ( $force_refresh ) {
            delete_transient( $transient_key );
        }

        $screenshot_url = get_transient( $transient_key );

        if ( false === $screenshot_url ) {
            // Construct mshots URL - w=400 is width, h=300 is height
            $screenshot_url = 'https://s0.wordpress.com/mshots/v1/' . urlencode( $site_url ) . '?w=400&h=300';
            
            // Cache for 24 hours
            set_transient( $transient_key, $screenshot_url, DAY_IN_SECONDS );
        }

        return $screenshot_url;
    } // End get_site_screenshot()


    /**
     * Render the dashboard page
     */
	public function render_page() {
        if ( LicenseManager::instance()->has_been_validated() ) {
            $sites = Database::get_sites_for_dashboard();
        } else {
            $sites = [];
        }

        $is_dev_debug_tools_active = is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' );

        $dashboard_toc = sanitize_text_field( get_option( 'prxctrl_dashboard_toc', 'no' ) );

        $latest_versions = Helpers::get_latest_versions();

        $allow_prev_wp = sanitize_text_field( get_option( 'prxctrl_dashboard_allow_prev_wp_outdated', '' ) ) === 'yes';
        $allow_prev_php = sanitize_text_field( get_option( 'prxctrl_dashboard_allow_prev_php_outdated', '' ) ) === 'yes';

        $latest_wp_parts  = explode( '.', $latest_versions[ 'wordpress' ] );
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

        foreach ( $sites as $site ) {

            $wp_current  = ! empty( $site->wordpress_version ) ? sanitize_text_field( $site->wordpress_version ) : '';
            $php_current = ! empty( $site->php_version ) ? implode( '.', array_slice( explode( '.', sanitize_text_field( $site->php_version ) ), 0, 2 ) ) : '';
            
            $site->is_wp_outdated  = ( $wp_current && version_compare( $wp_current, $allowed_wp_min, '<' ) );
            $site->is_php_outdated = ( $php_current && version_compare( $php_current, $allowed_php_min, '<' ) );
        }

        ?>
        <div id="prxctrl-check-all-sites-progress-bar">
            <div class="prxctrl-check-all-sites-progress-bar-fill"></div>
        </div>

		<h1><?php echo esc_attr( get_admin_page_title() ) ?></h1>

        <div class="prxctrl-dashboard-wrap">

            <?php if ( empty( $sites ) ) : ?>
                <?php
                $settings_link = '<a href="' . esc_url( Bootstrap::settings_url() ) . '" class="prxctrl-link">' . esc_html__( 'Settings', 'pluginrx-control-center' ) . '</a>';
                $agent_link = '<a href="https://pluginrx.com/plugin/pluginrx-control-center/" target="_blank" class="prxctrl-link">' . esc_html__( 'PluginRx Agent', 'pluginrx-control-center' ) . '</a>';
                ?>
                <div class="prxctrl-no-sites">
                    <h2><?php esc_html_e( 'Welcome to PluginRx Control Center!', 'pluginrx-control-center' ); ?></h2>

                    <p><?php
                        /* translators: %s: Link to settings page */
                        echo wp_kses_post( sprintf(
                            __( 'You haven’t added any sites yet. Go to %s to add your first site.', 'pluginrx-control-center' ),
                            $settings_link
                        ) );
                    ?></p>

                    <p><?php esc_html_e( 'For every site you manage from this dashboard (including this site), you need to install the ', 'pluginrx-control-center' ); ?>
                        <?php echo wp_kses_post( $agent_link ); ?><?php esc_html_e( ' plugin.', 'pluginrx-control-center' ); ?>
                    </p>

                    <p><?php esc_html_e( 'After installing the plugin on a site, generate an API key there and then enter that key on this site to connect the site.', 'pluginrx-control-center' ); ?></p>

                    <p><?php esc_html_e( 'Once sites are added and connected, they will appear here with their status, plugins, and themes.', 'pluginrx-control-center' ); ?></p>
                </div>
            <?php
            else : ?>
                <div class="prxctrl-dashboard-header">
                    <div class="prxctrl-dashboard-header-top">
                        <div class="prxctrl-dashboard-actions">
                            <button id="prxctrl-check-sites" class="button button-primary" data-wait-msg="<?php echo esc_html__( 'Checking all sites...', 'pluginrx-control-center' ); ?>">
                                <span class="dashicons dashicons-update"></span>
                                <?php
                                /* translators: %d: number of sites */
                                printf( esc_html__( 'Check All %d Sites', 'pluginrx-control-center' ), count( $sites ) );
                                ?>
                            </button>

                            <?php if ( $dashboard_toc === 'yes' && ! empty( $sites ) ) : ?>
                                <div class="prxctrl-dashboard-toc">
                                    <select id="prxctrl-toc-select" aria-label="<?php esc_html_e( 'Jump to site...', 'pluginrx-control-center' ); ?>">
                                        <option value=""><?php esc_html_e( 'Jump to...', 'pluginrx-control-center' ); ?></option>
                                        <?php foreach ( $sites as $site ) : ?>
                                            <option value="prxctrl-site-<?php echo absint( $site->id ); ?>">
                                                <?php echo esc_html( $site->site_name . ' (' . $site->site_url . ')' ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="prxctrl-latest-versions">
                            <span class="prxctrl-latest-wp-version"><strong><?php esc_html_e( 'Latest WordPress Version:', 'pluginrx-control-center' ); ?></strong> <?php echo esc_html( $latest_versions[ 'wordpress' ] ); ?></span>
                            <span class="prxctrl-latest-php-version"><strong><?php esc_html_e( 'Latest PHP Version:', 'pluginrx-control-center' ); ?></strong> <?php echo esc_html( $latest_versions[ 'php' ] ); ?></span>
                        </div>
                    </div>
                    <div class="prxctrl-dashboard-header-bottom">
                        <div class="prxctrl-check-all-sites-progress"></div>
                    </div>
                </div>

                <div class="prxctrl-dashboard-sites">
                    <?php foreach ( $sites as $site ) : 
                        $screenshot = self::get_site_screenshot( $site->site_url, $site->id );
                        $plugin_updates_needed = array_filter( $site->plugins, fn( $p ) => $p->update_available );
                        $plugin_updates_count = count( $plugin_updates_needed );
                        $theme_updates_needed = array_filter( $site->themes, fn( $t ) => $t->update_available );
                        $theme_updates_count = count( $theme_updates_needed );
                        $integration_actions = apply_filters( 'prxcntrl_integration_actions', [], $site->id );
                        ?>
                        <div id="prxctrl-site-<?php echo intval( $site->id ); ?>" class="prxctrl-site-section">
                            <div class="prxctrl-result-message"></div>
                            <div class="prxctrl-site-header">
                                <div class="prxctrl-site-header-left">
                                    <div class="prxctrl-site-thumbnail">
                                        <img src="<?php echo esc_url( $screenshot ); ?>" 
                                            alt="<?php echo esc_attr( $site->site_name ); ?>" 
                                            referrerpolicy="no-referrer"
                                            class="prxctrl-site-screenshot"
                                            loading="lazy" />
                                    </div>

                                    <h2 class="prxctrl-site-title"><?php echo esc_html( $site->site_name ?: $site->site_url ); ?></h2>
                                </div>

                                <div class="prxctrl-site-actions">
                                    <?php if ( ! empty( $integration_actions ) ) : ?>
                                        <?php foreach ( $integration_actions as $key => $labels ) : ?>
                                            <button class="button button-secondary prxctrl-<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>-button" data-action="<?php echo esc_attr( $key ); ?>" data-site-id="<?php echo intval( $site->id ); ?>" data-wait-msg="<?php echo esc_attr( $labels[ 'waiting_message' ] ); ?>">
                                                <?php echo esc_html( $labels[ 'button_label' ] ); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <button class="button button-secondary prxctrl-update-wp-button" data-site-id="<?php echo intval( $site->id ); ?>" data-action="update_wordpress" data-wait-msg="<?php echo esc_html__( 'Updating WordPress...', 'pluginrx-control-center' ); ?>" <?php echo ! $site->is_wp_outdated ? 'disabled' : ''; ?>>
                                        <?php esc_html_e( 'Update WordPress', 'pluginrx-control-center' ); ?>
                                    </button>

                                    <button class="button button-secondary prxctrl-update-themes-button" data-site-id="<?php echo intval( $site->id ); ?>" data-action="update_themes" data-wait-msg="<?php echo esc_html__( 'Updating Themes...', 'pluginrx-control-center' ); ?>" <?php echo $theme_updates_count === 0 ? 'disabled' : ''; ?>>
                                        <?php esc_html_e( 'Update Themes', 'pluginrx-control-center' ); ?>
                                    </button>

                                    <button class="button button-secondary prxctrl-update-plugins-button" data-site-id="<?php echo intval( $site->id ); ?>" data-action="update_plugins" data-wait-msg="<?php echo esc_html__( 'Updating Plugins...', 'pluginrx-control-center' ); ?>" <?php echo $plugin_updates_count === 0 ? 'disabled' : ''; ?>>
                                        <?php esc_html_e( 'Update Plugins', 'pluginrx-control-center' ); ?>
                                    </button>

                                    <button class="button button-secondary prxctrl-check-site-button" data-site-id="<?php echo intval( $site->id ); ?>" data-action="check_site" data-wait-msg="<?php echo esc_html__( 'Checking...', 'pluginrx-control-center' ); ?>">
                                        <?php esc_html_e( 'Check Site', 'pluginrx-control-center' ); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Site Info Grid -->
                            <div class="prxctrl-site-grid">
                                <!-- First Row -->
                                <?php
                                $last_checked = $site->last_checked;
                                if ( $last_checked && $is_dev_debug_tools_active ) {
                                    $last_checked = \Apos37\DevDebugTools\Helpers::convert_date_format( $last_checked );
                                }

                                $site_host = parse_url( $site->site_url, PHP_URL_HOST );
                                $site_host = preg_replace( '/^www\./', '', strtolower( (string) $site_host ) );

                                $site_url_type = sanitize_key( get_option( 'prxctrl_dashboard_site_path', 'admin' ) );
                                $site_url_href = '';
                                if ( $site_url_type === 'home' ) {
                                    $site_url_href = trailingslashit( $site->site_url );
                                    $icon_url_href = trailingslashit( $site->admin_path );
                                    $icon = 'dashicons-admin-network';
                                } else {
                                    if ( ! empty( $site->admin_path ) ) {
                                        $site_url_href = trailingslashit( $site->admin_path );
                                    } else {
                                        $site_url_href = trailingslashit( $site->site_url ) . 'wp-admin/';
                                    }
                                    $icon_url_href = trailingslashit( $site->site_url );
                                    $icon = 'dashicons-admin-home';
                                }

                                $admin_email_domain = '';
                                if ( ! empty( $site->admin_email ) && strpos( $site->admin_email, '@' ) !== false ) {
                                    $admin_email_domain = strtolower( substr( strrchr( $site->admin_email, '@' ), 1 ) );
                                }

                                $warn_admin_email = sanitize_key( get_option( 'prxctrl_dashboard_warn_admin_email', 'yes' ) );
                                $admin_email_warning = ( $warn_admin_email === 'yes' && $site_host && $admin_email_domain && $site_host !== $admin_email_domain );
                                ?>

                                <div class="prxctrl-site-row five-col">
                                    <div class="prxctrl-site-col">
                                        <strong><?php esc_html_e( 'Site URL', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-site-url">
                                            <a href="<?php echo esc_url( $site_url_href ); ?>" target="_blank" rel="noopener noreferrer" class="prxctrl-link">
                                                <?php echo esc_html( $site->site_url ); ?>
                                            </a>
                                        </span>
                                        <span class="prxctrl-icon-url">
                                            <a href="<?php echo esc_url( $icon_url_href ); ?>" target="_blank" rel="noopener noreferrer" class="prxctrl-link">
                                                <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                                            </a>
                                        </span>
                                    </div>

                                    <div class="prxctrl-site-col<?php echo $admin_email_warning ? ' prxctrl-warning' : ''; ?>">
                                        <strong><?php esc_html_e( 'Admin Email', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-admin-email"><?php echo esc_html( $site->admin_email ); ?></span>
                                    </div>

                                    <div class="prxctrl-site-col">
                                        <strong><?php esc_html_e( 'Server IP', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-server-ip"><?php echo esc_html( $site->server_ip ); ?></span>
                                    </div>

                                    <div class="prxctrl-site-col">
                                        <strong><?php esc_html_e( 'ABSPATH', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-abspath"><?php echo esc_html( $site->abspath ); ?></span>
                                    </div>

                                    <div class="prxctrl-site-col">
                                        <strong><?php esc_html_e( 'Last Checked', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-last-checked"><?php echo esc_html( $last_checked ); ?></span>
                                    </div>
                                </div>

                                <!-- Second Row -->
                                <div class="prxctrl-site-row five-col">
                                    <div class="prxctrl-site-col">
                                        <strong><?php esc_html_e( 'Multisite', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-is-multisite">
                                            <?php echo $site->is_multisite ? esc_html__( 'Yes', 'pluginrx-control-center' ) : esc_html__( 'No', 'pluginrx-control-center' ); ?>
                                        </span>
                                    </div>

                                    <div class="prxctrl-site-col">
                                        <strong><?php esc_html_e( 'Blog ID', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-blog-id"><?php echo esc_html( $site->blog_id ); ?></span>
                                    </div>

                                    <div class="prxctrl-site-col<?php echo $site->is_wp_outdated ? ' prxctrl-warning' : ''; ?>">
                                        <strong><?php esc_html_e( 'WordPress Version', 'pluginrx-control-center' ); ?>:</strong>
                                        <?php $wp_link = ( ! empty( $site->admin_path ) && $site->is_wp_outdated ) ? trailingslashit( $site->admin_path ) . 'update-core.php' : ''; ?>
                                        <?php if ( $wp_link ) : ?><a href="<?php echo esc_url( $wp_link ); ?>" target="_blank" class="prxctrl-link"><?php endif; ?>
                                            <span class="prxctrl-wp-version"><?php echo esc_html( $site->wordpress_version ); ?></span>
                                        <?php if ( $wp_link ) : ?></a><?php endif; ?>
                                    </div>

                                    <div class="prxctrl-site-col<?php echo $site->is_php_outdated ? ' prxctrl-warning' : ''; ?>">
                                        <strong><?php esc_html_e( 'PHP Version', 'pluginrx-control-center' ); ?>:</strong>
                                        <span class="prxctrl-php-version"><?php echo esc_html( $site->php_version ); ?></span>
                                    </div>

                                    <div class="prxctrl-site-col <?php echo $site->wp_debug ? 'prxctrl-debug-enabled' : ''; ?>">
                                        <strong><?php esc_html_e( 'WP Debug', 'pluginrx-control-center' ); ?>:</strong>
                                        <?php $wp_debug_link = ( ! empty( $site->admin_path ) && $site->wp_debug && $is_dev_debug_tools_active ) ? trailingslashit( $site->admin_path ) . 'admin.php?page=dev-debug-tools&tool=wpconfig' : ''; ?>
                                        <?php if ( $wp_debug_link ) : ?><a href="<?php echo esc_url( $wp_debug_link ); ?>" target="_blank" class="prxctrl-link"><?php endif; ?>
                                            <span class="prxctrl-wp-debug"><?php echo $site->wp_debug ? esc_html__( 'Enabled', 'pluginrx-control-center' ) : esc_html__( 'Disabled', 'pluginrx-control-center' ); ?></span>
                                        <?php if ( $wp_debug_link ) : ?></a><?php endif; ?>
                                    </div>
                                </div>

                                <!-- Integrations Row -->
                                <?php 
                                $integration_requests = apply_filters( 'prxcntrl_integration_requests', [], $site->id, $site->integrations );
                                if ( ! empty( $integration_requests ) ) : ?>
                                    <div class="prxctrl-site-row prxctrl-site-integrations-row">
                                        <?php foreach ( $integration_requests as $key => $integration ) : 
                                            $label = isset( $integration[ 'label' ] ) ? sanitize_text_field( $integration[ 'label' ] ) : ucfirst( str_replace( '_', ' ', $key ) );
                                            
                                            $raw_value = $site->integrations[ $key ] ?? '';
                                            if ( is_string( $raw_value ) ) {
                                                $maybe_array = maybe_unserialize( $raw_value );
                                            } else {
                                                $maybe_array = $raw_value;
                                            }

                                            if ( is_array( $maybe_array ) ) {
                                                $value = isset( $maybe_array[ 'value' ] ) ? (string) $maybe_array[ 'value' ] : '';
                                                $integration_link = isset( $maybe_array[ 'link' ] ) ? sanitize_text_field( $maybe_array[ 'link' ] ): ( isset( $integration[ 'link' ] ) ? sanitize_text_field( $integration[ 'link' ] ) : '' );
                                            } else {
                                                $value = (string) $raw_value;
                                                $integration_link = isset( $integration[ 'link' ] ) ? sanitize_text_field( $integration[ 'link' ] ) : '';
                                            }

                                            $has_value = (
                                                strtoupper( (string) $value ) !== 'N/A' 
                                                && $value !== '' 
                                                && $value !== '0'
                                            );
                                            $is_warning = (
                                                isset( $integration[ 'warn' ] )
                                                && filter_var( $integration[ 'warn' ], FILTER_VALIDATE_BOOLEAN )
                                                && $has_value
                                            );
                                            
                                            $full_link = '';

                                            if ( $integration_link && $has_value ) {
                                                if ( preg_match( '#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $integration_link ) ) {
                                                    $full_link = $integration_link;
                                                } elseif ( ! empty( $site->admin_path ) ) {
                                                    $full_link = trailingslashit( $site->admin_path ) . ltrim( $integration_link, '/' );
                                                }
                                            }

                                            $format = isset( $integration[ 'format' ] ) ? sanitize_key( $integration[ 'format' ] ) : 'none';
                                            if ( $format === 'filesize' && $has_value ) {
                                                $value = size_format( (int) $value );
                                            }

                                            $should_truncate = strlen( $value ) > 50;
                                            ?>
                                            <div class="prxctrl-site-col prxctrl-integration-col <?php echo $is_warning ? 'prxctrl-warning' : ''; ?>">
                                                <strong><?php echo esc_html( $label ); ?>:</strong>

                                                <?php if ( $full_link ) : ?>
                                                    <a href="<?php echo esc_url( $full_link ); ?>" target="_blank" class="prxctrl-link">
                                                <?php endif; ?>

                                                    <span class="prxctrl-integration-wrapper">
                                                        <span
                                                            class="prxctrl-integration-value<?php echo esc_attr( $should_truncate ? ' prxctrl-truncate' : '' ); ?>"
                                                            data-integration-key="<?php echo esc_attr( $key ); ?>"
                                                            data-should-warn="<?php echo $is_warning ? '1' : '0'; ?>"
                                                        >
                                                            <?php echo esc_html( $value ); ?>
                                                        </span>
                                                        <?php if ( $should_truncate ) : ?>
                                                            <span class="prxctrl-tooltip"><?php echo esc_html( $value ); ?></span>
                                                        <?php endif; ?>
                                                    </span>

                                                <?php if ( $full_link ) : ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Admin Users & Plugins & Themes Row -->
                                <div class="prxctrl-site-row prxctrl-site-summary-row">

                                    <!-- Plugins Column -->
                                    <div class="prxctrl-site-col plugin-count<?php echo ( $plugin_updates_count > 0 ) ? ' prxctrl-updates-available' : ''; ?>">
                                        <a href="#" class="prxctrl-toggle-plugins-table" data-site-id="<?php echo intval( $site->id ); ?>">
                                            <?php
                                            esc_html_e( 'Plugins', 'pluginrx-control-center' ); ?>
                                            (<span class="prxctrl-plugin-count"><?php echo esc_attr( count( $site->plugins ) ); ?></span>) — 
                                            <?php esc_html_e( 'Available Updates', 'pluginrx-control-center' ); ?>
                                            (<span class="prxctrl-plugin-update-count"><?php echo esc_attr( $plugin_updates_count ); ?></span>)
                                        </a>
                                    </div>

                                    <!-- Themes Column -->
                                    <div class="prxctrl-site-col theme-count<?php echo ( $theme_updates_count > 0 ) ? ' prxctrl-updates-available' : ''; ?>">
                                        <a href="#" class="prxctrl-toggle-themes-table" data-site-id="<?php echo intval( $site->id ); ?>">
                                            <?php 
                                            $active_theme = array_filter( $site->themes, fn( $t ) => $t->is_active );
                                            $active_theme = array_shift( $active_theme );
                                            esc_html_e( 'Themes', 'pluginrx-control-center' ); ?>
                                            (<span class="prxctrl-theme-count"><?php echo esc_attr( count( $site->themes ) ); ?></span>) — 
                                            <?php esc_html_e( 'Available Updates', 'pluginrx-control-center' ); ?>
                                            (<span class="prxctrl-theme-update-count"><?php echo esc_attr( $theme_updates_count ); ?></span>) — 
                                            <?php esc_html_e( 'Active Theme', 'pluginrx-control-center' ); ?>:
                                            <span class="prxctrl-active-theme">
                                                <?php echo $active_theme ? esc_html( $active_theme->name . ' ' . $active_theme->version ) : ''; ?>
                                            </span>
                                        </a>
                                    </div>

                                    <!-- Admin Users Column -->
                                     <div class="prxctrl-site-col admin-user-count<?php echo ( ! empty( $site->admin_users ) ) ? '' : ' prxctrl-no-admins'; ?>">
                                        <a href="#" class="prxctrl-toggle-admin-users-table" data-site-id="<?php echo intval( $site->id ); ?>">
                                            <?php esc_html_e( 'Admins', 'pluginrx-control-center' ); ?>
                                            (<span class="prxctrl-admin-user-count"><?php echo esc_attr( count( $site->admin_users ) ); ?></span>)
                                        </a>
                                    </div>

                                </div>

                                <!-- Full-width Plugins Table -->
                                <div class="prxctrl-plugins-table-wrapper full-width" id="prxctrl-plugins-table-<?php echo intval( $site->id ); ?>" style="display:none;">
                                    <table class="prxctrl-plugins-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Version', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Author', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Slug', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Active', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Update Available', 'pluginrx-control-center' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $site->plugins as $plugin ) : 
                                                $row_class = $plugin->is_active ? 'active-row' : 'inactive-row';
                                                if ( $plugin->update_available ) {
                                                    $row_class .= ' update-available-row';
                                                }
                                                ?>
                                                <tr class="<?php echo esc_attr( $row_class ); ?>">
                                                    <td><?php echo esc_html( $plugin->name ); ?></td>
                                                    <td><?php echo esc_html( $plugin->version ); ?></td>
                                                    <td><?php echo esc_html( $plugin->author ); ?></td>
                                                    <td><?php echo esc_html( $plugin->slug ); ?></td>
                                                    <td><?php echo $plugin->is_active ? esc_html__( 'Yes', 'pluginrx-control-center' ) : esc_html__( 'No', 'pluginrx-control-center' ); ?></td>
                                                    <td><?php echo $plugin->update_available ? esc_html__( 'Yes', 'pluginrx-control-center' ) : esc_html__( 'No', 'pluginrx-control-center' ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Full-width Themes Table -->
                                <div class="prxctrl-themes-table-wrapper full-width" id="prxctrl-themes-table-<?php echo intval( $site->id ); ?>" style="display:none;">
                                    <table class="prxctrl-themes-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Version', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Author', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Active', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Update Available', 'pluginrx-control-center' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $site->themes as $theme ) : 
                                                $row_class = $theme->is_active ? 'active-row' : 'inactive-row';
                                                if ( $theme->update_available ) {
                                                    $row_class .= ' update-available-row';
                                                }
                                                ?>
                                                <tr class="<?php echo esc_attr( $row_class ); ?>">
                                                    <td><?php echo esc_html( $theme->name ); ?></td>
                                                    <td><?php echo esc_html( $theme->version ); ?></td>
                                                    <td><?php echo esc_html( $theme->author ); ?></td>
                                                    <td><?php echo $theme->is_active ? esc_html__( 'Yes', 'pluginrx-control-center' ) : esc_html__( 'No', 'pluginrx-control-center' ); ?></td>
                                                    <td><?php echo $theme->update_available ? esc_html__( 'Yes', 'pluginrx-control-center' ) : esc_html__( 'No', 'pluginrx-control-center' ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Full-width Admin Users Table -->
                                <div class="prxctrl-admin-users-table-wrapper full-width" id="prxctrl-admin-users-table-<?php echo intval( $site->id ); ?>" style="display:none;">
                                    <table class="prxctrl-admin-users-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Username', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Email', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Role', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Developer', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'ID', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Registered', 'pluginrx-control-center' ); ?></th>
                                                <th><?php esc_html_e( 'Online Status', 'pluginrx-control-center' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $site->admin_users as $admin ) : 
                                                $user_edit_link = ( ! empty( $site->admin_path ) && ! empty( $admin->user_id ) ) 
                                                    ? trailingslashit( $site->admin_path ) . 'user-edit.php?user_id=' . intval( $admin->user_id ) 
                                                    : '';
                                                $registered    = $admin->user_registered;
                                                $online_status = $admin->online_status ?? '';
                                                $row_class = $online_status == 'online' ? 'online-row' : 'offline-row';

                                                if ( $is_dev_debug_tools_active ) {
                                                    if ( $registered ) {
                                                        $registered = \Apos37\DevDebugTools\Helpers::convert_date_format( $registered );
                                                    }
                                                    if ( $online_status ) {
                                                        if ( $online_status == 'online' ) {
                                                            $online_status = esc_html__( 'Online Now', 'pluginrx-control-center' );
                                                        } else if ( $online_status == 'unknown' ) {
                                                            $online_status = esc_html__( 'Unknown', 'pluginrx-control-center' );
                                                        } else {
                                                            $online_status = \Apos37\DevDebugTools\Helpers::convert_date_format( $online_status );
                                                        }
                                                    }
                                                }
                                               
                                                $role_display = '';
                                                if ( ! empty( $admin->role ) ) {
                                                    $roles = maybe_unserialize( $admin->role );

                                                    if ( is_array( $roles ) ) {
                                                        $roles = array_map( 'sanitize_text_field', $roles );
                                                        $role_display = implode( ', ', $roles );
                                                    } else {
                                                        $role_display = sanitize_text_field( (string) $roles );
                                                    }
                                                }
                                                ?>
                                                <tr class="<?php echo esc_attr( $row_class ); ?>">
                                                    <td><a href="<?php echo esc_url( $user_edit_link ); ?>" target="_blank"><?php echo esc_html( $admin->user_login ); ?></a></td>
                                                    <td><?php echo esc_html( $admin->display_name ); ?></td>
                                                    <td><?php echo esc_html( $admin->user_email ); ?></td>
                                                    <td><?php echo esc_html( $role_display ); ?></td>
                                                    <td><?php echo ! empty( $admin->is_dev ) ? esc_html__( 'Yes', 'pluginrx-control-center' ) : esc_html__( 'No', 'pluginrx-control-center' ); ?></td>
                                                    <td><?php echo esc_html( $admin->user_id ); ?></td>
                                                    <td><?php echo esc_html( $registered ); ?></td>
                                                    <td class="prxctrl-online-status"><?php echo esc_html( $online_status ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    } // End render_page()


    /**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'toplevel_page_prxctrl-dashboard' ) {
            return;
        }

		// Register and enqueue your CSS
        $text_domain = Bootstrap::textdomain();
        $css_path = Bootstrap::url( 'inc/css/' );
        $js_path  = Bootstrap::url( 'inc/js/' );
        $script_version = Bootstrap::script_version();

        // CSS
        wp_enqueue_style( $text_domain . '-shared-styles', $css_path . 'shared.css', [], $script_version );
		wp_enqueue_style( $text_domain . '-dashboard', $css_path . 'dashboard.css', [], $script_version );

        // JS
        wp_enqueue_script(
            $text_domain . '-dashboard',
            $js_path . 'dashboard.js',
            [ 'jquery' ],
            $script_version,
            true
        );

        wp_localize_script(
            $text_domain . '-dashboard',
            'prxctrl_dashboard',
            [
                'nonce'             => wp_create_nonce( 'prxctrl_remote_nonce' ),
                'console_enabled'   => sanitize_key( get_option( 'prxctrl_console_log', 'no' ) ),
                'links'             => [
                    'wordpress_version' => 'update-core.php',
                    'wp_debug'          => is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' ) ? 'admin.php?page=dev-debug-tools&tool=wpconfig' : '',
                ],
                'checking'          => esc_html__( 'Checking', 'pluginrx-control-center' ), 
                'all_sites_checked' => esc_html__( 'Done checking sites.', 'pluginrx-control-center' ),
                'confirmation'      => esc_html__( 'Are you sure?', 'pluginrx-control-center' ),
            ]
        );
    } // End enqueue_scripts()

}


Dashboard::instance();