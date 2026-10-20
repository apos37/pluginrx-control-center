<?php
/**
 * SITES
 *
 * Add, edit, group, sort and remove managed sites. Changes are made in modals and saved together
 * with the Save button. Check Connection tests a site right away, even before it is saved.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Sites {

    /**
     * Nonce action.
     */
    public const NONCE = 'prxctrl_sites';


    /**
     * @var Sites|null Singleton instance
     */
    private static ?Sites $instance = null;


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
        add_action( 'prxctrl_subheader_left', [ $this, 'render_subheader_left' ] );
        add_action( 'prxctrl_subheader_right', [ $this, 'render_subheader_right' ] );
        add_action( 'wp_ajax_prxctrl_save_sites', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_prxctrl_check_connection', [ $this, 'ajax_check_connection' ] );
    } // End __construct()


    /**
     * Enqueue assets.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! Helpers::is_plugin_screen() || 'sites' !== Menu::current_key() ) {
            return;
        }

        $version = Bootstrap::script_version();

        wp_enqueue_style( 'prxctrl-sites', Bootstrap::url( 'inc/css/sites.css' ), [ 'prxctrl-theme' ], $version );
        wp_enqueue_script( 'prxctrl-sites', Bootstrap::url( 'inc/js/sites.js' ), [ 'jquery', 'jquery-ui-sortable', 'prxctrl-common' ], $version, true );

        wp_localize_script( 'prxctrl-sites', 'prxctrlSites', [
            'nonce'      => wp_create_nonce( self::NONCE ),
            'custom'     => 'custom' === Options::get( 'sorting' ) ? '1' : '',
            'linkFields' => array_values( (array) Options::get( 'link_fields' ) ),
            'logsUrl'    => Helpers::page_url( 'logs' ),
            'i18n'       => [
                'addSite'        => __( 'Add New Site', 'pluginrx-control-center' ),
                'editSite'       => __( 'Edit Site', 'pluginrx-control-center' ),
                'addGroup'       => __( 'Add New Group', 'pluginrx-control-center' ),
                'editGroup'      => __( 'Edit Group', 'pluginrx-control-center' ),
                'add'            => __( 'Add', 'pluginrx-control-center' ),
                'update'         => __( 'Update', 'pluginrx-control-center' ),
                'noGroup'        => __( 'No Group', 'pluginrx-control-center' ),
                'keySaved'       => __( 'Saved. Leave blank to keep the current key.', 'pluginrx-control-center' ),
                'keyRequired'    => __( 'Paste the API key from Settings > Agent on that site.', 'pluginrx-control-center' ),
                'nameRequired'   => __( 'Enter a name.', 'pluginrx-control-center' ),
                'urlRequired'    => __( 'Enter a valid URL starting with http:// or https://.', 'pluginrx-control-center' ),
                'urlDuplicate'   => __( 'This URL is already in the list.', 'pluginrx-control-center' ),
                'keyInvalid'     => __( 'API keys are 64 characters (letters a-f and numbers).', 'pluginrx-control-center' ),
                /* translators: %s: site name */
                'confirmRemove'  => __( 'Remove "%s"? Its cached data is deleted when you save. Its logs are kept.', 'pluginrx-control-center' ),
                /* translators: %s: group name */
                'confirmRemoveGroup' => __( 'Delete the group "%s"? Its sites move to No Group.', 'pluginrx-control-center' ),
                'checking'       => __( 'Checking...', 'pluginrx-control-center' ),
                'connected'      => __( 'Connected', 'pluginrx-control-center' ),
                'failed'         => __( 'Failed', 'pluginrx-control-center' ),
                'viewLogs'       => __( 'View logs', 'pluginrx-control-center' ),
                'unsavedRow'     => __( 'Not saved yet', 'pluginrx-control-center' ),
                'show'           => __( 'Show', 'pluginrx-control-center' ),
                'hide'           => __( 'Hide', 'pluginrx-control-center' ),
                'login'          => __( 'Login Page', 'pluginrx-control-center' ),
                'edit'           => __( 'Edit', 'pluginrx-control-center' ),
                'remove'         => __( 'Remove', 'pluginrx-control-center' ),
                'deleteGroup'    => __( 'Delete', 'pluginrx-control-center' ),
                'checkConnection' => __( 'Check Connection', 'pluginrx-control-center' ),
                'newGroup'       => __( '+ New Group...', 'pluginrx-control-center' ),
                'groupRequired'  => __( 'Enter a name for the new group.', 'pluginrx-control-center' ),
                'savedSite'      => __( 'Site saved.', 'pluginrx-control-center' ),
                'removedSite'    => __( 'Site removed.', 'pluginrx-control-center' ),
                'savedGroup'     => __( 'Group saved.', 'pluginrx-control-center' ),
                'removedGroup'   => __( 'Group deleted.', 'pluginrx-control-center' ),
                'savedOrder'     => __( 'Order saved.', 'pluginrx-control-center' ),
            ],
        ] );
    } // End enqueue()


    /**
     * Render Add Site and Add Group. Changes save as soon as they are made.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_left( string $page ) : void {
        if ( 'sites' !== $page ) {
            return;
        }
        ?>
        <button type="button" class="prxctrl-button" id="prxctrl-add-site"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add New Site', 'pluginrx-control-center' ); ?></button>
        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-add-group"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add New Group', 'pluginrx-control-center' ); ?></button>
        <?php
    } // End render_subheader_left()


    /**
     * Render Check All Connections.
     *
     * @param string $page
     * @return void
     */
    public function render_subheader_right( string $page ) : void {
        if ( 'sites' !== $page ) {
            return;
        }

        $count = Database::count_sites();
        ?>
        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-check-connections"<?php echo $count ? '' : ' disabled'; ?>>
            <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
            <span class="prxctrl-check-connections-label"><?php echo esc_html( self::check_all_label( $count ) ); ?></span>
        </button>
        <?php
    } // End render_subheader_right()


    /**
     * Get the Check All Connections label.
     *
     * @param int $count
     * @return string
     */
    private static function check_all_label( int $count ) : string {
        /* translators: %d: number of sites */
        return sprintf( _n( 'Check %d Connection', 'Check All %d Connections', $count, 'pluginrx-control-center' ), $count );
    } // End check_all_label()


    /**
     * Add this site to its own Sites list, for when it is both the Control Center and an agent.
     *
     * @return int The site ID, or 0 when it was already listed.
     */
    public static function add_this_site() : int {
        $url = Helpers::normalize_url( home_url() );

        if ( '' === $url || Database::get_site_by_url( $url ) ) {
            return 0;
        }

        $site_id = (int) Database::insert_site( [
            'site_url'        => $url,
            'site_name'       => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
            'connection_type' => 'local',
            'protocol'        => 'local',
            'sort_order'      => Database::count_sites() + 1,
        ] );

        if ( $site_id ) {
            Database::log( $site_id, 'site_added', true, 'sites', sprintf( /* translators: %s: site URL */ __( 'Added %s.', 'pluginrx-control-center' ), $url ) );
        }

        return $site_id;
    } // End add_this_site()


    /**
     * Remove this site from its own Sites list when it stops being an agent.
     *
     * @return void
     */
    public static function remove_this_site() : void {
        $site = Database::get_site_by_url( Helpers::normalize_url( home_url() ) );

        if ( ! $site || 'local' !== $site->connection_type || (int) $site->local_blog_id > 0 ) {
            return;
        }

        Database::delete_site( (int) $site->id );
        Database::log( (int) $site->id, 'site_removed', true, 'sites', sprintf( /* translators: %s: site URL */ __( 'Removed %s.', 'pluginrx-control-center' ), $site->site_url ) );
    } // End remove_this_site()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_access() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'pluginrx-control-center' ) );
        }

        $sites = Database::get_sites();
        $counts = [ 'all' => count( $sites ), 'ok' => 0, 'error' => 0, 'never' => 0 ];

        foreach ( $sites as $site ) {
            $key = in_array( $site->status, [ 'ok', 'error' ], true ) ? $site->status : 'never';
            $counts[ $key ]++;
        }

        $custom = 'custom' === Options::get( 'sorting' );
        ?>
        <div class="wrap prxctrl-wrap prxctrl-sites-page<?php echo $custom ? ' is-custom-sort' : ''; ?>">
            <div class="prxctrl-list-container">
                <ul class="subsubsub prxctrl-status-views">
                    <?php
                    $views = [
                        'all'   => __( 'All', 'pluginrx-control-center' ),
                        'ok'    => __( 'Connected', 'pluginrx-control-center' ),
                        'error' => __( 'Errors', 'pluginrx-control-center' ),
                        'never' => __( 'Not Checked', 'pluginrx-control-center' ),
                    ];
                    ?>
                    <?php foreach ( $views as $key => $label ) : ?>
                        <li><a href="#" data-view="<?php echo esc_attr( $key ); ?>"<?php echo 'all' === $key ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?> <span class="count">(<span class="prxctrl-view-count"><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></span>)</span></a></li>
                    <?php endforeach; ?>
                </ul>

                <p class="prxctrl-sort-note">
                    <?php
                    if ( $custom ) {
                        esc_html_e( 'Drag sites and groups to change their order. Drop a site into another group to move it.', 'pluginrx-control-center' );
                    } else {
                        printf(
                            /* translators: %s: link to the Host settings */
                            esc_html__( 'Sites and groups are sorted alphabetically. Choose Custom sorting in %s to drag and drop them.', 'pluginrx-control-center' ),
                            '<a href="' . esc_url( Helpers::page_url( 'settings', [ 'tab' => 'host' ] ) ) . '">' . esc_html__( 'Settings', 'pluginrx-control-center' ) . '</a>'
                        );
                    }
                    ?>
                </p>

                <table class="wp-list-table widefat fixed striped prxctrl-sites-table" id="prxctrl-sites-table">
                    <thead>
                        <tr>
                            <?php if ( $custom ) : ?>
                                <th scope="col" class="column-handle"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'pluginrx-control-center' ); ?></span></th>
                            <?php endif; ?>
                            <th scope="col" class="column-primary"><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'URL', 'pluginrx-control-center' ); ?></th>
                            <th scope="col" class="column-connection"><?php esc_html_e( 'Connection', 'pluginrx-control-center' ); ?></th>
                            <th scope="col" class="column-checked"><?php esc_html_e( 'Last Checked', 'pluginrx-control-center' ); ?></th>
                            <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'pluginrx-control-center' ); ?></th>
                        </tr>
                    </thead>
                    <?php self::render_sections( $sites, $custom ); ?>
                </table>
            </div>

            <?php self::render_site_modal(); ?>
            <?php self::render_group_modal(); ?>
        </div>
        <?php
    } // End render_page()


    /**
     * Render the table bodies: one per group, with the ungrouped sites first.
     *
     * @param array $sites
     * @param bool  $custom
     * @return void
     */
    public static function render_sections( array $sites, bool $custom ) : void {
        $columns = $custom ? 6 : 5;

        foreach ( Database::group_sites( $sites, Database::get_groups() ) as $section ) {
            $group = $section[ 'group' ];
            $group_id = $group ? (int) $group->id : 0;
            ?>
            <tbody class="prxctrl-group-body" data-group-id="<?php echo esc_attr( (string) $group_id ); ?>" data-name="<?php echo esc_attr( $group ? (string) $group->name : '' ); ?>">
                <tr class="prxctrl-group-row<?php echo $group ? '' : ' is-ungrouped'; ?>">
                    <?php if ( $custom ) : ?>
                        <td class="column-handle"><?php if ( $group ) : ?><span class="dashicons dashicons-menu prxctrl-group-handle" title="<?php esc_attr_e( 'Drag to reorder groups', 'pluginrx-control-center' ); ?>"></span><?php endif; ?></td>
                    <?php endif; ?>
                    <td colspan="<?php echo esc_attr( (string) ( $columns - ( $custom ? 2 : 1 ) ) ); ?>">
                        <span class="dashicons <?php echo $group ? 'dashicons-category' : 'dashicons-admin-site-alt3'; ?>" aria-hidden="true"></span>
                        <strong class="prxctrl-group-label"><?php echo esc_html( $group ? (string) $group->name : __( 'No Group', 'pluginrx-control-center' ) ); ?></strong>
                        <span class="count">(<span class="prxctrl-group-count"><?php echo esc_html( (string) count( $section[ 'sites' ] ) ); ?></span>)</span>
                    </td>
                    <td class="column-actions">
                        <?php if ( $group ) : ?>
                            <button type="button" class="button-link prxctrl-edit-group"><?php esc_html_e( 'Edit', 'pluginrx-control-center' ); ?></button>
                            <span aria-hidden="true">|</span>
                            <button type="button" class="button-link button-link-delete prxctrl-remove-group"><?php esc_html_e( 'Delete', 'pluginrx-control-center' ); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                foreach ( $section[ 'sites' ] as $site ) {
                    self::render_row( $site, $custom );
                }
                ?>
            </tbody>
            <?php
        }
    } // End render_sections()


    /**
     * Render one site row.
     *
     * @param object $site
     * @param bool   $custom
     * @return void
     */
    public static function render_row( object $site, bool $custom ) : void {
        $status = in_array( $site->status, [ 'ok', 'error' ], true ) ? $site->status : 'never';
        $local = 'local' === $site->connection_type;
        $name = '' !== $site->site_name ? $site->site_name : Helpers::host( (string) $site->site_url );
        ?>
        <tr class="prxctrl-site-row" data-id="<?php echo esc_attr( (string) $site->id ); ?>" data-name="<?php echo esc_attr( $site->site_name ); ?>" data-url="<?php echo esc_attr( (string) $site->site_url ); ?>" data-group="<?php echo esc_attr( (string) $site->group_id ); ?>" data-login="<?php echo esc_attr( (string) $site->login_url ); ?>" data-links="<?php echo esc_attr( (string) wp_json_encode( (object) $site->links ) ); ?>" data-has-key="<?php echo $site->has_key ? '1' : '0'; ?>" data-local="<?php echo $local ? '1' : '0'; ?>" data-status="<?php echo esc_attr( $status ); ?>">
            <?php if ( $custom ) : ?>
                <td class="column-handle"><span class="dashicons dashicons-menu prxctrl-site-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pluginrx-control-center' ); ?>"></span></td>
            <?php endif; ?>
            <td class="column-primary">
                <strong class="prxctrl-row-name"><?php echo esc_html( $name ); ?></strong>
                <div class="row-actions prxctrl-quick-links">
                    <?php
                    $links = SiteCard::links( $site );
                    $last = array_key_last( $links );

                    foreach ( $links as $label => $url ) {
                        echo '<span><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>' . ( $label !== $last ? ' | ' : '' ) . '</span>';
                    }
                    ?>
                </div>
            </td>
            <td class="prxctrl-row-url" data-colname="<?php esc_attr_e( 'URL', 'pluginrx-control-center' ); ?>"><a href="<?php echo esc_url( (string) $site->site_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $site->site_url ); ?></a></td>
            <td class="column-connection" data-colname="<?php esc_attr_e( 'Connection', 'pluginrx-control-center' ); ?>">
                <span class="prxctrl-connection prxctrl-connection-<?php echo esc_attr( $status ); ?>" aria-live="polite">
                    <?php self::render_connection( $site ); ?>
                </span>
            </td>
            <td class="column-checked" data-colname="<?php esc_attr_e( 'Last Checked', 'pluginrx-control-center' ); ?>"><?php echo ! empty( $site->last_checked ) ? esc_html( Helpers::format_date( (string) $site->last_checked ) ) : '<span class="prxctrl-muted">' . esc_html__( 'Never', 'pluginrx-control-center' ) . '</span>'; ?></td>
            <td class="column-actions">
                <button type="button" class="button-link prxctrl-edit-site"><?php esc_html_e( 'Edit', 'pluginrx-control-center' ); ?></button>
                <span aria-hidden="true">|</span>
                <button type="button" class="button-link button-link-delete prxctrl-remove-site"><?php esc_html_e( 'Remove', 'pluginrx-control-center' ); ?></button>
                <span aria-hidden="true">|</span>
                <button type="button" class="button-link prxctrl-check-connection"><?php esc_html_e( 'Check Connection', 'pluginrx-control-center' ); ?></button>
            </td>
        </tr>
        <?php
    } // End render_row()


    /**
     * Render a site's connection status.
     *
     * @param object $site
     * @return void
     */
    private static function render_connection( object $site ) : void {
        if ( 'error' === $site->status ) {
            echo '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> <span class="prxctrl-connection-text" title="' . esc_attr( (string) $site->last_error ) . '">' . esc_html__( 'Error', 'pluginrx-control-center' ) . '</span>';
            echo ' <a href="' . esc_url( Helpers::page_url( 'logs', [ 'site' => (int) $site->id, 'status' => 'error' ] ) ) . '" class="prxctrl-log-link">' . esc_html__( 'View logs', 'pluginrx-control-center' ) . '</a>';
            return;
        }

        if ( 'ok' !== $site->status ) {
            echo '<span class="dashicons dashicons-marker" aria-hidden="true"></span> <span class="prxctrl-connection-text">' . esc_html__( 'Not checked', 'pluginrx-control-center' ) . '</span>';
            return;
        }

        $labels = [
            'v2'    => __( 'Connected (signed)', 'pluginrx-control-center' ),
            'v1'    => __( 'Connected (legacy)', 'pluginrx-control-center' ),
            'local' => __( 'Connected (local)', 'pluginrx-control-center' ),
        ];

        echo '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <span class="prxctrl-connection-text">' . esc_html( $labels[ $site->protocol ] ?? __( 'Connected', 'pluginrx-control-center' ) ) . '</span>';
    } // End render_connection()


    /**
     * Render the add/edit site modal.
     *
     * @return void
     */
    private static function render_site_modal() : void {
        ?>
        <div class="prxctrl-modal" id="prxctrl-site-modal" role="dialog" aria-modal="true" aria-labelledby="prxctrl-site-modal-title" hidden>
            <div class="prxctrl-modal-backdrop" data-close></div>
            <div class="prxctrl-modal-dialog">
                <div class="prxctrl-modal-header">
                    <h2 id="prxctrl-site-modal-title"><?php esc_html_e( 'Add New Site', 'pluginrx-control-center' ); ?></h2>
                    <button type="button" class="prxctrl-modal-close" data-close aria-label="<?php esc_attr_e( 'Close', 'pluginrx-control-center' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </div>
                <div class="prxctrl-modal-body">
                    <div class="prxctrl-field">
                        <label for="prxctrl-site-name"><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></label>
                        <input type="text" id="prxctrl-site-name" autocomplete="off">
                    </div>
                    <div class="prxctrl-field">
                        <label for="prxctrl-site-url"><?php esc_html_e( 'Site URL', 'pluginrx-control-center' ); ?></label>
                        <input type="url" id="prxctrl-site-url" placeholder="https://example.com" autocomplete="off">
                    </div>
                    <div class="prxctrl-field prxctrl-key-field">
                        <label for="prxctrl-site-key"><?php esc_html_e( 'API Key', 'pluginrx-control-center' ); ?> <?php Helpers::tooltip( __( 'Copy it from Settings > Agent on the site you are adding. It is stored on this site and used to sign every request; it is never shown again here.', 'pluginrx-control-center' ) ); ?></label>
                        <div class="prxctrl-input-group">
                            <input type="password" id="prxctrl-site-key" autocomplete="new-password" spellcheck="false">
                            <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-toggle-key"><?php esc_html_e( 'Show', 'pluginrx-control-center' ); ?></button>
                        </div>
                        <p class="description" id="prxctrl-site-key-help"></p>
                    </div>
                    <div class="prxctrl-field">
                        <label for="prxctrl-site-group"><?php esc_html_e( 'Group', 'pluginrx-control-center' ); ?></label>
                        <select id="prxctrl-site-group"></select>
                        <input type="text" id="prxctrl-site-new-group" placeholder="<?php esc_attr_e( 'New group name', 'pluginrx-control-center' ); ?>" aria-label="<?php esc_attr_e( 'New group name', 'pluginrx-control-center' ); ?>" autocomplete="off" hidden>
                    </div>
                    <div class="prxctrl-field">
                        <label for="prxctrl-site-login"><?php esc_html_e( 'Login URL', 'pluginrx-control-center' ); ?> <?php Helpers::tooltip( __( 'Shown as a quick link. Leave blank to use the default wp-login.php address.', 'pluginrx-control-center' ) ); ?></label>
                        <input type="url" id="prxctrl-site-login" placeholder="https://example.com/wp-login.php" autocomplete="off">
                    </div>
                    <div id="prxctrl-site-link-fields"></div>
                    <div class="prxctrl-field prxctrl-modal-check">
                        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-modal-check"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> <?php esc_html_e( 'Check Connection', 'pluginrx-control-center' ); ?></button>
                        <span id="prxctrl-modal-check-result" aria-live="polite"></span>
                    </div>
                    <p class="prxctrl-modal-error" role="alert" hidden></p>
                </div>
                <div class="prxctrl-modal-footer">
                    <button type="button" class="prxctrl-button prxctrl-button-secondary" data-close><?php esc_html_e( 'Cancel', 'pluginrx-control-center' ); ?></button>
                    <button type="button" class="prxctrl-button" id="prxctrl-site-modal-submit"><?php esc_html_e( 'Add', 'pluginrx-control-center' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    } // End render_site_modal()


    /**
     * Render the add/edit group modal.
     *
     * @return void
     */
    private static function render_group_modal() : void {
        ?>
        <div class="prxctrl-modal" id="prxctrl-group-modal" role="dialog" aria-modal="true" aria-labelledby="prxctrl-group-modal-title" hidden>
            <div class="prxctrl-modal-backdrop" data-close></div>
            <div class="prxctrl-modal-dialog">
                <div class="prxctrl-modal-header">
                    <h2 id="prxctrl-group-modal-title"><?php esc_html_e( 'Add New Group', 'pluginrx-control-center' ); ?></h2>
                    <button type="button" class="prxctrl-modal-close" data-close aria-label="<?php esc_attr_e( 'Close', 'pluginrx-control-center' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </div>
                <div class="prxctrl-modal-body">
                    <div class="prxctrl-field">
                        <label for="prxctrl-group-name"><?php esc_html_e( 'Name', 'pluginrx-control-center' ); ?></label>
                        <input type="text" id="prxctrl-group-name" autocomplete="off">
                    </div>
                    <div class="prxctrl-field">
                        <label><?php esc_html_e( 'Sites in This Group', 'pluginrx-control-center' ); ?></label>
                        <div class="prxctrl-checkboxes prxctrl-group-sites-list" id="prxctrl-group-sites"></div>
                    </div>
                    <p class="prxctrl-modal-error" role="alert" hidden></p>
                </div>
                <div class="prxctrl-modal-footer">
                    <button type="button" class="prxctrl-button prxctrl-button-secondary" data-close><?php esc_html_e( 'Cancel', 'pluginrx-control-center' ); ?></button>
                    <button type="button" class="prxctrl-button" id="prxctrl-group-modal-submit"><?php esc_html_e( 'Add', 'pluginrx-control-center' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    } // End render_group_modal()


    /**
     * Sanitize the link values for a site.
     *
     * @param mixed $links
     * @return array
     */
    private static function sanitize_links( $links ) : array {
        $clean = [];

        foreach ( (array) Options::get( 'link_fields' ) as $field ) {
            $url = esc_url_raw( (string) ( ( (array) $links )[ $field[ 'slug' ] ] ?? '' ), [ 'http', 'https' ] );

            if ( '' !== $url ) {
                $clean[ $field[ 'slug' ] ] = $url;
            }
        }

        return $clean;
    } // End sanitize_links()


    /**
     * AJAX: save all sites and groups.
     *
     * @return void
     */
    public function ajax_save() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        if ( ! Mode::host_here() ) {
            wp_send_json_error( [ 'message' => __( 'This site is not a Control Center.', 'pluginrx-control-center' ) ] );
        }

        $payload = isset( $_POST[ 'payload' ] ) ? json_decode( wp_unslash( $_POST[ 'payload' ] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded here and every value is sanitized below.

        if ( ! is_array( $payload ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'pluginrx-control-center' ) ] );
        }

        $existing = [];

        foreach ( Database::get_sites() as $site ) {
            $existing[ $site->id ] = $site;
        }

        $errors = [];
        $seen_urls = [];
        $seen_keys = [];
        $sites = [];
        $kept_ids = array_map( 'intval', array_filter( array_column( (array) ( $payload[ 'sites' ] ?? [] ), 'id' ), 'is_numeric' ) );

        foreach ( $existing as $site_id => $site ) {
            if ( ! empty( $site->api_key ) && in_array( (int) $site_id, $kept_ids, true ) ) {
                $seen_keys[ (string) $site->api_key ] = (int) $site_id;
            }
        }

        foreach ( (array) ( $payload[ 'sites' ] ?? [] ) as $index => $row ) {
            $row = (array) $row;
            $ref = sanitize_key( (string) ( $row[ 'id' ] ?? '' ) );
            $site_id = ctype_digit( $ref ) ? (int) $ref : 0;
            $current = $existing[ $site_id ] ?? null;
            $local = $current && 'local' === $current->connection_type;
            $url = $local ? (string) $current->site_url : Helpers::normalize_url( (string) ( $row[ 'url' ] ?? '' ) );
            $key = strtolower( trim( sanitize_text_field( (string) ( $row[ 'api_key' ] ?? '' ) ) ) );

            if ( '' === $url ) {
                $errors[ $ref ] = __( 'Enter a valid URL starting with http:// or https://.', 'pluginrx-control-center' );
                continue;
            }

            if ( isset( $seen_urls[ $url ] ) ) {
                $errors[ $ref ] = __( 'This URL is already in the list.', 'pluginrx-control-center' );
                continue;
            }

            if ( '' !== $key && ! preg_match( '/^[a-f0-9]{64}$/', $key ) ) {
                $errors[ $ref ] = __( 'API keys are 64 characters (letters a-f and numbers).', 'pluginrx-control-center' );
                continue;
            }

            if ( '' !== $key && isset( $seen_keys[ $key ] ) && $seen_keys[ $key ] !== $site_id ) {
                $errors[ $ref ] = __( 'This API key is already used by another site. Each agent has its own key.', 'pluginrx-control-center' );
                continue;
            }

            $seen_urls[ $url ] = true;

            if ( '' !== $key ) {
                $seen_keys[ $key ] = $site_id;
            }

            $sites[] = [
                'id'        => $current ? $site_id : 0,
                'ref'       => $ref,
                'group_ref' => sanitize_key( (string) ( $row[ 'group' ] ?? '0' ) ),
                'data'      => [
                    'site_name'  => sanitize_text_field( (string) ( $row[ 'name' ] ?? '' ) ),
                    'site_url'   => $url,
                    'login_url'  => esc_url_raw( (string) ( $row[ 'login_url' ] ?? '' ), [ 'http', 'https' ] ),
                    'links'      => self::sanitize_links( $row[ 'links' ] ?? [] ),
                    'sort_order' => (int) $index + 1,
                ],
                'key'       => $key,
                'changed'   => $current && $current->site_url !== $url,
            ];
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'message' => __( 'Some sites need attention.', 'pluginrx-control-center' ), 'errors' => $errors ] );
        }

        $deleted = array_diff( array_keys( $existing ), array_column( $sites, 'id' ) );

        foreach ( $deleted as $site_id ) {
            Database::delete_site( (int) $site_id );
            Database::log( (int) $site_id, 'site_removed', true, 'sites', sprintf( /* translators: %s: site URL */ __( 'Removed %s.', 'pluginrx-control-center' ), $existing[ $site_id ]->site_url ) );
        }

        $group_map = [ '0' => 0 ];
        $existing_groups = wp_list_pluck( Database::get_groups(), 'name', 'id' );
        $kept_groups = [];

        foreach ( (array) ( $payload[ 'groups' ] ?? [] ) as $index => $group ) {
            $group = (array) $group;
            $ref = sanitize_key( (string) ( $group[ 'id' ] ?? '' ) );
            $name = sanitize_text_field( (string) ( $group[ 'name' ] ?? '' ) );

            if ( '' === $name ) {
                continue;
            }

            if ( ctype_digit( $ref ) && isset( $existing_groups[ (int) $ref ] ) ) {
                Database::update_group( (int) $ref, $name, (int) $index + 1 );
                $group_map[ $ref ] = (int) $ref;
                $kept_groups[] = (int) $ref;
            } else {
                $group_map[ $ref ] = Database::insert_group( $name, (int) $index + 1 );
            }
        }

        foreach ( array_diff( array_keys( $existing_groups ), $kept_groups ) as $group_id ) {
            Database::delete_group( (int) $group_id );
        }

        foreach ( $sites as $site ) {
            $data = $site[ 'data' ];
            $data[ 'group_id' ] = $group_map[ $site[ 'group_ref' ] ] ?? 0;

            if ( '' !== $site[ 'key' ] ) {
                $data[ 'api_key' ] = $site[ 'key' ];
            }

            if ( $site[ 'id' ] ) {
                if ( $site[ 'changed' ] || '' !== $site[ 'key' ] ) {
                    $data[ 'protocol' ] = '';
                    delete_transient( 'prxctrl_v1_' . $site[ 'id' ] );
                }

                Database::update_site( $site[ 'id' ], $data );
                continue;
            }

            $data[ 'connection_type' ] = 'remote';
            $new_id = Database::insert_site( $data );

            if ( $new_id ) {
                Database::log( $new_id, 'site_added', true, 'sites', sprintf( /* translators: %s: site URL */ __( 'Added %s.', 'pluginrx-control-center' ), $data[ 'site_url' ] ) );
            } else {
                $errors[ $site[ 'ref' ] ] = __( 'This site could not be saved. Its URL or API key may already be used by another site.', 'pluginrx-control-center' );
            }
        }

        $custom = 'custom' === Options::get( 'sorting' );
        ob_start();
        self::render_sections( Database::get_sites(), $custom );
        $html = (string) ob_get_clean();

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'message' => __( 'Some sites could not be saved.', 'pluginrx-control-center' ), 'errors' => $errors, 'html' => $html ] );
        }

        $count = Database::count_sites();

        wp_send_json_success( [ 'html' => $html, 'count' => $count, 'label' => self::check_all_label( $count ) ] );
    } // End ajax_save()


    /**
     * AJAX: test a connection, with or without saving.
     *
     * @return void
     */
    public function ajax_check_connection() : void {
        check_ajax_referer( self::NONCE, 'nonce' );
        Access::require_ajax();

        $site_id = isset( $_POST[ 'site_id' ] ) ? absint( wp_unslash( $_POST[ 'site_id' ] ) ) : 0;
        $url = isset( $_POST[ 'url' ] ) ? Helpers::normalize_url( sanitize_text_field( wp_unslash( $_POST[ 'url' ] ) ) ) : '';
        $key = isset( $_POST[ 'api_key' ] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST[ 'api_key' ] ) ) ) ) : '';
        $site = $site_id ? Database::get_site( $site_id ) : null;

        if ( ! $site ) {
            $site_id = 0;
            $site = (object) [ 'id' => 0, 'site_url' => '', 'api_key' => '', 'protocol' => '', 'connection_type' => 'remote', 'local_blog_id' => 0 ];
        }

        if ( '' !== $url && 'local' !== $site->connection_type ) {
            $site->site_url = $url;
        }

        if ( '' !== $key ) {
            $site->api_key = $key;
        }

        if ( '' === (string) $site->site_url ) {
            wp_send_json_error( [ 'message' => __( 'Enter a valid URL starting with http:// or https://.', 'pluginrx-control-center' ) ] );
        }

        $result = Client::call( $site, 'ping' );

        if ( is_wp_error( $result ) ) {
            $message = Client::diagnose( $result );
            $details = $site_id ? $message : $site->site_url . ': ' . $message;

            Database::log( $site_id, 'check_connection', false, 'host', $details );

            if ( $site_id ) {
                Database::update_site( $site_id, [ 'status' => 'error', 'last_error' => $message ] );
            }

            wp_send_json_error( [ 'message' => $message, 'logs' => Helpers::page_url( 'logs', [ 'site' => $site_id, 'status' => 'error' ] ) ] );
        }

        $meta = (array) ( $result[ '_meta' ] ?? [] );
        $protocol = Client::is_local( $site ) ? 'local' : (string) ( $site->protocol ?? '' );

        $labels = [
            'v2'    => __( 'Connected (signed)', 'pluginrx-control-center' ),
            'v1'    => __( 'Connected (legacy)', 'pluginrx-control-center' ),
            'local' => __( 'Connected (local)', 'pluginrx-control-center' ),
        ];

        if ( $site_id ) {
            Database::update_site( $site_id, [ 'status' => 'ok', 'last_error' => '', 'agent_version' => sanitize_text_field( (string) ( $meta[ 'version' ] ?? '' ) ) ] );
            Database::log( $site_id, 'check_connection', true, 'host', $labels[ $protocol ] ?? __( 'Connected', 'pluginrx-control-center' ) );
        }

        wp_send_json_success( [
            'message'  => $labels[ $protocol ] ?? __( 'Connected', 'pluginrx-control-center' ),
            'protocol' => $protocol,
            'name'     => sanitize_text_field( (string) ( $meta[ 'site_name' ] ?? '' ) ),
        ] );
    } // End ajax_check_connection()

} // End class Sites


Sites::instance();
