<?php
/**
 * DATABASE
 *
 * Custom tables for managed sites, groups, cached site details and logs. All reads used by the
 * dashboard are batched, so the page needs the same handful of queries no matter how many sites
 * you manage.
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;


class Database {

    /**
     * Schema version.
     */
    public const VERSION = '2.0.1';


    /**
     * Site columns that can be written, with their formats.
     */
    public const SITE_COLUMNS = [
        'site_url'          => '%s',
        'site_name'         => '%s',
        'api_key'           => '%s',
        'group_id'          => '%d',
        'sort_order'        => '%d',
        'connection_type'   => '%s',
        'local_blog_id'     => '%d',
        'protocol'          => '%s',
        'agent_version'     => '%s',
        'permissions'       => '%s',
        'status'            => '%s',
        'admin_email'       => '%s',
        'server_ip'         => '%s',
        'abspath'           => '%s',
        'is_multisite'      => '%d',
        'blog_id'           => '%d',
        'wordpress_version' => '%s',
        'core_update'       => '%s',
        'php_version'       => '%s',
        'wp_debug'          => '%d',
        'admin_path'        => '%s',
        'http_status'       => '%d',
        'response_ms'       => '%d',
        'ssl_expires'       => '%s',
        'ssl_checked'       => '%s',
        'screenshot_ver'    => '%d',
        'login_url'         => '%s',
        'links'             => '%s',
        'notes'             => '%s',
        'ignored_warnings'  => '%s',
        'last_checked'      => '%s',
        'last_error'        => '%s',
        'other'             => '%s',
    ];


    /**
     * Table keys.
     */
    public const TABLES = [ 'sites', 'groups', 'admin_users', 'themes', 'plugins', 'integrations', 'actions' ];


    /**
     * Get the table prefix for the current context.
     *
     * @param bool|null $network Force the network tables on or off.
     * @return string
     */
    public static function prefix( ?bool $network = null ) : string {
        global $wpdb;

        $network = $network ?? Mode::is_network_context();

        return $network ? $wpdb->base_prefix . 'prxctrl_net_' : $wpdb->prefix . 'prxctrl_';
    } // End prefix()


    /**
     * Get a full table name.
     *
     * @param string    $name
     * @param bool|null $network Force the network tables on or off.
     * @return string
     */
    public static function table( string $name, ?bool $network = null ) : string {
        return self::prefix( $network ) . $name;
    } // End table()


    /**
     * Get the names of all tables in the current context.
     *
     * @return array
     */
    public static function table_names() : array {
        return array_map( [ self::class, 'table' ], self::TABLES );
    } // End table_names()


    /**
     * Get the stored schema version for the current context.
     *
     * @return string
     */
    public static function installed_version() : string {
        return (string) ( Mode::is_network_context() ? get_site_option( 'prxctrl_net_db_version', '' ) : get_option( 'prxctrl_db_version', '' ) );
    } // End installed_version()


    /**
     * Create the tables if the schema is out of date.
     *
     * @return void
     */
    public static function maybe_install() : void {
        if ( version_compare( self::installed_version(), self::VERSION, '>=' ) ) {
            return;
        }

        self::install();
    } // End maybe_install()


    /**
     * Create or update all tables with dbDelta.
     *
     * @return void
     */
    public static function install() : void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $p = self::prefix();

        $schemas = [
            "CREATE TABLE {$p}sites (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_url varchar(255) NOT NULL,
                site_name varchar(255) DEFAULT NULL,
                api_key char(64) DEFAULT NULL,
                group_id bigint(20) unsigned NOT NULL DEFAULT 0,
                sort_order int(11) NOT NULL DEFAULT 0,
                connection_type varchar(10) NOT NULL DEFAULT 'remote',
                local_blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
                protocol varchar(5) NOT NULL DEFAULT '',
                agent_version varchar(20) DEFAULT NULL,
                permissions text DEFAULT NULL,
                status varchar(10) NOT NULL DEFAULT '',
                admin_email varchar(255) DEFAULT NULL,
                server_ip varchar(45) DEFAULT NULL,
                abspath text DEFAULT NULL,
                is_multisite tinyint(1) DEFAULT 0,
                blog_id bigint(20) unsigned DEFAULT NULL,
                wordpress_version varchar(20) DEFAULT NULL,
                core_update varchar(20) DEFAULT NULL,
                php_version varchar(20) DEFAULT NULL,
                wp_debug tinyint(1) DEFAULT 0,
                admin_path text DEFAULT NULL,
                http_status smallint(5) unsigned NOT NULL DEFAULT 0,
                response_ms int(11) unsigned NOT NULL DEFAULT 0,
                ssl_expires datetime DEFAULT NULL,
                ssl_checked datetime DEFAULT NULL,
                screenshot_ver int(11) unsigned NOT NULL DEFAULT 0,
                login_url text DEFAULT NULL,
                links text DEFAULT NULL,
                notes text DEFAULT NULL,
                ignored_warnings text DEFAULT NULL,
                last_checked datetime DEFAULT NULL,
                last_error text DEFAULT NULL,
                other longtext DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY site_identity (site_url),
                UNIQUE KEY api_key (api_key),
                KEY blog_id (blog_id),
                KEY group_id (group_id),
                KEY last_checked (last_checked)
            ) {$charset_collate};",

            "CREATE TABLE {$p}groups (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(191) NOT NULL,
                sort_order int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY  (id)
            ) {$charset_collate};",

            "CREATE TABLE {$p}admin_users (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned DEFAULT NULL,
                user_id bigint(20) unsigned NOT NULL,
                user_login varchar(60) NOT NULL,
                display_name varchar(250) DEFAULT NULL,
                user_email varchar(100) DEFAULT NULL,
                role longtext DEFAULT NULL,
                is_dev tinyint(1) DEFAULT 0,
                user_registered datetime DEFAULT NULL,
                online_status varchar(50) DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY site_user (site_id,user_id),
                KEY site_id (site_id),
                KEY user_id (user_id)
            ) {$charset_collate};",

            "CREATE TABLE {$p}themes (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL,
                slug varchar(191) NOT NULL,
                name varchar(255) DEFAULT NULL,
                author varchar(255) DEFAULT NULL,
                version varchar(50) DEFAULT NULL,
                is_active tinyint(1) DEFAULT 0,
                update_available tinyint(1) DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY site_theme (site_id,slug),
                KEY site_id (site_id)
            ) {$charset_collate};",

            "CREATE TABLE {$p}plugins (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL,
                slug varchar(191) NOT NULL,
                name varchar(255) DEFAULT NULL,
                author varchar(255) DEFAULT NULL,
                version varchar(50) DEFAULT NULL,
                is_active tinyint(1) DEFAULT 0,
                update_available tinyint(1) DEFAULT 0,
                is_required tinyint(1) DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY site_plugin (site_id,slug),
                KEY site_id (site_id),
                KEY slug (slug)
            ) {$charset_collate};",

            "CREATE TABLE {$p}integrations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL,
                meta_key varchar(191) NOT NULL,
                meta_value longtext DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY site_meta (site_id,meta_key),
                KEY site_id (site_id),
                KEY meta_key (meta_key)
            ) {$charset_collate};",

            "CREATE TABLE {$p}actions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned DEFAULT NULL,
                site_id bigint(20) unsigned DEFAULT NULL,
                action varchar(100) NOT NULL,
                context varchar(100) DEFAULT NULL,
                status varchar(10) NOT NULL DEFAULT '',
                details longtext DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                requester varchar(255) DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY site_id (site_id),
                KEY action (action),
                KEY context (context),
                KEY status (status),
                KEY created_at (created_at)
            ) {$charset_collate};",
        ];

        foreach ( $schemas as $sql ) {
            dbDelta( $sql );
        }

        if ( Mode::is_network_context() ) {
            update_site_option( 'prxctrl_net_db_version', self::VERSION );
        } else {
            update_option( 'prxctrl_db_version', self::VERSION );
        }
    } // End install()


    /**
     * Check if a table exists in the current context.
     *
     * @param string $name Table key.
     * @return bool
     */
    public static function table_exists( string $name ) : bool {
        global $wpdb;

        $table = self::table( $name );

        return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
    } // End table_exists()


    /**
     * Check if a network table exists.
     *
     * @param string $name Table key.
     * @return bool
     */
    public static function table_exists_network( string $name ) : bool {
        global $wpdb;

        $table = self::table( $name, true );

        return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
    } // End table_exists_network()


    /**
     * Drop every table in the current context.
     *
     * @return void
     */
    public static function drop_tables() : void {
        global $wpdb;

        foreach ( self::table_names() as $table ) {
            $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup.
        }
    } // End drop_tables()


    /**
     * Prepare a raw site row for use: decode JSON columns and cast numbers.
     *
     * @param object $site
     * @return object
     */
    private static function hydrate( object $site ) : object {
        $site->id = (int) $site->id;
        $site->group_id = (int) ( $site->group_id ?? 0 );
        $site->sort_order = (int) ( $site->sort_order ?? 0 );
        $site->local_blog_id = (int) ( $site->local_blog_id ?? 0 );
        $site->links = Helpers::json_array( $site->links ?? '' );
        $site->ignored = Helpers::json_array( $site->ignored_warnings ?? '' );
        $site->permissions = ( isset( $site->permissions ) && '' !== $site->permissions && null !== $site->permissions ) ? Helpers::json_array( $site->permissions ) : null;
        $site->site_name = (string) ( $site->site_name ?? '' );
        $site->has_key = ! empty( $site->api_key );

        return $site;
    } // End hydrate()


    /**
     * Check if custom sorting is on.
     *
     * @return bool
     */
    private static function is_custom_sort() : bool {
        return 'custom' === Options::get( 'sorting' );
    } // End is_custom_sort()


    /**
     * Get all managed sites, sorted by the sorting setting.
     *
     * @param bool $details Also attach plugins, themes, admin users and integration values.
     * @return array
     */
    public static function get_sites( bool $details = false ) : array {
        global $wpdb;

        if ( self::is_custom_sort() ) {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, id ASC', self::table( 'sites' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY LOWER(site_name) ASC, id ASC', self::table( 'sites' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        }

        $sites = array_map( [ self::class, 'hydrate' ], (array) $rows );

        return $details ? self::attach_details( $sites ) : $sites;
    } // End get_sites()


    /**
     * Get one site.
     *
     * @param int  $site_id
     * @param bool $details
     * @return object|null
     */
    public static function get_site( int $site_id, bool $details = false ) : ?object {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table( 'sites' ), $site_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

        if ( ! $row ) {
            return null;
        }

        $site = self::hydrate( $row );

        return $details ? self::attach_details( [ $site ] )[ 0 ] : $site;
    } // End get_site()


    /**
     * Get a site by URL.
     *
     * @param string $site_url
     * @return object|null
     */
    public static function get_site_by_url( string $site_url ) : ?object {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE site_url = %s', self::table( 'sites' ), $site_url ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

        return $row ? self::hydrate( $row ) : null;
    } // End get_site_by_url()


    /**
     * Get the number of managed sites.
     *
     * @return int
     */
    public static function count_sites() : int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table( 'sites' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
    } // End count_sites()


    /**
     * Attach plugins, themes, admin users and integration values to sites with one query per table.
     *
     * @param array $sites
     * @return array
     */
    public static function attach_details( array $sites ) : array {
        global $wpdb;

        if ( empty( $sites ) ) {
            return $sites;
        }

        $by_id = [];

        foreach ( $sites as $site ) {
            $site->plugins = [];
            $site->themes = [];
            $site->admin_users = [];
            $site->integrations = [];
            $by_id[ $site->id ] = $site;
        }

        $ids = array_keys( $by_id );

        $queries = [
            'plugins'     => 'name',
            'themes'      => 'name',
            'admin_users' => 'display_name',
        ];

        foreach ( $queries as $property => $column ) {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE site_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY LOWER(%i) ASC', array_merge( [ self::table( $property ) ], $ids, [ $column ] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

            foreach ( (array) $rows as $row ) {
                if ( isset( $by_id[ (int) $row->site_id ] ) ) {
                    $by_id[ (int) $row->site_id ]->{ $property }[] = $row;
                }
            }
        }

        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT site_id, meta_key, meta_value FROM %i WHERE site_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( [ self::table( 'integrations' ) ], $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

        foreach ( (array) $rows as $row ) {
            if ( isset( $by_id[ (int) $row->site_id ] ) ) {
                $decoded = json_decode( (string) $row->meta_value, true );
                $by_id[ (int) $row->site_id ]->integrations[ $row->meta_key ] = ( null === $decoded && 'null' !== $row->meta_value ) ? (string) $row->meta_value : $decoded;
            }
        }

        return array_values( $by_id );
    } // End attach_details()


    /**
     * Keep only the writable site columns and encode arrays as JSON.
     *
     * @param array $data
     * @return array [ data, formats ]
     */
    private static function site_data( array $data ) : array {
        $clean = [];
        $formats = [];

        foreach ( self::SITE_COLUMNS as $column => $format ) {
            if ( ! array_key_exists( $column, $data ) ) {
                continue;
            }

            $value = $data[ $column ];

            if ( is_array( $value ) ) {
                $value = wp_json_encode( $value );
            }

            $clean[ $column ] = $value;
            $formats[] = $format;
        }

        return [ $clean, $formats ];
    } // End site_data()


    /**
     * Add a site.
     *
     * @param array     $data
     * @param bool|null $network Force the network tables on or off.
     * @return int Site ID, or 0 on failure.
     */
    public static function insert_site( array $data, ?bool $network = null ) : int {
        global $wpdb;

        [ $clean, $formats ] = self::site_data( $data );

        if ( empty( $clean[ 'site_url' ] ) ) {
            return 0;
        }

        $inserted = $wpdb->insert( self::table( 'sites', $network ), $clean, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.

        if ( ! $inserted ) {
            return 0;
        }

        $site_id = (int) $wpdb->insert_id;

        do_action( 'prxctrl_site_added', $site_id, $clean );

        return $site_id;
    } // End insert_site()


    /**
     * Update a site.
     *
     * @param int   $site_id
     * @param array $data
     * @return bool
     */
    public static function update_site( int $site_id, array $data ) : bool {
        global $wpdb;

        [ $clean, $formats ] = self::site_data( $data );

        if ( $site_id <= 0 || empty( $clean ) ) {
            return false;
        }

        $updated = $wpdb->update( self::table( 'sites' ), $clean, [ 'id' => $site_id ], $formats, [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

        return false !== $updated;
    } // End update_site()


    /**
     * Delete a site and everything stored for it, except its logs.
     *
     * @param int $site_id
     * @return bool
     */
    public static function delete_site( int $site_id ) : bool {
        global $wpdb;

        foreach ( [ 'admin_users', 'plugins', 'themes', 'integrations' ] as $table ) {
            $wpdb->delete( self::table( $table ), [ 'site_id' => $site_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        }

        $deleted = $wpdb->delete( self::table( 'sites' ), [ 'id' => $site_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

        if ( $deleted ) {
            do_action( 'prxctrl_site_deleted', $site_id );
        }

        return (bool) $deleted;
    } // End delete_site()


    /**
     * Get all groups, sorted by the sorting setting.
     *
     * @return array
     */
    public static function get_groups() : array {
        global $wpdb;

        if ( self::is_custom_sort() ) {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, id ASC', self::table( 'groups' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY LOWER(name) ASC, id ASC', self::table( 'groups' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        }


        foreach ( (array) $rows as $row ) {
            $row->id = (int) $row->id;
            $row->sort_order = (int) $row->sort_order;
        }

        return (array) $rows;
    } // End get_groups()


    /**
     * Add a group.
     *
     * @param string $name
     * @param int    $sort_order
     * @return int
     */
    public static function insert_group( string $name, int $sort_order = 0 ) : int {
        global $wpdb;

        $inserted = $wpdb->insert( self::table( 'groups' ), [ 'name' => $name, 'sort_order' => $sort_order ], [ '%s', '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.

        return $inserted ? (int) $wpdb->insert_id : 0;
    } // End insert_group()


    /**
     * Update a group.
     *
     * @param int    $group_id
     * @param string $name
     * @param int    $sort_order
     * @return void
     */
    public static function update_group( int $group_id, string $name, int $sort_order ) : void {
        global $wpdb;

        $wpdb->update( self::table( 'groups' ), [ 'name' => $name, 'sort_order' => $sort_order ], [ 'id' => $group_id ], [ '%s', '%d' ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
    } // End update_group()


    /**
     * Delete a group and move its sites to "No Group".
     *
     * @param int $group_id
     * @return void
     */
    public static function delete_group( int $group_id ) : void {
        global $wpdb;

        $wpdb->update( self::table( 'sites' ), [ 'group_id' => 0 ], [ 'group_id' => $group_id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        $wpdb->delete( self::table( 'groups' ), [ 'id' => $group_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
    } // End delete_group()


    /**
     * Arrange sites under their groups. Sites without a group come first.
     *
     * @param array $sites
     * @param array $groups
     * @return array List of [ 'group' => object|null, 'sites' => array ].
     */
    public static function group_sites( array $sites, array $groups ) : array {
        $sections = [ 0 => [ 'group' => null, 'sites' => [] ] ];

        foreach ( $groups as $group ) {
            $sections[ $group->id ] = [ 'group' => $group, 'sites' => [] ];
        }

        foreach ( $sites as $site ) {
            $key = isset( $sections[ $site->group_id ] ) ? $site->group_id : 0;
            $sections[ $key ][ 'sites' ][] = $site;
        }

        return array_values( $sections );
    } // End group_sites()


    /**
     * Insert many rows in one query per 100 rows.
     *
     * @param string $table   Table key.
     * @param array  $columns Column => format.
     * @param array  $rows    Rows keyed by column.
     * @return void
     */
    private static function bulk_insert( string $table, array $columns, array $rows ) : void {
        global $wpdb;

        $names = array_keys( $columns );

        foreach ( array_chunk( $rows, 100 ) as $chunk ) {
            $values = [];
            $placeholders = [];

            foreach ( $chunk as $row ) {
                $row_placeholders = [];

                foreach ( $names as $name ) {
                    if ( null === $row[ $name ] ) {
                        $row_placeholders[] = 'NULL';
                        continue;
                    }

                    $row_placeholders[] = $columns[ $name ];
                    $values[] = $row[ $name ];
                }

                $placeholders[] = '(' . implode( ', ', $row_placeholders ) . ')';
            }

            $sql = 'INSERT INTO %i (' . implode( ', ', array_fill( 0, count( $names ), '%i' ) ) . ') VALUES ' . implode( ', ', $placeholders );

            $wpdb->query( $wpdb->prepare( $sql, array_merge( [ self::table( $table ) ], $names, $values ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table; $sql is built only from %i, %s, %d and NULL placeholders.
        }
    } // End bulk_insert()


    /**
     * Replace a site's cached rows in a details table.
     *
     * @param int    $site_id
     * @param string $table
     * @param array  $columns
     * @param array  $rows
     * @return void
     */
    private static function replace_rows( int $site_id, string $table, array $columns, array $rows ) : void {
        global $wpdb;

        $wpdb->delete( self::table( $table ), [ 'site_id' => $site_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

        if ( ! empty( $rows ) ) {
            self::bulk_insert( $table, $columns, $rows );
        }
    } // End replace_rows()


    /**
     * Turn the plugins, themes, admin users and integration values in an agent response into rows.
     *
     * @param int   $site_id
     * @param array $data Response data from the agent.
     * @return array [ 'plugins' => rows, 'themes' => rows, 'admin_users' => rows, 'integrations' => rows ]
     */
    public static function detail_rows( int $site_id, array $data ) : array {
        $rows = [ 'plugins' => [], 'themes' => [], 'admin_users' => [], 'integrations' => [] ];

        foreach ( [ 'plugins', 'themes' ] as $type ) {
            foreach ( (array) ( $data[ $type ] ?? [] ) as $item ) {
                $item = (array) $item;
                $slug = substr( sanitize_text_field( (string) ( $item[ 'slug' ] ?? '' ) ), 0, 191 );

                if ( '' === $slug ) {
                    continue;
                }

                $row = [
                    'site_id'          => $site_id,
                    'slug'             => $slug,
                    'name'             => sanitize_text_field( (string) ( $item[ 'name' ] ?? '' ) ),
                    'author'           => sanitize_text_field( (string) ( $item[ 'author' ] ?? '' ) ),
                    'version'          => sanitize_text_field( (string) ( $item[ 'version' ] ?? '' ) ),
                    'is_active'        => ! empty( $item[ 'active' ] ) ? 1 : 0,
                    'update_available' => ! empty( $item[ 'update_available' ] ) ? 1 : 0,
                ];

                if ( 'plugins' === $type ) {
                    $row[ 'is_required' ] = ! empty( $item[ 'is_required' ] ) ? 1 : 0;
                }

                $rows[ $type ][ $slug ] = $row;
            }

            $rows[ $type ] = array_values( $rows[ $type ] );
        }

        foreach ( (array) ( $data[ 'admin_users' ] ?? [] ) as $admin ) {
            $admin = (array) $admin;
            $user_id = absint( $admin[ 'user_id' ] ?? 0 );

            if ( ! $user_id ) {
                continue;
            }

            $registered = sanitize_text_field( (string) ( $admin[ 'user_registered' ] ?? '' ) );

            $rows[ 'admin_users' ][ $user_id ] = [
                'site_id'         => $site_id,
                'user_id'         => $user_id,
                'user_login'      => substr( sanitize_user( (string) ( $admin[ 'user_login' ] ?? '' ) ), 0, 60 ),
                'display_name'    => sanitize_text_field( (string) ( $admin[ 'display_name' ] ?? '' ) ),
                'user_email'      => sanitize_email( (string) ( $admin[ 'user_email' ] ?? '' ) ),
                'role'            => wp_json_encode( array_values( array_map( 'sanitize_key', (array) ( $admin[ 'role' ] ?? [] ) ) ) ),
                'is_dev'          => ! empty( $admin[ 'is_dev' ] ) ? 1 : 0,
                'user_registered' => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $registered ) ? $registered : null,
                'online_status'   => substr( sanitize_text_field( (string) ( $admin[ 'online_status' ] ?? '' ) ), 0, 50 ),
            ];
        }

        $rows[ 'admin_users' ] = array_values( $rows[ 'admin_users' ] );

        foreach ( (array) ( $data[ 'integrations' ] ?? [] ) as $key => $value ) {
            $key = substr( sanitize_key( (string) $key ), 0, 191 );

            if ( '' === $key ) {
                continue;
            }

            $rows[ 'integrations' ][] = [
                'site_id'    => $site_id,
                'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- column of this plugin's own table, not a query.
                'meta_value' => wp_json_encode( is_scalar( $value ) || null === $value ? ( is_string( $value ) ? sanitize_text_field( $value ) : $value ) : map_deep( (array) $value, 'sanitize_text_field' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- column of this plugin's own table, not a query.
            ];
        }

        return $rows;
    } // End detail_rows()


    /**
     * Save the details returned by a site check. Anything the agent stopped sharing is cleared.
     *
     * @param int   $site_id
     * @param array $data Response data from the agent.
     * @return void
     */
    public static function save_details( int $site_id, array $data ) : void {
        $rows = self::detail_rows( $site_id, $data );

        self::replace_rows( $site_id, 'plugins', [ 'site_id' => '%d', 'slug' => '%s', 'name' => '%s', 'author' => '%s', 'version' => '%s', 'is_active' => '%d', 'update_available' => '%d', 'is_required' => '%d' ], $rows[ 'plugins' ] );
        self::replace_rows( $site_id, 'themes', [ 'site_id' => '%d', 'slug' => '%s', 'name' => '%s', 'author' => '%s', 'version' => '%s', 'is_active' => '%d', 'update_available' => '%d' ], $rows[ 'themes' ] );
        self::replace_rows( $site_id, 'admin_users', [ 'site_id' => '%d', 'user_id' => '%d', 'user_login' => '%s', 'display_name' => '%s', 'user_email' => '%s', 'role' => '%s', 'is_dev' => '%d', 'user_registered' => '%s', 'online_status' => '%s' ], $rows[ 'admin_users' ] );
        self::replace_rows( $site_id, 'integrations', [ 'site_id' => '%d', 'meta_key' => '%s', 'meta_value' => '%s' ], $rows[ 'integrations' ] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- columns of this plugin's own table, not a query.
    } // End save_details()


    /**
     * Add a log entry.
     *
     * @param int    $site_id 0 for this site (agent events) or when no site applies.
     * @param string $action
     * @param bool   $success
     * @param string $context
     * @param string $details
     * @param string $requester Who asked for it, for agent events requested by a Control Center.
     * @return int Log ID.
     */
    public static function log( int $site_id, string $action, bool $success, string $context = '', string $details = '', string $requester = '' ) : int {
        global $wpdb;

        $user_id = get_current_user_id();

        $data = [
            'user_id'    => $user_id ? $user_id : null,
            'site_id'    => $site_id,
            'action'     => substr( sanitize_key( $action ), 0, 100 ),
            'context'    => substr( sanitize_key( $context ), 0, 100 ),
            'status'     => $success ? 'success' : 'error',
            'details'    => sanitize_textarea_field( (string) apply_filters( 'prxctrl_log_details', $details, $site_id, $action, $success ) ),
            'ip_address' => Helpers::ip(),
            'created_at' => Helpers::now(),
        ];
        $formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ];

        if ( '' !== $requester ) {
            $data[ 'requester' ] = substr( sanitize_text_field( $requester ), 0, 255 );
            $formats[] = '%s';
        }

        $inserted = $wpdb->insert( self::table( 'actions' ), $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.

        if ( ! $inserted ) {
            return 0;
        }

        $log_id = (int) $wpdb->insert_id;

        do_action( 'prxctrl_log_added', $log_id, $data );

        return $log_id;
    } // End log()


    /**
     * Get the values for the log WHERE clause. Every filter is always in the SQL; an empty flag
     * turns it off, so the query never changes shape.
     *
     * @param array $filters site_id, action, context, status, search
     * @return array [ site flag, site ID, action, context, status, search, LIKE pattern ]
     */
    private static function log_args( array $filters ) : array {
        global $wpdb;

        $site = isset( $filters[ 'site_id' ] ) && '' !== $filters[ 'site_id' ] ? (string) $filters[ 'site_id' ] : '';
        $search = (string) ( $filters[ 'search' ] ?? '' );

        return [
            $site,
            (int) $site,
            (string) ( $filters[ 'action' ] ?? '' ),
            (string) ( $filters[ 'context' ] ?? '' ),
            (string) ( $filters[ 'status' ] ?? '' ),
            $search,
            '%' . $wpdb->esc_like( $search ) . '%',
        ];
    } // End log_args()


    /**
     * Get log entries.
     *
     * @param array  $filters
     * @param string $orderby
     * @param string $order
     * @param int    $per_page
     * @param int    $page
     * @return array [ 'items' => array, 'total' => int ]
     */
    public static function get_logs( array $filters, string $orderby, string $order, int $per_page, int $page ) : array {
        global $wpdb;

        $table = self::table( 'actions' );
        [ $site, $site_id, $action, $context, $status, $search, $like ] = self::log_args( $filters );
        $orderby = in_array( $orderby, [ 'created_at', 'site_id', 'action', 'context', 'status', 'user_id' ], true ) ? $orderby : 'created_at';
        $offset = max( 0, ( $page - 1 ) * $per_page );

        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE ( %s = '' OR site_id = %d ) AND ( %s = '' OR action = %s ) AND ( %s = '' OR context = %s ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR details LIKE %s OR action LIKE %s OR context LIKE %s OR requester LIKE %s OR ip_address LIKE %s )", $table, $site, $site_id, $action, $action, $context, $context, $status, $status, $search, $like, $like, $like, $like, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.

        if ( 'ASC' === strtoupper( $order ) ) {
            $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ( %s = '' OR site_id = %d ) AND ( %s = '' OR action = %s ) AND ( %s = '' OR context = %s ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR details LIKE %s OR action LIKE %s OR context LIKE %s OR requester LIKE %s OR ip_address LIKE %s ) ORDER BY %i ASC, id ASC LIMIT %d OFFSET %d", $table, $site, $site_id, $action, $action, $context, $context, $status, $status, $search, $like, $like, $like, $like, $like, $orderby, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        } else {
            $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ( %s = '' OR site_id = %d ) AND ( %s = '' OR action = %s ) AND ( %s = '' OR context = %s ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR details LIKE %s OR action LIKE %s OR context LIKE %s OR requester LIKE %s OR ip_address LIKE %s ) ORDER BY %i DESC, id DESC LIMIT %d OFFSET %d", $table, $site, $site_id, $action, $action, $context, $context, $status, $status, $search, $like, $like, $like, $like, $like, $orderby, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        }

        return [ 'items' => (array) $items, 'total' => $total ];
    } // End get_logs()


    /**
     * Count logs by status for the view links, honoring the other filters.
     *
     * @param array $filters
     * @return array [ 'all' => int, 'success' => int, 'error' => int ]
     */
    public static function count_logs_by_status( array $filters ) : array {
        global $wpdb;

        $filters[ 'status' ] = '';
        [ $site, $site_id, $action, $context, $status, $search, $like ] = self::log_args( $filters );

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS total FROM %i WHERE ( %s = '' OR site_id = %d ) AND ( %s = '' OR action = %s ) AND ( %s = '' OR context = %s ) AND ( %s = '' OR status = %s ) AND ( %s = '' OR details LIKE %s OR action LIKE %s OR context LIKE %s OR requester LIKE %s OR ip_address LIKE %s ) GROUP BY status", self::table( 'actions' ), $site, $site_id, $action, $action, $context, $context, $status, $status, $search, $like, $like, $like, $like, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        $counts = [ 'all' => 0, 'success' => 0, 'error' => 0 ];

        foreach ( (array) $rows as $row ) {
            $counts[ 'all' ] += (int) $row->total;

            if ( isset( $counts[ $row->status ] ) ) {
                $counts[ $row->status ] += (int) $row->total;
            }
        }

        return $counts;
    } // End count_logs_by_status()


    /**
     * Get the distinct values of a log column, for filters.
     *
     * @param string $column action|context
     * @return array
     */
    public static function log_values( string $column ) : array {
        global $wpdb;

        if ( ! in_array( $column, [ 'action', 'context' ], true ) ) {
            return [];
        }

        return array_filter( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT %i FROM %i ORDER BY %i ASC', $column, self::table( 'actions' ), $column ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
    } // End log_values()


    /**
     * Delete log entries by ID.
     *
     * @param array $ids
     * @return int Rows deleted.
     */
    public static function delete_logs( array $ids ) : int {
        global $wpdb;

        $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( [ self::table( 'actions' ) ], $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
    } // End delete_logs()


    /**
     * Clear logs, optionally only those with a given status.
     *
     * @param string $status '' for all, or success|error.
     * @return int Rows deleted.
     */
    public static function clear_logs( string $status = '' ) : int {
        global $wpdb;

        if ( '' === $status ) {
            return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', self::table( 'actions' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
        }

        return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE status = %s', self::table( 'actions' ), $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
    } // End clear_logs()


    /**
     * Delete logs older than a number of days.
     *
     * @param int       $days
     * @param bool|null $network Force the network tables on or off.
     * @return int Rows deleted.
     */
    public static function purge_logs( int $days, ?bool $network = null ) : int {
        global $wpdb;

        if ( $days <= 0 ) {
            return 0;
        }

        $cutoff = wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ), wp_timezone() );

        return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table( 'actions', $network ), $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
    } // End purge_logs()

} // End class Database
