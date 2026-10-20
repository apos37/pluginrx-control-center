<?php
/**
 * LOGS LIST TABLE
 *
 * The table on the Logs page, with status views, filters, per-page choice and bulk delete.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


class LogsListTable extends \WP_List_Table {

    /**
     * @var array Site names by ID
     */
    private array $site_names = [];


    /**
     * @var array Current filters
     */
    private array $filters = [];


    /**
     * @var array Counts by status
     */
    private array $counts = [];


    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'log',
            'plural'   => 'logs',
            'ajax'     => false,
        ] );

        foreach ( Database::get_sites() as $site ) {
            $this->site_names[ $site->id ] = '' !== $site->site_name ? $site->site_name : Helpers::host( (string) $site->site_url );
        }
    } // End __construct()


    /**
     * Read a filter from the query string.
     *
     * @param string $key
     * @return string
     */
    private static function query_arg( string $key ) : string {
        return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
    } // End query_arg()


    /**
     * Get the per-page value, remembering the user's choice.
     *
     * @return int
     */
    public static function per_page() : int {
        $requested = absint( self::query_arg( 'per_page' ) );
        $user_id = get_current_user_id();

        if ( in_array( $requested, Logs::PER_PAGE, true ) ) {
            update_user_meta( $user_id, 'prxctrl_logs_per_page', $requested );
            return $requested;
        }

        $saved = absint( get_user_meta( $user_id, 'prxctrl_logs_per_page', true ) );

        return in_array( $saved, Logs::PER_PAGE, true ) ? $saved : Logs::PER_PAGE[ 0 ];
    } // End per_page()


    /**
     * Columns.
     *
     * @return array
     */
    public function get_columns() : array {
        return [
            'cb'         => '<input type="checkbox" />',
            'created_at' => __( 'Date', 'pluginrx-control-center' ),
            'status'     => __( 'Status', 'pluginrx-control-center' ),
            'site_id'    => __( 'Site', 'pluginrx-control-center' ),
            'action'     => __( 'Action', 'pluginrx-control-center' ),
            'context'    => __( 'Context', 'pluginrx-control-center' ),
            'details'    => __( 'Details', 'pluginrx-control-center' ),
            'user_id'    => __( 'User', 'pluginrx-control-center' ),
        ];
    } // End get_columns()


    /**
     * Sortable columns.
     *
     * @return array
     */
    protected function get_sortable_columns() : array {
        return [
            'created_at' => [ 'created_at', true ],
            'status'     => [ 'status', false ],
            'site_id'    => [ 'site_id', false ],
            'action'     => [ 'action', false ],
            'context'    => [ 'context', false ],
        ];
    } // End get_sortable_columns()


    /**
     * Bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions() : array {
        return Access::can_manage() ? [ 'delete' => __( 'Delete', 'pluginrx-control-center' ) ] : [];
    } // End get_bulk_actions()


    /**
     * Load the items.
     *
     * @return void
     */
    public function prepare_items() : void {
        $this->filters = [
            'site_id' => self::query_arg( 'site' ),
            'action'  => sanitize_key( self::query_arg( 'log_action' ) ),
            'context' => sanitize_key( self::query_arg( 'context' ) ),
            'status'  => in_array( self::query_arg( 'status' ), [ 'success', 'error' ], true ) ? self::query_arg( 'status' ) : '',
            'search'  => self::query_arg( 's' ),
        ];

        if ( '' !== $this->filters[ 'site_id' ] ) {
            $this->filters[ 'site_id' ] = (string) absint( $this->filters[ 'site_id' ] );
        }

        $per_page = self::per_page();
        $result = Database::get_logs( $this->filters, sanitize_key( self::query_arg( 'orderby' ) ), self::query_arg( 'order' ), $per_page, $this->get_pagenum() );

        $this->items = $result[ 'items' ];
        $this->counts = Database::count_logs_by_status( $this->filters );
        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'created_at' ];

        $this->set_pagination_args( [
            'total_items' => $result[ 'total' ],
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $result[ 'total' ] / $per_page ),
        ] );
    } // End prepare_items()


    /**
     * Status views with counts.
     *
     * @return array
     */
    protected function get_views() : array {
        $current = $this->filters[ 'status' ];
        $base = remove_query_arg( [ 'status', 'paged' ] );

        $views = [
            ''        => [ __( 'All', 'pluginrx-control-center' ), $this->counts[ 'all' ] ],
            'success' => [ __( 'Success', 'pluginrx-control-center' ), $this->counts[ 'success' ] ],
            'error'   => [ __( 'Errors', 'pluginrx-control-center' ), $this->counts[ 'error' ] ],
        ];

        $links = [];

        foreach ( $views as $status => $view ) {
            $url = '' === $status ? $base : add_query_arg( 'status', $status, $base );
            $links[ '' === $status ? 'all' : $status ] = sprintf(
                '<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
                esc_url( $url ),
                $current === $status ? ' class="current" aria-current="page"' : '',
                esc_html( $view[ 0 ] ),
                esc_html( number_format_i18n( $view[ 1 ] ) )
            );
        }

        return $links;
    } // End get_views()


    /**
     * Render a filter select.
     *
     * @param string $name
     * @param string $label
     * @param array  $choices
     * @param string $current
     * @return void
     */
    private function filter_select( string $name, string $label, array $choices, string $current ) : void {
        ?>
        <label class="screen-reader-text" for="prxctrl-filter-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
        <select name="<?php echo esc_attr( $name ); ?>" id="prxctrl-filter-<?php echo esc_attr( $name ); ?>">
            <?php foreach ( $choices as $value => $choice ) : ?>
                <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $current, (string) $value ); ?>><?php echo esc_html( $choice ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    } // End filter_select()


    /**
     * Per page and filters next to Bulk Actions.
     *
     * @param string $which
     * @return void
     */
    protected function extra_tablenav( $which ) : void {
        if ( 'top' !== $which ) {
            return;
        }

        $per_page = [];

        foreach ( Logs::PER_PAGE as $number ) {
            /* translators: %d: number of entries per page */
            $per_page[ $number ] = sprintf( __( '%d per page', 'pluginrx-control-center' ), $number );
        }

        $sites = [ '' => __( 'All sites', 'pluginrx-control-center' ), '0' => __( 'This site', 'pluginrx-control-center' ) ] + $this->site_names;
        $actions = [ '' => __( 'All actions', 'pluginrx-control-center' ) ];

        foreach ( Database::log_values( 'action' ) as $action ) {
            $actions[ $action ] = Registry::action_label( $action );
        }

        $contexts = [ '' => __( 'All contexts', 'pluginrx-control-center' ) ];

        foreach ( Database::log_values( 'context' ) as $context ) {
            $contexts[ $context ] = self::context_label( $context );
        }

        $statuses = [
            ''        => __( 'All results', 'pluginrx-control-center' ),
            'success' => __( 'Success only', 'pluginrx-control-center' ),
            'error'   => __( 'Errors only', 'pluginrx-control-center' ),
        ];
        ?>
        <div class="alignleft actions prxctrl-log-filters">
            <?php
            $this->filter_select( 'per_page', __( 'Entries per page', 'pluginrx-control-center' ), $per_page, (string) self::per_page() );
            $this->filter_select( 'site', __( 'Filter by site', 'pluginrx-control-center' ), $sites, (string) $this->filters[ 'site_id' ] );
            $this->filter_select( 'log_action', __( 'Filter by action', 'pluginrx-control-center' ), $actions, $this->filters[ 'action' ] );
            $this->filter_select( 'context', __( 'Filter by context', 'pluginrx-control-center' ), $contexts, $this->filters[ 'context' ] );
            $this->filter_select( 'status', __( 'Filter by result', 'pluginrx-control-center' ), $statuses, $this->filters[ 'status' ] );
            submit_button( __( 'Filter', 'pluginrx-control-center' ), '', 'filter_action', false );
            ?>
        </div>
        <?php
    } // End extra_tablenav()


    /**
     * Get a readable label for a context.
     *
     * @param string $context
     * @return string
     */
    public static function context_label( string $context ) : string {
        $labels = [
            'host'                => __( 'Control Center', 'pluginrx-control-center' ),
            'agent'               => __( 'Agent', 'pluginrx-control-center' ),
            'upgrade'             => __( 'Upgrade', 'pluginrx-control-center' ),
            'sites'               => __( 'Sites', 'pluginrx-control-center' ),
            'ajax_request_error'  => __( 'Check (v1)', 'pluginrx-control-center' ),
            'ajax_request_success'=> __( 'Check (v1)', 'pluginrx-control-center' ),
            'ajax_action_error'   => __( 'Action (v1)', 'pluginrx-control-center' ),
            'ajax_action_success' => __( 'Action (v1)', 'pluginrx-control-center' ),
        ];

        return $labels[ $context ] ?? Helpers::humanize( $context );
    } // End context_label()


    /**
     * Checkbox column.
     *
     * @param object $item
     * @return string
     */
    protected function column_cb( $item ) : string {
        return '<input type="checkbox" name="log[]" value="' . esc_attr( (string) $item->id ) . '" />';
    } // End column_cb()


    /**
     * Default column output.
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    protected function column_default( $item, $column_name ) : string {
        switch ( $column_name ) {
            case 'created_at':
                return esc_html( Helpers::format_date( (string) $item->created_at ) );

            case 'status':
                $success = 'success' === $item->status;
                return '<span class="prxctrl-status-pill ' . ( $success ? 'is-ok' : 'is-error' ) . '"><span class="dashicons ' . ( $success ? 'dashicons-yes-alt' : 'dashicons-dismiss' ) . '" aria-hidden="true"></span> ' . esc_html( $success ? __( 'Success', 'pluginrx-control-center' ) : __( 'Error', 'pluginrx-control-center' ) ) . '</span>';

            case 'site_id':
                $site_id = (int) $item->site_id;
                $name = 0 === $site_id ? __( 'This site', 'pluginrx-control-center' ) : ( $this->site_names[ $site_id ] ?? __( '(removed site)', 'pluginrx-control-center' ) );
                return '<a href="' . esc_url( add_query_arg( 'site', $site_id, remove_query_arg( [ 'paged' ] ) ) ) . '">' . esc_html( $name ) . '</a>';

            case 'action':
                return esc_html( Registry::action_label( (string) $item->action ) );

            case 'context':
                return esc_html( self::context_label( (string) $item->context ) );

            case 'details':
                return '<div class="prxctrl-log-details">' . esc_html( (string) $item->details ) . '</div>';

            case 'user_id':
                $user = $item->user_id ? get_userdata( (int) $item->user_id ) : false;
                $name = $user ? $user->display_name : ( $item->user_id ? __( '(deleted user)', 'pluginrx-control-center' ) : __( 'System', 'pluginrx-control-center' ) );

                if ( ! $user && ! empty( $item->requester ) ) {
                    $name = (string) $item->requester;
                }

                $ip = '' !== (string) $item->ip_address ? '<br><span class="prxctrl-muted">' . esc_html( (string) $item->ip_address ) . '</span>' : '';
                return esc_html( $name ) . $ip;
        }

        return '';
    } // End column_default()


    /**
     * Row classes.
     *
     * @param object $item
     * @return void
     */
    public function single_row( $item ) : void {
        echo '<tr class="' . esc_attr( 'error' === $item->status ? 'prxctrl-log-error' : 'prxctrl-log-success' ) . '">';
        $this->single_row_columns( $item );
        echo '</tr>';
    } // End single_row()


    /**
     * Message when there are no logs.
     *
     * @return void
     */
    public function no_items() : void {
        esc_html_e( 'No log entries found.', 'pluginrx-control-center' );
    } // End no_items()

} // End class LogsListTable
