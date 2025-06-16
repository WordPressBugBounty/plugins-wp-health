<?php
namespace WPUmbrella\DataTransferObject;

#[AllowDynamicProperties]
class Plugin
{
    public $name;
    public $slug;
    public $is_active;
    public $key;
    public $version;
    public $require_wp_version;
    public $require_php_version;
    public $title;
    public $changelog;
    public $need_update;

    public function getPropertiesValues()
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'changelog' => $this->changelog,
            'is_active' => $this->is_active,
            'key' => $this->key,
            'version' => $this->version,
            'require_wp_version' => $this->require_wp_version,
            'require_php_version' => $this->require_php_version,
            'title' => $this->title,
            'need_update' => $this->need_update,
        ];
    }
}
