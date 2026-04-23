<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class ThemeCollector implements CollectorInterface
{
    public function getId()
    {
        return 'theme_info';
    }

    public function collect()
    {
        try {
            $provider = wp_umbrella_get_service('ThemesProvider');

            if (!$provider || !method_exists($provider, 'getThemes')) {
                return $this->emptyResult();
            }

            $themes = $provider->getThemes();

            if (!is_array($themes)) {
                return $this->emptyResult();
            }

            $activeTheme = null;
            $parentTheme = null;

            foreach ($themes as $theme) {
                if (!is_array($theme) || empty($theme['active'])) {
                    continue;
                }

                $hasUpdate = isset($theme['latest_version'])
                    && isset($theme['version'])
                    && $theme['latest_version'] !== $theme['version'];

                $activeTheme = [
                    'name' => isset($theme['name']) ? $theme['name'] : '',
                    'version' => isset($theme['version']) ? $theme['version'] : '',
                    'author' => isset($theme['author']) ? $theme['author'] : '',
                    'template' => isset($theme['template']) ? $theme['template'] : '',
                    'update_available' => $hasUpdate,
                    'latest_version' => isset($theme['latest_version']) ? $theme['latest_version'] : null,
                ];

                if (!empty($theme['template']) && isset($theme['stylesheet']) && $theme['template'] !== $theme['stylesheet']) {
                    foreach ($themes as $candidate) {
                        if (is_array($candidate) && isset($candidate['stylesheet']) && $candidate['stylesheet'] === $theme['template']) {
                            $parentTheme = [
                                'name' => isset($candidate['name']) ? $candidate['name'] : '',
                                'version' => isset($candidate['version']) ? $candidate['version'] : '',
                            ];
                            break;
                        }
                    }
                }

                break;
            }

            return [
                'active_theme' => $activeTheme,
                'parent_theme' => $parentTheme,
            ];
        } catch (\Exception $e) {
            return $this->emptyResult();
        }
    }

    protected function emptyResult()
    {
        return [
            'active_theme' => null,
            'parent_theme' => null,
        ];
    }
}
