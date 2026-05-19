<?php
namespace WPUmbrella\Actions\Admin\Ajax;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Actions\Admin\Option;

class ValidationApiKey implements ExecuteHooksBackend
{
    protected $optionService;

    protected $getOwnerService;

    public function __construct()
    {
        $this->optionService = \wp_umbrella_get_service('Option');
        $this->getOwnerService = wp_umbrella_get_service('Owner');
    }

    public function hooks()
    {
        add_action('wp_ajax_wp_umbrella_valid_api_key', [$this, 'validate']);
        add_action('wp_ajax_wp_umbrella_check_api_key', [$this, 'check']);
    }

    public function validate()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_valid_api_key')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['api_key'])) {
            wp_send_json_error([
                'code' => 'missing_parameters',
            ]);
            exit;
        }

        $apiKey = sanitize_text_field($_POST['api_key']);
        $httpAuthUser = isset($_POST['http_auth_user']) ? sanitize_text_field($_POST['http_auth_user']) : null;
        $httpAuthPassword = isset($_POST['http_auth_password']) ? sanitize_text_field($_POST['http_auth_password']) : null;

        if ($httpAuthUser === Option::SECURED_VALUE || empty($httpAuthUser)) {
            $httpAuthUser = null;
        }
        if ($httpAuthPassword === Option::SECURED_VALUE || empty($httpAuthPassword)) {
            $httpAuthPassword = null;
        }

        $probe = $this->probeHttpAuthRequirement($httpAuthUser, $httpAuthPassword);

        if ($probe['determined']) {
            if (!$probe['needed']) {
                $httpAuthUser = null;
                $httpAuthPassword = null;
            } elseif (!empty($probe['missing'])) {
                wp_send_json_error([
                    'code' => 'http_auth_required',
                ]);
                return;
            } elseif (empty($probe['valid'])) {
                wp_send_json_error([
                    'code' => 'http_auth_invalid',
                ]);
                return;
            }
        }

        $options['allowed'] = false;
        if (empty($apiKey)) {
            wp_send_json_error([
                'code' => 'missing_parameters',
            ]);
            exit;
        }

        if ($apiKey === Option::SECURED_VALUE) {
            wp_send_json_success([
                'code' => 'success',
                'unchanged' => true,
            ]);
            return;
        }

        $optionsBdd = $this->optionService->getOptions();
        $newOptions = wp_parse_args($options, $optionsBdd);

        try {
            $data = $this->getOwnerService->validateApiKeyOnApplication([
                'api_key' => $apiKey,
            ]);

            if (isset($data['code'])) {
                $newOptions['allowed'] = false;
                $newOptions['api_key'] = '';
                $this->optionService->setOptions($newOptions);

                wp_send_json_error([
                    'code' => 'api_key_invalid',
                ]);
                return;
            }

            if (!isset($data['result'])) {
                wp_send_json_success([
                    'user' => null,
                    'api_key' => $apiKey,
                    'project_id' => null
                ]);

                return;
            }

            $owner = $data['result'];

            if (isset($owner['total_projects']) && isset($owner['limit_projects'])) {
                if ($owner['total_projects'] >= $owner['limit_projects']) {
                    wp_send_json_success([
                        'user' => $owner,
                        'api_key' => $apiKey,
                        'projects' => null,
                        'code' => 'limit_excedeed',
                    ]);
                    return;
                }
            }

            $projectId = isset($owner['project']['id']) ? $owner['project']['id'] : null;
            $code = isset($data['code']) ? $data['code'] : null;

            // Generate a new secret token
            $secretToken = wp_umbrella_generate_random_string(128);

            if (!wp_umbrella_is_new_hash()) {
                wp_umbrella_init_new_hash();
            }

            $newOptions['secret_token'] = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);
            $newOptions['allowed'] = true;
            $newOptions['api_key'] = $apiKey;
            $newOptions['project_id'] = $projectId;

            $this->optionService->setOptions($newOptions);

            wp_cache_flush(); // Flush cache to get new options
            wp_load_alloptions(true);

            // Project exist, need to regenerate secret token
            if ($code === null && $projectId !== null) {
                $responseValidateSecret = wp_umbrella_get_service('Projects')->validateSecretToken([
                    'base_url' => site_url(),
                    'rest_url' => rest_url(),
                    'secret_token' => $secretToken,
                    'http_auth_user' => $httpAuthUser,
                    'http_auth_password' => $httpAuthPassword,
                    'save' => true
                ], $apiKey);

                $code = isset($responseValidateSecret['data']['code']) ? $responseValidateSecret['data']['code'] : null;

                if ($code === null && isset($responseValidateSecret['code'])) {
                    $code = $responseValidateSecret['code'];
                } elseif ($code === null) {
                    $code = 'failed_authorize_wordpress';
                }

                if (!is_array($responseValidateSecret) || !isset($responseValidateSecret['success'])) {
                    $newOptions['allowed'] = false;
                    $newOptions['api_key'] = '';
                    $newOptions['project_id'] = '';
                    $newOptions['secret_token'] = '';
                    $this->optionService->setOptions($newOptions);

                    wp_send_json_error([
                        'code' => $code,
                    ]);
                    return;
                }

                if (!$responseValidateSecret['success']) {
                    $newOptions['allowed'] = false;
                    $newOptions['api_key'] = '';
                    $newOptions['project_id'] = '';
                    $newOptions['secret_token'] = '';
                    $this->optionService->setOptions($newOptions);

                    wp_send_json_error([
                        'code' => $code,
                    ]);
                    return;
                }

                $requestToken = wp_umbrella_request_token_from_response($responseValidateSecret);
                if ($requestToken) {
                    $newOptions['request_token'] = $requestToken;
                    $newOptions['api_key'] = '';
                }

                $this->optionService->setOptions($newOptions);

                wp_send_json_success([
                    'code' => 'success',
                    'user' => $owner,
                    'api_key' => $apiKey,
                ]);
            } elseif ($projectId === null) {
                // Api is valid but project not exist
                $name = get_bloginfo('name');
                $hosting = wp_umbrella_get_service('HostResolver')->getCurrentHost();

                // Necessary for call rest_url(); with universal request
                wp_umbrella_get_service('WordPressContext')->requireWpRewrite();

                $data = [
                    'base_url' => site_url(),
                    'home_url' => home_url(),
                    'rest_url' => rest_url(),
                    'backdoor_url' => plugins_url(),
                    'admin_url' => get_admin_url(),
                    'wp_umbrella_url' => WP_UMBRELLA_DIRURL,
                    'secret_token' => $secretToken,
                    'is_multisite' => is_multisite(),
                    'name' => empty($name) ? site_url() : $name,
                    'hosting' => $hosting,
                ];

                if ($httpAuthUser !== null && $httpAuthPassword !== null) {
                    $data['http_auth_user'] = $httpAuthUser;
                    $data['http_auth_password'] = $httpAuthPassword;
                }

                $response = wp_umbrella_get_service('Projects')->createProjectOnApplication($data, $apiKey);
                $projectId = isset($response['result']['id']) ? $response['result']['id'] : null;

                if ($response['success'] === 'success' && $projectId !== null) {
                    $newOptions['project_id'] = $projectId;
                    $this->optionService->setOptions($newOptions);

                    // Force update option to v4 for new project only
                    update_option('wp_umbrella_backup_version', 'v4', false);

                    wp_send_json_success([
                        'code' => 'success',
                        'user' => $owner,
                        'api_key' => $apiKey,
                    ]);
                    return;
                }

                $newOptions['allowed'] = false;
                $newOptions['api_key'] = '';
                $newOptions['project_id'] = '';
                $newOptions['secret_token'] = '';
                $this->optionService->setOptions($newOptions);

                wp_send_json_error([
                    'code' => $response['code'],
                    'user' => $owner,
                    'api_key' => $apiKey,
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'code' => 'unknown_error',
            ]);
            exit;
        }
    }

    protected function probeHttpAuthRequirement($httpAuthUser, $httpAuthPassword)
    {
        $restUrl = rest_url();

        $args = [
            'timeout' => 5,
            'sslverify' => false,
            'redirection' => 0,
        ];

        $responseNoAuth = wp_remote_get($restUrl, $args);

        if (is_wp_error($responseNoAuth)) {
            error_log('[DEBUG-TOKEN] [probeHttpAuthRequirement] no-auth probe failed: ' . $responseNoAuth->get_error_message());
            return ['determined' => false];
        }

        $status = (int) wp_remote_retrieve_response_code($responseNoAuth);
        $wwwAuth = wp_remote_retrieve_header($responseNoAuth, 'www-authenticate');
        error_log('[DEBUG-TOKEN] [probeHttpAuthRequirement] url=' . $restUrl . ' no-auth status=' . $status . ' www-authenticate=' . (is_array($wwwAuth) ? implode(',', $wwwAuth) : ($wwwAuth ?: '<none>')));

        if ($status >= 200 && $status < 400) {
            return ['determined' => true, 'needed' => false];
        }

        if ($status !== 401) {
            return ['determined' => false];
        }

        if (empty($wwwAuth) || stripos((is_array($wwwAuth) ? implode(',', $wwwAuth) : $wwwAuth), 'basic') === false) {
            return ['determined' => false];
        }

        if (empty($httpAuthUser) || empty($httpAuthPassword)) {
            return ['determined' => true, 'needed' => true, 'missing' => true];
        }

        $argsWithAuth = $args;
        $argsWithAuth['headers'] = [
            'Authorization' => 'Basic ' . base64_encode($httpAuthUser . ':' . $httpAuthPassword),
        ];

        $responseWithAuth = wp_remote_get($restUrl, $argsWithAuth);

        if (is_wp_error($responseWithAuth)) {
            error_log('[DEBUG-TOKEN] [probeHttpAuthRequirement] with-auth probe failed: ' . $responseWithAuth->get_error_message());
            return ['determined' => false];
        }

        $statusWithAuth = (int) wp_remote_retrieve_response_code($responseWithAuth);
        error_log('[DEBUG-TOKEN] [probeHttpAuthRequirement] with-auth status=' . $statusWithAuth);

        if ($statusWithAuth >= 200 && $statusWithAuth < 400) {
            return ['determined' => true, 'needed' => true, 'valid' => true];
        }

        return ['determined' => true, 'needed' => true, 'valid' => false];
    }

    public function check()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_check_api_key')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['api_key'])) {
            wp_send_json_error([
                'code' => 'missing_parameters',
            ]);
            exit;
        }

        $apiKey = sanitize_text_field($_POST['api_key']);

        if (empty($apiKey)) {
            wp_send_json_error([
                'code' => 'missing_parameters',
            ]);
            exit;
        }

        if ($apiKey === Option::SECURED_VALUE) {
            wp_send_json_success([
                'api_key' => null,
                'user' => null,
                'project_id' => null,
                'code' => 'success',
                'unchanged' => true,
            ]);
            return;
        }

        try {
            $data = $this->getOwnerService->validateApiKeyOnApplication([
                'api_key' => $apiKey,
            ]);

            if (isset($data['code'])) {
                wp_send_json_error([
                    'code' => 'api_key_invalid',
                ]);
                return;
            }

            if (!isset($data['result'])) {
                wp_send_json_success([
                    'api_key' => $apiKey,
                    'user' => null,
                    'project_id' => null
                ]);

                return;
            }

            $owner = $data['result'];

            if (isset($owner['total_projects']) && isset($owner['limit_projects'])) {
                if ($owner['total_projects'] >= $owner['limit_projects']) {
                    wp_send_json_success([
                        'user' => $owner,
                        'api_key' => $apiKey,
                        'projects' => null,
                        'code' => 'limit_excedeed',
                    ]);
                    return;
                }
            }

            $workspaces = isset($data['result']['workspaces']) ? $data['result']['workspaces'] : [];

            if ($data && !isset($data['code']) && isset($data['result']['project']['id'])) {
                wp_send_json_success([
                    'code' => 'success',
                    'project_id' => $data['result']['project']['id'],
                    'workspaces' => $workspaces
                ]);

                return;
            } elseif (!isset($data['result']['project']['id'])) {
                wp_send_json_success([
                    'code' => 'project_not_exist',
                    'project_id' => null,
                    'workspaces' => $workspaces
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'code' => 'unknown_error',
            ]);
            exit;
        }
    }
}
