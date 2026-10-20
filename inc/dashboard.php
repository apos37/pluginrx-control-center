<?php
/**
 * DASHBOARD
 *
 * On a host, lists every managed site in its group with status, widgets and actions. On an agent,
 * shows this site exactly as the Control Center sees it.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Dashboard {

    /**
     * Nonce action.
     */
    public const NONCE = 'prxctrl_dashboard';


    /**
     * Site columns filled from an agent response.
     */
    private const DATA_COLUMNS = [
        'admin_email'       => 'email',
        'server_ip'         => 'text',
        'abspath'           => 'text',
        'is_multisite'      => 'int',
        'blog_id'           => 'int',
        'wordpress_version' => 'text',
        'core_update'       => 'text',
        'php_version'       => 'text',
        'wp_debug'          => 'int',
        'admin_path'        => 'url',
    ];


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
    private function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'prxctrl_header_right', [ $this, 'render_latest_versions' ] );
        add_action( 'prxctrl_subheader_left', [ $this, 'render_subheader_left' ] );
        add_action( 'prxctrl_subheader_right', [ $this, 'render_subheader_right' ] );

        $actions = [
            'prxctrl_check_site'       => 'ajax_check_site',
            'prxctrl_site_action'      => 'ajax_site_action',
            'prxctrl_site_login'       => 'ajax_site_login',
            'prxctrl_save_notes'       => 'ajax_save_notes',
            'prxctrl_ignore_warning'   => 'ajax_ignore_warning',
            'prxctrl_display_options'  => 'ajax_display_options',
            'prxctrl_screenshot_ready' => 'ajax_screenshot_ready',
        ];

        foreach ( $actions as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ $this, $method ] );
        }
    } // End __construct()


    /**
     * Check if the current page is the dashboard.
     *
     * @return bool
     */
    private static function is_dashboard() : bool {
        return Helpers::is_plugin_screen() && 'dashboard' === Menu::current_key();
    } // End is_dashboard()


    /**
     * Enqueue assets.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! self::is_dashboard() ) {
            return;
        }

        $version = Bootstrap::script_version();

        wp_enqueue_style( 'prxctrl-dashboard', Bootstrap::url( 'inc/css/dashboard.css' ), [ 'prxctrl-theme' ], $version );
        wp_enqueue_script( 'prxctrl-dashboard', Bootstrap::url( 'inc/js/dashboard.js' ), [ 'jquery', 'prxctrl-common' ], $version, true );

        wp_localize_script( 'prxctrl-dashboard', 'prxctrlDashboard', [
            'nonce'       => wp_create_nonce( self::NONCE ),
            'isHost'      => Mode::host_here() ? '1' : '',
            'concurrency' => (int) Options::get( 'concurrency' ),
            'logsUrl'     => Helpers::page_url( 'logs' ),
            'i18n'        => [
                'checking'       => __( 'Checking', 'pluginrx-control-center' ),
                'checkingSite'   => __( 'Checking...', 'pluginrx-control-center' ),
                /* translators: 1: number done, 2: total, 3: site name */
                'progress'       => __( 'Checking %1$s of %2$s: %3$s', 'pluginrx-control-center' ),
                /* translators: 1: number of sites, 2: number with errors */
                'done'           => __( 'Done. Sites checked: %1$s. Errors: %2$s.', 'pluginrx-control-center' ),
                'cancelled'      => __( 'Stopped.', 'pluginrx-control-center' ),
                'confirm'        => __( 'Are you sure?', 'pluginrx-control-center' ),
                /* translators: %s: action name */
                'confirmAction'  => __( 'Run "%s" on this site?', 'pluginrx-control-center' ),
                /* translators: 1: action name, 2: number of sites */
                'confirmGroup'   => __( 'Run "%1$s" on %2$s sites in this group?', 'pluginrx-control-center' ),
                'noGroupSites'   => __( 'No sites in this group can run that right now.', 'pluginrx-control-center' ),
                'rechecking'     => __( 'Re-checking the site...', 'pluginrx-control-center' ),
                'loginOpening'   => __( 'Opening...', 'pluginrx-control-center' ),
                'loginWaitTitle' => __( 'Logging in...', 'pluginrx-control-center' ),
                'loginWait'      => __( 'Please wait while you are logged in. This can take a few seconds.', 'pluginrx-control-center' ),
                'notesSaved'     => __( 'Notes saved.', 'pluginrx-control-center' ),
                /* translators: %d: number of matches */
                'matches'        => __( 'Matches: %d', 'pluginrx-control-center' ),
                'noMatches'      => __( 'No matches', 'pluginrx-control-center' ),
                'viewLogs'       => __( 'View logs', 'pluginrx-control-center' ),
            ],
        ] );
    } // End enqueue()


    /**
     * Render the latest WordPress and PHP versions in the header.
     *
     * @param string $page
     * @return void
     */
    public function render_latest_versions( string $page ) : void {
        if ( 'dashboard' !== $page ) {
            return;
        }

        $latest = Helpers::latest_versions();
        ?>
        <div class="prxctrl-latest-versions">
            <span><?php esc_html_e( 'Latest WordPress', 'pluginrx-control-center' ); ?> <strong><?php echo esc_html( '' !== $latest[ 'wordpress' ] ? $latest[ 'wordpress' ] : '?' ); ?></strong></span>
            <span><?php esc_html_e( 'Latest PHP', 'pluginrx-control-center' ); ?> <strong><?php echo esc_html( '' !== $latest[ 'php' ] ? $latest[ 'php' ] : '?' ); ?></strong></span>
        </div>
        <?php
    } // End render_latest_versions()


    /**
     * Render the Check All button.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_left( string $page ) : void {
        if ( 'dashboard' !== $page || ! Mode::host_here() ) {
            return;
        }

        $count = Database::count_sites();

        if ( ! $count ) {
            return;
        }
        ?>
        <button type="button" class="prxctrl-button" id="prxctrl-check-all">
            <span class="dashicons dashicons-update" aria-hidden="true"></span>
            <?php
            /* translators: %d: number of sites */
            echo esc_html( sprintf( _n( 'Check %d Site', 'Check All %d Sites', $count, 'pluginrx-control-center' ), $count ) );
            ?>
        </button>
        <button type="button" class="prxctrl-button prxctrl-button-danger" id="prxctrl-check-all-cancel" hidden><?php esc_html_e( 'Stop', 'pluginrx-control-center' ); ?></button>
        <?php
    } // End render_subheader_left()


    /**
     * Render Jump To and Expand/Collapse All.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_right( string $page ) : void {
        if ( 'dashboard' !== $page || ! Mode::host_here() || ! Database::count_sites() ) {
            return;
        }

        if ( Options::get( 'jump_menu' ) ) {
            ?>
            <select id="prxctrl-jump" aria-label="<?php esc_attr_e( 'Jump to a site', 'pluginrx-control-center' ); ?>">
                <option value=""><?php esc_html_e( 'Jump to...', 'pluginrx-control-center' ); ?></option>
                <?php foreach ( Database::get_sites() as $site ) : ?>
                    <option value="prxctrl-site-<?php echo esc_attr( (string) $site->id ); ?>"><?php echo esc_html( '' !== $site->site_name ? $site->site_name : Helpers::host( (string) $site->site_url ) ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php
        }
        ?>
        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-expand-all"><?php esc_html_e( 'Expand All', 'pluginrx-control-center' ); ?></button>
        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-collapse-all"><?php esc_html_e( 'Collapse All', 'pluginrx-control-center' ); ?></button>
        <?php
    } // End render_subheader_right()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_access() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'pluginrx-control-center' ) );
        }
        ?>
        <div class="wrap prxctrl-wrap prxctrl-dashboard">
            <?php
            if ( Mode::host_here() ) {
                self::render_host();
            } else {
                self::render_agent();
            }
            ?>
        </div>
        <?php
    } // End render_page()


    /**
     * Render the host dashboard.
     *
     * @return void
     */
    private static function render_host() : void {
        $sites = (array) apply_filters( 'prxctrl_dashboard_sites', Database::get_sites( true ) );

        if ( empty( $sites ) ) {
            self::render_empty();
            return;
        }

        $sections = Database::group_sites( $sites, Database::get_groups() );
        $has_groups = count( $sections ) > 1;

        self::render_tools();
        ?>
        <div id="prxctrl-progress" class="prxctrl-progress" hidden>
            <span class="dashicons dashicons-update spin" aria-hidden="true"></span>
            <div class="prxctrl-progress-body">
                <div class="prxctrl-progress-text" role="status" aria-live="polite"></div>
                <div class="prxctrl-progress-bar"><div class="prxctrl-progress-fill"></div></div>
            </div>
        </div>

        <div class="prxctrl-sites">
            <?php foreach ( $sections as $section ) : ?>
                <?php
                if ( empty( $section[ 'sites' ] ) && null === $section[ 'group' ] ) {
                    continue;
                }

                $group_id = $section[ 'group' ] ? (int) $section[ 'group' ]->id : 0;
                ?>
                <div class="prxctrl-group" data-group-id="<?php echo esc_attr( (string) $group_id ); ?>">
                    <?php if ( $has_groups ) : ?>
                        <?php self::render_group_header( $section ); ?>
                    <?php endif; ?>
                    <div class="prxctrl-group-sites" id="prxctrl-group-<?php echo esc_attr( (string) $group_id ); ?>">
                        <?php
                        foreach ( $section[ 'sites' ] as $site ) {
                            SiteCard::render( $site, SiteCard::HOST );
                        }

                        if ( empty( $section[ 'sites' ] ) ) {
                            echo '<p class="prxctrl-muted prxctrl-empty-group">' . esc_html__( 'No sites in this group yet.', 'pluginrx-control-center' ) . '</p>';
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    } // End render_host()


    /**
     * Render a group header with its group actions.
     *
     * @param array $section
     * @return void
     */
    private static function render_group_header( array $section ) : void {
        $group_id = $section[ 'group' ] ? (int) $section[ 'group' ]->id : 0;
        $name = $section[ 'group' ] ? (string) $section[ 'group' ]->name : __( 'No Group', 'pluginrx-control-center' );
        $count = count( $section[ 'sites' ] );
        ?>
        <div class="prxctrl-group-header">
            <button type="button" class="prxctrl-group-toggle" aria-expanded="true" aria-controls="prxctrl-group-<?php echo esc_attr( (string) $group_id ); ?>">
                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                <span class="prxctrl-group-name"><?php echo esc_html( $name ); ?></span>
                <span class="prxctrl-group-count"><?php echo esc_html( sprintf( /* translators: %d: number of sites */ _n( '%d site', '%d sites', $count, 'pluginrx-control-center' ), $count ) ); ?></span>
            </button>
            <?php if ( $count ) : ?>
                <div class="prxctrl-group-actions">
                    <label class="screen-reader-text" for="prxctrl-group-action-<?php echo esc_attr( (string) $group_id ); ?>"><?php esc_html_e( 'Group action', 'pluginrx-control-center' ); ?></label>
                    <select id="prxctrl-group-action-<?php echo esc_attr( (string) $group_id ); ?>" class="prxctrl-group-action">
                        <option value=""><?php esc_html_e( 'Group actions...', 'pluginrx-control-center' ); ?></option>
                        <option value="check_site"><?php esc_html_e( 'Check Sites', 'pluginrx-control-center' ); ?></option>
                        <?php foreach ( Registry::actions() as $key => $action ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $action[ 'label' ] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="prxctrl-button prxctrl-button-secondary prxctrl-group-run"><?php esc_html_e( 'Run', 'pluginrx-control-center' ); ?></button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    } // End render_group_header()


    /**
     * Render the Display Options and Search containers.
     *
     * @return void
     */
    private static function render_tools() : void {
        $hidden = SiteCard::hidden_keys();
        $layout = Registry::widget_layout();
        $widgets = Registry::widgets();
        $general = [
            'screenshot'  => __( 'Screenshot', 'pluginrx-control-center' ),
            'links'       => __( 'Quick Links', 'pluginrx-control-center' ),
            'unavailable' => __( 'Integrations that are not shared or not active', 'pluginrx-control-center' ),
        ];
        $groups = [
            __( 'Widgets', 'pluginrx-control-center' ) => array_intersect_key( array_map( [ self::class, 'widget_label' ], $widgets ), array_flip( $layout[ 'enabled' ] ) ),
            __( 'Action Buttons', 'pluginrx-control-center' ) => Registry::buttons(),
            __( 'General', 'pluginrx-control-center' ) => $general,
        ];
        ?>
        <div class="prxctrl-tools">
            <details class="prxctrl-options" id="prxctrl-display-options">
                <summary>
                    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    <?php esc_html_e( 'Display Options', 'pluginrx-control-center' ); ?>
                    <span class="prxctrl-options-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </summary>
                <div class="prxctrl-options-body">
                    <p class="prxctrl-section-desc"><?php esc_html_e( 'Choose what you see on the dashboard. This only changes what is shown to you, not what is checked or saved.', 'pluginrx-control-center' ); ?></p>
                    <?php foreach ( $groups as $heading => $items ) : ?>
                        <div class="prxctrl-options-col">
                            <h3><?php echo esc_html( $heading ); ?></h3>
                            <div class="prxctrl-checkboxes">
                                <?php foreach ( $items as $key => $label ) : ?>
                                    <label>
                                        <input type="checkbox" class="prxctrl-display-toggle" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! in_array( $key, $hidden, true ) ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>

            <details class="prxctrl-options" id="prxctrl-search-box">
                <summary>
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <?php esc_html_e( 'Search Plugins, Themes and Admins', 'pluginrx-control-center' ); ?>
                    <span class="prxctrl-options-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </summary>
                <div class="prxctrl-options-body prxctrl-search-body">
                    <div class="prxctrl-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input type="search" id="prxctrl-search" placeholder="<?php esc_attr_e( 'Search across all sites...', 'pluginrx-control-center' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search across all sites', 'pluginrx-control-center' ); ?>">
                    </div>
                    <select id="prxctrl-search-scope" aria-label="<?php esc_attr_e( 'What to search', 'pluginrx-control-center' ); ?>">
                        <option value="plugins"><?php esc_html_e( 'Plugins', 'pluginrx-control-center' ); ?></option>
                        <option value="themes"><?php esc_html_e( 'Themes', 'pluginrx-control-center' ); ?></option>
                        <option value="admin_users"><?php esc_html_e( 'Admins', 'pluginrx-control-center' ); ?></option>
                    </select>
                    <span id="prxctrl-search-summary" class="prxctrl-muted" aria-live="polite"></span>
                    <div id="prxctrl-search-results" class="prxctrl-search-results" hidden>
                        <div class="prxctrl-search-column" data-list="on">
                            <h3><?php esc_html_e( 'Found on', 'pluginrx-control-center' ); ?> <span class="count"></span></h3>
                            <ul></ul>
                        </div>
                        <div class="prxctrl-search-column" data-list="off">
                            <h3><?php esc_html_e( 'Not found on', 'pluginrx-control-center' ); ?> <span class="count"></span></h3>
                            <ul></ul>
                        </div>
                        <div class="prxctrl-search-column" data-list="unknown">
                            <h3><?php esc_html_e( 'No data yet', 'pluginrx-control-center' ); ?> <?php Helpers::tooltip( __( 'These sites have not been checked yet, or do not share this list. Check them to include them in the search.', 'pluginrx-control-center' ) ); ?> <span class="count"></span></h3>
                            <ul></ul>
                        </div>
                    </div>
                </div>
            </details>
        </div>
        <?php
    } // End render_tools()


    /**
     * Get a widget's label.
     *
     * @param array $widget
     * @return string
     */
    public static function widget_label( array $widget ) : string {
        return (string) $widget[ 'label' ];
    } // End widget_label()


    /**
     * Render the welcome box when no sites have been added.
     *
     * @return void
     */
    private static function render_empty() : void {
        ?>
        <div class="prxctrl-box prxctrl-welcome">
            <div class="prxctrl-box-header"><h2><?php esc_html_e( 'Welcome to your Control Center', 'pluginrx-control-center' ); ?></h2></div>
            <div class="prxctrl-box-body">
                <ol class="prxctrl-steps">
                    <li><?php esc_html_e( 'Install PluginRx Control Center on each site you want to manage, and choose Agent (or Both) during setup.', 'pluginrx-control-center' ); ?></li>
                    <li>
                        <?php
                        printf(
                            /* translators: %s: this site's domain */
                            esc_html__( 'On each of those sites, open Settings > Agent, turn on remote access, add %s as a Control Center domain and generate an API key.', 'pluginrx-control-center' ),
                            '<code>' . esc_html( Client::host_name() ) . '</code>'
                        );
                        ?>
                    </li>
                    <li><?php esc_html_e( 'Add each site and its API key on the Sites page.', 'pluginrx-control-center' ); ?></li>
                </ol>
                <p><a class="prxctrl-button" href="<?php echo esc_url( Helpers::page_url( 'sites' ) ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add Your First Site', 'pluginrx-control-center' ); ?></a></p>
            </div>
        </div>
        <?php
    } // End render_empty()


    /**
     * Render the agent's view of its own site.
     *
     * @return void
     */
    private static function render_agent() : void {
        $site = self::own_site();
        $unshared = count( array_diff( array_keys( Registry::requests() ), (array) $site->permissions ) );
        ?>
        <div class="prxctrl-note">
            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            <p>
                <?php
                if ( ! Options::get( 'agent_remote_access' ) ) {
                    esc_html_e( 'Remote access is off, so no Control Center can see this site right now. When it is on, this is exactly what will be shared.', 'pluginrx-control-center' );
                } else {
                    esc_html_e( 'This is exactly what this site shares with the Control Center.', 'pluginrx-control-center' );
                }

                if ( $unshared ) {
                    echo ' ';
                    /* translators: %d: number of items */
                    echo esc_html( sprintf( _n( '%d item is not shared.', '%d items are not shared.', $unshared, 'pluginrx-control-center' ), $unshared ) );
                }

                if ( Access::can_manage() ) {
                    echo ' <a href="' . esc_url( Helpers::page_url( 'settings', [ 'tab' => 'agent' ] ) ) . '">' . esc_html__( 'Change what is shared', 'pluginrx-control-center' ) . '</a>';
                }
                ?>
            </p>
        </div>
        <div class="prxctrl-sites">
            <?php SiteCard::render( $site, SiteCard::AGENT ); ?>
        </div>
        <?php
    } // End render_agent()


    /**
     * Build a site object for this site from what it shares.
     *
     * @return object
     */
    public static function own_site( bool $refresh = false ) : object {
        $allowed = Agent::allowed();
        $checks = self::own_checks( $refresh );
        $data = Agent::collect( $allowed );
        $rows = Database::detail_rows( 0, $data );

        $site = (object) array_merge( self::columns_from_data( $data ), [
            'id'               => 0,
            'site_name'        => wp_strip_all_tags( get_bloginfo( 'name' ) ),
            'site_url'         => home_url(),
            'group_id'         => 0,
            'status'           => 'ok',
            'protocol'         => 'local',
            'agent_version'    => Bootstrap::version(),
            'permissions'      => $allowed,
            'ignored'          => [],
            'links'            => [],
            'login_url'        => '',
            'http_status'      => $checks[ 'status' ],
            'response_ms'      => $checks[ 'ms' ],
            'ssl_expires'      => $checks[ 'ssl_expires' ],
            'ssl_checked'      => $checks[ 'checked' ],
            'screenshot_ver'   => 0,
            'notes'            => '',
            'last_checked'     => Helpers::now(),
            'last_error'       => '',
            'other'            => wp_json_encode( [ 'integrations' => $data[ '_meta' ][ 'integrations' ] ?? null ] ),
            'connection_type'  => 'self',
            'local_blog_id'    => 0,
            'integrations'     => [],
        ] );

        foreach ( [ 'plugins', 'themes', 'admin_users' ] as $type ) {
            $site->{ $type } = array_map( [ self::class, 'to_object' ], $rows[ $type ] );
        }

        foreach ( $rows[ 'integrations' ] as $row ) {
            $site->integrations[ $row[ 'meta_key' ] ] = json_decode( $row[ 'meta_value' ], true );
        }

        return $site;
    } // End own_site()


    /**
     * Measure this site's own response time and SSL certificate, the way a Control Center does.
     * Remembered for ten minutes so opening the dashboard stays fast.
     *
     * @param bool $refresh Measure again now.
     * @return array
     */
    private static function own_checks( bool $refresh ) : array {
        $cached = get_transient( 'prxctrl_own_checks' );

        if ( ! $refresh && is_array( $cached ) ) {
            return $cached;
        }

        $ping = Client::ping( home_url( '/' ) );
        $ssl = Client::ssl_expiry( home_url( '/' ) );
        $checks = [
            'status'      => (int) $ping[ 'status' ],
            'ms'          => (int) $ping[ 'ms' ],
            'ssl_expires' => $ssl[ 'expires' ],
            'checked'     => Helpers::now(),
        ];

        set_transient( 'prxctrl_own_checks', $checks, 10 * MINUTE_IN_SECONDS );

        return $checks;
    } // End own_checks()


    /**
     * Cast an array to an object.
     *
     * @param array $row
     * @return object
     */
    public static function to_object( array $row ) : object {
        return (object) $row;
    } // End to_object()


    /**
     * Get the site columns from an agent response. Missing keys are cleared because the agent
     * stopped sharing them.
     *
     * @param array $data
     * @return array
     */
    public static function columns_from_data( array $data ) : array {
        $columns = [];

        foreach ( self::DATA_COLUMNS as $column => $type ) {
            $value = $data[ $column ] ?? null;

            switch ( $type ) {
                case 'email':
                    $columns[ $column ] = null === $value ? '' : sanitize_email( (string) $value );
                    break;
                case 'int':
                    $columns[ $column ] = null === $value ? 0 : (int) $value;
                    break;
                case 'url':
                    $columns[ $column ] = null === $value ? '' : esc_url_raw( (string) $value, [ 'http', 'https' ] );
                    break;
                default:
                    $columns[ $column ] = null === $value ? '' : sanitize_text_field( (string) $value );
            }
        }

        return $columns;
    } // End columns_from_data()


    /**
     * Get the permissions a response implies. v2 agents report them; for v1 agents, they are the
     * keys that came back, plus every action (v1 agents do not say which actions are allowed).
     *
     * @param array $data
     * @return array
     */
    private static function permissions_from_data( array $data ) : array {
        if ( isset( $data[ '_meta' ][ 'permissions' ] ) && is_array( $data[ '_meta' ][ 'permissions' ] ) ) {
            return array_values( array_map( 'sanitize_key', $data[ '_meta' ][ 'permissions' ] ) );
        }

        $keys = array_merge( array_keys( $data ), array_keys( (array) ( $data[ 'integrations' ] ?? [] ) ), array_keys( Registry::actions() ) );

        return array_values( array_intersect( array_map( 'sanitize_key', $keys ), array_keys( Registry::permissions() ) ) );
    } // End permissions_from_data()


    /**
     * Check a site: fetch its data, test the front end and SSL, and save everything.
     *
     * @param object $site Site row.
     * @return array [ 'success' => bool, 'message' => string ]
     */
    public static function check_site( object $site ) : array {
        $site_id = (int) $site->id;
        $result = Client::call( $site, 'request', Client::request_body() );
        $ping = Client::ping( (string) $site->site_url );
        $now = Helpers::now();

        $update = [
            'http_status' => $ping[ 'status' ],
            'response_ms' => $ping[ 'ms' ],
        ];

        if ( 0 === strpos( (string) $site->site_url, 'https://' ) && ( empty( $site->ssl_checked ) || strtotime( (string) $site->ssl_checked ) < strtotime( $now ) - 12 * HOUR_IN_SECONDS ) ) {
            $ssl = Client::ssl_expiry( (string) $site->site_url );
            $update[ 'ssl_expires' ] = $ssl[ 'expires' ];
            $update[ 'ssl_checked' ] = $now;
        }

        if ( is_wp_error( $result ) ) {
            $message = Client::diagnose( $result );

            Database::update_site( $site_id, array_merge( $update, [ 'status' => 'error', 'last_error' => $message ] ) );
            Database::log( $site_id, 'check_site', false, 'host', $message );

            return [ 'success' => false, 'message' => $message ];
        }

        $data = (array) apply_filters( 'prxctrl_site_data', $result, $site );
        $meta = (array) ( $data[ '_meta' ] ?? [] );

        $update = array_merge( $update, self::columns_from_data( $data ), [
            'status'         => 'ok',
            'last_error'     => '',
            'last_checked'   => $now,
            'permissions'    => self::permissions_from_data( $data ),
            'agent_version'  => isset( $meta[ 'version' ] ) ? sanitize_text_field( (string) $meta[ 'version' ] ) : '',
            'screenshot_ver' => time(),
            'other'          => [
                'login_ready'    => ! empty( $meta[ 'login_ready' ] ),
                'require_signed' => ! empty( $meta[ 'require_signed' ] ),
                'remote_name'    => isset( $meta[ 'site_name' ] ) ? sanitize_text_field( (string) $meta[ 'site_name' ] ) : '',
                'integrations'   => isset( $meta[ 'integrations' ] ) && is_array( $meta[ 'integrations' ] ) ? array_map( 'boolval', array_combine( array_map( 'sanitize_key', array_keys( $meta[ 'integrations' ] ) ), array_values( $meta[ 'integrations' ] ) ) ) : null,
            ],
        ] );

        if ( '' === $site->site_name && ! empty( $meta[ 'site_name' ] ) ) {
            $update[ 'site_name' ] = sanitize_text_field( (string) $meta[ 'site_name' ] );
        }

        Database::update_site( $site_id, $update );
        Database::save_details( $site_id, $data );

        if ( Options::get( 'log_checks' ) ) {
            Database::log( $site_id, 'check_site', true, 'host', __( 'Site checked.', 'pluginrx-control-center' ) );
        }

        do_action( 'prxctrl_site_checked', $site_id, $data );

        return [ 'success' => true, 'message' => '' ];
    } // End check_site()


    /**
     * Get a site for an AJAX request, or end the request.
     *
     * @param int $site_id
     * @return object
     */
    private static function site_or_fail( int $site_id ) : object {
        $site = $site_id ? Database::get_site( $site_id ) : null;

        if ( ! $site ) {
            wp_send_json_error( [ 'message' => __( 'That site no longer exists. Reload the page.', 'pluginrx-control-center' ) ] );
        }

        return $site;
    } // End site_or_fail()


    /**
     * Render a site card to a string.
     *
     * @param int $site_id
     * @return string
     */
    private static function card_html( int $site_id ) : string {
        $site = Database::get_site( $site_id, true );

        if ( ! $site ) {
            return '';
        }

        ob_start();
        SiteCard::render( $site, SiteCard::HOST );

        return (string) ob_get_clean();
    } // End card_html()


    /**
     * AJAX: check a site (or this site, on an agent).
     *
     * @return void
     */
    public function ajax_check_site() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        if ( ! Mode::host_here() ) {
            ob_start();
            SiteCard::render( self::own_site( true ), SiteCard::AGENT );
            wp_send_json_success( [ 'html' => ob_get_clean() ] );
        }

        $site = self::site_or_fail( isset( $_POST[ 'site_id' ] ) ? absint( wp_unslash( $_POST[ 'site_id' ] ) ) : 0 );
        $result = self::check_site( $site );

        $response = [
            'html'       => self::card_html( (int) $site->id ),
            'screenshot' => Client::screenshot_url( Database::get_site( (int) $site->id ) ),
        ];

        if ( ! $result[ 'success' ] ) {
            $response[ 'message' ] = $result[ 'message' ];
            wp_send_json_error( $response );
        }

        wp_send_json_success( $response );
    } // End ajax_check_site()


    /**
     * AJAX: run an action on a site.
     *
     * @return void
     */
    public function ajax_site_action() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        $site = self::site_or_fail( isset( $_POST[ 'site_id' ] ) ? absint( wp_unslash( $_POST[ 'site_id' ] ) ) : 0 );
        $type = isset( $_POST[ 'type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'type' ] ) ) : '';

        if ( ! isset( Registry::actions()[ $type ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Unknown action.', 'pluginrx-control-center' ) ] );
        }

        $result = Client::call( $site, 'action', [ 'type' => $type ] );

        if ( is_wp_error( $result ) ) {
            $message = Client::diagnose( $result );
            Database::log( (int) $site->id, $type, false, 'host', $message );
            wp_send_json_error( [ 'message' => $message ] );
        }

        $success = ! empty( $result[ 'success' ] );
        $message = wp_strip_all_tags( (string) ( $result[ 'message' ] ?? '' ) );

        if ( '' === $message ) {
            $message = $success ? __( 'Done.', 'pluginrx-control-center' ) : __( 'The site reported a problem.', 'pluginrx-control-center' );
        }

        Database::log( (int) $site->id, $type, $success, 'host', $message );

        do_action( 'prxctrl_action_performed', (int) $site->id, $type, $result );

        if ( ! $success ) {
            wp_send_json_error( [ 'message' => $message ] );
        }

        wp_send_json_success( [ 'message' => $message ] );
    } // End ajax_site_action()


    /**
     * AJAX: get a single-use login link.
     *
     * @return void
     */
    public function ajax_site_login() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        $site = self::site_or_fail( isset( $_POST[ 'site_id' ] ) ? absint( wp_unslash( $_POST[ 'site_id' ] ) ) : 0 );
        $result = Client::call( $site, 'login' );

        if ( is_wp_error( $result ) ) {
            $message = Client::diagnose( $result );
            Database::log( (int) $site->id, 'remote_login', false, 'host', $message );
            wp_send_json_error( [ 'message' => $message ] );
        }

        $url = esc_url_raw( (string) ( $result[ 'url' ] ?? '' ), [ 'http', 'https' ] );

        if ( '' === $url || ( ! Client::is_local( $site ) && Helpers::host( $url ) !== Helpers::host( (string) $site->site_url ) ) ) {
            Database::log( (int) $site->id, 'remote_login', false, 'host', __( 'The site returned a login link for a different domain.', 'pluginrx-control-center' ) );
            wp_send_json_error( [ 'message' => __( 'The site returned an invalid login link.', 'pluginrx-control-center' ) ] );
        }

        Database::log( (int) $site->id, 'remote_login', true, 'host', __( 'Single-use login link opened.', 'pluginrx-control-center' ) );

        wp_send_json_success( [ 'url' => $url ] );
    } // End ajax_site_login()


    /**
     * AJAX: save a site's notes.
     *
     * @return void
     */
    public function ajax_save_notes() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        $site = self::site_or_fail( isset( $_POST[ 'site_id' ] ) ? absint( wp_unslash( $_POST[ 'site_id' ] ) ) : 0 );
        $notes = isset( $_POST[ 'notes' ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ 'notes' ] ) ) : '';

        Database::update_site( (int) $site->id, [ 'notes' => mb_substr( $notes, 0, 5000 ) ] );

        wp_send_json_success( [ 'html' => self::card_html( (int) $site->id ) ] );
    } // End ajax_save_notes()


    /**
     * AJAX: ignore or restore a warning on one site.
     *
     * @return void
     */
    public function ajax_ignore_warning() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        $site = self::site_or_fail( isset( $_POST[ 'site_id' ] ) ? absint( wp_unslash( $_POST[ 'site_id' ] ) ) : 0 );
        $widget = isset( $_POST[ 'widget' ] ) ? sanitize_key( wp_unslash( $_POST[ 'widget' ] ) ) : '';
        $ignore = ! empty( $_POST[ 'ignore' ] );

        if ( ! isset( Registry::widgets()[ $widget ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Unknown widget.', 'pluginrx-control-center' ) ] );
        }

        $ignored = array_values( array_diff( (array) $site->ignored, [ $widget ] ) );

        if ( $ignore ) {
            $ignored[] = $widget;
        }

        Database::update_site( (int) $site->id, [ 'ignored_warnings' => $ignored ] );

        wp_send_json_success( [ 'html' => self::card_html( (int) $site->id ) ] );
    } // End ajax_ignore_warning()


    /**
     * AJAX: save the current user's display options.
     *
     * @return void
     */
    public function ajax_display_options() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        $hidden = isset( $_POST[ 'hidden' ] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ 'hidden' ] ) ) : [];
        $allowed = array_merge( array_keys( Registry::widgets() ), array_keys( Registry::buttons() ), [ 'screenshot', 'links', 'unavailable' ] );

        update_user_meta( get_current_user_id(), 'prxctrl_hidden', array_values( array_intersect( $hidden, $allowed ) ) );

        wp_send_json_success();
    } // End ajax_display_options()


    /**
     * AJAX: check if a site's screenshot has finished rendering.
     *
     * @return void
     */
    public function ajax_screenshot_ready() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        $site = self::site_or_fail( isset( $_POST[ 'site_id' ] ) ? absint( wp_unslash( $_POST[ 'site_id' ] ) ) : 0 );
        $url = Client::screenshot_url( $site );

        wp_send_json_success( [ 'ready' => '' !== $url && Client::screenshot_ready( $url ), 'url' => $url ] );
    } // End ajax_screenshot_ready()

} // End class Dashboard


Dashboard::instance();
