<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\TwoFactor\RecoveryCodes;
use WPUmbrella\Services\TwoFactor\SecretStore;
use WPUmbrella\Services\TwoFactor\TotpReplayGuard;

if (!defined('ABSPATH')) {
    exit;
}

class MetaProtection implements ExecuteHooks
{
    public function hooks()
    {
        add_filter('is_protected_meta', [$this, 'protectTwoFactorKeys'], 10, 3);
    }

    /**
     * @param bool   $protected
     * @param string $metaKey
     * @param string $metaType
     *
     * @return bool
     */
    public function protectTwoFactorKeys($protected, $metaKey, $metaType = '')
    {
        if ($metaType !== 'user') {
            return $protected;
        }

        $keys = [
            SecretStore::META_SECRET,
            SecretStore::META_ENROLLED_AT,
            RecoveryCodes::META_KEY,
            TotpReplayGuard::META_KEY,
        ];

        return in_array($metaKey, $keys, true) ? true : $protected;
    }
}
