<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class RecoveryCodes
{
    const META_KEY = 'wp_umbrella_2fa_recovery_code';

    const COUNT = 10;

    const GROUPS = 4;

    const GROUP_SIZE = 4;

    const LOW_THRESHOLD = 3;

    const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @param int $userId
     *
     * @return array
     */
    public function regenerate($userId)
    {
        $this->clear($userId);

        $codes = [];

        for ($i = 0; $i < self::COUNT; ++$i) {
            $code = $this->generateCode();
            $codes[] = $code;
            add_user_meta((int) $userId, self::META_KEY, $this->hash($code));
        }

        return $codes;
    }

    /**
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public function consume($userId, $code)
    {
        $normalized = $this->normalize($code);

        if ($normalized === null) {
            return false;
        }

        global $wpdb;

        $hash = $this->hash($normalized);

        $metaId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s AND meta_value = %s LIMIT 1",
                (int) $userId,
                self::META_KEY,
                $hash
            )
        );

        if ($metaId === null) {
            return false;
        }

        $deleted = $wpdb->delete($wpdb->usermeta, ['umeta_id' => (int) $metaId], ['%d']);

        if ($deleted !== 1) {
            return false;
        }

        wp_cache_delete((int) $userId, 'user_meta');

        return true;
    }

    /**
     * @param int $userId
     *
     * @return int
     */
    public function remaining($userId)
    {
        $codes = get_user_meta((int) $userId, self::META_KEY, false);

        if (!is_array($codes)) {
            return 0;
        }

        return count($codes);
    }

    /**
     * @param int $userId
     *
     * @return void
     */
    public function clear($userId)
    {
        delete_user_meta((int) $userId, self::META_KEY);
    }

    /**
     * @param string $code
     *
     * @return string
     */
    public function format($code)
    {
        return implode('-', str_split($code, self::GROUP_SIZE));
    }

    /**
     * @return string
     */
    protected function generateCode()
    {
        $length = self::GROUPS * self::GROUP_SIZE;
        $alphabetLength = strlen(self::ALPHABET);
        $code = '';

        for ($i = 0; $i < $length; ++$i) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }

    /**
     * @param string $code
     *
     * @return string|null
     */
    protected function normalize($code)
    {
        if (!is_string($code)) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        if (strlen($normalized) !== self::GROUPS * self::GROUP_SIZE) {
            return null;
        }

        if (strspn($normalized, self::ALPHABET) !== strlen($normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param string $code
     *
     * @return string
     */
    protected function hash($code)
    {
        return hash('sha256', $code);
    }
}
