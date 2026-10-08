<?php
namespace WPUmbrella\Services\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

class UpdateLock
{
    const NAME_SERVICE = 'UpdateLock';

    const TTL = 900;

    const PHASE_PREPARED = 'prepared';
    const PHASE_UPDATING = 'updating';
    const PHASE_UPDATED = 'updated';

    const ACQUIRED = 'acquired';
    const REFUSED_OTHER = 'refused_other';
    const REFUSED_SELF_UPDATING = 'refused_self_updating';

    const CHILD_PENDING = 'pending';
    const CHILD_CLAIMED = 'claimed';
    const CHILD_DETACHED = 'detached';
    const CHILD_DONE = 'done';
    const CHILD_ABANDONED = 'abandoned';

    const DELEGATION_NOT_STARTED = 'not_started';
    const DELEGATION_RUNNING = 'running';
    const DELEGATION_DONE = 'done';
    const DELEGATION_UNKNOWN = 'unknown';

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $phase
     * @return string
     */
    public function acquire($slug, $token = null, $phase = self::PHASE_PREPARED)
    {
        if ($this->insert($slug, $token, $phase)) {
            return self::ACQUIRED;
        }

        $row = $this->read($slug);

        if ($row === null) {
            return $this->insert($slug, $token, $phase) ? self::ACQUIRED : self::REFUSED_OTHER;
        }

        if ($row['value'] === null) {
            $this->remove($slug, $row['raw']);

            return $this->insert($slug, $token, $phase) ? self::ACQUIRED : self::REFUSED_OTHER;
        }

        if (!$this->isHolder($row, $token) || $this->normalize($token) === '') {
            return self::REFUSED_OTHER;
        }

        if ($row['value']['phase'] === self::PHASE_UPDATING) {
            return self::REFUSED_SELF_UPDATING;
        }

        return $this->swap($slug, $row['raw'], $token, $phase) ? self::ACQUIRED : self::REFUSED_OTHER;
    }

    /**
     * @param string $theme
     * @return string
     */
    public function themeSlug($theme)
    {
        return 'themes/' . $theme;
    }

