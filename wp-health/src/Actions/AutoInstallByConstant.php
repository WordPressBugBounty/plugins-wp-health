<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;

class AutoInstallByConstant implements ExecuteHooks, ActivationHook
{
    protected $optionService;

    protected $getOwnerService;

    public function __construct()
    {
        $this->optionService = wp_umbrella_get_service('Option');
        $this->getOwnerService = wp_umbrella_get_service('Owner');
    }

    public function hooks()
    {
        add_action('init', [$this, 'handleAutoInstall']);
    }

    public function activate()
    {
        $this->handleAutoInstall();
    }

    protected function successAutoInstall()
    {
        try {
            if (file_exists(WP_UMBRELLA_DIR_MAIN_FILE) && !is_multisite()) {
                $pluginFileContent = @file_get_contents(WP_UMBRELLA_DIR_MAIN_FILE);
                $pluginFileContent = str_replace('define("WP_UMBRELLA_AUTO_INSTALL_WITH_CONSTANT", true);', '', $pluginFileContent);
                $pluginFileContent = str_replace("define('WP_UMBRELLA_AUTO_INSTALL_WITH_CONSTANT', true);", '', $pluginFileContent);
                @file_put_contents(WP_UMBRELLA_DIR_MAIN_FILE, $pluginFileContent);

                delete_option('wp_umbrella_number_trial_auto_install');
            }
        } catch (\Exception $e) {
            // No black magic
        }
    }

    public function handleAutoInstall()
    {
        if (!defined('WP_UMBRELLA_AUTO_INSTALL_WITH_CONSTANT')) {
            return;
        }

        if (!WP_UMBRELLA_AUTO_INSTALL_WITH_CONSTANT) {
            return;
        }

        if (!defined('WP_UMBRELLA_API_KEY')) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        $apiKey = wp_umbrella_get_api_key();

        if (!empty($apiKey)) {
            return;
        }

        $numberTrialAutoInstall = get_option('wp_umbrella_number_trial_auto_install', 0);

        if ($numberTrialAutoInstall > 3) {
            return;
        }

        $numberTrialAutoInstall++;
        update_option('wp_umbrella_number_trial_auto_install', $numberTrialAutoInstall, false);

        $apiKey = WP_UMBRELLA_API_KEY;
        $httpAuthUser = defined('WP_UMBRELLA_HTTP_AUTH_USER') ? WP_UMBRELLA_HTTP_AUTH_USER : null;
        $httpAuthPassword = defined('WP_UMBRELLA_HTTP_AUTH_PASSWORD') ? WP_UMBRELLA_HTTP_AUTH_PASSWORD : null;

        $data = $this->getOwnerService->validateApiKeyOnApplication([
            'api_key' => $apiKey,
        ]);

        $secretToken = wp_umbrella_generate_random_string(128);

        $options = [
            'allowed' => false,
            'api_key' => $apiKey,
            'project_id' => '',
            'secret_token' => wp_umbrella_get_service('WordPressContext')->getHash($secretToken),
        ];

        $owner = $data['result'];

        if (isset($owner['total_projects']) && isset($owner['limit_projects'])) {
            if ($owner['total_projects'] >= $owner['limit_projects']) {
                return;
            }
        }

        // Project exist, need to regenerate secret token
        if ($data && !isset($data['code']) && isset($owner['project']['id'])) {
            $options['allowed'] = true;
            $options['project_id'] = $owner['project']['id'];

            $this->optionService->setOptions($options);

            $responseValidateSecret = wp_umbrella_get_service('Projects')->validateSecretToken([
                'base_url' => site_url(),
                'rest_url' => rest_url(),
                'secret_token' => $secretToken,
                'http_auth_user' => $httpAuthUser,
                'http_auth_password' => $httpAuthPassword,
            ], $apiKey);

            // Not valid secret token
            if (!is_array($responseValidateSecret) || !isset($responseValidateSecret['success'])) {
                $options['allowed'] = false;
                $options['api_key'] = '';
                $options['project_id'] = '';
                $options['secret_token'] = '';
                $this->optionService->setOptions($options);

                return;
            }

            // No success
            if (!$responseValidateSecret['success']) {
                $options['allowed'] = false;
                $options['api_key'] = '';
                $options['project_id'] = '';
                $options['secret_token'] = '';
                $this->optionService->setOptions($options);

                return;
            }

            $this->optionService->setOptions($options);
            $this->successAutoInstall();
            return;
        } elseif (!isset($owner['project']['id'])) {
            $name = get_bloginfo('name');
            $hosting = wp_umbrella_get_service('HostResolver')->getCurrentHost();

            // Necessary for call rest_url(); with universal request
            wp_umbrella_get_service('WordPressContext')->requireWpRewrite();

            $options['allowed'] = true;

            $this->optionService->setOptions($options);

            $dataCreateProject = [
                'base_url' => site_url(),
                'home_url' => home_url(),
                'rest_url' => rest_url(),
                'http_auth_user' => $httpAuthUser,
                'http_auth_password' => $httpAuthPassword,
                'backdoor_url' => plugins_url(),
                'admin_url' => get_admin_url(),
                'wp_umbrella_url' => WP_UMBRELLA_DIRURL,
                'secret_token' => $secretToken,
                'is_multisite' => is_multisite(),
                'name' => empty($name) ? site_url() : $name,
                'hosting' => $hosting,
            ];

            $response = wp_umbrella_get_service('Projects')->createProjectOnApplication($dataCreateProject, $apiKey);

            if (!is_array($response)) {
                $options['secret_token'] = '';
                $options['api_key'] = '';
                $options['project_id'] = '';
                $options['allowed'] = false;
                $this->optionService->setOptions($options);
                return;
            }

            if (isset($response['code']) && !$response['code'] !== 'success') {
                $options['secret_token'] = '';
                $options['api_key'] = '';
                $options['project_id'] = '';
                $options['allowed'] = false;
                $this->optionService->setOptions($options);
                return;
            }

            if (!isset($response['result'])) {
                $options['secret_token'] = '';
                $options['api_key'] = '';
                $options['project_id'] = '';
                $options['allowed'] = false;
                $this->optionService->setOptions($options);
                return;
            }

            $options['allowed'] = true;
            $options['project_id'] = $response['result']['id'];

            $this->optionService->setOptions($options);

            $this->successAutoInstall();
        }
    }
}
