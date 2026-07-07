<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class MaskLoginErrors implements ExecuteHooks
{
    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('mask_login_errors')) {
            return;
        }

        add_filter('login_errors', [$this, 'maskLoginErrors']);
    }

    public function maskLoginErrors($message)
    {
        global $errors;

        if (!is_wp_error($errors)) {
            return $message;
        }

        $sensitiveCodes = ['invalid_username', 'incorrect_password', 'invalid_email', 'invalidcombo'];

        foreach ($errors->get_error_codes() as $code) {
            if (in_array($code, $sensitiveCodes, true)) {
                return __('The username or password is incorrect.', 'wp-health');
            }
        }

        return $message;
    }
}
