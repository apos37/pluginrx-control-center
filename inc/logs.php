<?php 
/**
 * Logs Page
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Logs {

    /**
     * @var Logs|null Singleton instance
     */
    private static ?Logs $instance = null;


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
     * The page
     *
     * @return void
     */
    public function render_page() {
        $logs_table = new Logs_List_Table();
        $logs_table->prepare_items();

        $filter_site    = isset( $_GET[ 'filter_site' ] ) ? intval( wp_unslash( $_GET[ 'filter_site' ] ) ) : '';
        $sites = Database::get_sites_for_logs();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="prxctrl-logs" />
                <select name="filter_site">
                    <option value=""><?php echo esc_html__( 'All Sites', 'pluginrx-control-center' ); ?></option>

                    <?php foreach ( $sites as $site ) : ?>
                        <option value="<?php echo esc_attr( $site->id ); ?>" <?php selected( $filter_site, $site->id ); ?>>
                            <?php echo esc_html( $site->site_name ); ?> (<?php echo esc_html( $site->site_url ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="button"><?php echo esc_html__( 'Filter', 'pluginrx-control-center' ); ?></button>
            </form>

            <form method="post">
                <?php $logs_table->display(); ?>
            </form>
        </div>
        <?php
    } // End render_page()


    /**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        $slug = 'prxctrl-logs';
        if ( empty( $_GET[ 'page' ] ) || sanitize_text_field( wp_unslash( $_GET[ 'page' ] ) ) !== $slug ) {
            return;
        }

		// Register and enqueue your CSS
        $text_domain = Bootstrap::textdomain();
        $css_path = Bootstrap::url( 'inc/css/' );
        $script_version = Bootstrap::script_version();

        // CSS
        wp_enqueue_style( $text_domain . '-shared-styles', $css_path . 'shared.css', [], $script_version );
        wp_enqueue_style( $text_domain . '-logs', $css_path . 'logs.css', [], $script_version );
    } // End enqueue_scripts()


}


Logs::instance();