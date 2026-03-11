<?php
namespace WPUmbrella\Services\BrokenLinkChecker;

class LinkRepository
{
    public function shouldScanPage($pageUrl)
    {
        global $wpdb;
        $tableName = LinkTableManager::getTableName();
        $scanInterval = (int) get_option('wp_umbrella_blc_scan_interval', 24);

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableName} WHERE page_url = %s AND collected_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $pageUrl,
            $scanInterval
        ));

        return (int) $result === 0;
    }

    public function insertLinks($pageUrl, array $links)
    {
        if (empty($links)) {
            return;
        }

        global $wpdb;
        $tableName = LinkTableManager::getTableName();
        $now = current_time('mysql');

        $values = [];
        $placeholders = [];

        foreach ($links as $link) {
            $linkHash = md5($pageUrl . $link['href']);
            $placeholders[] = '(%s, %s, %s, %s, %d, %s, %s, %s)';
            $values[] = $linkHash;
            $values[] = $pageUrl;
            $values[] = $link['href'];
            $values[] = $link['anchor_text'];
            $values[] = $link['is_internal'];
            $values[] = $link['position'];
            $values[] = $link['rel'];
            $values[] = $now;
        }

        $sql = "INSERT INTO {$tableName} (link_hash, page_url, href, anchor_text, is_internal, position, rel, collected_at) VALUES "
            . implode(', ', $placeholders)
            . " ON DUPLICATE KEY UPDATE collected_at = VALUES(collected_at), anchor_text = VALUES(anchor_text), position = VALUES(position), rel = VALUES(rel), sent_at = NULL";

        $wpdb->query($wpdb->prepare($sql, $values));
    }

    public function getUnsentLinks($limit = 500)
    {
        global $wpdb;
        $tableName = LinkTableManager::getTableName();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tableName} WHERE sent_at IS NULL ORDER BY collected_at ASC LIMIT %d",
            $limit
        ));
    }

    public function markAsSent(array $ids)
    {
        if (empty($ids)) {
            return;
        }

        global $wpdb;
        $tableName = LinkTableManager::getTableName();
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$tableName} SET sent_at = NOW() WHERE id IN ({$placeholders})",
            $ids
        ));
    }

    public function countUnsent()
    {
        global $wpdb;
        $tableName = LinkTableManager::getTableName();

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableName} WHERE sent_at IS NULL");
    }

    public function deleteOldSent($daysOld = 7)
    {
        global $wpdb;
        $tableName = LinkTableManager::getTableName();

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tableName} WHERE sent_at IS NOT NULL AND sent_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $daysOld
        ));
    }
}
