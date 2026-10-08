<?php
namespace WPUmbrella\Services\Provider\Compatibility;

class PremiumUpdateDetector
{
    /**
     * @param object|false $transient
     * @param string $type 'plugins' or 'themes'
     * @return object
     */
    public function enrich($transient, $type)
    {
        if (!is_object($transient)) {
            $transient = new \stdClass();
            $transient->last_checked = time();
            $transient->checked = [];
            $transient->response = [];
        }

        try {
            $enriched = apply_filters("pre_set_site_transient_update_{$type}", $transient, "update_{$type}");
        } catch (\Exception $e) {
            return $transient;
        }

        if (!is_object($enriched) || empty($enriched->response)) {
            return $transient;
        }

        foreach ($enriched->response as $basename => $updateInfo) {
            if (!isset($transient->response[$basename])) {
                $transient->response[$basename] = $updateInfo;
            }
        }

        return $transient;
    }

    public function cacheTransients()
    {
        foreach (['plugins', 'themes'] as $type) {
            $transient = get_site_transient("update_{$type}");

            if (!is_object($transient)) {
                continue;
            }

            update_option("wp_umbrella_transient_update_{$type}", $transient, false);
        }
    }

    public function cacheAdminPluginUpdates()
    {
        $transient = get_site_transient('update_plugins');
        $response = is_object($transient) && isset($transient->response) && is_array($transient->response) ? $transient->response : [];

        update_option('wp_umbrella_admin_update_plugins', $response, false);
    }

    /**
     * @return array
     */
    public function getAdminPluginUpdates()
    {
        $response = get_option('wp_umbrella_admin_update_plugins', []);

        return is_array($response) ? $response : [];
    }

    public function restoreTransients()
    {
        foreach (['plugins', 'themes'] as $type) {
            add_filter("site_transient_update_{$type}", function ($value) use ($type) {
                if (is_object($value) && (!empty($value->response) || !empty($value->updates))) {
                    return $value;
                }

                $cached = get_option("wp_umbrella_transient_update_{$type}");

                if (is_object($cached)) {
                    return $cached;
                }

                return $value;
            });
        }
    }
}
