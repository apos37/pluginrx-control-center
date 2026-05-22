<?php
/**
 * Database-related operations
 */

namespace PluginRx\ControlCenter;

if ( ! defined( 'ABSPATH' ) ) exit;

class Database {

    /**
     *  Available tables
     *
     * @var array
     */
    public static function tables() {
        global $wpdb;

        $tables = [
            'sites'        => __( 'Sites', 'pluginrx-control-center' ),
            'admin_users'  => __( 'Admin Users', 'pluginrx-control-center' ),
            'themes'       => __( 'Themes', 'pluginrx-control-center' ),
            'plugins'      => __( 'Plugins', 'pluginrx-control-center' ),
            'actions'      => __( 'Actions', 'pluginrx-control-center' ),
            'integrations' => __( 'Integrations', 'pluginrx-control-center' ),
        ];

        $prefixed_tables = [];
        foreach ( $tables as $key => $name ) {
            $prefixed_tables[ $wpdb->prefix . 'prxctrl_' . $key ] = $name;
        }

        return $prefixed_tables;
    } // End tables()


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?Database $instance = null;


    /**
     * Get the singleton instance
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
        // self::delete_tables();
        self::maybe_create_tables();
    } // End __construct()


    /**
     * Create the custom table if it doesn't exist
     *
     * @return void
     */
    public static function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'prxctrl_';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Sites table
        $sites_table = $prefix . 'sites';
        $sql = "
            CREATE TABLE {$sites_table} (
                id BIGINT( 20 ) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_url VARCHAR( 255 ) NOT NULL,
                site_name VARCHAR( 255 ) DEFAULT NULL,
                api_key CHAR( 64 ) DEFAULT NULL,
                admin_email VARCHAR( 255 ) DEFAULT NULL,
                server_ip VARCHAR( 45 ) DEFAULT NULL,
                abspath TEXT DEFAULT NULL,
                is_multisite TINYINT( 1 ) DEFAULT 0,
                blog_id BIGINT( 20 ) UNSIGNED DEFAULT NULL,
                wordpress_version VARCHAR( 20 ) DEFAULT NULL,
                php_version VARCHAR( 20 ) DEFAULT NULL,
                wp_debug TINYINT( 1 ) DEFAULT 0,
                admin_path TEXT DEFAULT NULL,
                last_checked DATETIME DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                other LONGTEXT DEFAULT NULL,
                PRIMARY KEY ( id ),
                UNIQUE KEY site_identity ( site_url ),
                UNIQUE KEY api_key ( api_key ),
                KEY blog_id ( blog_id ),
                KEY last_checked ( last_checked )
            ) {$charset_collate};
        ";
        dbDelta( $sql );

        // Admin users table
        $admin_users_table = $prefix . 'admin_users';
        $sql = "
            CREATE TABLE {$admin_users_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id BIGINT(20) UNSIGNED DEFAULT NULL,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                user_login VARCHAR(60) NOT NULL,
                display_name VARCHAR(250) DEFAULT NULL,
                user_email VARCHAR(100) DEFAULT NULL,
                role LONGTEXT DEFAULT NULL,
                is_dev TINYINT(1) DEFAULT 0,
                user_registered DATETIME DEFAULT NULL,
                online_status VARCHAR(50) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY site_user (site_id, user_id),
                KEY site_id (site_id),
                KEY user_id (user_id)
            ) {$charset_collate};
        ";
        dbDelta( $sql );

        // Themes table
        $themes_table = $prefix . 'themes';
        $sql = "
            CREATE TABLE {$themes_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id BIGINT(20) UNSIGNED NOT NULL,
                slug VARCHAR(191) NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                author VARCHAR(255) DEFAULT NULL,
                version VARCHAR(50) DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 0,
                update_available TINYINT(1) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY site_theme (site_id, slug),
                KEY site_id (site_id)
            ) {$charset_collate};
        ";
        dbDelta( $sql );

        // Plugins table
        $plugins_table = $prefix . 'plugins';
        $sql = "
            CREATE TABLE {$plugins_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id BIGINT(20) UNSIGNED NOT NULL,
                slug VARCHAR(191) NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                author VARCHAR(255) DEFAULT NULL,
                version VARCHAR(50) DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 0,
                update_available TINYINT(1) DEFAULT 0,
                is_required TINYINT(1) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY site_plugin (site_id, slug),
                KEY site_id (site_id),
                KEY slug (slug)
            ) {$charset_collate};
        ";
        dbDelta( $sql );

