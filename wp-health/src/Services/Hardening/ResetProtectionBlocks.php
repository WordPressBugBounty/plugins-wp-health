<?php
namespace WPUmbrella\Services\Hardening;

use WPUmbrella\Actions\Hardening\AttackerIps\FilterStorage;
use WPUmbrella\Actions\Hardening\LoginRateLimit;

if (!defined('ABSPATH')) {
    exit;
}

class ResetProtectionBlocks
{
    const TRANSIENT_OPTION_PREFIX = '_transient_';

    const STORED_ENTRIES_LIMIT = 5000;

    public function reset()
    {
        (new FilterStorage())->clear();

        return [
            'community_filter_cleared' => true,
            'rate_limit_entries_cleared' => $this->clearRateLimitEntries(),
        ];
    }

    protected function clearRateLimitEntries()
    {
        return $this->clearIndexedEntries() + $this->clearStoredEntries();
    }

    protected function clearIndexedEntries()
    {
        $index = get_option(LoginRateLimit::BLOCK_INDEX_KEY, []);

        if (!is_array($index) || empty($index)) {
            return 0;
        }

        $cleared = 0;

        foreach (array_keys($index) as $hash) {
            $hash = (string) $hash;

            delete_transient(LoginRateLimit::BLOCK_PREFIX . $hash);
            delete_transient(LoginRateLimit::STRIKES_PREFIX . $hash);
            delete_transient(LoginRateLimit::TRANSIENT_PREFIX . $hash);

            $cleared++;
        }

        delete_option(LoginRateLimit::BLOCK_INDEX_KEY);

        return $cleared;
    }

    protected function clearStoredEntries()
    {
        global $wpdb;

        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
                $wpdb->esc_like(self::TRANSIENT_OPTION_PREFIX . LoginRateLimit::TRANSIENT_PREFIX) . '%',
                self::STORED_ENTRIES_LIMIT
            )
        );

        if (empty($names)) {
            return 0;
        }

        $cleared = 0;

        foreach ($names as $name) {
            delete_transient(substr($name, strlen(self::TRANSIENT_OPTION_PREFIX)));

            $cleared++;
        }

        return $cleared;
    }
}
