<?php
namespace WPUmbrella\Services;

class Register
{
    protected $optionService;

    public function __construct()
    {
        $this->optionService = wp_umbrella_get_service('Option');
    }

    /**
     * @param array $data
     *
     * @return User|null
     */
    public function register($data)
    {
        $hosting = wp_umbrella_get_service('HostResolver')->getCurrentHost();

        $secretToken = wp_umbrella_generate_random_string(128);

        if (!wp_umbrella_is_new_hash()) {
            wp_umbrella_init_new_hash();
        }

        $hashSecretToken = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);

        $options = wp_umbrella_get_options([
            'secure' => false
        ]);

        $options['secret_token'] = $hashSecretToken;
        $this->optionService->setOptions($options);

        $response = wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/register', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'email' => $data['email'],
                'password' => $data['password'],
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'hosting' => $hosting,
                'newsletters' => $data['newsletters'],
                'with_project' => true,
                'base_url' => site_url(),
                'home_url' => home_url(),
                'secret_token' => $secretToken,
                'project_name' => get_bloginfo('name'),
                'terms' => true,
            ]),
            'timeout' => 50,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body['success']) {
            return $body;
        }

        $user = $body['result'];

        $options = $this->optionService->getOptions();
        if (isset($user['token']['accessToken'])) {
            $options['api_key'] = $user['token']['accessToken'];
            $options['secret_token'] = $hashSecretToken;
            $options['allowed'] = true;
        }

        if (isset($user['project'])) {
            $options['project_id'] = $user['project']['id'];
        }

        $this->optionService->setOptions($options);

        return $user;
    }
}