    /**
     * @param string $result
     * @return string
     */
    public function refusalCode($result)
    {
        if ($result === self::REFUSED_SELF_UPDATING) {
            return 'update_in_progress_self';
        }

        return 'update_in_progress';
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @return bool
     */
    public function isHeldByOther($slug, $token = null)
    {
        $row = $this->read($slug);

        if ($row === null || $row['value'] === null) {
            return false;
        }

        if ($this->normalize($token) === '') {
            return true;
        }

        return !$this->isHolder($row, $token);
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $phase
     * @return bool
     */
    public function markPhase($slug, $token, $phase)
    {
        $row = $this->read($slug);

        if ($row === null || $row['value'] === null || !$this->isHolder($row, $token) || $this->hasRunningChild($row)) {
            return false;
        }

        return $this->swap($slug, $row['raw'], $token, $phase);
    }

    /**
     * @param string $slug
     * @return string|null
     */
    public function holderFingerprint($slug)
    {
        $row = $this->read($slug);

        if ($row === null || $row['value'] === null) {
            return null;
        }

        return substr(hash('sha256', $row['value']['token']), 0, 12);
    }

    /**
     * @param string $slug
     * @return string|null
     */
    public function phase($slug)
    {
        $row = $this->read($slug);

        if ($row === null || $row['value'] === null) {
            return null;
        }

        return $row['value']['phase'];
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @return bool
     */
    public function release($slug, $token = null)
    {
        $row = $this->read($slug);

        if ($row === null || $row['value'] === null || !$this->isHolder($row, $token) || $this->hasRunningChild($row)) {
            return false;
        }

        return $this->remove($slug, $row['raw']);
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $operation
     * @return bool
     */
    public function delegate($slug, $token, $operation)
    {
        $row = $this->read($slug);

        if ($row === null || $row['value'] === null || $this->normalize($token) === '' || !$this->isHolder($row, $token)) {
            return false;
        }

        if ($row['value']['phase'] !== self::PHASE_UPDATING || !is_string($operation) || $operation === '' || $this->hasRunningChild($row)) {
            return false;
        }

        return $this->swap($slug, $row['raw'], $token, self::PHASE_UPDATING, [
            'child' => ['operation' => $operation, 'status' => self::CHILD_PENDING],
        ]);
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $operation
     * @return bool
     */
    public function claimDelegation($slug, $token, $operation)
    {
        $row = $this->delegationRow($slug, $token, $operation);

        if ($row === null || $row['value']['child']['status'] !== self::CHILD_PENDING || $row['value']['phase'] !== self::PHASE_UPDATING) {
            return false;
        }

        return $this->swap($slug, $row['raw'], $token, self::PHASE_UPDATING, [
            'child' => ['operation' => $operation, 'status' => self::CHILD_CLAIMED],
        ]);
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $operation
     * @param array $result
     * @return bool
     */
    public function completeDelegation($slug, $token, $operation, $result)
    {
        $row = $this->delegationRow($slug, $token, $operation);

        if ($row === null || !$this->hasRunningChild($row)) {
            return false;
        }

        $phase = $row['value']['child']['status'] === self::CHILD_DETACHED ? self::PHASE_UPDATED : $row['value']['phase'];

        return $this->swap($slug, $row['raw'], $token, $phase, [
            'child' => [
                'operation' => $operation,
                'status' => self::CHILD_DONE,
                'result' => is_array($result) ? $result : [],
            ],
        ]);
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $operation
     * @return void
     */
    public function completeDelegationOnShutdown($slug, $token, $operation)
    {
        register_shutdown_function(function () use ($slug, $token, $operation) {
            $this->completeDelegation($slug, $token, $operation, [
                'status' => 'error',
                'code' => 'unknown_error',
                'data' => [],
            ]);
        });
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $operation
     * @return array ['status' => string, 'result' => array|null]
     */
    public function settleDelegation($slug, $token, $operation)
    {
        $row = $this->delegationRow($slug, $token, $operation);

        if ($row === null) {
            return ['status' => self::DELEGATION_UNKNOWN, 'result' => null];
        }

        $child = $row['value']['child'];

        if ($child['status'] === self::CHILD_ABANDONED) {
            return ['status' => self::DELEGATION_NOT_STARTED, 'result' => null];
        }

        if ($child['status'] === self::CHILD_DONE) {
            return [
                'status' => self::DELEGATION_DONE,
                'result' => isset($child['result']) && is_array($child['result']) ? $child['result'] : [],
            ];
        }

        if ($child['status'] === self::CHILD_CLAIMED || $child['status'] === self::CHILD_DETACHED) {
            return ['status' => self::DELEGATION_RUNNING, 'result' => null];
        }

        if ($child['status'] !== self::CHILD_PENDING || $row['value']['phase'] !== self::PHASE_UPDATING) {
            return ['status' => self::DELEGATION_UNKNOWN, 'result' => null];
        }

        $abandoned = $this->swap($slug, $row['raw'], $token, self::PHASE_UPDATING, [
            'child' => ['operation' => $operation, 'status' => self::CHILD_ABANDONED],
        ]);

        return [
            'status' => $abandoned ? self::DELEGATION_NOT_STARTED : self::DELEGATION_RUNNING,
            'result' => null,
        ];
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @param string $operation
     * @return array ['status' => string, 'result' => array|null]
     */
    public function detachDelegation($slug, $token, $operation)
    {
        $row = $this->delegationRow($slug, $token, $operation);

        if ($row !== null && $row['value']['child']['status'] === self::CHILD_CLAIMED && $this->detach($slug, $row)) {
            return ['status' => self::DELEGATION_RUNNING, 'result' => null];
        }

        return $this->settleDelegation($slug, $token, $operation);
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @return void
     */
    public function finishOnShutdown($slug, $token)
    {
        register_shutdown_function(function () use ($slug, $token) {
            $this->finishIfUpdating($slug, $token);
        });
    }

    /**
     * @param string $slug
     * @param string|null $token
     * @return bool
     */
    public function finishIfUpdating($slug, $token)
    {
        $row = $this->read($slug);

        if ($row === null || $row['value'] === null || !$this->isHolder($row, $token)) {
            return false;
        }

        if ($row['value']['phase'] !== self::PHASE_UPDATING) {
            return false;
        }

        if ($this->hasRunningChild($row)) {
            if ($row['value']['child']['status'] === self::CHILD_CLAIMED) {
                $this->detach($slug, $row);
            }

            return false;
        }

        return $this->swap($slug, $row['raw'], $token, self::PHASE_UPDATED);
    }

    protected function detach($slug, $row)
    {
        $child = $row['value']['child'];
        $child['status'] = self::CHILD_DETACHED;

        return $this->swap($slug, $row['raw'], $row['value']['token'], $row['value']['phase'], ['child' => $child]);
    }

    protected function insert($slug, $token, $phase)
    {
        global $wpdb;

        $rows = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$this->table()} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $this->key($slug),
            $this->encode($token, $phase)
        ));

        return (int) $rows > 0;
    }

    /**
     * @return array|null
     */
    protected function read($slug)
    {
        global $wpdb;

        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$this->table()} WHERE option_name = %s",
            $this->key($slug)
        ));

        if ($raw === null) {
            return null;
        }

        return [
            'raw' => (string) $raw,
            'value' => $this->decode((string) $raw),
        ];
    }

    protected function swap($slug, $previousRaw, $token, $phase, $extra = [])
    {
        global $wpdb;

        $next = $this->encode($token, $phase, $extra);

        if (!is_string($next)) {
            return false;
        }

        if ($next === $previousRaw) {
            return true;
        }

        $rows = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            $next,
            $this->key($slug),
            $previousRaw
        ));

        return (int) $rows > 0;
    }

    protected function remove($slug, $previousRaw)
    {
        global $wpdb;

        $rows = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table()} WHERE option_name = %s AND option_value = %s",
            $this->key($slug),
            $previousRaw
        ));

