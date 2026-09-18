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

    public function setData($data, $duration = null)
    {
        if ($duration === null) {
            $duration = MINUTE_IN_SECONDS * 60;
        }

        set_transient($this->key, $data, apply_filters($this->key . '_duration', $duration));
    }

    public function getData($withCache = true)
    {
        $withCache = apply_filters($this->key . '_active', $withCache);

        if ($withCache) {
            $data = DataTemporary::getDataByKey($this->key);
            if ($data !== null) {
                return apply_filters('wp_umbrella_white_label_data', $data);
            }

            $cacheData = get_transient($this->key);
            if ($cacheData) {
                DataTemporary::setDataByKey($this->key, $cacheData);
                return apply_filters('wp_umbrella_white_label_data', $cacheData);
            }
        }

        $owner = wp_umbrella_get_service('Owner')->getOwnerImplicitApiKey();

        // An account with no white label answers with the key and no value,
        // which is an answer. A call that failed carries no key at all.
        $answered = is_array($owner) && array_key_exists('white_label', $owner);

        $data = $answered && !empty($owner['white_label'])
            ? $owner['white_label']
            : $this->getDefaultData();

        // A call that never answered is retried sooner: holding the defaults
        // for the usual hour would show the plugin under its own name for that
        // whole time.
        $this->setData($data, $answered ? null : MINUTE_IN_SECONDS * 5);
        DataTemporary::setDataByKey($this->key, $data);

        return apply_filters('wp_umbrella_white_label_data', $data);
    }
}
