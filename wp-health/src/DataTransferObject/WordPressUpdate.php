<?php
namespace WPUmbrella\DataTransferObject;

if (!defined('ABSPATH')) {
    exit;
}

#[AllowDynamicProperties]
class WordPressUpdate
{
    public $is_up_to_date;
    public $wordpress_version;
    public $hosting;
    public $is_ssl;
    public $is_multisite;
    public $disk_free_space;
    public $memory_limit;
    public $urls;
    public $php_version;
    public $php_extensions;
    public $is_indexable;
    public $curl_is_defined;
    public $zip_is_defined;
    public $defined_data;
    public $base_directory;
    public $latest;
}
