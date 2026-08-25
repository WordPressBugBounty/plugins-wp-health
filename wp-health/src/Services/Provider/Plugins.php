<?php
namespace WPUmbrella\Services\Provider;

use WPUmbrella\Core\Schemas\PluginSchema;
use WPUmbrella\Services\Provider\Compatibility\PremiumUpdateDetector;
use Morphism\Morphism;

class Plugins
{
    const NAME_SERVICE = 'PluginsProvider';

    /**
     * A changelog is the whole history of a plugin, not the pending release.
     * WooCommerce Subscriptions alone answers with 319 KB, which then crosses
     * the admin-ajax loopback, our API and the dashboard to render one modal.
     */
    const CHANGELOG_MAX_LENGTH = 30000;

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

    protected function checkReallySimpleSSLProUpdates($transient)
    {
        if (!defined('rsssl_plugin') || !defined('rsssl_pro')) {
            return $transient;
        }

        if (!empty($transient->response[rsssl_plugin])) {
            return $transient;
        }

        try {
            $rsslUpdate = (new Compatibility\ReallySimpleSSLProUpdate())->checkUpdate();

            if ($rsslUpdate !== null && !empty($rsslUpdate->response[rsssl_plugin])) {
                $transient->response[rsssl_plugin] = $rsslUpdate->response[rsssl_plugin];
                $transient->checked[rsssl_plugin] = $rsslUpdate->checked[rsssl_plugin] ?? rsssl_version;
            }
        } catch (\Exception $e) {
            // Best-effort: if RSS Pro update detection fails, continue without it.
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

        // Enrich with premium plugin updates (Divi, Elementor Pro, YITH, etc.)
        $current = (new PremiumUpdateDetector())->enrich($current, 'plugins');

        // Really Simple SSL Pro guards its updater behind admin checks.
        // Manually check for updates when running in our non-admin context.
        $current = $this->checkReallySimpleSSLProUpdates($current);

        if (!empty($current->response)) {
            $pluginsByKey = array_column($data, 'key');

            foreach ($current->response as $pluginPath => $value) {
                if ($pluginPath === 'secupress-pro/secupress-pro.php') {
                    $current = $this->checkSecupressUpdates($current);
                }

                $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginPath, false, false);
                if (strlen($pluginData['Name']) > 0 && strlen($pluginData['Version']) > 0) {
                    $index = array_search($pluginPath, $pluginsByKey);

                    if ($index !== false && isset($current->response[$pluginPath])) {
                        $current->response[$pluginPath]->name = $pluginData['Name'];
                        $current->response[$pluginPath]->old_version = $pluginData['Version'];
                        $current->response[$pluginPath]->file = $pluginPath;

                        $data[$index]['update'] = $current->response[$pluginPath];
                    }
                }
            }
        } else {
            $needUpdates = get_plugin_updates();

            $pluginsByName = array_column($data, 'Name');
            if (!empty($needUpdates)) {
                foreach ($needUpdates as $plugin) {
                    $index = array_search($plugin->Name, $pluginsByName);
                    if ($index !== false) {
                        $data[$index]['update'] = $plugin->update;
                    }
                }
            }
        }

        $data = $this->addBlockedUpdates($data, $current);

        Morphism::setMapper('WPUmbrella\DataTransferObject\Plugin', $schema);

        return Morphism::map('WPUmbrella\DataTransferObject\Plugin', $data);
    }

    /**
     * @param array $data
     * @param object|false $transient
     * @return array
     */
    protected function addBlockedUpdates($data, $transient)
    {
        if (!is_object($transient) || empty($transient->no_update)) {
            return $data;
        }

        $pluginsByKey = array_column($data, 'key');

        foreach ($transient->no_update as $pluginPath => $entry) {
            $newVersion = is_object($entry) && isset($entry->new_version) ? $entry->new_version : null;
            if (!$this->isVersionString($newVersion)) {
                continue;
            }

            $index = array_search($pluginPath, $pluginsByKey);
            if ($index === false || isset($data[$index]['update'])) {
                continue;
            }

            $installedVersion = isset($data[$index]['Version']) ? $data[$index]['Version'] : '';
            if (!$this->isVersionString($installedVersion)) {
                continue;
            }

            if (version_compare($newVersion, $installedVersion, '<=')) {
                continue;
            }

            $reason = $this->getBlockedUpdateReason($entry);
            if ($reason === null) {
                continue;
            }

            $update = clone $entry;
            $update->name = $data[$index]['Name'];
            $update->old_version = $installedVersion;
            $update->file = $pluginPath;
            $update->is_blocked = true;
            $update->blocked_reason = $reason;

            $data[$index]['update'] = $update;
        }

        return $data;
    }

    /**
     * The update_plugins transient is shared with every third-party updater, so
     * an entry can hold anything: arrays, objects, null. Every value read from it
     * goes through here before reaching version_compare() or the is_*_compatible()
     * helpers, which fatal on a non-string in PHP 8.
     *
     * @param mixed $version
     * @return bool
     */
    protected function isVersionString($version)
    {
        return is_string($version) && $version !== '';
    }

