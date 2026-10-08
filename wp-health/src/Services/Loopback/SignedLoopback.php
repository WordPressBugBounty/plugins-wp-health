<?php
namespace WPUmbrella\Services\Loopback;

if (!defined('ABSPATH')) {
    exit;
}

class SignedLoopback
{
    const NAME_SERVICE = 'SignedLoopback';

    const SNAPSHOT = 'snapshot';

    const UPDATE = 'update';

    const TOKEN_FIELD = 'loopback_token';

    const MAX_REPORTED_KEYS = 10;

    const MAX_REPORTED_KEY_LENGTH = 40;

    /** @var array */
    protected static $prepared = [];

    /** @var array */
    protected static $fields = [
        self::SNAPSHOT => [
            'post' => ['action', 'nonce', 'timestamp', 'signature', self::TOKEN_FIELD],
            'get' => ['x-umbrella-load-admin'],
        ],
        self::UPDATE => [
            'post' => ['action', 'plugin', 'timestamp', 'lock_token', 'operation', 'nonce', 'signature', self::TOKEN_FIELD],
            'get' => ['force-check'],
        ],
    ];

    /**
     * @param string $kind
     * @return array{stripped: string[], token: string}
     */
    public function prepare($kind)
    {
        if (isset(self::$prepared[$kind])) {
            return self::$prepared[$kind];
        }

        $token = isset($_POST[self::TOKEN_FIELD]) && is_string($_POST[self::TOKEN_FIELD]) ? (string) wp_unslash($_POST[self::TOKEN_FIELD]) : '';
        $timestamp = isset($_POST['timestamp']) && is_string($_POST['timestamp']) ? (string) wp_unslash($_POST['timestamp']) : '';

        $stripped = $this->stripUnexpectedFields($kind);
        $tokenState = $token === '' ? 'missing' : $this->consumeToken($token, $kind, $timestamp);

        self::$prepared[$kind] = [
            'stripped' => $stripped,
            'token' => $tokenState,
        ];

        $events = ['signed_ok', 'token_' . $tokenState];
        if (!empty($stripped)) {
            $events[] = 'extra_fields_stripped';
        }

        $reportedKeys = $this->reportedKeys($stripped);

        try {
            wp_umbrella_get_service('LoopbackStats')->record($kind, $events, $reportedKeys);
        } catch (\Throwable $e) {
        }

        if ($kind === self::UPDATE) {
            $trace = wp_umbrella_get_service('RequestTrace');

            if (!empty($stripped)) {
                $trace->addTrace('loopback_extra_fields_stripped', [
                    'count' => count($stripped),
                    'keys' => $reportedKeys,
                ]);
            }

            $trace->addTrace('loopback_token_' . $tokenState);
        }

        return self::$prepared[$kind];
    }

    /**
     * @param string $token
     * @param string $kind
     * @param string $timestamp
     * @return string
     */
    protected function consumeToken($token, $kind, $timestamp)
    {
        try {
            return wp_umbrella_get_service('LoopbackToken')->consume($token, $kind, $timestamp);
        } catch (\Throwable $e) {
            return LoopbackToken::UNKNOWN;
        }
    }

    /**
     * @param string $kind
     * @return string[]
     */
    protected function stripUnexpectedFields($kind)
    {
        $allowedPost = self::$fields[$kind]['post'];
        $allowedGet = array_merge(self::$fields[$kind]['get'], $this->adminAjaxQueryKeys());

        $stripped = array_merge(
            $this->strip($_POST, $allowedPost),
            $this->strip($_GET, $allowedGet)
        );

        if (isset($_REQUEST) && is_array($_REQUEST)) {
            $stripped = array_merge($stripped, $this->strip($_REQUEST, array_merge($allowedPost, $allowedGet)));
        }

        return array_values(array_unique($stripped));
    }

    /**
     * @return string[]
     */
    protected function adminAjaxQueryKeys()
    {
        $query = wp_parse_url(admin_url('admin-ajax.php'), PHP_URL_QUERY);

        if (!is_string($query) || $query === '') {
            return [];
        }

        parse_str($query, $params);

        return array_map('strval', array_keys($params));
    }

    /**
     * @param array $source
     * @param string[] $allowed
     * @return string[] The removed keys
     */
    protected function strip(array & $source, array $allowed)
    {
        $removed = [];

        foreach (array_keys($source) as $key) {
            if (!in_array((string) $key, $allowed, true)) {
                unset($source[$key]);
                $removed[] = (string) $key;
            }
        }

        return $removed;
    }

    /**
     * @param string[] $keys
     * @return string
     */
    protected function reportedKeys(array $keys)
    {
        $reported = [];

        foreach (array_slice($keys, 0, self::MAX_REPORTED_KEYS) as $key) {
            $reported[] = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($key, 0, self::MAX_REPORTED_KEY_LENGTH));
        }

        return implode(',', $reported);
    }
}
