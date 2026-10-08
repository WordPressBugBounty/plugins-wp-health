<?php
namespace WPUmbrella\Services\Provider\Compatibility;

class SeedProdUpdater
{
    const HOOK = 'pre_set_site_transient_update_plugins';

    /** @var bool */
    protected $checked = false;

    /** @var object|null */
    protected $update = null;

    public function register()
    {
        if (!defined('SEEDPROD_PRO_VERSION') || !defined('SEEDPROD_PRO_SLUG') || !function_exists('seedprod_pro_updater')) {
            return;
        }

        try {
            remove_action('admin_init', 'seedprod_pro_updater', 0);
            seedprod_pro_updater();

            add_filter(self::HOOK, [$this, 'normalizeUpdate'], 20);
        } catch (\Throwable $e) {
            // Best-effort.
        }
    }

    protected function keepFirstAnswer($transient)
    {
        if (!$this->checked) {
            $this->checked = true;
            $this->update = isset($transient->response[SEEDPROD_PRO_SLUG]) && is_object($transient->response[SEEDPROD_PRO_SLUG])
                ? $transient->response[SEEDPROD_PRO_SLUG]
                : null;
            $this->removeSeedProdFilter();

            return $transient;
        }

        if ($this->update !== null
            && empty($transient->response[SEEDPROD_PRO_SLUG])
            && isset($transient->checked[SEEDPROD_PRO_SLUG], $this->update->new_version)
            && version_compare($transient->checked[SEEDPROD_PRO_SLUG], $this->update->new_version, '<')
        ) {
            $transient->response[SEEDPROD_PRO_SLUG] = $this->update;
        }

        return $transient;
    }

    protected function removeSeedProdFilter()
    {
        global $wp_filter;

        if (!isset($wp_filter[self::HOOK]) || !class_exists('SeedProd_Updater')) {
            return;
        }

        foreach ($wp_filter[self::HOOK]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && $callback['function'][0] instanceof \SeedProd_Updater) {
                    remove_filter(self::HOOK, $callback['function'], $priority);
                }
            }
        }
    }

    /**
     * @param mixed $transient
     * @return mixed
     */
    public function normalizeUpdate($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        $transient = $this->keepFirstAnswer($transient);

        if (empty($transient->response[SEEDPROD_PRO_SLUG]) || !is_object($transient->response[SEEDPROD_PRO_SLUG])) {
            return $transient;
        }

        $update = $transient->response[SEEDPROD_PRO_SLUG];
        $slug = dirname(SEEDPROD_PRO_SLUG);
        $originalSlug = isset($update->slug) ? (string) $update->slug : '';
        $originalPlugin = isset($update->plugin) ? (string) $update->plugin : '';

        if ($originalSlug === $slug && $originalPlugin === SEEDPROD_PRO_SLUG) {
            return $transient;
        }

        $update->slug = $slug;
        $update->plugin = SEEDPROD_PRO_SLUG;
        $update->wp_umbrella_normalized = [
            'slug' => $originalSlug,
            'plugin' => $originalPlugin,
            'by' => 'seedprod-pro',
        ];

        return $transient;
    }
}
