<?php
namespace WPUmbrella\Services\Manage;

class TrackUpgradeProcess
{
    public function getPluginVersionFromFile($plugin_file)
    {
        $content = file_get_contents($plugin_file);
        if (preg_match('/Version:\s*(\S+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function prepareDataBeforePluginUpdate($plugins)
    {
        global $umbrellaPreUpdateData;

        $umbrellaPreUpdateData = [];

        foreach ($plugins as $plugin) {
            $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $oldVersion = $this->getPluginVersionFromFile(WP_PLUGIN_DIR . '/' . $plugin);

            $umbrellaPreUpdateData[] = [
                'name' => $pluginData['Name'],
                'plugin' => $plugin,
                'old_version' => $oldVersion,
                'new_version' => null,
            ];
        }
    }

    public function getDataAfterPluginUpdate($plugins)
    {
        global $umbrellaPreUpdateData;

        foreach ($plugins as $plugin) {
            $newVersion = $this->getPluginVersionFromFile(WP_PLUGIN_DIR . '/' . $plugin);

            if (!is_array($umbrellaPreUpdateData)) {
                return $umbrellaPreUpdateData;
            }

            $umbrellaPreUpdateData = array_map(function ($item) use ($newVersion, $plugin) {
                if ($item['plugin'] !== $plugin) {
                    return $item;
                }

                $item['new_version'] = $newVersion;
                return $item;
            }, $umbrellaPreUpdateData);
        }

        return $umbrellaPreUpdateData;
    }
}
