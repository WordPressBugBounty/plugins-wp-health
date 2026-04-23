<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class PluginsCollector implements CollectorInterface
{
    public function getId()
    {
        return 'plugins';
    }

    public function collect()
    {
        return [];
    }

    public function collectActive()
    {
        return $this->filterByStatus(true);
    }

    public function collectInactive()
    {
        return $this->filterByStatus(false);
    }

    protected function filterByStatus($active)
    {
        try {
            $provider = wp_umbrella_get_service('PluginsProvider');

            if (!$provider || !method_exists($provider, 'getPlugins')) {
                return [];
            }

            $allPlugins = $provider->getPlugins([
                'light' => true,
                'clear_updates' => false,
            ]);

            if (!is_array($allPlugins) && !($allPlugins instanceof \Traversable)) {
                return [];
            }

            $result = [];

            foreach ($allPlugins as $plugin) {
                if (!is_object($plugin)) {
                    continue;
                }

                $isActive = !empty($plugin->active);

                if ($isActive !== $active) {
                    continue;
                }

                $hasUpdate = isset($plugin->update) && !empty($plugin->update);

                $result[] = [
                    'name' => isset($plugin->name) ? $plugin->name : '',
                    'slug' => isset($plugin->slug) ? $plugin->slug : '',
                    'version' => isset($plugin->version) ? $plugin->version : '',
                    'author' => isset($plugin->author_name) ? $plugin->author_name : '',
                    'update_available' => $hasUpdate,
                    'latest_version' => isset($plugin->version_latest) ? $plugin->version_latest : null,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
