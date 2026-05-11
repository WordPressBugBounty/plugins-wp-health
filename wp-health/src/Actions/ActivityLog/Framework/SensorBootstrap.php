<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

use WPUmbrella\Actions\ActivityLog\Sensors\CommentTermMenuSensor;
use WPUmbrella\Actions\ActivityLog\Sensors\ContentSensor;
use WPUmbrella\Actions\ActivityLog\Sensors\PluginSensor;
use WPUmbrella\Actions\ActivityLog\Sensors\ThemeCoreOptionSensor;
use WPUmbrella\Actions\ActivityLog\Sensors\UserSensor;
use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Wires the activity log framework into WordPress.
 *
 * - Creates the buffer table on plugin activation (via SchemaInstaller).
 * - On every request, registers $wpdb->{TABLE_NAME} and, when the activity
 *   log is enabled in settings, schedules the sync and registers all
 *   sensors (sensors are added in #210 to #213, see the TODO list below).
 *
 * Discovered automatically by Kernel::buildClasses() because the file lives
 * under src/Actions/ and the class implements ExecuteHooks.
 */
class SensorBootstrap implements ExecuteHooks, ActivationHook, DeactivationHook
{
    const OPTION_ENABLED = 'wp_umbrella_activity_log_enabled';

    /**
     * Plugin activation: create the buffer table.
     *
     * @return void
     */
    public function activate()
    {
        SchemaInstaller::install();
    }

    /**
     * Plugin deactivation: cancel the recurring sync. We deliberately keep
     * the buffer table around so that pending events can still be drained
     * after the plugin is reactivated.
     *
     * @return void
     */
    public function deactivate()
    {
        (new SyncScheduler())->unschedule();
    }

    /**
     * Per request hook registration.
     *
     * @return void
     */
    public function hooks()
    {
        SchemaInstaller::registerTable();

        if (!self::isEnabled()) {
            return;
        }

        $this->bootstrap();
    }

    /**
     * Schedules the sync and registers every active sensor.
     *
     * @return void
     */
    public function bootstrap()
    {
        // Action Scheduler's data store is only initialized on the WP `init`
        // hook (priority 1). Calling as_*_action() before that emits doing_it_wrong
        // notices on every request. Defer scheduling to init priority 20 so AS is
        // ready while sensors still register on plugins_loaded as expected.
        add_action('init', function () {
            (new SyncScheduler())->schedule();
        }, 20);

        $buffer = new EventBuffer();
        $sensors = $this->getSensors($buffer);

        foreach ($sensors as $sensor) {
            $sensor->register();
        }
    }

    /**
     * Returns the list of sensor instances to register.
     *
     * Sensors are added in dedicated sub issues:
     *
     * @param EventBuffer $buffer
     *
     * @return AbstractSensor[]
     */
    protected function getSensors(EventBuffer $buffer)
    {
        return [
            new PluginSensor($buffer),
            new UserSensor($buffer),
            new ContentSensor($buffer),
            new ThemeCoreOptionSensor($buffer),
            new CommentTermMenuSensor($buffer),
        ];
    }

    /**
     * Reads the activity log toggle from the plugin options.
     *
     * Default: false (opt in for existing projects).
     *
     * @return bool
     */
    public static function isEnabled()
    {
        $value = get_option(self::OPTION_ENABLED, false);

        if (is_string($value)) {
            return $value === '1' || strtolower($value) === 'true';
        }

        return (bool) $value;
    }
}
