<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Creates the activity log buffer table on plugin activation.
 *
 * The buffer is a transient holding area, events live here only until the
 * sync scheduler drains them and POSTs them to the WP Umbrella API.
 */
class SchemaInstaller
{
    const TABLE_NAME = 'umbrella_activity_log_buffer';

    /**
     * Per request cache to avoid running SHOW TABLES multiple times.
     *
     * @var bool
     */
    protected static $tableChecked = false;

    /**
     * @return string
     */
    public static function getTableName()
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Registers the table with $wpdb so $wpdb->{TABLE_NAME} works.
     *
     * @return void
     */
    public static function registerTable()
    {
        global $wpdb;

        $wpdb->tables[] = self::TABLE_NAME;
        $wpdb->{self::TABLE_NAME} = self::getTableName();
    }

    /**
     * Lazily creates the table the first time it is needed inside a request.
     *
     * @return void
     */
    public static function ensureTableExists()
    {
        if (self::$tableChecked) {
            return;
        }

        self::$tableChecked = true;

        global $wpdb;
        $tableName = self::getTableName();

        $row = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));

        if ($row !== $tableName) {
            self::install();
        }
    }

    /**
     * Creates the buffer table. Idempotent thanks to dbDelta.
     *
     * @return void
     */
    public static function install()
    {
        global $wpdb;

        $tableName = self::getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        // `created_at` is `datetime(3)` (not `timestamp`) so the column has no
        // implicit timezone semantics. The application writes a UTC value
        // explicitly via gmdate() to avoid drift with the MySQL session TZ.
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL auto_increment,
            event_key varchar(100) NOT NULL,
            severity varchar(20) NOT NULL,
            channel varchar(20) NOT NULL,
            occurred_at datetime(3) NOT NULL,
            payload longtext NOT NULL,
            created_at datetime(3) NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_occurred_at (occurred_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drops the buffer table. Used on uninstall.
     *
     * @return void
     */
    public static function drop()
    {
        global $wpdb;

        $tableName = self::getTableName();
        $wpdb->query("DROP TABLE IF EXISTS {$tableName}");
    }
}
