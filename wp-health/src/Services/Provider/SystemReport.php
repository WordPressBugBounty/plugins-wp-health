<?php
namespace WPUmbrella\Services\Provider;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Services\Provider\SystemReport\WordPressEnvironmentCollector;
use WPUmbrella\Services\Provider\SystemReport\ServerEnvironmentCollector;
use WPUmbrella\Services\Provider\SystemReport\DatabaseCollector;
use WPUmbrella\Services\Provider\SystemReport\SecurityCollector;
use WPUmbrella\Services\Provider\SystemReport\PluginsCollector;
use WPUmbrella\Services\Provider\SystemReport\ThemeCollector;
use WPUmbrella\Services\Provider\SystemReport\FilesystemCollector;
use WPUmbrella\Services\Provider\SystemReport\PostTypeCollector;
use WPUmbrella\Services\Provider\SystemReport\CronCollector;
use WPUmbrella\Services\Provider\SystemReport\ConfigurationCollector;
use WPUmbrella\Services\Provider\SystemReport\AgentContextCollector;
use WPUmbrella\Services\Provider\SystemReport\DebugStatusCollector;
use WPUmbrella\Services\Provider\SystemReport\ErrorLogCollector;

class SystemReport
{
    const NAME_SERVICE = 'SystemReportProvider';

    protected static $allSections = [
        'agent_context',
        'wordpress_environment',
        'server_environment',
        'database',
        'security',
        'active_plugins',
        'inactive_plugins',
        'theme_info',
        'filesystem_permissions',
        'post_type_counts',
        'cron_health',
        'wordpress_configuration',
        'debug_status',
        'error_log',
    ];

    public function getData($options = [])
    {
        $errorLogLines = isset($options['error_log_lines']) ? (int) $options['error_log_lines'] : 50;

        $requestedSections = !empty($options['sections'])
            ? array_intersect($options['sections'], self::$allSections)
            : self::$allSections;

        if (!in_array('agent_context', $requestedSections)) {
            array_unshift($requestedSections, 'agent_context');
        }

        $collectors = $this->buildCollectors($errorLogLines);

        $data = [];
        foreach ($requestedSections as $section) {
            if (!isset($collectors[$section])) {
                continue;
            }

            try {
                $data[$section] = $collectors[$section]();
            } catch (\Exception $e) {
                $data[$section] = ['_error' => $e->getMessage()];
            } catch (\Throwable $e) {
                $data[$section] = ['_error' => $e->getMessage()];
            }
        }

        return $data;
    }

    protected function buildCollectors($errorLogLines)
    {
        $plugins = new PluginsCollector();
        $errorLog = new ErrorLogCollector($errorLogLines);

        return [
            'agent_context' => function () { return (new AgentContextCollector())->collect(); },
            'wordpress_environment' => function () { return (new WordPressEnvironmentCollector())->collect(); },
            'server_environment' => function () { return (new ServerEnvironmentCollector())->collect(); },
            'database' => function () { return (new DatabaseCollector())->collect(); },
            'security' => function () { return (new SecurityCollector())->collect(); },
            'active_plugins' => function () use ($plugins) { return $plugins->collectActive(); },
            'inactive_plugins' => function () use ($plugins) { return $plugins->collectInactive(); },
            'theme_info' => function () { return (new ThemeCollector())->collect(); },
            'filesystem_permissions' => function () { return (new FilesystemCollector())->collect(); },
            'post_type_counts' => function () { return (new PostTypeCollector())->collect(); },
            'cron_health' => function () { return (new CronCollector())->collect(); },
            'wordpress_configuration' => function () { return (new ConfigurationCollector())->collect(); },
            'debug_status' => function () { return (new DebugStatusCollector())->collect(); },
            'error_log' => function () use ($errorLog) { return $errorLog->collect(); },
        ];
    }
}
