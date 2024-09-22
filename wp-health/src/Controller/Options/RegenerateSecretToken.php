<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class RegenerateSecretToken extends AbstractController
{
    public function executePost($params)
    {
        $secretToken = wp_umbrella_generate_random_string(128);
        $options = wp_umbrella_get_options([
            'secure' => false
        ]);

        $options['api_key'] = wp_umbrella_get_api_key();

        $oldSecretToken = wp_umbrella_get_secret_token();
        $options['secret_token'] = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);

        wp_umbrella_get_service('Option')->setOptions($options);

        $responseValidateSecret = wp_umbrella_get_service('Projects')->validateSecretToken([
            'base_url' => site_url(),
            'rest_url' => rest_url(),
            'secret_token' => $secretToken,
            'save' => true
        ], wp_umbrella_get_api_key());

        if (!is_array($responseValidateSecret) || !isset($responseValidateSecret['success'])) {
            $options['secret_token'] = $oldSecretToken;
            wp_umbrella_get_service('Option')->setOptions($options);
            return $this->returnResponse([
                'code' => 'error'
            ]);
        }

        if (!$responseValidateSecret['success']) {
            $options['secret_token'] = $oldSecretToken;
            wp_umbrella_get_service('Option')->setOptions($options);
            return $this->returnResponse([
                'code' => 'error'
            ]);
        }

        $options['secret_token'] = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);
        wp_umbrella_get_service('Option')->setOptions($options);

        wp_load_alloptions(true);

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }
}
