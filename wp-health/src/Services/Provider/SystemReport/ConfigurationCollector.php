<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class ConfigurationCollector implements CollectorInterface
{
    public function getId()
    {
        return 'wordpress_configuration';
    }

    public function collect()
    {
        global $wpdb;

        $autoloadedSizeKb = null;
        $autoloadedCount = null;
        $totalCount = null;
        $prefixLength = null;

        try {
            if ($wpdb) {
                $autoloadedSize = $wpdb->get_var(
                    "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
                );
                $autoloadedSizeKb = $autoloadedSize !== null ? round((int) $autoloadedSize / 1024, 2) : null;

                $autoloadedCount = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload = 'yes'"
                );

                $totalCount = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->options}"
                );

                $prefixLength = strlen($wpdb->prefix);
            }
        } catch (\Exception $e) {
            // Database queries failed
        }

        $rewriteRules = get_option('rewrite_rules');

        return [
            'autoloaded_options_size_kb' => $autoloadedSizeKb,
            'autoloaded_options_count' => $autoloadedCount,
            'total_options_count' => $totalCount,
            'rewrite_rules_count' => is_array($rewriteRules) ? count($rewriteRules) : 0,
            'table_prefix_length' => $prefixLength,
        ];
    }
}