        // Actions audit table
        $actions_table = $prefix . 'actions';
        $sql = "
            CREATE TABLE {$actions_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED DEFAULT NULL,
                site_id BIGINT(20) UNSIGNED DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                context VARCHAR(100) DEFAULT NULL,
                details LONGTEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY site_id (site_id),
                KEY action (action),
                KEY created_at (created_at)
            ) {$charset_collate};
        ";
        dbDelta( $sql );

        // Integrations meta table
        $integrations_table = $prefix . 'integrations';
        $sql = "
            CREATE TABLE {$integrations_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id BIGINT(20) UNSIGNED NOT NULL,
                meta_key VARCHAR(191) NOT NULL,
                meta_value LONGTEXT DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY site_meta (site_id, meta_key),
                KEY site_id (site_id),
                KEY meta_key (meta_key)
            ) {$charset_collate};
        ";
        dbDelta( $sql );
    } // End maybe_create_table()


    /**
     * Delete the custom tables, only used on uninstall.php
     *
     * @return void
     */
    public static function delete_tables() {
        global $wpdb;

        foreach ( self::tables() as $table_name => $table_label ) {
            // if ( $table_name === $wpdb->prefix . 'prxctrl_sites' ) {
            //     continue;
            // }
            $sql = "DROP TABLE IF EXISTS " . $table_name . ";";
            $wpdb->query( $sql );
        }
    } // End delete_tables()


    /**
     * Get all registered sites
     *
     * @return array
     */
    public static function get_sites_for_settings() : array {
        global $wpdb;

        $table = $wpdb->prefix . 'prxctrl_sites';

        $site_order = get_option( 'prxctrl_settings_sorting', [] );

        if ( ! empty( $site_order ) ) {
            $order_ids = implode( ',', array_map( 'absint', $site_order ) );
            return $wpdb->get_results(
                "SELECT id, site_name, site_url, api_key, last_checked
                FROM {$table}
                ORDER BY FIELD(id, {$order_ids})",
                OBJECT
            );
        }

        return $wpdb->get_results(
            "SELECT id, site_name, site_url, api_key, last_checked
            FROM {$table}
            ORDER BY site_name ASC",
            OBJECT
        );
    } // End get_sites_for_settings()


    /**
     * Get all registered sites for logs filtering
     *
     * @return array
     */
    public static function get_sites_for_logs() : array {
        global $wpdb;

        $table = $wpdb->prefix . 'prxctrl_sites';

        $dashboard_sorting = sanitize_key(
            get_option( 'prxctrl_dashboard_sorting', 'same_as_settings' )
        );

        if ( $dashboard_sorting === 'same_as_settings' ) {
            $site_order = get_option( 'prxctrl_site_order', [] );

            if ( ! empty( $site_order ) ) {
                $order_ids = implode( ',', array_map( 'absint', $site_order ) );
                return $wpdb->get_results(
                    "SELECT id, site_name, site_url
                    FROM {$table}
                    ORDER BY FIELD( id, {$order_ids} )",
                    OBJECT
                ) ?: [];
            }
        }

        return $wpdb->get_results(
            "SELECT id, site_name, site_url
            FROM {$table}
            ORDER BY site_name ASC",
            OBJECT
        ) ?: [];
    } // End get_sites_for_logs()


    /**
     * Get a site by its ID
     *
     * @param int $site_id Site ID
     * @return object|null Site object or null if not found
     */
    public static function get_site_for_api( $site_id ) : ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'prxctrl_sites';
        $site = $wpdb->get_row( $wpdb->prepare( "SELECT site_url, api_key FROM {$table} WHERE id = %d", $site_id ) );
        return $site ?: null;
    } // End get_site_url_by_id()


    /**
     * Get a site by its URL
     *
     * @param string $site_url Site URL
     * @return object|null Site object or null if not found
     */
    public static function get_site_by_url( $site_url ) : ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'prxctrl_sites';
        $site = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE site_url = %s", $site_url ) );
        return $site ?: null;
    } // End get_site_by_url()


    /**
     * Get all sites for dashboard display, ordered alphabetically by site_name.
     *
     * @return array Array of site objects.
     */
    public static function get_sites_for_dashboard() {
        global $wpdb;

        $sites_table        = $wpdb->prefix . 'prxctrl_sites';
        $plugins_table      = $wpdb->prefix . 'prxctrl_plugins';
        $themes_table       = $wpdb->prefix . 'prxctrl_themes';
        $integrations_table = $wpdb->prefix . 'prxctrl_integrations';
        $admin_users_table  = $wpdb->prefix . 'prxctrl_admin_users';

        $dashboard_sorting = sanitize_key(
            get_option( 'prxctrl_dashboard_sorting', 'same_as_settings' )
        );

        if ( $dashboard_sorting === 'same_as_settings' ) {
            $site_order = get_option( 'prxctrl_settings_sorting', [] );

            if ( ! empty( $site_order ) ) {
                $order_ids = implode( ',', array_map( 'absint', $site_order ) );
                $sites = $wpdb->get_results(
                    "SELECT * FROM {$sites_table}
                    ORDER BY FIELD( id, {$order_ids} )"
                );
            } else {
                $sites = $wpdb->get_results(
                    "SELECT * FROM {$sites_table}
                    ORDER BY site_name ASC"
                );
            }
        } else {
            $sites = $wpdb->get_results(
                "SELECT * FROM {$sites_table}
                ORDER BY site_name ASC"
            );
        }

        if ( ! empty( $sites ) ) {
            foreach ( $sites as $site ) {
                $site_id = absint( $site->id );

                // Get admin users
                $site->admin_users = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT *
                        FROM {$admin_users_table}
                        WHERE site_id = %d
                        ORDER BY display_name ASC",
                        $site_id
                    )
                );

                // Get plugins
                $site->plugins = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$plugins_table}
                        WHERE site_id = %d
                        ORDER BY name ASC",
                        $site_id
                    )
                );

                // Get themes
                $site->themes = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$themes_table}
                        WHERE site_id = %d
                        ORDER BY name ASC",
                        $site_id
                    )
                );

                // Get integrations
                $integration_rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT meta_key, meta_value
                        FROM {$integrations_table}
                        WHERE site_id = %d",
                        $site_id
                    ),
                    OBJECT_K
                );

                $site->integrations = [];

                foreach ( $integration_rows as $key => $row ) {
                    $site->integrations[ $key ] = maybe_unserialize( $row->meta_value );
                }
            }
        }

        return $sites;
    } // End get_sites_for_dashboard()


    /**
     * Add a new site
     *
     * @param string      $site_url Site URL
     * @param string|null $site_name Site Name
     * @param string|null $api_key API Key
     * @return int|false Inserted site ID or false on failure
     */
    public static function add_site( $site_url, $site_name = null, $api_key = null ) : int|false {
        global $wpdb;

        $table = $wpdb->prefix . 'prxctrl_sites';

        $inserted = $wpdb->insert(
            $table,
            [
                'site_url'  => esc_url_raw( $site_url ),
                'site_name' => $site_name,
                'api_key'   => $api_key,
            ],
            [ '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    } // End add_site()


    /**
     * Update an existing site
     *
     * @param int   $site_id Site ID
     * @param array $data Associative array of columns to update
     *
     * @return bool True on success, false on failure
     */
    public static function update_site( $site_id, $data ) : bool {
        global $wpdb;

        $sites_table = $wpdb->prefix . 'prxctrl_sites';

        $allowed_keys = [
            'site_url', 'site_name', 'api_key', 'admin_email', 'server_ip', 'abspath', 
            'is_multisite', 'blog_id', 'wordpress_version', 'php_version', 'wp_debug', 
            'admin_path', 'last_checked', 'last_error', 'other'
        ];

        $update_data = [ ];
        $format = [ ];

        foreach ( $allowed_keys as $key ) {
            if ( array_key_exists( $key, $data ) ) {
                $update_data[ $key ] = $data[ $key ];
                if ( in_array( $key, [ 'is_multisite', 'wp_debug', 'blog_id' ], true ) ) {
                    $format[] = '%d';
                } elseif ( $key === 'last_checked' ) {
                    $format[] = '%s';
                } else {
                    $format[] = '%s';
                }
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $sites_table,
            $update_data,
            [ 'id' => $site_id ],
            $format,
            [ '%d' ]
        );

        return false !== $updated;
    } // End update_site()


    /**
     * Update admin users for a site.
     *
     * @param int   $site_id Site ID.
     * @param array $admin_users Array of admin user data with keys: ID, user_login, user_email, display_name, user_registered, is_dev, is_super_admin.
     *
     * @return void
     */
    public static function update_admin_users( $site_id, $admin_users ) {
        global $wpdb;

        $table = $wpdb->prefix . 'prxctrl_admin_users';

        foreach ( $admin_users as $user ) {
            $wpdb->replace(
                $table,
                [
                    'site_id'        => $site_id,
                    'user_id'        => $user[ 'user_id' ] ?? 0,
                    'user_login'     => $user[ 'user_login' ] ?? '',
                    'display_name'   => $user[ 'display_name' ] ?? '',
                    'user_email'     => $user[ 'user_email' ] ?? '',
                    'role'           => maybe_serialize( $user[ 'role' ] ?? '' ),
                    'is_dev'         => ! empty( $user[ 'is_dev' ] ) ? 1 : 0,
                    'user_registered'=> $user[ 'user_registered' ] ?? null,
                    'online_status'  => $user[ 'online_status' ] ?? 'Unknown',
                ],
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
                ]
            );
        }
    } // End update_admin_users()


    /**
     * Update plugins for a site.
     *
     * @param int   $site_id Site ID.
     * @param array $plugins Array of plugin data with keys: slug, name, author, version, active, update_available, is_required.
     *
     * @return void
     */
    public static function update_plugins( $site_id, $plugins ) {
        global $wpdb;

        $table = $wpdb->prefix . 'prxctrl_plugins';

        foreach ( $plugins as $plugin ) {
            $wpdb->replace(
                $table,
                [
                    'site_id'          => $site_id,
                    'slug'             => $plugin[ 'slug' ] ?? '',
                    'name'             => $plugin[ 'name' ] ?? '',
                    'author'           => $plugin[ 'author' ] ?? '',
                    'version'          => $plugin[ 'version' ] ?? '',
                    'is_active'        => ! empty( $plugin[ 'active' ] ) ? 1 : 0,
                    'update_available' => ! empty( $plugin[ 'update_available' ] ) ? 1 : 0,
                    'is_required'      => ! empty( $plugin[ 'is_required' ] ) ? 1 : 0,
                ],
                [
                    '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d'
                ]
            );
        }
    } // End update_plugins()
    

    /**
     * Update themes for a site.
     *
     * @param int   $site_id Site ID.
     * @param array $themes Array of theme data with keys: slug, name, author, version, active, update_available.
     *
     * @return void
     */
    public static function update_themes( $site_id, $themes ) {
        global $wpdb;

        $table = $wpdb->prefix . 'prxctrl_themes';

        foreach ( $themes as $theme ) {
            $wpdb->replace(
                $table,
                [
                    'site_id'          => $site_id,
                    'slug'             => $theme[ 'slug' ] ?? '',
                    'name'             => $theme[ 'name' ] ?? '',
                    'author'           => $theme[ 'author' ] ?? '',
                    'version'          => $theme[ 'version' ] ?? '',
                    'is_active'        => ! empty( $theme[ 'active' ] ) ? 1 : 0,
                    'update_available' => ! empty( $theme[ 'update_available' ] ) ? 1 : 0,
                ],
                [
                    '%d', '%s', '%s', '%s', '%s', '%d', '%d'
                ]
            );
        }
    } // End update_themes()


    /**
     * Update integrations for a site.
     *
     * @param int   $site_id Site ID.
     * @param array $data Associative array of integration data.
     *
     * @return void
     */
    public static function update_integrations( $site_id, $data ) {
        global $wpdb;

        $integrations_table = $wpdb->prefix . 'prxctrl_integrations';

        $integrations = apply_filters( 'prxcntrl_integration_requests', [], $site_id, $data );
        
        if ( ! empty( $integrations ) ) {
            foreach ( $integrations as $key => $integration ) {
                $wpdb->replace(
                    $integrations_table,
                    [
                        'site_id'    => $site_id,
                        'meta_key'   => $key,
                        'meta_value' => maybe_serialize( $data[ $key ] ?? '' ),
                    ],
                    [
                        '%d',
                        '%s',
                        '%s',
                    ]
                );
            }
        }
    } // End update_integrations()

    
    /**
     * Delete a site and all associated records
     *
     * @param int $site_id
     *
     * @return bool True if the site was deleted, false on failure
     */
    public static function delete_site( $site_id ) : bool {
        global $wpdb;

        $prefix            = $wpdb->prefix;
        $sites_table       = $prefix . 'prxctrl_sites';
        $admin_users_table = $prefix . 'prxctrl_admin_users';
        $plugins_table     = $prefix . 'prxctrl_plugins';
        $themes_table      = $prefix . 'prxctrl_themes';
        $actions_table     = $prefix . 'prxctrl_actions';

        // Delete associated admin users
        $wpdb->delete( $admin_users_table, [ 'site_id' => $site_id ], [ '%d' ] );

        // Delete associated plugins
        $wpdb->delete( $plugins_table, [ 'site_id' => $site_id ], [ '%d' ] );

        // Delete associated themes
        $wpdb->delete( $themes_table, [ 'site_id' => $site_id ], [ '%d' ] );

        // Delete associated actions
        $wpdb->delete( $actions_table, [ 'site_id' => $site_id ], [ '%d' ] );

        // Delete the site itself
        $deleted = $wpdb->delete( $sites_table, [ 'id' => $site_id ], [ '%d' ] );

        return false !== $deleted;
    } // End delete_site()


    /**
     * Log an action performed on a site
     *
     * @param int    $site_id Site ID
     * @param array  $args Associative array with keys: action (string), context (string|null), details (mixed|null)
     *
     * @return int Inserted action ID
     */
    public static function log_site_action( $site_id, $args = [] ) {
        global $wpdb;

        $actions_table = $wpdb->prefix . 'prxctrl_actions';

        $defaults = [
            'action'  => '',
            'context' => null,
            'details' => null,
        ];

        $args = wp_parse_args( $args, $defaults );

        $ip_address = $_SERVER[ 'REMOTE_ADDR' ] ?? null;
        $ip_address = $ip_address ? filter_var( $ip_address, FILTER_VALIDATE_IP ) : null;

        $data = [
            'user_id'    => get_current_user_id() ?: null,
            'site_id'    => $site_id,
            'action'     => $args[ 'action' ],
            'context'    => $args[ 'context' ],
            'details'    => maybe_serialize( $args[ 'details' ] ),
            'ip_address' => $ip_address,
            'created_at' => current_time( 'mysql' ),
        ];

        $format = [
            '%d', // user_id
            '%d', // site_id
            '%s', // action
            '%s', // context
            '%s', // details
            '%s', // ip_address
            '%s', // created_at
        ];

        $wpdb->insert( $actions_table, $data, $format );

        return $wpdb->insert_id;
    } // End log_site_action()


    /**
     * Delete all actions associated with a specific site
     *
     * @param int $site_id Site ID
     * @return int|false Number of rows deleted or false on failure
     */
    public static function delete_actions_by_site( $site_id ) {
        global $wpdb;

        $actions_table = $wpdb->prefix . 'prxctrl_actions';
        $site_id       = intval( $site_id );

        if ( $site_id <= 0 ) {
            return false;
        }

        return $wpdb->delete( $actions_table, [ 'site_id' => $site_id ], [ '%d' ] );
    } // End delete_actions_by_site()


    /**
     * Delete actions by their IDs
     *
     * @param int|array $action_ids Single action ID or array of action IDs
     * @return int|false Number of rows deleted or false on failure
     */
    public static function delete_actions_by_id( $action_ids ) {
        global $wpdb;

        $actions_table = $wpdb->prefix . 'prxctrl_actions';

        if ( empty( $action_ids ) ) {
            return false;
        }

        if ( ! is_array( $action_ids ) ) {
            $action_ids = [ intval( $action_ids ) ];
        } else {
            $action_ids = array_map( 'intval', $action_ids );
        }

        if ( empty( $action_ids ) ) {
            return false;
        }

        $placeholders = implode( ',', array_fill( 0, count( $action_ids ), '%d' ) );
        $query        = "DELETE FROM {$actions_table} WHERE id IN ($placeholders)";

        return $wpdb->query( $wpdb->prepare( $query, $action_ids ) );
    } // End delete_actions_by_id()

}


Database::instance();