    /**
     * @param object $entry
     * @return string|null
     */
    protected function getBlockedUpdateReason($entry)
    {
        $requiresWordPress = isset($entry->requires) ? $entry->requires : '';
        if ($this->isVersionString($requiresWordPress)
            && function_exists('is_wp_version_compatible')
            && !is_wp_version_compatible($requiresWordPress)
        ) {
            return 'wordpress_version';
        }

        $requiresPhp = isset($entry->requires_php) ? $entry->requires_php : '';
        if ($this->isVersionString($requiresPhp)
            && function_exists('is_php_version_compatible')
            && !is_php_version_compatible($requiresPhp)
        ) {
            return 'php_version';
        }

        return null;
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

        $update = null;
        if (!empty($needUpdates) && is_array($needUpdates)) {
            if (isset($needUpdates[$plugin])) {
                $update = $needUpdates[$plugin]->update;
                $data['update'] = $update;
            }
        }

        $informations = $this->getPluginChangelog($plugin, [
            'update_slug' => is_object($update) && isset($update->slug) ? $update->slug : null,
        ]);

        $changelog = $this->getChangelogFromInformation($informations);

        if ($changelog !== null) {
            $truncated = $this->truncateChangelog($changelog);

            $data['changelog'] = $truncated['changelog'];
            $data['changelog_truncated'] = $truncated['truncated'];
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

    /**
     * @param string $plugin Plugin file, eg. woocommerce-subscriptions/woocommerce-subscriptions.php
     * @param array $options
     * @return object|null
     */
    public function getPluginChangelog($plugin, $options = [])
    {
        if (empty($plugin)) {
            return null;
        }

        if (!function_exists('plugins_api')) {
            require_once \ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        foreach ($this->getPluginInformationSlugs($plugin, $options) as $slug) {
            $api = \plugins_api('plugin_information', [
                'slug' => $slug,
                'fields' => [
                    'sections' => true,
                    'changelog' => true,
                ]
            ]);

            if (!is_wp_error($api) && $this->getChangelogFromInformation($api) !== null) {
                return $api;
            }
        }

        return apply_filters('wp_umbrella_plugin_information', null, $plugin);
    }

    /**
     * plugins_api() is filtered by every third-party updater on the site, so
     * "sections" comes back as an array, as an object, or not at all. Reading
     * it with an array offset fatals in PHP 8 when an updater hands us an
     * object, and isset() does not protect against that.
     *
     * @param mixed $informations
     * @return string|null
     */
    protected function getChangelogFromInformation($informations)
    {
        if (!is_object($informations) || !isset($informations->sections)) {
            return null;
        }

        $sections = $informations->sections;

        if (is_object($sections)) {
            $sections = get_object_vars($sections);
        }

        if (!is_array($sections)) {
            return null;
        }

        if (empty($sections['changelog']) || !is_string($sections['changelog'])) {
            return null;
        }

        return $sections['changelog'];
    }

    /**
     * Keep the most recent releases and drop the tail of the history, cutting
     * on a version heading so the markup stays whole. A reader comparing what
     * they run against what they would install never needs the rest.
     *
     * @param string $changelog
     * @return array{changelog: string, truncated: bool}
     */
    protected function truncateChangelog($changelog)
    {
        if (!is_string($changelog) || strlen($changelog) <= self::CHANGELOG_MAX_LENGTH) {
            return [
                'changelog' => is_string($changelog) ? $changelog : '',
                'truncated' => false,
            ];
        }

        $blocks = preg_split('/(?=<h[1-4][\s>])/i', $changelog);

        if (is_array($blocks) && count($blocks) > 1) {
            $kept = '';

            foreach ($blocks as $block) {
                if ($kept !== '' && strlen($kept) + strlen($block) > self::CHANGELOG_MAX_LENGTH) {
                    break;
                }

                $kept .= $block;
            }

            if ($kept !== '' && strlen($kept) < strlen($changelog)) {
                return [
                    'changelog' => $kept,
                    'truncated' => true,
                ];
            }
        }

        // No version heading to cut on, or a first release longer than the cap.
        // Fall back to a hard cut moved back to the last tag boundary, so the
        // markup is never split in the middle of a tag.
        $cut = substr($changelog, 0, self::CHANGELOG_MAX_LENGTH);
        $lastTag = strrpos($cut, '<');

        if ($lastTag !== false && $lastTag > 0) {
            $cut = substr($cut, 0, $lastTag);
        }

        return [
            'changelog' => $cut,
            'truncated' => true,
        ];
    }

    /**
     * The slug plugins_api() answers to is not always the plugin directory.
     * A plugin distributed outside wordpress.org registers its own plugins_api
     * filter and only recognises the slug its updater wrote in the
     * update_plugins transient: woocommerce.com extensions, for instance, are
     * known as "woocommerce-com-<slug>" and ignore the directory name. That
     * transient slug is what wp-admin uses to build its "View details" link,
     * so it comes first here.
     *
     * @param string $plugin
     * @param array $options
     * @return array
     */
    protected function getPluginInformationSlugs($plugin, $options = [])
    {
        $slugs = [];

        $updateSlug = isset($options['update_slug']) ? $options['update_slug'] : null;
        if (is_string($updateSlug) && $updateSlug !== '') {
            $slugs[] = $updateSlug;
        }

        $slugExplode = explode('/', $plugin);
        if (isset($slugExplode[0]) && $slugExplode[0] !== '') {
            $slugs[] = $slugExplode[0];
        }

        $slugs[] = $plugin;

        return array_unique($slugs);
    }
}
