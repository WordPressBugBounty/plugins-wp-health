<?php
namespace WPUmbrella\Services;

class Snapshot
{
    public function getData()
    {
        $plugins = wp_umbrella_get_service('PluginsProvider')->getPlugins();
        $wordpressData = wp_umbrella_get_service('WordPressProvider')->get();
        $themes = wp_umbrella_get_service('ThemesProvider')->getThemes();
        $databaseOptimization = wp_umbrella_get_service('DatabaseOptimizationManager')->getData();

        return [
            'plugins' => $plugins,
            'warnings' => $wordpressData,
            'themes' => $themes,
            'database_optimization' => $databaseOptimization,
            'unlisted_code' => $this->getUnlistedCode(),
        ];
    }

    protected function getUnlistedCode()
    {
        try {
            return wp_umbrella_get_service('UnlistedCodeAnalyzer')->analyze();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function handle()
    {
        $data = $this->getData();

        wp_umbrella_get_service('Projects')->snapshotData($data);
    }
}
