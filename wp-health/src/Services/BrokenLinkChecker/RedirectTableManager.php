<?php
namespace WPUmbrella\Services\BrokenLinkChecker;

class RedirectTableManager
{
    const TABLE_NAME = 'umbrella_redirects';

    public static function getTableName()
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    private static $tableChecked = false;

    public static function ensureTableExists()
    {
        if (self::$tableChecked) {
            return;
        }

        self::$tableChecked = true;

        global $wpdb;
        $tableName = self::getTableName();

        $row = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));

        if ($row !== $tableName) {
            self::createTable();
        }
    }

    public static function createTable()
    {
        global $wpdb;

        $tableName = self::getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL auto_increment,
            source_pattern varchar(2048) NOT NULL,
            destination_url varchar(2048) NOT NULL,
            redirect_type int NOT NULL DEFAULT 301,
            match_type varchar(20) NOT NULL DEFAULT 'exact',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_source_pattern (source_pattern(191))
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function dropTable()
    {
        global $wpdb;
        $tableName = self::getTableName();
        $wpdb->query("DROP TABLE IF EXISTS {$tableName}");
    }

    public static function registerTable()
    {
        global $wpdb;
        $wpdb->tables[] = self::TABLE_NAME;
        $wpdb->{self::TABLE_NAME} = self::getTableName();
    }
}