        return (int) $rows > 0;
    }

    protected function hasRunningChild($row)
    {
        return $row['value']['child'] !== null && in_array($row['value']['child']['status'], [self::CHILD_CLAIMED, self::CHILD_DETACHED], true);
    }

    protected function isHolder($row, $token)
    {
        return hash_equals($row['value']['token'], $this->normalize($token));
    }

    protected function encode($token, $phase, $extra = [])
    {
        return json_encode(array_merge([
            'token' => $this->normalize($token),
            'time' => time(),
            'phase' => (string) $phase,
        ], $extra), defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
    }

    /**
     * @return array|null
     */
    protected function decode($raw)
    {
        $value = json_decode($raw, true);

        if (!is_array($value) || !isset($value['token'], $value['time'])) {
            return null;
        }

        if (time() - (int) $value['time'] > self::TTL) {
            return null;
        }

        return [
            'token' => (string) $value['token'],
            'time' => (int) $value['time'],
            'phase' => isset($value['phase']) ? (string) $value['phase'] : '',
            'child' => $this->decodeChild($value),
        ];
    }

    /**
     * @return array|null
     */
    protected function decodeChild($value)
    {
        if (!isset($value['child']) || !is_array($value['child'])) {
            return null;
        }

        $child = $value['child'];

        if (!isset($child['operation'], $child['status']) || !is_string($child['operation']) || !is_string($child['status'])) {
            return null;
        }

        return $child;
    }

    /**
     * @return array|null
     */
    protected function delegationRow($slug, $token, $operation)
    {
        if (!is_string($operation) || $operation === '' || $this->normalize($token) === '') {
            return null;
        }

        $row = $this->read($slug);

        if ($row === null || $row['value'] === null || !$this->isHolder($row, $token)) {
            return null;
        }

        $child = $row['value']['child'];

        if ($child === null || !hash_equals($child['operation'], $operation)) {
            return null;
        }

        return $row;
    }

    protected function normalize($token)
    {
        if (!is_string($token)) {
            return '';
        }

        return $token;
    }

    protected function table()
    {
        global $wpdb;

        return $wpdb->base_prefix . 'options';
    }

    protected function key($slug)
    {
        $parts = array_filter(explode('/', str_replace('\\', '/', (string) $slug)), function ($part) {
            return $part !== '' && $part !== '.';
        });

        return 'wp_umbrella_update_lock_' . implode('/', $parts);
    }
}
