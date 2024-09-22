<?php
namespace WPUmbrella\Services;

class GetSettingsData
{
    protected $data = null;

    protected $getOwnerService;

    public function __construct()
    {
        $this->getOwnerService = wp_umbrella_get_service('Owner');
    }

    public function getData()
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $owner = null;
        if (wp_umbrella_allowed()) {
            $owner = $this->getOwnerService->getOwnerImplicitApiKey();
        }

        $allowTracking = get_option('wp_health_allow_tracking');
        $disallowOneClickAccess = get_option('wp_umbrella_disallow_one_click_access');
        $options = wp_umbrella_get_options([
            'secure' => false
        ]);

        $whiteLabel = wp_umbrella_get_service('WhiteLabel')->getData();

        $hasHtpasswd = false;
        if (file_exists(ABSPATH . '.htpasswd') && file_exists(ABSPATH . '.htaccess')) {
            try {
                $htaccess = @file_get_contents(ABSPATH . '.htaccess');
                if (strpos($htaccess, 'AuthType Basic') !== false) {
                    $hasHtpasswd = true;
                }
            } catch (\Exception $e) {
                $hasHtpasswd = true;
            }
        }

        $this->data = [
            'api_key' => $options['api_key'] ?? null,
            'secret_token' => $options['secret_token'] ?? null,
            'has_htpasswd' => apply_filters('wp_umbrella_has_htpasswd', $hasHtpasswd) ? true : false,
            'user' => $owner,
            'allow_tracking' => $allowTracking && !empty($allowTracking) ? true : false,
            'allow_one_click_access' => !$disallowOneClickAccess || empty($disallowOneClickAccess) ? true : false,
            'white_label' => $whiteLabel
        ];

        return $this->data;
    }
}
