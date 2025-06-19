<?php
namespace WPUmbrella\Services\Provider;

use WPUmbrella\Core\Schemas\PluginSchema;
use Morphism\Morphism;

class Plugins
{
    const NAME_SERVICE = 'PluginsProvider';

    protected $schema;

    public function __construct()
    {
        $this->createDefaultSchema();
    }

    protected function createDefaultSchema()
    {
        $this->schema = new PluginSchema();
    }

    protected function checkSecupressUpdates($transient)
    {
        if (defined('SECUPRESS_WEB_MAIN') && defined('SECUPRESS_FILE') && defined('SECUPRESS_PRO_VERSION')) {
            $secupressData = wp_umbrella_get_service('SecuPressProUpdate')->checkUpdate();

            if ($secupressData !== null) {
                $transient->response['secupress-pro/secupress-pro.php'] = $secupressData->response['secupress-pro/secupress-pro.php'];
                $transient->checked['secupress-pro/secupress-pro.php'] = $secupressData->response['secupress-pro/secupress-pro.php']->new_version;
            }
        }

        return $transient;
    }

    public function getPlugins($options = [])
    {
        wp_umbrella_get_service('RequestSettings')->triggerAdminInit();

        $light = $options['light'] ?? false;

        if (defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        $clearUpdates = $options['clear_updates'] ?? true;

        if ($clearUpdates) {
            wp_umbrella_get_service('ManagePlugin')->clearUpdates();
        }

        $plugins = get_plugins();
        $data = [];
        $i = 0;
        foreach ($plugins as $key => $plugin) {
            $data[$i] = $plugin;
            $data[$i]['key'] = $key;

            $slugExplode = explode('/', $key);
            if (isset($slugExplode[0])) {
                $data[$i]['slug'] = $slugExplode[0];
            }

            $data[$i]['active'] = is_plugin_active($key);
            ++$i;
        }
        $schema = $this->schema->getSchema([
            'light' => $light
        ]);

        $current = wp_umbrella_get_service('WordPressContext')->getTransient('update_plugins');

        if (!empty($current->response)) {
            $pluginsByKey = array_column($data, 'key');

            foreach ($current->response as $pluginPath => $value) {
                if ($pluginPath === 'secupress-pro/secupress-pro.php') {
                    $current = $this->checkSecupressUpdates($current);
                }

                $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginPath, false, false);
                if (strlen($pluginData['Name']) > 0 && strlen($pluginData['Version']) > 0) {
                    $index = array_search($pluginPath, $pluginsByKey);

                    if (isset($current->response[$pluginPath])) {
                        $current->response[$pluginPath]->name = $pluginData['Name'];
                        $current->response[$pluginPath]->old_version = $pluginData['Version'];
                        $current->response[$pluginPath]->file = $pluginPath;
                        unset($current->response[$pluginPath]->upgrade_notice);

                        $data[$index]['update'] = $current->response[$pluginPath];
                    }
                }
            }

            Morphism::setMapper('WPUmbrella\DataTransferObject\Plugin', $schema);

            return Morphism::map('WPUmbrella\DataTransferObject\Plugin', $data);
        } else {
            $needUpdates = get_plugin_updates();

            $pluginsByName = array_column($data, 'Name');
            if (!empty($needUpdates)) {
                foreach ($needUpdates as $plugin) {
                    $index = array_search($plugin->Name, $pluginsByName);
                    $data[$index]['update'] = $plugin->update;
                }
            }

            Morphism::setMapper('WPUmbrella\DataTransferObject\Plugin', $schema);

            return Morphism::map('WPUmbrella\DataTransferObject\Plugin', $data);
        }
    }

    public function getPlugin($plugin, $options = [])
    {
        $clearUpdates = $options['clear_updates'] ?? true;

        if (defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        if ($clearUpdates) {
            wp_umbrella_get_service('ManagePlugin')->clearUpdates();
        }

        if (!file_exists(sprintf('%s/%s', untrailingslashit(WP_PLUGIN_DIR), $plugin))) {
            return null;
        }

        $path = sprintf('%s/%s', untrailingslashit(WP_PLUGIN_DIR), $plugin);
        $data = get_plugin_data($path);

        $slugExplode = explode('/', $plugin);

        $data['slug'] = $slugExplode[0];
        $data['active'] = is_plugin_active($plugin);

        $needUpdates = [];
        $needUpdates = get_plugin_updates();

        if (!empty($needUpdates) && is_array($needUpdates)) {
            if (isset($needUpdates[$plugin])) {
                $data['update'] = $needUpdates[$plugin]->update;
            }
        }

        $informations = $this->getPluginChangelog($plugin);

        if ($informations && isset($informations->sections['changelog'])) {
            $data['changelog'] = $informations->sections['changelog'];
        }

        $schema = $this->schema->getSchema([
            'light' => false
        ]);

        Morphism::setMapper('WPUmbrella\DataTransferObject\Plugin', $schema);

        return Morphism::map('WPUmbrella\DataTransferObject\Plugin', $data);
    }

    /**
     *
     * @param string $file
     * @return DTOPlugin
     */
    public function getPluginByFile($file, $options = [])
    {
        $plugins = $this->getPlugins($options);

        $plugin = null;
        foreach ($plugins as $key => $item) {
            if ($item->key !== $file) {
                continue;
            }

            $plugin = $item;
            break;
        }

        return $plugin;
    }

    public function getPluginTags($slug)
    {
        $url = sprintf('https://api.wordpress.org/plugins/info/1.0/%s.json', $slug);
        $response = wp_remote_get($url);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function getPluginChangelog($slug)
    {
        if (empty($slug)) {
            return null;
        }

        if (!function_exists('plugins_api')) {
            require_once \ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $slugExplode = explode('/', $slug);

        if (isset($slugExplode[0])) {
            $api = \plugins_api('plugin_information', [
                'slug' => $slugExplode[0],
                'fields' => [
                    'sections' => true,
                    'changelog' => true,
                ]
            ]);

            if ($api && isset($api->errors)) {
                $api = \plugins_api('plugin_information', [
                    'slug' => $slug,
                    'fields' => [
                        'sections' => true,
                        'changelog' => true,
                    ]
                ]);
            }
        } else {
            $api = \plugins_api('plugin_information', [
                'slug' => $slug,
                'fields' => [
                    'sections' => true,
                    'changelog' => true,
                ]
            ]);
        }

        if (is_wp_error($api)) {
            return null;
        }

        return $api;
    }
}
