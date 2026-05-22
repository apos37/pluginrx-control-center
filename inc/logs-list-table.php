<?php
/**
 * Logs List Table
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Logs_List_Table extends \WP_List_Table {

    private $table_name;
    private $sites_cache = [];


    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        parent::__construct( [
            'singular' => 'log',
            'plural'   => 'logs',
            'ajax'     => false,
        ] );
        $this->table_name = $wpdb->prefix . 'prxctrl_actions';
    } // End __construct()


    /**
     * Define columns
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'created_at' => __( 'Date', 'pluginrx-control-center' ),
            'site_id'    => __( 'Site', 'pluginrx-control-center' ),
            'action'     => __( 'Action', 'pluginrx-control-center' ),
            'context'    => __( 'Context', 'pluginrx-control-center' ),
            'details'    => __( 'Details', 'pluginrx-control-center' ),
            'user_id'    => __( 'User', 'pluginrx-control-center' ),
        ];
    } // End get_columns()


    /**
     * Checkbox column
     *
     * @param object $item
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="log[]" value="%s" />', $item->id
        );
    } // End column_cb()


    /**
     * Define sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'created_at' => [ 'created_at', false ],
            'user_id'    => [ 'user_id', false ],
            'site_id'    => [ 'site_id', false ],
            'action'     => [ 'action', false ],
        ];
    } // End get_sortable_columns()

    
    /**
     * Define bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'delete' => 'Delete'
        ];
    } // End get_bulk_actions()


    /**
     * Process bulk actions
     *
     * @return void
     */
    public function process_bulk_action() {
        global $wpdb;

        if ( 'delete' === $this->current_action() && ! empty( $_POST[ 'log' ] ) ) {
            $ids = array_map( 'absint', $_POST[ 'log' ] );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
                    ...$ids
                )
            );
        }
    } // End process_bulk_action()


    /**
     * Prepare items
     *
     * @return void
     */
    public function prepare_items() {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $this->process_bulk_action();

        $where = [];
        $params = [];

        if ( ! empty( $_GET[ 'filter_site' ] ) ) {
            $where[] = 'site_id = %d';
            $params[] = intval( $_GET[ 'filter_site' ] );
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $orderby = 'id';
        $order = 'DESC';
        if ( ! empty( $_GET[ 'orderby' ] ) && ! empty( $_GET[ 'order' ] ) ) {
            $orderby = sanitize_sql_orderby( $_GET[ 'orderby' ] );
            $order = strtoupper( $_GET[ 'order' ] ) === 'ASC' ? 'ASC' : 'DESC';
        }

        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} $where_sql" );

        $offset = ( $current_page - 1 ) * $per_page;
        $query = "SELECT * FROM {$this->table_name} $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $items = $wpdb->get_results( $wpdb->prepare( $query, ...array_merge( $params, [ $per_page, $offset ] ) ) );

        $this->items = $items;

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );
    } // End prepare_items()


    
    /**
     * Created at column
     *
     * @param object $item
     * @return string
     */
    public function column_created_at( $item ) {
        $date = $item->created_at ?? '';
        if ( $date && is_plugin_active( 'dev-debug-tools/dev-debug-tools.php' ) ) {
            $date = \Apos37\DevDebugTools\Helpers::convert_date_format( $date );
        }
        return esc_html( $date );
    } // End column_created_at()


    /**
     * Site ID column
     *
     * @param object $item
     * @return string
     */
    public function column_site_id( $item ) {
        global $wpdb;

        $site_id = intval( $item->site_id );

        if ( ! $site_id ) {
            return '';
        }

        // Check cache first
        if ( ! isset( $this->sites_cache[ $site_id ] ) ) {
            $site = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT site_name, site_url FROM {$wpdb->prefix}prxctrl_sites WHERE id = %d",
                    $site_id
                )
            );

            $this->sites_cache[ $site_id ] = $site;
        } else {
            $site = $this->sites_cache[ $site_id ];
        }

        if ( $site ) {
            return esc_html( $site->site_name ) . '<br><small>' . esc_url( $site->site_url ) . '</small>';
        }

        return '';
    } // End column_site_id()


    /**
     * User ID column
     *
     * @param object $item
     * @return string
     */
    public function column_user_id( $item ) {
        $display = '';
        if ( $item->user_id ) {
            $user = get_userdata( intval( $item->user_id ) );
            if ( $user ) {
                $display = esc_html( $user->display_name );
            }
        }

        if ( ! empty( $item->ip_address ) ) {
            $display .= '<br><small>' . esc_html( $item->ip_address ) . '</small>';
        }

        return $display;
    } // End column_user_id()


    /**
     * Default column
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    public function column_default( $item, $column_name ) {
        return isset( $item->$column_name ) ? wp_kses_post( $item->$column_name ) : '';
    } // End column_default()

}
