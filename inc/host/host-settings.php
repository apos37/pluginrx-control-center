<?php
/**
 * HOST SETTINGS
 *
 * The Host tab of the Settings page: widget layout, dashboard, versions, connection and link fields,
 * plus the panels shared by the whole plugin.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class HostSettings {

    /**
     * @var HostSettings|null Singleton instance
     */
    private static ?HostSettings $instance = null;


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
    } // End __construct()


    /**
     * Enqueue assets.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! SettingsUi::is_tab( 'host' ) ) {
            return;
        }

        wp_enqueue_style( 'prxctrl-dashboard', Bootstrap::url( 'inc/css/dashboard.css' ), [ 'prxctrl-theme' ], Bootstrap::script_version() );

        SettingsUi::enqueue( 'host', [
            'i18n' => [
                'saved'          => __( 'Settings saved.', 'pluginrx-control-center' ),
                'savedReload'    => __( 'Settings saved. Reloading...', 'pluginrx-control-center' ),
                'importOk'       => __( 'Settings uploaded. Reloading...', 'pluginrx-control-center' ),
                'importError'    => __( 'That file is not a valid settings export.', 'pluginrx-control-center' ),
                'confirmImport'  => __( 'Uploading will overwrite your current settings. Continue?', 'pluginrx-control-center' ),
                'confirmReset'   => __( 'Reset every setting on this page to its default?', 'pluginrx-control-center' ),
                'resetOk'        => __( 'Settings reset. Reloading...', 'pluginrx-control-center' ),
                'labelRequired'  => __( 'Each link field needs a label.', 'pluginrx-control-center' ),
                'slugDuplicate'  => __( 'Each link field needs a different slug.', 'pluginrx-control-center' ),
                'confirmRemoveLink' => __( 'Remove this link field? Links saved for it on each site are kept until you save a site again.', 'pluginrx-control-center' ),
            ],
        ], [ 'jquery-ui-sortable' ] );
    } // End enqueue()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        $latest = Helpers::latest_versions();
        ?>
        <div class="wrap prxctrl-wrap prxctrl-settings" id="prxctrl-settings-form">
            <?php self::render_widget_manager(); ?>

            <div class="prxctrl-settings-grid">
                <?php
                SettingsUi::open_box( __( 'Dashboard', 'pluginrx-control-center' ) );
                SettingsUi::select( 'sorting', __( 'Sorting', 'pluginrx-control-center' ), [
                    'alpha'  => __( 'Alphabetically', 'pluginrx-control-center' ),
                    'custom' => __( 'Custom (drag and drop on the Sites page)', 'pluginrx-control-center' ),
                ], __( 'How sites and groups are ordered on the Dashboard and Sites pages.', 'pluginrx-control-center' ) );
                SettingsUi::checkbox( 'jump_menu', __( 'Include a "Jump to..." Menu', 'pluginrx-control-center' ), __( 'Adds a dropdown in the dashboard subheader that scrolls straight to a site. Handy when you manage a lot of sites.', 'pluginrx-control-center' ) );
                SettingsUi::checkbox( 'screenshots', __( 'Show Site Screenshots', 'pluginrx-control-center' ), __( 'Screenshots are taken by the WordPress.com mShots service, which receives your sites\' public URLs. A fresh screenshot is requested each time a site is checked, so you can see that it still loads after updates.', 'pluginrx-control-center' ) );
                SettingsUi::number( 'concurrency', __( 'Sites to Check at Once', 'pluginrx-control-center' ), __( 'Check All runs one site at a time by default. Checking more at once is faster but puts more load on this server.', 'pluginrx-control-center' ) );
                SettingsUi::number( 'ssl_warning_days', __( 'SSL Expiry Warning (Days)', 'pluginrx-control-center' ), __( 'Warn when a site\'s SSL certificate expires within this many days.', 'pluginrx-control-center' ) );
                SettingsUi::number( 'admin_inactive_days', __( 'Inactive Admin Warning (Days)', 'pluginrx-control-center' ), __( 'Warn when an administrator has not been active for this many days. Activity comes from Developer Debug Tools\' online tracking when that plugin is active, otherwise from the admin\'s latest login session. Admins with no known activity are not flagged. Enter 0 to turn this off.', 'pluginrx-control-center' ) );
                SettingsUi::checkbox( 'warn_admin_email', __( 'Warn About Mismatching Admin Emails', 'pluginrx-control-center' ), __( 'Warn when the admin email\'s domain does not match the site\'s domain. You can ignore this warning on individual sites from the dashboard.', 'pluginrx-control-center' ) );
                SettingsUi::close_box();

                SettingsUi::open_box( __( 'Versions', 'pluginrx-control-center' ) );
                SettingsUi::select( 'wp_warning', __( 'WordPress Version Warning', 'pluginrx-control-center' ), Helpers::version_levels( '' !== $latest[ 'wordpress' ] ? $latest[ 'wordpress' ] : get_bloginfo( 'version' ) ), __( 'Warn when a site runs a WordPress version older than this.', 'pluginrx-control-center' ) );
                SettingsUi::select( 'php_warning', __( 'PHP Version Warning', 'pluginrx-control-center' ), Helpers::version_levels( '' !== $latest[ 'php' ] ? $latest[ 'php' ] : PHP_VERSION ), __( 'Warn when a site runs a PHP version older than this.', 'pluginrx-control-center' ) );
                SettingsUi::close_box();

                SettingsUi::open_box( __( 'Connection', 'pluginrx-control-center' ) );
                SettingsUi::number( 'timeout', __( 'Timeout (Seconds)', 'pluginrx-control-center' ), __( 'How long to wait for a site to respond. Updates can take a while on slower sites. Your PHP max_execution_time may also need to be raised.', 'pluginrx-control-center' ) );
                ?>
                <div class="prxctrl-field">
                    <label><?php esc_html_e( 'This Control Center\'s Domain', 'pluginrx-control-center' ); ?> <?php Helpers::tooltip( __( 'Add this exact domain to Control Center Domains in the Agent settings on each site you manage.', 'pluginrx-control-center' ) ); ?></label>
                    <code class="prxctrl-copy" tabindex="0"><?php echo esc_html( Client::host_name() ); ?></code>
                </div>
                <?php
                SettingsUi::close_box();

                SettingsUi::render_date_box();

                SettingsUi::open_box( __( 'Link Fields', 'pluginrx-control-center' ), 'prxctrl-link-fields-box' );
                self::render_link_fields();
                SettingsUi::close_box();

                SettingsUi::render_access_box();

                SettingsUi::open_box( __( 'Logs', 'pluginrx-control-center' ) );
                SettingsUi::render_log_fields();
                SettingsUi::close_box();

                SettingsUi::render_advanced_box();
                ?>
            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * Render the widget manager: a sample site to arrange, and a tray for disabled widgets.
     *
     * @return void
     */
    private static function render_widget_manager() : void {
        $sample = SiteCard::sample_site();
        $states = SiteCard::widget_states( $sample );
        $layout = Registry::widget_layout();
        ?>
        <section class="prxctrl-box prxctrl-widget-manager" id="prxctrl-widget-manager">
            <div class="prxctrl-box-header"><h2><?php esc_html_e( 'Widget Manager', 'pluginrx-control-center' ); ?></h2></div>
            <div class="prxctrl-box-body">
                <p class="prxctrl-section-desc"><?php esc_html_e( 'Drag widgets to arrange them the way every site appears on the dashboard. Drag a widget to Disabled Widgets to stop showing it. Widgets added by other plugins start in Disabled Widgets.', 'pluginrx-control-center' ); ?></p>
                <div class="prxctrl-preview">
                    <?php SiteCard::render( $sample, SiteCard::PREVIEW ); ?>
                </div>
                <h3 class="prxctrl-tray-title"><?php esc_html_e( 'Disabled Widgets', 'pluginrx-control-center' ); ?></h3>
                <div class="prxctrl-widgets prxctrl-widget-tray" id="prxctrl-widget-tray">
                    <?php
                    foreach ( $layout[ 'disabled' ] as $key ) {
                        if ( isset( $states[ $key ] ) ) {
                            SiteCard::render_widget( $states[ $key ], $sample, SiteCard::PREVIEW );
                        }
                    }
                    ?>
                    <p class="prxctrl-tray-empty prxctrl-muted"><?php esc_html_e( 'Drop widgets here to hide them.', 'pluginrx-control-center' ); ?></p>
                </div>
                <input type="hidden" name="widget_layout" data-json="1" id="prxctrl-widget-layout" value="<?php echo esc_attr( (string) wp_json_encode( $layout ) ); ?>">
            </div>
        </section>
        <?php
    } // End render_widget_manager()


    /**
     * Render the link fields repeater.
     *
     * @return void
     */
    private static function render_link_fields() : void {
        $fields = (array) Options::get( 'link_fields' );
        ?>
        <p class="prxctrl-section-desc"><?php esc_html_e( 'Add your own links for each site, such as hosting, repository or analytics. You fill them in when adding or editing a site, and they show as quick links on hover. Slugs cannot be changed after saving.', 'pluginrx-control-center' ); ?></p>
        <div class="prxctrl-link-fields" id="prxctrl-link-fields">
            <?php foreach ( $fields as $field ) : ?>
                <?php self::render_link_field_row( (string) $field[ 'label' ], (string) $field[ 'slug' ], true ); ?>
            <?php endforeach; ?>
        </div>
        <template id="prxctrl-link-field-template"><?php self::render_link_field_row( '', '', false ); ?></template>
        <button type="button" class="prxctrl-button prxctrl-button-secondary" id="prxctrl-add-link-field"><?php esc_html_e( 'Add New Link Field', 'pluginrx-control-center' ); ?> <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button>
        <input type="hidden" name="link_fields" data-json="1" id="prxctrl-link-fields-value" value="<?php echo esc_attr( (string) wp_json_encode( array_values( $fields ) ) ); ?>">
        <?php
    } // End render_link_fields()


    /**
     * Render one link field row.
     *
     * @param string $label
     * @param string $slug
     * @param bool   $saved
     * @return void
     */
    private static function render_link_field_row( string $label, string $slug, bool $saved ) : void {
        ?>
        <div class="prxctrl-link-field" data-saved="<?php echo $saved ? '1' : '0'; ?>">
            <span class="dashicons dashicons-menu prxctrl-link-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pluginrx-control-center' ); ?>" aria-hidden="true"></span>
            <input type="text" class="prxctrl-link-label" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Label, e.g. Hosting', 'pluginrx-control-center' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'pluginrx-control-center' ); ?>">
            <input type="text" class="prxctrl-link-slug" value="<?php echo esc_attr( $slug ); ?>" placeholder="<?php esc_attr_e( 'slug', 'pluginrx-control-center' ); ?>" aria-label="<?php esc_attr_e( 'Slug', 'pluginrx-control-center' ); ?>"<?php echo $saved ? ' readonly' : ''; ?>>
            <button type="button" class="prxctrl-link-remove" aria-label="<?php esc_attr_e( 'Remove', 'pluginrx-control-center' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
        </div>
        <?php
    } // End render_link_field_row()

} // End class HostSettings


HostSettings::instance();
