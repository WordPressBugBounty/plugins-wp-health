<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures plugin lifecycle events: activate, deactivate, install, update, delete.
 *
 * Event keys emitted:
 * - plugin.activated     (HIGH)
 * - plugin.deactivated   (HIGH)
 * - plugin.installed     (MEDIUM)
 * - plugin.updated       (MEDIUM)
 * - plugin.deleted       (HIGH)
 *
 * Hook bridges:
 * - activated_plugin / deactivated_plugin: direct mapping with networkWide flag.
 * - upgrader_process_complete: dispatches install vs update from $hookExtra['action']
 *   and only handles type=plugin (themes/core go to other sensors).
 * - delete_plugin / deleted_plugin: the plugin file is gone after deletion, so we
 *   capture name+version on the "before" hook and emit on the "after" hook with
 *   $deleted=true.
 */
class PluginSensor extends AbstractSensor
{
    /**
     * Plugin metadata captured on delete_plugin so we can emit a meaningful
     * event on deleted_plugin (when the file is already gone).
     *
     * @var array<string, array{name: string, version: string}>
     */
    protected $deletionCache = [];

    /**
     * @return void
     */
    public function register()
    {
        add_action('activated_plugin', [$this, 'onActivated'], 10, 2);
        add_action('deactivated_plugin', [$this, 'onDeactivated'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'onUpgraderComplete'], 10, 2);
        add_action('delete_plugin', [$this, 'onBeforeDelete'], 10, 1);
        add_action('deleted_plugin', [$this, 'onDeleted'], 10, 2);
    }

    public function onActivated($pluginFile, $networkWide = false)
    {
        $this->recordEvent(
            'plugin.activated',
            'HIGH',
            $this->buildContext($pluginFile, $networkWide)
        );
    }

    public function onDeactivated($pluginFile, $networkWide = false)
    {
        $this->recordEvent(
            'plugin.deactivated',
            'HIGH',
            $this->buildContext($pluginFile, $networkWide)
        );
    }

    public function onUpgraderComplete($upgrader, $hookExtra)
    {
        if (!is_array($hookExtra)) {
            return;
        }

        if (!isset($hookExtra['type']) || $hookExtra['type'] !== 'plugin') {
            return;
        }

        $action = isset($hookExtra['action']) ? (string) $hookExtra['action'] : '';
        $eventKey = $action === 'install' ? 'plugin.installed' : 'plugin.updated';

        $plugins = [];

        if (isset($hookExtra['plugins']) && is_array($hookExtra['plugins'])) {
            $plugins = $hookExtra['plugins'];
        } elseif (isset($hookExtra['plugin']) && is_string($hookExtra['plugin'])) {
            $plugins = [$hookExtra['plugin']];
        }

        foreach ($plugins as $pluginFile) {
            if (!is_string($pluginFile) || $pluginFile === '') {
                continue;
            }

            $this->recordEvent(
                $eventKey,
                'MEDIUM',
                $this->buildContext($pluginFile, false)
            );
        }
    }

    public function onBeforeDelete($pluginFile)
    {
        if (!is_string($pluginFile) || $pluginFile === '') {
            return;
        }

        $info = $this->getPluginInfo($pluginFile);

        if ($info !== null) {
            $this->deletionCache[$pluginFile] = $info;
        }
    }

    public function onDeleted($pluginFile, $deleted)
    {
        if (!$deleted || !is_string($pluginFile) || $pluginFile === '') {
            return;
        }

        $info = isset($this->deletionCache[$pluginFile]) ? $this->deletionCache[$pluginFile] : null;
        unset($this->deletionCache[$pluginFile]);

        $this->recordEvent('plugin.deleted', 'HIGH', [
            'pluginFile' => $pluginFile,
            'pluginName' => $info !== null ? $info['name'] : null,
            'pluginVersion' => $info !== null ? $info['version'] : null,
            'networkWide' => false,
        ]);
    }

    protected function buildContext($pluginFile, $networkWide)
    {
        $info = is_string($pluginFile) && $pluginFile !== ''
            ? $this->getPluginInfo($pluginFile)
            : null;

        return [
            'pluginFile' => is_string($pluginFile) ? $pluginFile : null,
            'pluginName' => $info !== null ? $info['name'] : null,
            'pluginVersion' => $info !== null ? $info['version'] : null,
            'networkWide' => (bool) $networkWide,
        ];
    }

    /**
     * @param string $pluginFile
     *
     * @return array{name: string, version: string}|null
     */
    protected function getPluginInfo($pluginFile)
    {
        if (!defined('WP_PLUGIN_DIR')) {
            return null;
        }

        $absolutePath = WP_PLUGIN_DIR . '/' . $pluginFile;

        if (!file_exists($absolutePath)) {
            return null;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_plugin_data($absolutePath, false, false);

        if (!is_array($data)) {
            return null;
        }

        return [
            'name' => isset($data['Name']) ? (string) $data['Name'] : '',
            'version' => isset($data['Version']) ? (string) $data['Version'] : '',
        ];
    }
}
