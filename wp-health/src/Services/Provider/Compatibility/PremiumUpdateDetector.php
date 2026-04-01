<?php
namespace WPUmbrella\Services\Provider\Compatibility;

class PremiumUpdateDetector
{
    /**
     * Detect premium plugin/theme updates via pre_set_site_transient filter.
     *
     * Premium plugins (Elementor Pro, YITH, ACF Pro, Divi, Avada, etc.) inject
     * their update info via `pre_set_site_transient_update_*` when the transient
     * is saved. We simulate this to capture updates not in WordPress.org.
     *
     * @param object|false $transient The current update transient
     * @param string $type 'plugins' or 'themes'
     * @return object The enriched transient
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

    /**
     * Cache update transients to wp_options as fallback.
     *
     * Sites with ext_object_cache enabled but no persistent backend (e.g. Redis
     * without persistence) lose transients between requests. This saves a copy
     * so we can restore it later via `restoreTransients()`.
     */
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

    /**
     * Restore cached update transients when WordPress returns empty data.
     *
     * Hooks into `site_transient_update_*` to provide our cached copy when
     * the real transient is missing (common on unreliable object cache setups).
     */
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
