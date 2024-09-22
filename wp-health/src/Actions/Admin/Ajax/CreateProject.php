<?php
namespace WPUmbrella\Actions\Admin\Ajax;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Actions\Admin\Option;

class CreateProject implements ExecuteHooks
{
    public function hooks()
    {
		add_action('wp_ajax_wp_umbrella_create_project', [$this, 'handle']);
    }

    public function handle()
    {
		if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }


        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_create_project')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : null;
		if(!$token){
			wp_send_json_error([
                'code' => 'missing_parameters',
            ]);
            exit;
		}

		$httpAuthUser = isset($_POST['http_auth_user']) ? sanitize_text_field($_POST['http_auth_user']) : null;
		$httpAuthPassword = isset($_POST['http_auth_password']) ? sanitize_text_field($_POST['http_auth_password']) : null;
		if($httpAuthUser === Option::SECURED_VALUE){
			$httpAuthUser = null;
		}
		if($httpAuthPassword === Option::SECURED_VALUE){
			$httpAuthPassword = null;
		}


        $name = get_bloginfo('name');
        $hosting = wp_umbrella_get_service('HostResolver')->getCurrentHost();

		// Necessary for call rest_url(); with universal request
		wp_umbrella_get_service('WordPressContext')->requireWpRewrite();

		$secretToken = wp_umbrella_generate_random_string(128);
		$newOptions = wp_umbrella_get_service('Option')->getOptions([
			"secure" => false
		]);

		$newOptions['api_key'] = $token;
		$newOptions['secret_token'] = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);
		wp_umbrella_get_service('Option')->setOptions($newOptions);
		wp_cache_flush(); // Flush cache to get new options
		wp_load_alloptions( true );

        $data = [
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

        $response = wp_umbrella_get_service('Projects')->createProjectOnApplication($data, $token);

		if (!is_array($response)) {
			$newOptions["secret_token"] = "";
			$newOptions["api_key"] = "";
			$newOptions["project_id"] = "";
			wp_umbrella_get_service('Option')->setOptions($newOptions);
            wp_send_json(['success' => false, 'code' => 'failed_connect_api']);
			exit;
        }

		if(isset($response['code']) && !$response['code'] !== 'success'){
			$newOptions["secret_token"] = "";
			$newOptions["api_key"] = "";
			$newOptions["project_id"] = "";
			wp_umbrella_get_service('Option')->setOptions($newOptions);
			wp_send_json(['success' => false, 'code' => 'failed_connect_api']);
			exit;
		}

        if (!isset($response['result'])) {
            wp_send_json($response);
			exit;
        }

        $newOptions['allowed'] = true;
        $newOptions['project_id'] = $response['result']['id'];

        wp_umbrella_get_service('Option')->setOptions($newOptions);

        return wp_send_json($response);
    }
}
