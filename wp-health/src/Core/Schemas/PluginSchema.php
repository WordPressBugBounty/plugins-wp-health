<?php
namespace WPUmbrella\Core\Schemas;

use WPUmbrella\Core\Models\SchemaInterface;

class PluginSchema implements SchemaInterface
{
    public function getSchema($options = [])
    {
        $light = $options['light'] ?? false;

        if (!$light) {
            return [
                'name' => 'Name',
                'slug' => 'slug',
                'is_active' => 'active',
                'key' => 'key',
                'version' => 'Version',
                'require_wp_version' => 'RequiresWP',
                'require_php_version' => 'RequiresPHP',
                'title' => 'Title',
                'changelog' => 'changelog',
                'need_update' => (object) [
                    'path' => 'update',
                    'fn' => function ($data) {
                        if (!$data || !\is_object($data)) {
                            return false;
                        }

                        return [
                            'id' => \property_exists($data, 'id') ? $data->id : '',
                            'slug' => \property_exists($data, 'slug') ? $data->slug : '',
                            'plugin' => \property_exists($data, 'plugin') ? $data->plugin : '',
                            'new_version' => \property_exists($data, 'new_version') ? $data->new_version : '',
                            'url' => \property_exists($data, 'url') ? $data->url : '',
                            'package' => \property_exists($data, 'package') ? $data->package : '',
                            'tested' => \property_exists($data, 'tested') ? $data->tested : '',
                            'requires_php' => \property_exists($data, 'requires_php') ? $data->requires_php : '',
                            'compatibility' => \property_exists($data, 'compatibility') ? $data->compatibility : '',
                        ];
                    },
                ],
            ];
        }

        return [
            'name' => 'Name',
            'slug' => 'slug',
            'is_active' => 'active',
            'key' => 'key',
            'version' => 'Version',
            'require_wp_version' => 'RequiresWP',
            'require_php_version' => 'RequiresPHP',
            'changelog' => 'changelog',
            'title' => 'Title',
        ];
    }
}
