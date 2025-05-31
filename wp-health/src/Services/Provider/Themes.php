<?php
namespace WPUmbrella\Services\Provider;

use Morphism\Morphism;

class Themes
{
    const NAME_SERVICE = 'ThemesProvider';

    protected function setThemeDirectories()
    {
        global $wp_theme_directories;

        // When the plugin is MU-loaded, the WordPress theme directories are not set.
        if (empty($wp_theme_directories)) {
            // Register the default theme directory root.
            register_theme_directory(get_theme_root());
        }
    }

    public function checkDiviTheme()
    {
        if (!defined('ET_CORE_URL') || !defined('ET_CORE_VERSION')) {
            return;
        }

        $transientDivi = get_site_transient('et_update_themes');

        $transient = get_site_transient('update_themes');

        if (!class_exists('ET_Core_Updates')) {
            return;
        }

        try {
            if (!isset($GLOBALS['et_core_updates'])) {
                $etCoreUpdate = new \ET_Core_Updates(ET_CORE_URL, ET_CORE_VERSION);
            } else {
                $etCoreUpdate = $GLOBALS['et_core_updates'];
            }

            $lastChecked = $etCoreUpdate->check_themes_updates($transient);

            if (!$transientDivi) {
                return $lastChecked;
            }

            if (!empty($lastChecked->response) && isset($lastChecked->response['Divi'])) {
                if (isset($lastChecked->checked['Divi']) && $lastChecked->response['Divi']['new_version'] !== $lastChecked->checked['Divi']) {
                    // Create a copy of lastChecked before modifying transient
                    $lastCheckedCopy = clone $lastChecked;

                    // Update the transient with Divi transient
                    $transient->checked['Divi'] = $lastChecked->response['Divi']['new_version'];
                    $transient->response['Divi'] = empty($transientDivi->response) ? $lastChecked->response['Divi'] : $transientDivi->response['Divi'];
                    set_site_transient('update_themes', $transient);

                    // Return the unmodified copy
                    return $lastCheckedCopy;
                }
            }

            // After 4.27.1
            if (isset($transientDivi->response['Divi']) && isset($transientDivi->checked['Divi']) && $transientDivi->response['Divi']['new_version'] !== $transientDivi->checked['Divi']) {
                $transientCopy = clone $transient;

                // Update the transient with Divi transient
                $transientCopy->checked['Divi'] = $transientDivi->response['Divi']['new_version'];
                $transientCopy->response['Divi'] = $transientDivi->response['Divi'];
                if (isset($transientCopy->no_update['Divi'])) {
                    unset($transientCopy->no_update['Divi']);
                }

                // Return the unmodified copy
                return $transientCopy;
            }

            return $lastChecked;
        } catch (\Exception $e) {
            return;
        }
    }

    /**
    * Check if update is available.
    *
    * @param mixed $transient
    * @param array $update
    */
    public function checkYootheme($transient, array $update)
    {
        $version = $transient->checked[$update['id']] ?? null;

        $data = $this->fetchDataYootheme($update);

        if (
            !$version || ($version && version_compare($version, $data->version, '<'))
        ) {
            if (!$transient) {
                $transient = new \stdClass();
                $transient->last_checked = time();
            }

            $transient->checked[$update['id']] = $data->version;
            $transient->response[$update['id']] = (array) $data;

            set_site_transient('update_themes', $transient);
        }
    }

    /**
     * Fetches the update data from remote server.
     *
     * @param array $update
     *
     * @return object|false
     */
    public function fetchDataYootheme(array $update)
    {
        $url = parse_url($update['remote']);
        $remote = add_query_arg(['user-agent' => true], $update['remote']);

        if (
            ($response = wp_remote_retrieve_body(wp_remote_get($remote))) &&
            ($body = @json_decode($response))
        ) {
            if ($data = $this->latestVersionYootheme($update, $body->versions ?? [$body])) {
                $data->slug = $update['name'];
                $data->url = $data->url ?? "{$url['scheme']}://{$url['host']}";
                $data->sections = isset($data->sections) ? (array) $data->sections : [];
                $data->banners = isset($data->banners) ? (array) $data->banners : [];
                $data->new_version = $data->version;

                $type = $update['type'] ?? '';
                if (in_array($type, ['plugin', 'theme'])) {
                    $data->$type = $update['name'];
                }

                return $data;
            }
        }

        return false;
    }

