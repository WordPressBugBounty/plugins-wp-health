<?php
namespace WPUmbrella\Services\Provider;

if (!defined('ABSPATH')) {
    exit;
}

class Languages
{
    const NAME_SERVICE = 'LanguagesProvider';

    public function format($translation)
    {
        $translation = (object) $translation;

        return [
            'type' => isset($translation->type) ? (string) $translation->type : '',
            'slug' => !empty($translation->slug) ? (string) $translation->slug : 'default',
            'language' => isset($translation->language) ? (string) $translation->language : '',
            'version' => isset($translation->version) ? (string) $translation->version : '',
            'updated' => isset($translation->updated) ? (string) $translation->updated : '',
        ];
    }

    public function getData()
    {
        if (!function_exists('wp_get_translation_updates')) {
            return [];
        }

        $updates = array_filter(wp_get_translation_updates(), [$this, 'hasPackage']);

        return array_values(array_map([$this, 'format'], $updates));
    }

    /**
     * Language_Pack_Upgrader cannot install a pack without a package URL, so
     * such a pack is never reported as an update.
     */
    public function hasPackage($translation)
    {
        $translation = (object) $translation;

        return !empty($translation->package);
    }
}
