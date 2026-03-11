<?php
namespace WPUmbrella\Services\BrokenLinkChecker;

class LinkTableManager
{
    const TABLE_NAME = 'umbrella_collected_links';

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
            page_url varchar(2048) NOT NULL,
            href varchar(2048) NOT NULL,
            link_hash varchar(32) NOT NULL,
            anchor_text text,
            is_internal tinyint(1) NOT NULL DEFAULT 0,
            position varchar(20) DEFAULT 'content',
            rel varchar(255) DEFAULT NULL,
            collected_at datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_link_hash (link_hash),
            KEY idx_unsent (sent_at, collected_at),
            KEY idx_page_url_collected (page_url(191), collected_at)
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