    /**
     * Gets the latest version from version array.
     *
     * @param array $update
     * @param array $versions
     *
     * @return object|null
     */
    public function latestVersionYootheme(array $update, array $versions)
    {
        $stabilities = ['stable'];

        // add preferred stability
        if (isset($update['stability'])) {
            $stabilities[] = $update['stability'];
        }

        // sort versions, the newest first
        usort($versions, function ($a, $b) {
            return version_compare($a->version, $b->version) * -1;
        });

        // get the latest version with preferred stability
        foreach ($versions as $version) {
            if (
                isset($version->php_minimum) &&
                version_compare(PHP_VERSION, $version->php_minimum, '<')
            ) {
                continue;
            }

            if (empty($version->stability)) {
                $version->stability = 'stable';
            }

            if (in_array($version->stability, $stabilities, true)) {
                return $version;
            }
        }

        return null;
    }

    public function getThemes()
    {
        require_once ABSPATH . '/wp-admin/includes/theme.php';

        $this->setThemeDirectories();

        // Check for YOOtheme themes
        if (class_exists('\YOOtheme\Theme\Wordpress\ThemeLoader', false) || is_dir(get_theme_root() . '/yootheme')) {
            $this->checkYootheme(get_site_transient('update_themes'), [
                'remote' => 'https://yootheme.com/api/update/yootheme_wp',
                'id' => 'yootheme',
                'name' => 'yootheme',
                'stability' => 'stable'
            ]);
        }

        // Check for Elegant Themes
        if (defined('ET_CORE_URL') && defined('ET_CORE_VERSION')) {
            $lastChecked = $this->checkDiviTheme();
        }

        $themes = wp_get_themes();

        if (defined('ET_CORE_URL') && defined('ET_CORE_VERSION')) { // Divi theme
            if (!empty($lastChecked->response) && isset($lastChecked->response['Divi'])) {
                if ($lastChecked->response['Divi']['new_version'] !== $lastChecked->checked['Divi']) {
                    $themes['Divi']['latest_version'] = $lastChecked->response['Divi']['new_version'];
                }

                if (is_null($themes['Divi']['latest_version'])) {
                    $themes['Divi']['response'] = $lastChecked->response['Divi']['new_version'];
                }
            }
        }

        $active = get_option('current_theme');
        $stylesheet = get_stylesheet();

        if (function_exists('get_site_transient') && $transient = get_site_transient('update_themes')) {
            $current = $transient;
        } elseif ($transient = get_transient('update_themes')) {
            $current = $transient;
        } else {
            $current = get_option('update_themes');
        }

        $siteTransient = get_site_transient('update_themes');

        foreach ((array) $themes as $key => $theme) {
            $themeStylesheet = $theme->get_stylesheet();
            $new_version = isset($current->response[$themeStylesheet]) ? $current->response[$themeStylesheet]['new_version'] : null;

            $theme_array = [
                'name' => $theme->get('Name'),
                'active' => $active == $theme->get('Name') || $stylesheet === $themeStylesheet,
                'template' => $theme->get_template(),
                'stylesheet' => $themeStylesheet, // "stylesheet", for some reason. This is the theme identifier; ie. "twentytwelve".
                'screenshot' => $theme->get_screenshot(),
                'author_uri' => $theme->get('AuthorURI'),
                'author' => $theme->get('Author'),
                'latest_version' => $new_version ? $new_version : $theme->get('Version'),
                'version' => $theme->get('Version'),
                'theme_uri' => $theme->get('ThemeURI'),
                'require_wp' => $theme->get('RequiresWP'),
                'package' => isset($siteTransient->response[$themeStylesheet]) && isset($siteTransient->response[$themeStylesheet]['package']) ? $siteTransient->response[$themeStylesheet]['package'] : null,
                'requires_php' => $theme->get('RequiresPHP'),
            ];

            $themes[$key] = $theme_array;
        }

        return array_values($themes);
    }

    public function getCurrentTheme()
    {
        require_once ABSPATH . '/wp-admin/includes/theme.php';

        $this->setThemeDirectories();

        $theme = wp_get_theme();

        if ($theme instanceof \WP_Theme) {
            return  [
                'name' => $theme->get('Name'),
                'active' => true,
                'template' => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet(),
                'screenshot' => $theme->get_screenshot(),
                'author_uri' => $theme->get('AuthorURI'),
                'author' => $theme->get('Author'),
                'version' => $theme->get('Version'),
                'theme_uri' => $theme->get('ThemeURI'),
                'require_wp' => $theme->get('RequiresWP'),
                'requires_php' => $theme->get('RequiresPHP'),
            ];
        }

        return get_option('current_theme');
    }
}
