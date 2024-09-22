<?php
namespace WPUmbrella\Services\Provider;

if (!defined('ABSPATH')) {
    exit;
}

use Morphism\Morphism;

class Languages
{
    const NAME_SERVICE = 'LanguagesProvider';

    public function getData()
    {
        $updates = [
            'core' => [],
            'plugins' => [],
            'themes' => [],
        ];

        $transients = ['update_core' => 'core', 'update_plugins' => 'plugins', 'update_themes' => 'themes'];

        foreach ($transients as $transient => $type) {
            $transient = get_site_transient($transient);

            if (empty($transient->translations)) {
                continue;
            }

            foreach ($transient->translations as $translation) {
                $updates[$type][] = (object)$translation;
            }
        }

        return $updates;
    }
}
