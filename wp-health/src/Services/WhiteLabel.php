<?php
namespace WPUmbrella\Services;

use WPUmbrella\Helpers\DataTemporary;

class WhiteLabel
{
    protected $key = 'wp_umbrella_white_label_data_cache';

    public function getDefaultData()
    {
        return [
            'hide_plugin' => false,
            'plugin_name' => __('WP Umbrella', 'wp-health'),
            'plugin_description' => __('WP Umbrella is the ultimate all-in-one solution to manage, maintain and monitor one, or multiple WordPress websites.', 'wp-health'),
            'plugin_author' => 'WP Umbrella',
            'plugin_author_url' => 'https://wp-umbrella.com/',
            'logo' => 'https://wp-umbrella.com/wp-content/themes/wp-umbrella/public/images/logo-full.svg',
            'catchphrase' => __('Helping Agencies and Freelancers with their WordPress Maintenance Business 🚀', 'wp-health'),
            'catchphrase_2' => __('Now go to WP Umbrella’s application to make the most of our features (automatic backups, uptime monitoring, safe update, php error monitoring, maintenance report, etc).  You can white label the plugin at any moment!', 'wp-health'),
            'view_company_details' => false,
            'view_api_box' => true,
            'email_support' => ''
        ];
    }

    public function hideMenu($withCache = true)
    {
        $data = $this->getData($withCache);
        return apply_filters('wp_umbrella_white_label_hide_menu', $data['hide_plugin']);
    }

    public function setData($data)
    {
        set_transient($this->key, $data, apply_filters($this->key . '_duration', MINUTE_IN_SECONDS * 60));
    }

    public function getData($withCache = true)
    {
        $data = DataTemporary::getDataByKey($this->key);
        if ($data !== null && apply_filters($this->key . '_active', $withCache)) {
            return apply_filters('wp_umbrella_white_label_data', $data);
        }

        $cacheData = get_transient($this->key);
        if ($cacheData && apply_filters($this->key . '_active', $withCache)) {
            DataTemporary::setDataByKey($this->key, $cacheData);
            return apply_filters('wp_umbrella_white_label_data', $cacheData);
        }

        $default = $this->getDefaultData();

        if ($data === null) {
            $owner = wp_umbrella_get_service('Owner')->getOwnerImplicitApiKey();

            if (!isset($owner['white_label'])) {
                $owner['white_label'] = $default;
            }

            $data = $owner['white_label'];
        }

        $this->setData($data);
        DataTemporary::setDataByKey($this->key, $data);

        return apply_filters('wp_umbrella_white_label_data', $data);
    }
}
